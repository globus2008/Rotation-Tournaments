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
		case 'auth_not_authorized':
		case 'auth_forbidden':
		case 'not_admin_permission':
		case 'auth_not_logged_in':
		case 'auth_not_authenticated':
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
		case 'edit_match_results_closed':
			return __('Results of this tournament can no longer be changed.', 'doubles-rotation-tournament');
		case 'match_not_finished':
			return __('This match has not been played yet.', 'doubles-rotation-tournament');
		case 'match_result_changed':
			return __('The result of match no.', 'doubles-rotation-tournament') . ' ' . intval($result['match_number']) . ' ' . __('was changed.', 'doubles-rotation-tournament');
		case 'missing_tournament_or_player_id':
			return __('Tournament or user not found.', 'doubles-rotation-tournament');
		case 'add_player_forbidden':
			return __('You do not have permission to perform this action.', 'doubles-rotation-tournament');
		case 'add_player_tournament_closed':
			return __('Players cannot be added to a closed tournament.', 'doubles-rotation-tournament');
		case 'remove_player_not_in_tournament':
			return __('The player is not in this tournament.', 'doubles-rotation-tournament');
		case 'remove_player_has_played':
			return __("The player cannot be removed because he has already played at least one match in the tournament.", "doubles-rotation-tournament");
		case 'suspend_player_tournament_closed':
			return __('Players cannot be suspended in a closed tournament.', 'doubles-rotation-tournament');
		case 'player_added_to_tournament':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('was added to the tournament.', 'doubles-rotation-tournament');
		case 'player_removed_from_tournament':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('was removed from the tournament.', 'doubles-rotation-tournament');
		case 'all_players_suspended':
			return __('All players have temporarily suspended participation.', 'doubles-rotation-tournament');
		case 'single_player_suspended':
			return $result['player_name'] . ' ' . __('has temporarily suspended participation.', 'doubles-rotation-tournament');
		case 'all_players_restored':
			return __('All players have renewed participation.', 'doubles-rotation-tournament');
		case 'single_player_restored':
			return $result['player_name'] . ' ' . __('has renewed participation.', 'doubles-rotation-tournament');
		case 'player_added_to_special_group':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('has been added to a special group.', 'doubles-rotation-tournament');
		case 'player_removed_from_special_group':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('was taken from a special group.', 'doubles-rotation-tournament');
		case 'player_already_in_special_group':
			return __('The player is already in the special group.', 'doubles-rotation-tournament');
		case 'player_not_in_special_group':
			return __('The player is not in the special group.', 'doubles-rotation-tournament');
		case 'payment_recorded':
			return __('The payment of the player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('has been added to the list.', 'doubles-rotation-tournament');
		case 'payment_removed':
			return __('The payment of the player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('has been removed from the list.', 'doubles-rotation-tournament');
		case 'payment_not_found':
			return __('The player has no recorded payment.', 'doubles-rotation-tournament');
		case 'add_admin_tournament_closed':
			return __('Organizers cannot be added to a closed tournament.', 'doubles-rotation-tournament');
		case 'admin_not_found':
			return __('The user is not an organizer of this tournament.', 'doubles-rotation-tournament');
		case 'cannot_remove_founder':
			return __('The founder of the tournament cannot be removed.', 'doubles-rotation-tournament');
		case 'organizer_added':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('has been added to the admins.', 'doubles-rotation-tournament');
		case 'organizer_removed':
			return __('Player', 'doubles-rotation-tournament') . ' ' . $result['player_name'] . ' ' . __('is no longer an organizer.', 'doubles-rotation-tournament');
		case 'invalid_tournament_id':
			return __('The tournament was not found.', 'doubles-rotation-tournament');
		case 'invalid_tournament_type':
			return __('Unknown tournament type.', 'doubles-rotation-tournament');
		case 'tournament_closed_for_changes':
			return __('The tournament was closed more than 24 hours ago and can no longer be changed.', 'doubles-rotation-tournament');
		case 'tournament_updated':
			$text = __('Tournament parameters no.', 'doubles-rotation-tournament') . ' ' . $tournament_id . ' ' . __('were saved.', 'doubles-rotation-tournament');
			if (!empty($result['post_not_allowed'])) {
				$text = __('You do not have the necessary rights to create a post.', 'doubles-rotation-tournament') . ' ' . $text;
			}
			return $text;
		case 'tournament_deleted':
			return $no . __('was deleted.', 'doubles-rotation-tournament');
		case 'final_players_not_different':
			return __('Please choose different names for L1, R1, L2 and R2!', 'doubles-rotation-tournament');
		case 'final_four_saved':
			return __('Final group composition saved.', 'doubles-rotation-tournament') . ' ' . __('You can start playing the final match.', 'doubles-rotation-tournament');
		case 'final_result_saved':
			return __('The final match is over!', 'doubles-rotation-tournament');
		case 'invalid_action':
			return __('Invalid request.', 'doubles-rotation-tournament');
		case 'round_continued':
			return __("The end of the round will be announced again.", "doubles-rotation-tournament");
		case 'notification_hidden':
			return __("Other notifications will be hidden.", "doubles-rotation-tournament");
		case 'tournament_ended':
			return __('The tournament was closed.', 'doubles-rotation-tournament');
		case 'registered':
			return __("You signed up for tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.';
		case 'already_registered':
			return __("You are already registered in tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.';
		case 'registration_is_closed':
			return __("The registration for the tournament has already been closed.", "doubles-rotation-tournament");
		case 'registration_max_players':
			return __("We are sorry, but the maximum number of registered participants has been reached in tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.';
		case 'unregistered':
			return __('You have left the tournament.', 'doubles-rotation-tournament');
		case 'unregister_player_has_played':
			return __("The player cannot be removed because he has already played at least one match in the tournament.", "doubles-rotation-tournament");
		case 'create_failed':
			return __('The tournament could not be created.', 'doubles-rotation-tournament');
		case 'tournament_created':
			return __('A new tournament has just been created.', 'doubles-rotation-tournament');
		case 'registration_incomplete_data':
			return __('Invalid value entered.', 'doubles-rotation-tournament');
		case 'registration_creation_failed':
			return __('Could not create user.', 'doubles-rotation-tournament');
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

/**
 * Correct the result of a finished or skipped match. Organizer only, and only while
 * doroto_match_results_editable() allows it. 0:0 turns the match into a skipped one,
 * a score for a skipped match counts it as played.
 * @return array|WP_Error match_result_changed with match_number and last_update
 * @since 2.0.0 (from doroto_change_game_form_submit; the REST route counted a formerly
 *               skipped match as removed instead of added)
 */
function doroto_service_change_result(int $tournament_id, int $match_number, int $result_1, int $result_2)
{
	global $wpdb;

	if ($tournament_id <= 0 || $match_number <= 0 || $result_1 < 0 || $result_2 < 0) {
		return doroto_service_error('invalid_data');
	}
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_not_authorized', 401);
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $match_number, $result_1, $result_2) {
		$tournament = doroto_service_admin_tournament($tournament_id);
		if (is_wp_error($tournament)) {
			return $tournament;
		}
		if (!doroto_match_results_editable($tournament)) {
			return doroto_service_error('edit_match_results_closed', 403);
		}

		$matches = maybe_unserialize($tournament->matches_list);
		$matches = is_array($matches) ? $matches : [];
		$key = null;
		foreach ($matches as $k => $match) {
			if ($match['match_number'] == $match_number) {
				$key = $k;
				break;
			}
		}
		if ($key === null) {
			return doroto_service_error('match_not_found', 404);
		}

		$original = $matches[$key];
		$was_skipped = intval($original['hide']) === 1;
		$becomes_skipped = $result_1 === 0 && $result_2 === 0;
		if (!$was_skipped && intval($original['result_1']) === 0 && intval($original['result_2']) === 0) {
			return doroto_service_error('match_not_finished');
		}
		if ($was_skipped && $becomes_skipped) {
			return doroto_service_error('invalid_data');
		}

		// Statistics get the difference. A skipped match that gets a score is a new game
		// ($correct = false); a played match that becomes 0:0 is taken back (hide = 1).
		$change = $original;
		$change['result_1'] = $result_1 - intval($original['result_1']);
		$change['result_2'] = $result_2 - intval($original['result_2']);
		$change['hide'] = $becomes_skipped ? 1 : 0;
		doroto_update_statistics_by_result($tournament_id, $tournament, $change, !$was_skipped, false);

		$matches[$key]['result_1'] = $result_1;
		$matches[$key]['result_2'] = $result_2;
		$matches[$key]['hide'] = $becomes_skipped ? 1 : 0;
		$matches[$key]['played'] = 1;

		$last_update = doroto_now_ms();
		$updated = $wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			['matches_list' => serialize($matches), 'last_update' => $last_update],
			['id' => $tournament_id]
		);
		if ($updated === false) {
			return doroto_service_error('db_save_result_failed', 500);
		}
		ob_start();
		doroto_tournament_progress($tournament_id);
		ob_end_clean();

		return doroto_service_ok('match_result_changed', [
			'match_number' => $match_number,
			'last_update' => max($last_update, doroto_service_stored_last_update($tournament_id)),
		]);
	});
}

/**
 * Recompute the progress after a change of players (prints nothing).
 * @since 2.0.0
 */
function doroto_service_progress(int $tournament_id)
{
	ob_start();
	doroto_tournament_progress($tournament_id);
	ob_end_clean();
}

/**
 * Tell a player that an organizer added him to a tournament.
 * @since 2.0.0 (moved from doroto_add_player_via_api)
 */
function doroto_send_added_to_tournament_email(int $player_id, stdClass $tournament)
{
	$player_data = get_userdata($player_id);
	if (!$player_data) {
		return;
	}
	$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));
	$tournament_name = $tournament->name;

	$subject = sprintf(
		/* translators: %s: Tournament name. */
		__('You have been added to the tournament: %s', 'doubles-rotation-tournament'),
		$tournament_name
	);
	$body = wp_sprintf(
		'<p>%s</p>',
		sprintf(
			/* translators: %s: Player name. */
			esc_html__('Hello %s,', 'doubles-rotation-tournament'),
			$player_name
		)
	);
	$body .= wp_sprintf(
		'<p>%s</p>',
		sprintf(
			wp_kses(
				/* translators: %s: Tournament name. */
				__('You have also been added to the \'<strong>%s</strong>\' tournament.', 'doubles-rotation-tournament'),
				['strong' => []]
			),
			esc_html($tournament_name)
		)
	);
	$body .= wp_sprintf(
		'<p>%s<br>%s</p>',
		esc_html__('Best regards,', 'doubles-rotation-tournament'),
		esc_html__('The Doroto Team', 'doubles-rotation-tournament')
	);

	// A mail transport warning printed before the JSON broke the app's parser,
	// so the app reported an error although the player had been added.
	ob_start();
	wp_mail($player_data->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
	ob_end_clean();
}

/**
 * Add an existing user to a tournament (organizer only) and e-mail him.
 * @return array|WP_Error player_added_to_tournament with player_name, already_added, last_update
 * @since 2.0.0 (merged from doroto_add_player_form_submit and doroto_add_player_via_api)
 */
function doroto_service_add_player(int $tournament_id, int $player_id)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	if ($tournament_id <= 0 || $player_id <= 0 || get_userdata($player_id) === false) {
		return doroto_service_error('missing_tournament_or_player_id');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $player_id) {
		// Fresh read under the lock, so a concurrent change is not overwritten.
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) < 1) {
			return doroto_service_error('add_player_forbidden', 403);
		}
		if (intval($tournament->close_tournament) === 1) {
			return doroto_service_error('add_player_tournament_closed', 403);
		}

		$players = maybe_unserialize($tournament->players);
		$players = is_array($players) ? array_map('intval', $players) : [];
		$already_added = in_array($player_id, $players, true);
		$last_update = doroto_now_ms();

		if (!$already_added) {
			$players[] = $player_id;
			$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
			$updated = $wpdb->update(
				$wpdb->prefix . 'doroto_tournaments',
				[
					'players' => serialize($players),
					'statistics' => serialize($statistics),
					'last_update' => $last_update,
				],
				['id' => $tournament_id]
			);
			if ($updated === false) {
				return doroto_service_error('db_update_failed', 500);
			}
			doroto_send_added_to_tournament_email($player_id, $tournament);
			doroto_service_progress($tournament_id);
		}

		return doroto_service_ok('player_added_to_tournament', [
			'player_name' => doroto_find_player_name($player_id, intval($tournament->whole_names)),
			'already_added' => $already_added,
			'last_update' => $last_update,
		]);
	});
}

