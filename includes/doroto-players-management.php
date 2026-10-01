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
 * register a new player in the tournament
 * @version 1.3.6 (not only web admin can change game results)
 * @since 1.0.0
 */
function doroto_register_player($tournament_id)
{
	global $wpdb;

	$current_user = wp_get_current_user();
	$current_user_id = intval($current_user->ID);
	$output = '';

	if (isset($_GET['tournament_id'])) {
		$tournament_id = intval($_GET['tournament_id']);
	} else {
		$output = sanitize_text_field(__("Tournament ID was not provided.", "doubles-rotation-tournament"));
		doroto_info_messsages_save($output);
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	if (!is_user_logged_in()) {
		$output = sanitize_text_field(__("If you want to register for the tournament, you must log in to your account!", "doubles-rotation-tournament"));
		doroto_info_messsages_save($output);
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament !== null) {
		// Checking if the tournament is open
		if ($tournament->open_registration == '1') {
			// Checking if the player field is defined and if it really is an field            
			// Get a list of tournament players
			$players = maybe_unserialize($tournament->players);
			$special_group = maybe_unserialize($tournament->special_group);

			if (!is_array($players)) {
				$players = [];
			}

			if (!is_array($special_group)) {
				$special_group = [];
			}

			// Checking if the user is already registered
			if (in_array($current_user_id, $players)) {
				doroto_remove_player_from_tournament($tournament_id, $current_user_id);
			} else {
				// The user is not registered, we will add him
				$max_players = intval($tournament->max_players);
				if (count($players) < $max_players || $max_players == 0) {
					$players[] = intval($current_user->ID);
					$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
					$wpdb->update(
						$table_name,
						array(
							'players' => serialize($players),
							'statistics' => serialize($statistics),
							'last_update'  => round(microtime(true) * 1000)
						),
						array('id' => $tournament_id)
					);
					$output = sanitize_text_field(__("You signed up for tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.');
				} else {
					//the tournament has reached the maximum number of entries
					$output = sanitize_text_field(__("We are sorry, but the maximum number of registered participants has been reached in tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.');
				}
			}
		} else {
			$output = sanitize_text_field(__("The registration for the tournament has already been closed.", "doubles-rotation-tournament"));
		}
	} else {
		$output = sanitize_text_field(__("The tournament was not found.", "doubles-rotation-tournament"));
	}
	doroto_tournament_progress($tournament_id);
	doroto_info_messsages_save($output);
	doroto_redirect_modify_url($tournament_id, "");
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
	$tournaments = $wpdb->get_results("SELECT * FROM $table_name");

	foreach ($tournaments as $tournament) {
		$players = maybe_unserialize($tournament->players);
		$admin_users = maybe_unserialize($tournament->admin_users);

		if (!is_array($players) || !is_array($admin_users)) {
			continue;
		}

		if (!in_array($current_user_id, $players)) {
			continue;
		}

		if ($level == 0) {
			$all_players[] = $current_user_id;
			return $all_players;
		}

		if ($level == 2 && !in_array($current_user_id, $admin_users)) {
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

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);

	if (!$tournament) {
		wp_send_json_error(['message' => 'Tournament not found']);
		return;
	}

	$admin_users = maybe_unserialize($tournament->admin_users);
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
add_action('wp_ajax_nopriv_doroto_add_current_user_to_admin', 'doroto_add_current_user_to_admin');


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
