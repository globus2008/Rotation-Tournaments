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
		case 'auth_unauthorized':
		case 'auth_forbidden':
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
		case 'invalid_data':
			return __('Invalid value entered.', 'doubles-rotation-tournament');
		case 'edit_match_forbidden':
			return __('You do not have permission to perform this action.', 'doubles-rotation-tournament');
		case 'match_not_found':
			return __('The match was not found.', 'doubles-rotation-tournament');
		case 'tournament_not_scheduled':
			return $no . __('has not been scheduled yet.', 'doubles-rotation-tournament');
		case 'match_already_entered':
			$data = $result->get_error_data();
			return __('The result of match no.', 'doubles-rotation-tournament') . ' ' . intval($data['match_number'] ?? 0) . ' '
				. __('was previously entered with a score', 'doubles-rotation-tournament') . ' '
				. intval($data['result_1'] ?? 0) . ':' . intval($data['result_2'] ?? 0) . '.';
		case 'match_result_updated':
			if (!empty($result['hidden'])) {
				return __('Match no.', 'doubles-rotation-tournament') . ' ' . intval($result['match_number']) . ' '
					. __('was skipped.', 'doubles-rotation-tournament');
			}
			return __('The result of match no.', 'doubles-rotation-tournament') . ' ' . intval($result['match_number']) . ' '
				. __('was saved with a score', 'doubles-rotation-tournament') . ' '
				. intval($result['result_1']) . ':' . intval($result['result_2']) . '.';
		case 'matches_skipped':
			/* translators: %d: number of skipped matches */
			return sprintf(_n('%d match was skipped.', '%d matches were skipped.', intval($result['skipped']), 'doubles-rotation-tournament'), intval($result['skipped']));
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

/**
 * Current `last_update` of a tournament as stored (progress and the draw bump it again).
 * @since 2.0.0
 */
function doroto_service_stored_last_update(int $tournament_id): int
{
	global $wpdb;
	return intval($wpdb->get_var($wpdb->prepare(
		"SELECT last_update FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	)));
}

/**
 * Recompute the progress and draw new matches after results changed. Caller holds the lock.
 * @return bool true when no new match could be drawn
 * @since 2.0.0
 */
function doroto_service_progress_and_draw(int $tournament_id): bool
{
	ob_start();
	doroto_tournament_progress($tournament_id);
	$offer_html = doroto_offer_games($tournament_id, 0);
	ob_end_clean();
	return $offer_html !== '';
}

/**
 * May the current user enter the result of this match?
 * Organizers always; a player only for his own match when the tournament allows it.
 * @since 2.0.0 (same rule as the web guard and the REST route)
 */
function doroto_service_may_enter_result(stdClass $tournament, int $match_number): bool
{
	if (doroto_is_admin(intval($tournament->id)) > 0) {
		return true;
	}
	$user_id = get_current_user_id();
	if ($user_id <= 0 || intval($tournament->allow_input_results) !== 1) {
		return false;
	}
	$matches = maybe_unserialize($tournament->matches_list);
	foreach (is_array($matches) ? $matches : [] as $match) {
		if (intval($match['match_number']) === $match_number) {
			// IDs may be stored as strings or ints in the serialized data.
			$players = array_map('intval', [$match['player_1'], $match['player_2'], $match['player_3'], $match['player_4']]);
			return in_array($user_id, $players, true);
		}
	}
	return false;
}

/**
 * Enter the result of an open match (or skip it), then draw the next matches.
 * @return array|WP_Error match_result_updated with no_new_match and last_update
 * @since 2.0.0 (merged from doroto_update_match_result and doroto_rest_update_match_result)
 */
function doroto_service_enter_result(int $tournament_id, int $match_number, int $result_1, int $result_2, bool $hide = false)
{
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	// 0:0 is the "not entered yet" marker of a match, so it cannot be a result.
	if ($match_number <= 0 || $result_1 < 0 || $result_2 < 0 || (!$hide && $result_1 === 0 && $result_2 === 0)) {
		return doroto_service_error('invalid_data');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($tournament_id, $match_number, $result_1, $result_2, $hide) {
		// Read under the lock: another court may have saved its result a moment ago.
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (!doroto_service_may_enter_result($tournament, $match_number)) {
			return doroto_service_error('edit_match_forbidden', 403);
		}
		$result = doroto_store_match_result_locked($tournament, $match_number, $result_1, $result_2, $hide);
		if (is_wp_error($result)) {
			return $result;
		}
		$result['no_new_match'] = doroto_service_progress_and_draw($tournament_id);
		$result['last_update'] = max(doroto_service_stored_last_update($tournament_id), $result['last_update']);
		return $result;
	});
}

/**
 * Skip several ongoing matches, then draw once, so the new matches come from all
 * freed players. Organizer only. Matches finished in the meantime are left alone.
 * @param int[] $match_numbers
 * @return array|WP_Error matches_skipped with skipped, no_new_match, last_update
 * @since 2.0.0 (from doroto_rest_skip_matches, 1.6.2)
 */
function doroto_service_skip_matches(int $tournament_id, array $match_numbers)
{
	$match_numbers = array_values(array_unique(array_filter(array_map('intval', $match_numbers), function ($n) {
		return $n > 0;
	})));
	if (empty($match_numbers)) {
		return doroto_service_error('invalid_data');
	}
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($tournament_id, $match_numbers) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) <= 0) {
			return doroto_service_error('auth_forbidden', 403);
		}

		$skipped = 0;
		foreach ($match_numbers as $match_number) {
			// Each store changes the row, so read it again before the next one.
			$fresh = doroto_prepare_tournament($tournament_id);
			$result = doroto_store_match_result_locked($fresh, $match_number, 0, 0, true);
			if (!is_wp_error($result)) {
				$skipped++;
			}
		}

		$no_new_match = false;
		if ($skipped > 0) {
			$no_new_match = doroto_service_progress_and_draw($tournament_id);
		}
		return doroto_service_ok('matches_skipped', [
			'skipped' => $skipped,
			'no_new_match' => $no_new_match,
			'last_update' => doroto_service_stored_last_update($tournament_id),
		]);
	});
}