/**
 * Remove a player who has not played yet. An organizer may remove anybody, a player himself.
 * @return array|WP_Error player_removed_from_tournament with player_name, last_update
 * @since 2.0.0 (merged from doroto_remove_player_from_tournament and doroto_remove_player_via_api)
 */
function doroto_service_remove_player(int $tournament_id, int $player_id)
{
	global $wpdb;

	$current_user_id = get_current_user_id();
	if ($current_user_id === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	if ($tournament_id <= 0 || $player_id <= 0) {
		return doroto_service_error('missing_tournament_or_player_id');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $player_id, $current_user_id) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) < 1 && $player_id !== $current_user_id) {
			return doroto_service_error('auth_insufficient_permissions', 403);
		}

		$players = maybe_unserialize($tournament->players);
		$players = is_array($players) ? array_map('intval', $players) : [];
		if (!in_array($player_id, $players, true)) {
			return doroto_service_error('remove_player_not_in_tournament');
		}

		$statistics = maybe_unserialize($tournament->statistics);
		$statistics = is_array($statistics) ? $statistics : [];
		$statistics_new = doroto_remove_player_from_statistics_table($tournament, $statistics, $player_id);
		if ($statistics === $statistics_new) {
			return doroto_service_error('remove_player_has_played');
		}

		$special_group = maybe_unserialize($tournament->special_group);
		$special_group = is_array($special_group) ? $special_group : [];
		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'players' => serialize(array_values(array_diff($players, [$player_id]))),
				'statistics' => serialize($statistics_new),
				'special_group' => serialize(array_values(array_diff($special_group, [$player_id]))),
				'last_update' => $last_update,
			],
			['id' => $tournament_id]
		);
		doroto_service_progress($tournament_id);

		return doroto_service_ok('player_removed_from_tournament', [
			'player_name' => doroto_find_player_name($player_id, intval($tournament->whole_names)),
			'last_update' => $last_update,
		]);
	});
}

