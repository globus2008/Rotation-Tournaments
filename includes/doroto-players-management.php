<?php
if (! defined('ABSPATH')) {
	exit;
}


/**
 * add a new player to statistics array
 * @since 1.0.0
 * @version 1.0.1
 */
function doroto_add_player_statistics_table(&$statistics, $tournament, $players)
{
	global $wpdb;
	foreach ($statistics as &$player) {

		foreach ($players as $player_id) {
			if (!in_array($player_id, array_column($player['playmates'], 'player_id')) && $player['player_id'] != $player_id) {
				$player['playmates'][] = array('player_id' => $player_id, 'count' => 0);
			}
			if (!in_array($player_id, array_column($player['playmates_L'], 'player_id')) && $player['player_id'] != $player_id) {
				$player['playmates_L'][] = array('player_id' => $player_id, 'count' => 0);
			}
			if (!in_array($player_id, array_column($player['playmates_P'], 'player_id')) && $player['player_id'] != $player_id) {
				$player['playmates_P'][] = array('player_id' => $player_id, 'count' => 0);
			}
			if (!in_array($player_id, array_column($player['opponents'], 'player_id')) && $player['player_id'] != $player_id) {
				$player['opponents'][] = array('player_id' => $player_id, 'count' => 0);
			}
		}
	}
	unset($player);
	return $statistics;
}


/**
 * it goes through the $statistics array and if active for a player is 1, adds their player_id to the active players array
 * @since 1.0.0
 */
function doroto_find_active_players($statistics)
{
	$activePlayers = array();
	if (empty($statistics)) {
		return $activePlayers;
	}
	foreach ($statistics as $player) {
		if ($player['active'] == 1) {
			$activePlayers[] = $player['player_id'];
		}
	}
	return $activePlayers;
}


/**
 * return winner name
 * @since 1.0.0
 * @version 1.3.1
 */
function doroto_get_winner($tournament_id, $whole_names)
{
	global $wpdb;

	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament == null) {
		$doroto_output_form = sanitize_text_field(__('The tournament was not found.', 'doubles-rotation-tournament'));
		return '';
	}

	$players = maybe_unserialize($tournament->players);
	if (empty($players)) {
		$doroto_output_form = '<p>' . sanitize_text_field(__('No one has registered for the tournament yet.', 'doubles-rotation-tournament')) . '<p>';
		return '';
	}

	$statistics = maybe_unserialize($tournament->statistics);
	$temp_suspend_winner = intval($tournament->temp_suspend_winner);
	$minimum_matches = intval($tournament->minimum_matches);

	// Sort the players by ratio, descending
	usort($statistics, function ($a, $b) {
		return $b['ratio'] <=> $a['ratio'];
	});

	$players = maybe_unserialize($tournament->players);

	$special_group = maybe_unserialize($tournament->special_group);
	$special_group_can_win = intval($tournament->special_group_can_win);

	if (!is_array($special_group)) {
		$special_group = [];
	}

	$winner_ratio = 0;
	$winner_ratio_special = 0;
	$winners = [];

	foreach ($statistics as $index => $player_result) {
		// Check if player ID is in special_group array and skip this player
		if ((in_array($player_result['player_id'], $special_group)) && $special_group_can_win == 0) {
			continue;
		} elseif (!$temp_suspend_winner && $player_result['active'] == 0) {
			continue;
		} elseif ($player_result['games'] < $minimum_matches) {
			continue;
		} elseif ((in_array($player_result['player_id'], $special_group)) && $special_group_can_win == 2) {
			if ($winner_ratio_special <= $player_result['ratio']) {
				$winners[] = $player_result['player_id'];
				$winner_ratio_special = $player_result['ratio'];
				continue;
			} elseif ($winner_ratio > 0 && $winner_ratio_special > 0) {
				break;
			}
		} else {
			if ($winner_ratio <= $player_result['ratio']) {
				$winners[] = $player_result['player_id'];
				$winner_ratio = $player_result['ratio'];
				continue;
			} elseif ($winner_ratio > 0 && ($winner_ratio_special > 0 && $special_group_can_win == 2)) {
				break;
			}
		}
	}
	if ($winner_ratio == 0 && $winner_ratio_special == 0) {
		$winners = [];
	}
	return $winners;
}


