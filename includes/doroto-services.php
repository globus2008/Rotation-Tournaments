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
