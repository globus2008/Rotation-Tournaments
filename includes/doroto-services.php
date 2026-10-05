<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Services: tournament changes shared by the web forms, the REST API and the blocks.
 *
 * A service never reads $_POST/$_GET, never redirects and never prints. It returns
 * an array on success (see doroto_service_ok()) or a WP_Error whose code is the
 * REST `error_code` the app already knows. The callers check the nonce or token,
 * lock the tournament and turn the result into a REST response or a flash message.
 * @since 2.0.0
 */

/**
 * Successful service result.
 * @param string $action the REST `action` code, e.g. 'registration_toggled'
 * @param array $extra additional fields for the REST response
 * @since 2.0.0
 */
function doroto_service_ok(string $action, array $extra = []): array
{
	return array_merge(['success' => true, 'action' => $action], $extra);
}

/**
 * Failed service result.
 * @param string $code the REST `error_code`
 * @param int $status HTTP status for the REST response
 * @since 2.0.0
 */
function doroto_service_error(string $code, int $status = 400): WP_Error
{
	return new WP_Error($code, $code, ['status' => $status]);
}

/**
 * Convert a service result to the REST response format used since 1.4.7:
 * {success, action, ...} or {error_code} with an HTTP status.
 * @since 2.0.0
 */
function doroto_service_rest_response($result): WP_REST_Response
{
	if (is_wp_error($result)) {
		$data = $result->get_error_data();
		$status = is_array($data) && isset($data['status']) ? intval($data['status']) : 400;
		return new WP_REST_Response(['error_code' => $result->get_error_code()], $status);
	}
	return new WP_REST_Response($result, 200);
}

/**
 * Translated text of a service result for the web flash message.
 * @param array|WP_Error $result
 * @param int $tournament_id used in some texts
 * @since 2.0.0
 */
function doroto_service_message($result, int $tournament_id = 0): string
{
	$code = is_wp_error($result) ? $result->get_error_code() : ($result['action'] ?? '');
	$no = __("Tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . ' ';

	switch ($code) {
		case 'tournament_not_found':
			return __('The tournament was not found.', 'doubles-rotation-tournament');
		case 'auth_unauthorized_or_expired':
		case 'auth_insufficient_permissions':
			return __('You do not have permission to perform this action.', 'doubles-rotation-tournament');
		case 'toggle_reg_when_tournament_closed':
			return __('Registration cannot be changed in a closed tournament.', 'doubles-rotation-tournament');
		case 'toggle_reg_not_enough_players':
			return $no . __("does not have sufficient occupancy to close registration.", "doubles-rotation-tournament");
		case 'toggle_tournament_when_reg_open':
			return __("A tournament cannot be closed while player registration is open.", "doubles-rotation-tournament");
		case 'registration_toggled':
			return !empty($result['open_registration'])
				? $no . __("was open for registration.", "doubles-rotation-tournament")
				: __("Registration of tournament players no.", "doubles-rotation-tournament") . ' ' . $tournament_id . ' ' . __("was closed.", "doubles-rotation-tournament");
		case 'tournament_state_toggled':
			return !empty($result['close_tournament'])
				? __('The tournament was closed.', 'doubles-rotation-tournament')
				: __('The tournament was reopened.', 'doubles-rotation-tournament');
	}
	return '';
}

/**
 * Load a tournament the current user organizes.
 * @return object|WP_Error
 * @since 2.0.0
 */
function doroto_service_admin_tournament(int $tournament_id)
{
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized_or_expired', 401);
	}
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return doroto_service_error('tournament_not_found', 404);
	}
	if (doroto_is_admin($tournament_id) < 1) {
		return doroto_service_error('auth_insufficient_permissions', 403);
	}
	return $tournament;
}

/**
 * Open or close the registration of players.
 * Closing needs at least 4 players; it prepares the match list and the statistics.
 * @since 2.0.0 (merged from doroto_toggle_registration and doroto_rest_toggle)
 */
function doroto_service_toggle_registration(int $tournament_id)
{
	global $wpdb;

	$tournament = doroto_service_admin_tournament($tournament_id);
	if (is_wp_error($tournament)) {
		return $tournament;
	}
	if (intval($tournament->close_tournament) === 1) {
		return doroto_service_error('toggle_reg_when_tournament_closed');
	}

	$table = $wpdb->prefix . 'doroto_tournaments';
	$last_update = doroto_now_ms();

	if (intval($tournament->open_registration) === 1) {
		$players = maybe_unserialize($tournament->players);
		if (!is_array($players) || count($players) < 4) {
			return doroto_service_error('toggle_reg_not_enough_players');
		}
		$matches = maybe_unserialize($tournament->matches_list);
		if (empty($matches)) {
			$matches = doroto_generate_fake_match($players);
		}
		$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
		$wpdb->update(
			$table,
			[
				'open_registration' => 0,
				'matches_list' => serialize($matches),
				'statistics' => serialize($statistics),
				'last_update' => $last_update,
			],
			['id' => $tournament_id]
		);
		$open = 0;
	} else {
		$wpdb->update($table, ['open_registration' => 1, 'last_update' => $last_update], ['id' => $tournament_id]);
		$open = 1;
	}

	return doroto_service_ok('registration_toggled', [
		'last_update' => $last_update,
		'open_registration' => $open,
	]);
}

/**
 * Close or reopen a tournament. Both directions clear the final pairs and result.
 * @since 2.0.0 (merged from doroto_toggle_tournament and doroto_rest_toggle)
 */
function doroto_service_toggle_tournament(int $tournament_id)
{
	global $wpdb;

	$tournament = doroto_service_admin_tournament($tournament_id);
	if (is_wp_error($tournament)) {
		return $tournament;
	}
	if (intval($tournament->open_registration) === 1) {
		return doroto_service_error('toggle_tournament_when_reg_open');
	}

	$close = intval($tournament->close_tournament) === 1 ? 0 : 1;
	$last_update = doroto_now_ms();
	$wpdb->update(
		$wpdb->prefix . 'doroto_tournaments',
		[
			'close_tournament' => $close,
			'final_four' => '',
			'final_result' => '',
			'close_date' => $close === 1 ? gmdate('Y-m-d H:i:s') : '9999-09-09 09:09:09',
			'last_update' => $last_update,
		],
		['id' => $tournament_id]
	);

	return doroto_service_ok('tournament_state_toggled', [
		'last_update' => $last_update,
		'close_tournament' => $close,
	]);
}
