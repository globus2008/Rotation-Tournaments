<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Central permission guard for the front-end forms (admin-post.php handlers).
 *
 * The original handlers only verify a nonce and is_user_logged_in(). A nonce
 * proves who sent the form, not what that user may do, and the forms (and their
 * nonces) are rendered for every visitor. So any subscriber could add or remove
 * players, change payments or even delete tournaments (and the linked WP page).
 *
 * The guard runs at priority 0, before the original handler, and stops the
 * request when the current user lacks the rights for the given tournament.
 * @since 1.6.0
 */

/**
 * Map of admin-post actions to the permission they need.
 * 'admin'        - tournament admin (doroto_is_admin() > 0)
 * 'admin_or_self' - tournament admin, or the player acting on himself;
 *                   value is the POST field holding the affected player ID
 * 'match_player' - tournament admin, or a player of that match when the
 *                   tournament allows players to enter results
 * 'final_player' - tournament admin, or one of the four finalists when the
 *                   tournament allows players to enter results
 * Tournament admin = organizer listed in admin_users, or a web administrator,
 * editor or author (see doroto_is_admin()).
 */
function doroto_form_permissions()
{
	return [
		'doroto_add_player_to_tournament' => ['admin'],
		'doroto_register_add_player_to_tournament' => ['admin'],
		'doroto_add_special_group_to_tournament' => ['admin'],
		'doroto_remove_special_group_to_tournament' => ['admin'],
		'doroto_add_admin_to_tournament' => ['admin'],
		'doroto_tournament_parameters' => ['admin'],
		'doroto_tournament_parameters_save' => ['admin'],
		'doroto_enter_payment_in_tournament' => ['admin'],
		'doroto_remove_payment_in_tournament' => ['admin'],
		'doroto_change_game_result' => ['admin'],
		'doroto_disable_player_in_tournament' => ['admin_or_self', 'disable_player'],
		'doroto_enable_player_in_tournament' => ['admin_or_self', 'enable_player'],
		'doroto_remove_player_from_tournament' => ['admin_or_self', 'player_to_remove'],
		'doroto_submit_match_result' => ['match_player'],
		'doroto_submit_final_result' => ['final_player'],
	];
}

/**
 * Decide whether the current user may run the given form action.
 * @since 1.6.0
 */
function doroto_user_may_submit_form(string $action, array $rule, int $tournament_id)
{
	if (!is_user_logged_in() || $tournament_id <= 0) {
		return false;
	}
	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament === null) {
		return false;
	}
	if (doroto_is_admin($tournament_id) > 0) {
		return true;
	}

	$current_user_id = intval(get_current_user_id());

	if ($rule[0] === 'admin_or_self') {
		$field = $rule[1];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handler verifies the nonce
		$player_id = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
		return $player_id > 0 && $player_id === $current_user_id;
	}

	if ($rule[0] === 'final_player') {
		if (intval($tournament->allow_input_results) !== 1) {
			return false;
		}
		$final_four = maybe_unserialize($tournament->final_four);
		$finalists = is_array($final_four) ? array_map('intval', array_values($final_four)) : [];
		return in_array($current_user_id, $finalists, true);
	}

	if ($rule[0] === 'match_player') {
		if (intval($tournament->allow_input_results) !== 1) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handler verifies the nonce
		$match_number = isset($_POST['match_number']) ? intval($_POST['match_number']) : 0;
		$matches = maybe_unserialize($tournament->matches_list);
		if (!is_array($matches)) {
			return false;
		}
		foreach ($matches as $match) {
			if (intval($match['match_number']) === $match_number) {
				$players = array_map('intval', [$match['player_1'], $match['player_2'], $match['player_3'], $match['player_4']]);
				return in_array($current_user_id, $players, true);
			}
		}
		return false;
	}

	return false;
}

/**
 * Priority-0 hook on every protected admin-post action.
 * @since 1.6.0
 */
function doroto_guard_form_submission()
{
	$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
	$rules = doroto_form_permissions();
	if (!isset($rules[$action])) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handler verifies the nonce
	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());

	if (!doroto_user_may_submit_form($action, $rules[$action], $tournament_id)) {
		doroto_info_messsages_save(sanitize_text_field(__('You do not have permission to perform this action.', 'doubles-rotation-tournament')));
		doroto_redirect_modify_url(max(0, $tournament_id), "");
		exit;
	}
}

foreach (array_keys(doroto_form_permissions()) as $doroto_guarded_action) {
	add_action('admin_post_' . $doroto_guarded_action, 'doroto_guard_form_submission', 0);
	add_action('admin_post_nopriv_' . $doroto_guarded_action, 'doroto_guard_form_submission', 0);
}
unset($doroto_guarded_action);