/**
 * Suspend ($active = false) or restore a player; player 0 = all players (organizer only).
 * An organizer may change anybody, a player only himself.
 * @return array|WP_Error all_players_suspended / single_player_suspended /
 *                        all_players_restored / single_player_restored (player_name, last_update)
 * @since 2.0.0 (merged from the suspend/restore forms and REST routes)
 */
function doroto_service_set_player_active(int $tournament_id, int $player_id, bool $active)
{
	global $wpdb;

	$current_user_id = get_current_user_id();
	if ($current_user_id === 0) {
		return doroto_service_error('auth_not_authorized', 401);
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $player_id, $active, $current_user_id) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (!$active && intval($tournament->close_tournament) === 1) {
			return doroto_service_error('suspend_player_tournament_closed', 403);
		}
		if (doroto_is_admin($tournament_id) < 1 && $player_id !== $current_user_id) {
			// Codes of 1.4.7 kept for the app.
			return doroto_service_error($active ? 'auth_forbidden' : 'suspend_player_tournament_closed', 403);
		}

		$statistics = maybe_unserialize($tournament->statistics);
		if (!is_array($statistics)) {
			return doroto_service_error('invalid_tournament_statistics', 500);
		}
		foreach ($statistics as &$player) {
			if ($player_id === 0 || intval($player['player_id']) === $player_id) {
				$player['active'] = $active ? 1 : 0;
			}
		}
		unset($player);

		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			['statistics' => serialize($statistics), 'last_update' => $last_update],
			['id' => $tournament_id]
		);
		doroto_service_progress($tournament_id);

		if ($player_id === 0) {
			return doroto_service_ok($active ? 'all_players_restored' : 'all_players_suspended', ['last_update' => $last_update]);
		}
		return doroto_service_ok($active ? 'single_player_restored' : 'single_player_suspended', [
			'player_name' => doroto_find_player_name($player_id, intval($tournament->whole_names)),
			'last_update' => $last_update,
		]);
	});
}