/**
 * Creating a shortcode for the player dropdown box
 * @since 1.0.0
 */
function doroto_get_players_from_tournaments($tournament_id)
{
	global $wpdb;

	$result = $wpdb->get_var(
		$wpdb->prepare("SELECT players FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id)
	);

	if ($result) {
		$players = maybe_unserialize($result);
		if (is_array($players)) {
			return $players;
		}
	}
	return array(); // If there is no player list or there is an error, we return an empty field.
}


/**
 * Public URL of the page that shows a tournament.
 * Used as redirect target instead of HTTP_REFERER: invitation links are opened
 * from e-mail, WhatsApp or Facebook, where the referer is an external host and
 * wp_safe_redirect() fell back to /wp-admin/.
 * @since 1.6.0
 */
function doroto_tournament_page_url(int $tournament_id)
{
	$main_page_id = intval(get_option('doroto_main_page_id'));
	$base = ($main_page_id && get_post($main_page_id)) ? get_permalink($main_page_id) : home_url('/');
	return $tournament_id > 0 ? add_query_arg('tournament_id', $tournament_id, $base) : $base;
}

/**
 * Invitation (join) link of a tournament. Safe to share and to click repeatedly.
 * @since 1.6.0
 */
function doroto_join_url(int $tournament_id)
{
	return add_query_arg(
		['action' => 'doroto_register_player', 'tournament_id' => $tournament_id],
		admin_url('admin-ajax.php')
	);
}

/**
 * Link that removes the current user from a tournament (nonce protected).
 * @since 1.6.0
 */
function doroto_leave_url(int $tournament_id)
{
	return wp_nonce_url(
		add_query_arg(
			['action' => 'doroto_register_player', 'tournament_id' => $tournament_id, 'doroto_leave' => 1],
			admin_url('admin-ajax.php')
		),
		'doroto_leave_' . $tournament_id
	);
}

/**
 * Add the given user to a tournament (used by the invitation link and REST).
 * Returns one of: 'added', 'already', 'closed', 'full', 'not_found'.
 * @since 1.6.0
 */
function doroto_add_user_to_tournament(int $tournament_id, int $user_id, bool $ignore_closed = false)
{
	global $wpdb;
	return doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $user_id, $ignore_closed) {
		$tournament = doroto_prepare_tournament($tournament_id);
		if ($tournament === null || $user_id <= 0 || get_userdata($user_id) === false) {
			return 'not_found';
		}
		$players = maybe_unserialize($tournament->players);
		if (!is_array($players)) {
			$players = [];
		}
		$players = array_map('intval', $players);
		if (in_array($user_id, $players, true)) {
			return 'already';
		}
		if (!$ignore_closed && $tournament->open_registration != '1') {
			return 'closed';
		}
		$max_players = intval($tournament->max_players);
		if ($max_players > 0 && count($players) >= $max_players) {
			return 'full';
		}
		$players[] = $user_id;
		$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			array(
				'players' => serialize($players),
				'statistics' => serialize($statistics),
				'last_update'  => doroto_now_ms()
			),
			array('id' => $tournament_id)
		);
		doroto_tournament_progress($tournament_id);
		return 'added';
	});
}

/**
 * Invitation link handler: register the current user in the tournament.
 * Before 1.6.0 the link toggled the registration, so a second click (or a
 * link preview opened by a messenger) silently unregistered the player.
 * Now it only joins; leaving needs the nonce-protected doroto_leave_url().
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_join / doroto_service_leave)
 */
function doroto_register_player()
{
	$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
	$current_user_id = intval(get_current_user_id());

	if ($tournament_id <= 0) {
		doroto_info_messsages_save(sanitize_text_field(__("Tournament ID was not provided.", "doubles-rotation-tournament")));
		wp_safe_redirect(doroto_tournament_page_url(0));
		exit;
	}

	if (!is_user_logged_in()) {
		// Send the visitor to log in (or register) and come back to the same link.
		wp_safe_redirect(wp_login_url(doroto_join_url($tournament_id)));
		exit;
	}

	if (!empty($_GET['doroto_leave'])) {
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
		$result = wp_verify_nonce($nonce, 'doroto_leave_' . $tournament_id)
			? doroto_service_leave($tournament_id)
			: doroto_service_error('auth_insufficient_permissions', 403);
	} else {
		$result = doroto_service_join($tournament_id);
	}
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	wp_safe_redirect(doroto_tournament_page_url($tournament_id));
	exit;
}