/**
 * Add a player to, or take him from, a player list of a tournament (organizer only).
 * Used for the special group (`special_group`) and the payments (`payment_done`).
 * @param string $column 'special_group' or 'payment_done'
 * @return array|WP_Error see doroto_service_set_special_group() and doroto_service_set_payment()
 * @since 2.0.0
 */
function doroto_service_set_list_member(int $tournament_id, string $column, int $player_id, bool $add)
{
	global $wpdb;

	if (!in_array($column, ['special_group', 'payment_done'], true)) {
		return doroto_service_error('invalid_data');
	}
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	if ($tournament_id <= 0 || $player_id <= 0) {
		return doroto_service_error('invalid_data');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $column, $player_id, $add) {
		$tournament = doroto_service_admin_tournament($tournament_id);
		if (is_wp_error($tournament)) {
			return $tournament;
		}
		$players = maybe_unserialize($tournament->players);
		$players = is_array($players) ? array_map('intval', $players) : [];
		if ($add && !in_array($player_id, $players, true)) {
			return doroto_service_error('remove_player_not_in_tournament');
		}

		// IDs may be stored as strings or ints in the serialized data.
		$list = maybe_unserialize($tournament->$column);
		$list = is_array($list) ? array_map('intval', array_values($list)) : [];
		$member = in_array($player_id, $list, true);
		if ($member === $add) {
			return ['changed' => false, 'tournament' => $tournament, 'last_update' => intval($tournament->last_update)];
		}

		// array_values: a gap in the keys made json_encode send an object, not a list.
		$list = $add ? array_merge($list, [$player_id]) : array_values(array_diff($list, [$player_id]));
		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[$column => serialize($list), 'last_update' => $last_update],
			['id' => $tournament_id]
		);
		doroto_service_progress($tournament_id);
		return ['changed' => true, 'tournament' => $tournament, 'last_update' => $last_update];
	});
}

/**
 * Add a player to the special group or take him from it.
 * @return array|WP_Error player_added_to_special_group / player_already_in_special_group /
 *                        player_removed_from_special_group / player_not_in_special_group
 * @since 2.0.0 (merged from the special group forms and REST routes)
 */
function doroto_service_set_special_group(int $tournament_id, int $player_id, bool $add)
{
	$result = doroto_service_set_list_member($tournament_id, 'special_group', $player_id, $add);
	if (is_wp_error($result)) {
		return $result;
	}
	if (!$result['changed']) {
		return doroto_service_ok($add ? 'player_already_in_special_group' : 'player_not_in_special_group', [
			'last_update' => $result['last_update'],
		]);
	}
	return doroto_service_ok($add ? 'player_added_to_special_group' : 'player_removed_from_special_group', [
		'player_name' => doroto_find_player_name($player_id, intval($result['tournament']->whole_names)),
		'last_update' => $result['last_update'],
	]);
}

/**
 * Record a payment of a player or remove it.
 * @return array|WP_Error payment_recorded / payment_removed, or payment_not_found
 * @since 2.0.0 (merged from the payment forms and REST routes)
 */
function doroto_service_set_payment(int $tournament_id, int $player_id, bool $paid)
{
	$result = doroto_service_set_list_member($tournament_id, 'payment_done', $player_id, $paid);
	if (is_wp_error($result)) {
		return $result;
	}
	if (!$paid && !$result['changed']) {
		return doroto_service_error('payment_not_found');
	}
	$extra = $paid ? ['message' => __('Payment(s) recorded.', 'doubles-rotation-tournament')] : []; // REST field of 1.4.7
	return doroto_service_ok($paid ? 'payment_recorded' : 'payment_removed', $extra + [
		'player_name' => doroto_find_player_name($player_id, intval($result['tournament']->whole_names)),
		'last_update' => $result['last_update'],
	]);
}

/**
 * Is the current user the founder of the tournament (admin_users[0]) or a web administrator
 * (doroto_is_admin() == 2)? Only they manage the organizers.
 * @since 2.0.0 (from doroto_super_admin_check)
 */
function doroto_service_is_founder(stdClass $tournament): bool
{
	$user_id = get_current_user_id();
	$admins = maybe_unserialize($tournament->admin_users);
	$founder = (is_array($admins) && !empty($admins)) ? intval(reset($admins)) : 0;
	return $user_id > 0 && ($user_id === $founder || doroto_is_admin(intval($tournament->id)) == 2);
}

/**
 * Make a user an organizer of the tournament. Any organizer may do it.
 * @return array|WP_Error organizer_added with player_name, last_update
 * @since 2.0.0 (merged from doroto_add_admin_form_submit and doroto_rest_add_admin)
 */
function doroto_service_add_admin(int $tournament_id, int $user_id)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized_or_expired', 401);
	}
	if ($user_id <= 0 || get_userdata($user_id) === false) {
		return doroto_service_error('missing_tournament_or_player_id');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $user_id) {
		$tournament = doroto_service_admin_tournament($tournament_id);
		if (is_wp_error($tournament)) {
			return $tournament;
		}
		if (intval($tournament->close_tournament) === 1) {
			return doroto_service_error('add_admin_tournament_closed');
		}

		$admins = maybe_unserialize($tournament->admin_users);
		$admins = is_array($admins) ? array_map('intval', array_values($admins)) : [];
		$last_update = intval($tournament->last_update);
		if (!in_array($user_id, $admins, true)) {
			$admins[] = $user_id;
			$last_update = doroto_now_ms();
			$wpdb->update(
				$wpdb->prefix . 'doroto_tournaments',
				['admin_users' => serialize($admins), 'last_update' => $last_update],
				['id' => $tournament_id]
			);
		}
		return doroto_service_ok('organizer_added', [
			'player_name' => doroto_find_player_name($user_id, intval($tournament->whole_names)),
			'last_update' => $last_update,
		]);
	});
}

/**
 * Take the organizer rights from a user. Founder only; the founder himself stays.
 * @return array|WP_Error organizer_removed with player_name, last_update;
 *                        errors cannot_remove_founder, admin_not_found
 * @since 2.0.0 (from doroto_rest_remove_admin)
 */
function doroto_service_remove_admin(int $tournament_id, int $user_id)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized_or_expired', 401);
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $user_id) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (!doroto_service_is_founder($tournament)) {
			return doroto_service_error('auth_insufficient_permissions', 403);
		}

		$admins = maybe_unserialize($tournament->admin_users);
		$admins = is_array($admins) ? array_map('intval', array_values($admins)) : [];
		$key = array_search($user_id, $admins, true);
		if ($key === false) {
			return doroto_service_error('admin_not_found', 404);
		}
		if ($key === 0) {
			return doroto_service_error('cannot_remove_founder', 403);
		}
		unset($admins[$key]);
		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			['admin_users' => serialize(array_values($admins)), 'last_update' => $last_update],
			['id' => $tournament_id]
		);
		return doroto_service_ok('organizer_removed', [
			'player_name' => doroto_find_player_name($user_id, intval($tournament->whole_names)),
			'last_update' => $last_update,
		]);
	});
}

/**
 * Tournament settings a client may change, with their sanitizers (REST field names).
 * @since 2.0.0 (from doroto_tournament_save_via_api)
 */
function doroto_service_settings_fields(): array
{
	return [
		'name' => function ($val) {
			return substr(sanitize_text_field($val), 0, 100);
		},
		'courts_available' => 'intval',
		'tournament_type' => 'intval',
		'max_players' => 'intval',
		'whole_names' => 'intval',
		'minimum_matches' => 'intval',
		'allow_input_results' => 'intval',
		'two_special_group' => 'intval',
		'two_out_group' => 'intval',
		'special_group_can_win' => 'intval',
		'temp_suspend_winner' => 'intval',
		'play_final_match' => 'intval',
		'min_not_playing' => 'intval',
		'payment_display' => 'intval',
		'announce_round_end' => 'intval',
		'games_hour' => 'intval',
		'average_result' => 'intval',
		'visibility' => 'intval',
		'invitation' => function ($val) {
			return substr(wp_kses((string) $val, doroto_allowed_html()), 0, 5000);
		},
		'latitude' => 'floatval',
		'longitude' => 'floatval',
	];
}

/**
 * Was the tournament closed more than 24 hours ago? Then its settings stay as they are.
 * @since 2.0.0 (rule of doroto_tournament_save_via_api)
 */