add_action('wp_ajax_doroto_register_player', 'doroto_register_player');
add_action('wp_ajax_nopriv_doroto_register_player', 'doroto_register_player');
add_action('wp_ajax_doroto_toggle_registration', 'doroto_toggle_registration');


/**
 * check if a user has already registered to a tournament
 * @since 1.0.0
 * level = 3 quick escape (players from the current tournament)
 * level = 2 quick escape (for whom they have already organized a tournament in the past)
 * level = 1 quick escape (that they have met at a tournament where was also another organizer)
 * level = 0 quick escape (all players w/o limitations)
 */
function doroto_current_user_in_tournaments($level)
{
	global $wpdb;

	$all_players = [];
	$current_user_id = intval(get_current_user_id());
	if ($level == 3 || !$current_user_id) {
		return $all_players;
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	// Only the two columns we need; SELECT * loaded all match lists and statistics.
	$tournaments = $wpdb->get_results("SELECT players, admin_users FROM $table_name");

	foreach ($tournaments as $tournament) {
		$players = maybe_unserialize($tournament->players);
		$admin_users = maybe_unserialize($tournament->admin_users);

		if (!is_array($players) || !is_array($admin_users)) {
			continue;
		}
		$players = array_map('intval', $players);
		$admin_users = array_map('intval', $admin_users);

		// An organizer who does not play himself must still see the players of
		// his own tournaments (previously only tournaments he played in counted,
		// so a non-playing organizer saw an almost empty list).
		$is_player = in_array($current_user_id, $players, true);
		$is_organizer = in_array($current_user_id, $admin_users, true);
		if (!$is_player && !$is_organizer) {
			continue;
		}

		if ($level == 0) {
			$all_players[] = $current_user_id;
			return $all_players;
		}

		if ($level == 2 && !$is_organizer) {
			continue;
		}

		$new_values = array_diff($players, $all_players);
		$all_players = array_merge($all_players, $new_values);
	}
	return $all_players;
}



/**
 * add current user among admin
 * @version 1.3.7 
 * @since 1.3.7
 */
function doroto_add_current_user_to_admin()
{
	global $wpdb;

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;

	if ($tournament_id === 0) {
		wp_send_json_error(['message' => 'Invalid tournament ID']);
		return;
	}

	$player_id = intval(get_current_user_id());
	if ($player_id === 0 || !is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
		return;
	}

	check_ajax_referer('doroto_help_tour', 'nonce');

	// The guided tour may only make the visitor admin of the example tournaments.
	// Previously any user could take over (and then delete) any tournament.
	$example_ids = [];
	for ($i = 1; $i <= 4; $i++) {
		$example_ids[] = intval(doroto_read_settings('tournament_example_' . $i, 0));
	}
	if (!in_array($tournament_id, $example_ids, true) && !current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Not allowed']);
		return;
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);

	if (!$tournament) {
		wp_send_json_error(['message' => 'Tournament not found']);
		return;
	}

	$admin_users = maybe_unserialize($tournament->admin_users);
	if (!is_array($admin_users)) {
		$admin_users = [];
	}
	if (!in_array($player_id, $admin_users)) {
		$admin_users[] = $player_id;
		$wpdb->update(
			$table_name,
			[
				'admin_users' => serialize($admin_users),
				'last_update'  => round(microtime(true) * 1000)
			],
			['id' => $tournament_id]
		);
	}

	wp_send_json_success(['message' => 'User added as admin successfully']);
}
add_action('wp_ajax_doroto_add_current_user_to_admin', 'doroto_add_current_user_to_admin');


/**
 * filter to display data for the help icon
 * @since 1.3.7
 */
function doroto_player_filter_help()
{
	global $wpdb;

	$current_user_id = intval(get_current_user_id());
	if ($current_user_id == 0 || !is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
		return;
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
	if ($tournament_id === 0) {
		wp_send_json_error(['message' => 'Invalid tournament ID']);
		return;
	}
	$filter_input = isset($_POST['filter_input']) ? intval($_POST['filter_input']) : 0;
	$special_group_output = isset($_POST['special_group_output']) ? intval($_POST['special_group_output']) : 0;

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);

	$special_group = maybe_unserialize($tournament->special_group);
	$players = maybe_unserialize($tournament->players);

	if (!empty($players) && is_array($players) && !empty($special_group) && is_array($special_group)) {
		if ($special_group_output == 1) {
			$players = $special_group;
		} else {
			$players = array_diff($players, $special_group);
		}
		if (empty($players)) {
			return;
		}
		$random_key = array_rand($players);
		$selected_player = $players[$random_key];
	} else {
		return;
	}


	if ($filter_input === 0) {
		$selected_player = 0;
	}
	update_user_meta($current_user_id, 'doroto_filter_results', $selected_player);
	wp_send_json_success(['message' => 'Filter was set successfully']);
}
add_action('wp_ajax_doroto_player_filter_help', 'doroto_player_filter_help');
add_action('wp_ajax_nopriv_doroto_player_filter_help', 'doroto_player_filter_help');

/**
 * A deleted account (the app's "Delete profile" or deletion in wp-admin) stayed
 * an active player of its tournaments: it kept being drawn into matches as
 * "Unknown player". In every tournament that is not closed the player is
 * removed when they have not played yet, otherwise suspended so the played
 * matches and statistics stay. They also leave the organizers, the special
 * group and the payment list.
 * @since 1.6.1
 */
function doroto_remove_deleted_user_from_tournaments($user_id)
{
	global $wpdb;
	$user_id = intval($user_id);
	if ($user_id <= 0) {
		return;
	}
	$table = $wpdb->prefix . 'doroto_tournaments';
	$like = '%' . $wpdb->esc_like('i:' . $user_id . ';') . '%';
	$ids = $wpdb->get_col($wpdb->prepare(
		"SELECT id FROM $table WHERE close_tournament = 0 AND (players LIKE %s OR admin_users LIKE %s)",
		$like,
		$like
	));

	foreach ($ids as $tournament_id) {
		$tournament_id = intval($tournament_id);
		doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $table, $tournament_id, $user_id) {
			$tournament = doroto_prepare_tournament($tournament_id);
			if (!$tournament) {
				return;
			}
			$without = function ($value) use ($user_id) {
				$list = maybe_unserialize($value);
				$list = is_array($list) ? $list : [];
				return array_values(array_filter($list, function ($id) use ($user_id) {
					return intval($id) !== $user_id;
				}));
			};
			$players = maybe_unserialize($tournament->players);
			$players = is_array($players) ? $players : [];
			$statistics = maybe_unserialize($tournament->statistics);
			$statistics = is_array($statistics) ? $statistics : [];

			$fields = [
				'special_group' => serialize($without($tournament->special_group)),
				'payment_done' => serialize($without($tournament->payment_done)),
			];
			// Never leave a tournament without its organizer (index 0).
			$admins = maybe_unserialize($tournament->admin_users);
			if (is_array($admins) && count($admins) > 1 && intval(reset($admins)) !== $user_id) {
				$fields['admin_users'] = serialize($without($tournament->admin_users));
			}

			if (in_array($user_id, array_map('intval', $players), true)) {
				$statistics_new = doroto_remove_player_from_statistics_table($tournament, $statistics, $user_id);
				if ($statistics_new !== $statistics) {
					// Not played yet: remove completely.
					$fields['players'] = serialize($without($tournament->players));
					$fields['statistics'] = serialize($statistics_new);
				} else {
					// Played already: suspend, keep the history.
					foreach ($statistics as &$row) {
						if (intval($row['player_id']) === $user_id) {
							$row['active'] = 0;
						}
					}
					unset($row);
					$fields['statistics'] = serialize($statistics);
				}
			}

			$fields['last_update'] = doroto_now_ms();
			$wpdb->update($table, $fields, ['id' => $tournament_id]);
			doroto_tournament_progress($tournament_id);
		});
	}
}
add_action('delete_user', 'doroto_remove_deleted_user_from_tournaments');