function doroto_service_closed_for_changes(stdClass $tournament): bool
{
	if (intval($tournament->close_tournament) !== 1 || empty($tournament->close_date)) {
		return false;
	}
	$closed = strtotime($tournament->close_date . ' UTC');
	return $closed !== false && (time() - $closed) >= 24 * 3600;
}

/**
 * Save tournament settings (organizer only). Missing fields keep their value.
 * Flags in $params: delete_tournament, empty_tournament (start again with the same players),
 * new_post (create a WordPress post for the tournament).
 * The tournament type changes only before the first match was drawn; the sport name
 * in the tournament name and invitation follows it.
 * @return array|WP_Error tournament_updated (last_update, post_not_allowed) or tournament_deleted
 * @since 2.0.0 (merged from doroto_tournament_parameters_results and doroto_tournament_save_via_api)
 */
function doroto_service_save_settings(int $tournament_id, array $params)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_not_logged_in', 401);
	}
	if ($tournament_id <= 0) {
		return doroto_service_error('invalid_tournament_id');
	}
	$types = doroto_tournament_types();
	if (isset($params['tournament_type']) && !array_key_exists(intval($params['tournament_type']), $types)) {
		// An unknown type was stored as is (e.g. 11) and the tournament then silently played as singles.
		return doroto_service_error('invalid_tournament_type');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $params, $types) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) == 0) {
			return doroto_service_error('not_admin_permission', 403);
		}

		if (!empty($params['delete_tournament'])) {
			$page_id = intval($tournament->page_id);
			if ($page_id > 0) {
				wp_delete_post($page_id, true);
			}
			$wpdb->delete($wpdb->prefix . 'doroto_tournaments', ['id' => $tournament_id]);
			return doroto_service_ok('tournament_deleted');
		}

		if (doroto_service_closed_for_changes($tournament)) {
			return doroto_service_error('tournament_closed_for_changes', 403);
		}

		$fields = [];
		foreach (doroto_service_settings_fields() as $key => $sanitizer) {
			if (isset($params[$key])) {
				$fields[$key] = call_user_func($sanitizer, $params[$key]);
			}
		}
		if (isset($fields['name']) && $fields['name'] === '') {
			unset($fields['name']);
		}

		$old_type = intval($tournament->tournament_type);
		if (isset($fields['tournament_type']) && $fields['tournament_type'] !== $old_type) {
			$matches = maybe_unserialize($tournament->matches_list);
			if (is_array($matches) && count($matches) > 1) {
				unset($fields['tournament_type']);
			} else {
				$before = sanitize_text_field($types[$old_type] ?? '');
				$after = sanitize_text_field($types[$fields['tournament_type']]);
				if ($before !== '') {
					foreach (['name', 'invitation'] as $text) {
						$value = $fields[$text] ?? $tournament->$text;
						$fields[$text] = str_replace($before, $after, (string) $value);
					}
				}
			}
		}

		if (!empty($params['empty_tournament'])) {
			$fields['statistics'] = '';
			$fields['matches_list'] = '';
			$fields['open_registration'] = 1;
			$fields['close_tournament'] = 0;
			$fields['final_result'] = '';
			$fields['final_four'] = '';
			$fields['playing'] = '';
			$fields['close_date'] = '9999-09-09 09:09:09';
		}

		$last_update = doroto_now_ms();
		if (!empty($fields)) {
			$fields['last_update'] = $last_update;
			$wpdb->update($wpdb->prefix . 'doroto_tournaments', $fields, ['id' => $tournament_id]);
		}

		// Names (whole_names) and the special group rules are part of the statistics table.
		$tournament = doroto_prepare_tournament($tournament_id);
		$players = maybe_unserialize($tournament->players);
		$statistics = doroto_create_statistics_table($tournament, is_array($players) ? $players : [], intval($tournament->whole_names));
		$updated = $wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			['statistics' => serialize($statistics), 'last_update' => $last_update],
			['id' => $tournament_id]
		);
		if ($updated === false) {
			return doroto_service_error('db_update_failed', 500);
		}
		doroto_service_progress($tournament_id);

		$post_not_allowed = false;
		if (!empty($params['new_post'])) {
			$only_admin_posts = intval(doroto_read_settings('only_admin_posts', 1));
			$level = doroto_is_admin($tournament_id);
			if (($only_admin_posts == 1 && $level == 2) || ($only_admin_posts == 0 && $level > 0)) {
				doroto_create_new_tournament_post($tournament_id);
			} else {
				$post_not_allowed = true;
			}
		}

		return doroto_service_ok('tournament_updated', [
			'last_update' => max($last_update, doroto_service_stored_last_update($tournament_id)),
			'post_not_allowed' => $post_not_allowed,
		]);
	});
}

/**
 * Choose the two final pairs (L1+R1 against L2+R2). Organizer only; closes the tournament.
 * @return array|WP_Error final_four_saved with last_update
 * @since 2.0.0 (merged from doroto_save_final_doubles and tournament-save)
 */
function doroto_service_set_final_four(int $tournament_id, int $l1, int $p1, int $l2, int $p2)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_not_logged_in', 401);
	}
	$four = [$l1, $p1, $l2, $p2];
	if (min($four) <= 0) {
		return doroto_service_error('invalid_data');
	}
	if (count(array_unique($four)) !== 4) {
		return doroto_service_error('final_players_not_different');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $l1, $p1, $l2, $p2) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) == 0) {
			return doroto_service_error('not_admin_permission', 403);
		}
		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'final_four' => serialize(['l1' => $l1, 'p1' => $p1, 'l2' => $l2, 'p2' => $p2]),
				'close_tournament' => 1,
				'last_update' => $last_update,
			],
			['id' => $tournament_id]
		);
		return doroto_service_ok('final_four_saved', ['last_update' => $last_update]);
	});
}

/**
 * Enter the result of the final match. Organizers, or one of the four finalists when
 * players may enter results. Closes the tournament.
 * @return array|WP_Error final_result_saved with last_update
 * @since 2.0.0 (merged from doroto_update_final_match_result and tournament-save)
 */
function doroto_service_set_final_result(int $tournament_id, int $result_1, int $result_2)
{
	global $wpdb;

	$user_id = get_current_user_id();
	if ($user_id === 0) {
		return doroto_service_error('auth_not_logged_in', 401);
	}
	if ($result_1 < 0 || $result_2 < 0 || ($result_1 === 0 && $result_2 === 0)) {
		return doroto_service_error('invalid_data');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $result_1, $result_2, $user_id) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) == 0) {
			$four = maybe_unserialize($tournament->final_four);
			$finalists = is_array($four) ? array_map('intval', array_values($four)) : [];
			if (intval($tournament->allow_input_results) !== 1 || !in_array($user_id, $finalists, true)) {
				return doroto_service_error('not_admin_permission', 403);
			}
		}
		$last_update = doroto_now_ms();
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'final_result' => serialize(['result_1' => $result_1, 'result_2' => $result_2]),
				'close_tournament' => 1,
				'last_update' => $last_update,
			],
			['id' => $tournament_id]
		);
		return doroto_service_ok('final_result_saved', ['last_update' => $last_update]);
	});
}

/**
 * Answer the "end of the round" notice (organizer only):
 * 'next' = announce the end of the next round again, 'hide' = no more notices,
 * 'end' = close the tournament (like the toggle, the final pairs are cleared).
 * @return array|WP_Error round_continued / notification_hidden / tournament_ended with last_update
 * @since 2.0.0 (merged from the notice links and doroto_rest_round_end_action)
 */
function doroto_service_round_end_action(int $tournament_id, string $action)
{
	global $wpdb;

	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	$codes = ['next' => 'round_continued', 'hide' => 'notification_hidden', 'end' => 'tournament_ended'];
	if (!isset($codes[$action])) {
		return doroto_service_error('invalid_action');
	}

	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $action, $codes) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return doroto_service_error('tournament_not_found', 404);
		}
		if (doroto_is_admin($tournament_id) <= 0) {
			return doroto_service_error('auth_forbidden', 403);
		}

		$last_update = doroto_now_ms();
		if ($action === 'end') {
			$fields = intval($tournament->close_tournament) === 1 ? [] : [
				'close_tournament' => 1,
				'final_four' => '',
				'final_result' => '',
				'close_date' => gmdate('Y-m-d H:i:s'),
			];
		} else {
			$fields = ['announce_round_end' => $action === 'next' ? 2 : 0];
		}
		$fields['last_update'] = $last_update;
		$wpdb->update($wpdb->prefix . 'doroto_tournaments', $fields, ['id' => $tournament_id]);

		doroto_service_progress_and_draw($tournament_id);
		return doroto_service_ok($codes[$action], [
			'last_update' => max($last_update, doroto_service_stored_last_update($tournament_id)),
		]);
	});
}

/**
 * The current user joins a tournament with open registration.
 * @return array|WP_Error registered / already_registered (last_update);
 *                        errors registration_is_closed, registration_max_players
 * @since 2.0.0 (merged from doroto_register_player and doroto_rest_register_player)
 */
function doroto_service_join(int $tournament_id)
{
	$user_id = get_current_user_id();
	if ($user_id === 0) {
		return doroto_service_error('auth_not_logged_in', 401);
	}
	$status = doroto_add_user_to_tournament($tournament_id, $user_id);
	switch ($status) {
		case 'added':
			return doroto_service_ok('registered', ['last_update' => doroto_service_stored_last_update($tournament_id)]);
		case 'already':
			return doroto_service_ok('already_registered', ['last_update' => doroto_service_stored_last_update($tournament_id)]);
		case 'closed':
			return doroto_service_error('registration_is_closed', 403);
		case 'full':
			return doroto_service_error('registration_max_players');
	}
	return doroto_service_error('tournament_not_found', 404);
}

/**
 * The current user leaves a tournament while its registration is open and he has not played.
 * @return array|WP_Error unregistered (last_update); errors registration_is_closed, unregister_player_has_played
 * @since 2.0.0 (merged from the leave link and doroto_rest_register_player)
 */
function doroto_service_leave(int $tournament_id)
{
	$user_id = get_current_user_id();
	if ($user_id === 0) {
		return doroto_service_error('auth_not_logged_in', 401);
	}
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return doroto_service_error('tournament_not_found', 404);
	}
	$players = maybe_unserialize($tournament->players);
	if (!is_array($players) || !in_array($user_id, array_map('intval', $players), true)) {
		return doroto_service_ok('unregistered', ['last_update' => intval($tournament->last_update)]);
	}
	if ($tournament->open_registration != '1') {
		return doroto_service_error('registration_is_closed', 403);
	}
	$result = doroto_service_remove_player($tournament_id, $user_id);
	if (is_wp_error($result)) {
		switch ($result->get_error_code()) {
			case 'remove_player_has_played':
				return doroto_service_error('unregister_player_has_played');
		}
		return $result;
	}
	return doroto_service_ok('unregistered', ['last_update' => $result['last_update']]);
}

/**
 * May the current user create tournaments? With "only_admin_creates" only web
 * administrators, editors and authors may.
 * @since 2.0.0
 */
function doroto_service_may_create_tournament(): bool
{
	if (get_current_user_id() === 0) {
		return false;
	}
	if (doroto_read_settings('only_admin_creates', 0) != 1) {
		return true;
	}
	$roles = (array) wp_get_current_user()->roles;
	return (bool) array_intersect(['administrator', 'editor', 'author'], $roles);
}

/**
 * Create a tournament of the given type; the current user becomes its founder.
 * @return array|WP_Error tournament_created with tournament_id
 * @since 2.0.0 (merged from doroto_add_tournament_result and doroto_rest_add_tournament)
 */
function doroto_service_add_tournament(int $tournament_type)
{
	if (get_current_user_id() === 0) {
		return doroto_service_error('auth_not_authenticated', 401);
	}
	if (!doroto_service_may_create_tournament()) {
		return doroto_service_error('auth_insufficient_permissions', 403);
	}
	if (!array_key_exists($tournament_type, doroto_tournament_types())) {
		return doroto_service_error('invalid_tournament_type');
	}
	$tournament_id = intval(doroto_insert_tournament($tournament_type));
	if ($tournament_id <= 0) {
		return doroto_service_error('create_failed', 500);
	}
	return doroto_service_ok('tournament_created', ['tournament_id' => $tournament_id]);
}

/**
 * Organizer creates a player account and adds it to the tournament in one step.
 * When the e-mail already exists the existing user is added. A new account gets the
 * subscriber role, `doroto_creator` (so it shows up in the organizer's list) and an
 * e-mail with a link to set the password.
 * @return array|WP_Error player_added_to_tournament with created, already_in_tournament,
 *                        email_sent, player_id, player_name, last_update
 * @since 2.0.0 (merged from doroto_register_add_player_form_submit and doroto_rest_create_player)
 */
function doroto_service_create_player(int $tournament_id, string $email, string $name, string $surname)
{
	$current_user_id = get_current_user_id();
	if ($current_user_id === 0) {
		return doroto_service_error('auth_unauthorized', 401);
	}
	$email = sanitize_email($email);
	$name = sanitize_text_field($name);
	$surname = sanitize_text_field($surname);

	if ($tournament_id <= 0 || !doroto_prepare_tournament($tournament_id)) {
		return doroto_service_error('tournament_not_found', 404);
	}
	if (doroto_is_admin($tournament_id) < 1) {
		return doroto_service_error('add_player_forbidden', 403);
	}
	if (empty($email) || !is_email($email) || $name === '' || $surname === '') {
		return doroto_service_error('registration_incomplete_data');
	}

	$created = false;
	$email_sent = false;
	$existing = get_user_by('email', $email);
	if ($existing) {
		$user_id = intval($existing->ID);
	} else {
		$username = sanitize_user(explode('@', $email)[0] . '_' . wp_generate_password(4, false));
		$user_id = wp_create_user($username, wp_generate_password(16, true), $email);
		if (is_wp_error($user_id)) {
			return doroto_service_error('registration_creation_failed', 500);
		}
		$user_id = intval($user_id);
		wp_update_user([
			'ID' => $user_id,
			'first_name' => $name,
			'last_name' => $surname,
			'display_name' => trim($name . ' ' . $surname),
		]);
		(new WP_User($user_id))->set_role('subscriber');
		update_user_meta($user_id, 'doroto_creator', $current_user_id);
		$created = true;
		$email_sent = doroto_send_account_created_email($user_id);
	}

	$status = doroto_add_user_to_tournament($tournament_id, $user_id, true);
	if ($status === 'full') {
		return new WP_Error('registration_max_players', 'registration_max_players', ['status' => 400, 'created' => $created]);
	}
	if ($status === 'not_found') {
		return doroto_service_error('tournament_not_found', 404);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	return doroto_service_ok('player_added_to_tournament', [
		'created' => $created,
		'already_in_tournament' => $status === 'already',
		'email_sent' => $email_sent,
		'player_id' => $user_id,
		'player_name' => doroto_find_player_name($user_id, intval($tournament->whole_names)),
		'last_update' => intval($tournament->last_update),
	]);
}
