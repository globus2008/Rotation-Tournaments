<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * display tournament progress
 * @since 1.3.6
 * @version 1.3.6
 */
function doroto_display_tournament_progress()
{
	global $wpdb;
	$tournament_id = doroto_getTournamentId();
	if (!doroto_check_need_to_display('doroto_display_player_statistics')) {
		return;
	}

	if ($tournament_id == 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = '<div>' . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	$announce_round_end = intval($tournament->announce_round_end);
	if ($announce_round_end == 0) {
		return;
	}

	$planned_combinations = intval($tournament->planned_combinations);
	$rest_combinations = intval($tournament->rest_combinations);
	if ($planned_combinations == 0) {
		$output = '<div>' . esc_html__('Tournament progress calculation is not available.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	$finished_progress = floor(($planned_combinations - $rest_combinations) * 100 / $planned_combinations);
	$min_not_playing = intval($tournament->min_not_playing);
	$games_hour = intval($tournament->games_hour);
	$average_result = intval($tournament->average_result);
	$close_tournament = intval($tournament->close_tournament);
	$play_final_match = intval($tournament->play_final_match);

	$courts_possible = doroto_matches_to_select_count($tournament, 0);
	if ($courts_possible == 0) {
		$courts_possible = 1;
	}
	if ($min_not_playing == 0 && $rest_combinations > 4) {
		$draw_index = 0.7;
	} else {
		$draw_index = 1;
	}

	if (doroto_check_if_doubles($tournament)) {
		$players_coef = 2;
	} else {
		$players_coef = 1;
	}

	/*
	print('$rest_combinations:' . $rest_combinations);
	print('$play_final_match:' . $play_final_match);
	print('$players_coef:' . $players_coef);
	print('$courts_possible:' . $courts_possible);
	//print('$game_hour:'.$game_hour);
	print('$draw_index:' . $draw_index);
*/
	$hours_to_end = (($rest_combinations + ($play_final_match * $players_coef)) * $average_result) / ($players_coef * $games_hour * $courts_possible * $draw_index);
	//print('$hours_to_end:'.$hours_to_end);

	if ($close_tournament) {
		$finished_progress = 100;
		$hours_to_end = 0;
	}

	$output = '<div>' . esc_html__('Tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . ': ';
	$output .= esc_html__('completed', 'doubles-rotation-tournament') . ' ' . esc_html($finished_progress) . ' %.</div>';

	if ($hours_to_end > 0) {
		$output .= '<div id="doroto-tournament-progress">' . esc_html__('Estimated time to finish:', 'doubles-rotation-tournament') . ' ';
		if ($hours_to_end >= 1) {
			$output .= esc_html(floor($hours_to_end)) . ' ' . esc_html__('hours', 'doubles-rotation-tournament') . ' ';
			if (floor($hours_to_end) != $hours_to_end) {
				$output .= ' ' . esc_html__('&', 'doubles-rotation-tournament') . ' ';
			}
		}
		if (floor($hours_to_end) != $hours_to_end) {
			$output .= esc_html(floor(60 * ($hours_to_end - floor($hours_to_end)))) . ' ' . esc_html__('minutes', 'doubles-rotation-tournament') . '.</div>';
		} else {
			$output .= '.</div>';
		}
	}


	$games = maybe_unserialize($tournament->matches_list);
	if (is_array($games)) {
		$tournament_name = sanitize_text_field($tournament->name);
		$matches_played_count = 0;
		$game_played_count = 0;
		foreach ($games as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && ($match['result_1'] != 0 || $match['result_2'] != 0)) {
				$matches_played_count++;
				$game_played_count += $match['result_1'] + $match['result_2'];
			}
		}
	}
	return $output;
}

add_shortcode('doroto_display_tournament_progress', 'doroto_display_tournament_progress');


/**
 * display players statistics
 * @since 1.0.0
 * @version 1.2.5
 */
function doroto_display_player_statistics()
{
	global $wpdb;
	$tournament_id = doroto_getTournamentId();
	if (!doroto_check_need_to_display('doroto_display_player_statistics')) {
		return;
	}

	if ($tournament_id == 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = '<div>' . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	$statistics = maybe_unserialize($tournament->statistics);
	$players = maybe_unserialize($tournament->players);
	$special_group = maybe_unserialize($tournament->special_group);
	if (empty($statistics)) {
		$output = '<p>' . esc_html__('Once registration is closed, statistical data will be available.', 'doubles-rotation-tournament') . '</p>';
		return $output;
	}
	$whole_names = intval($tournament->whole_names);

	$current_user_id = intval(get_current_user_id());

	if (doroto_check_if_presentation_on()) {
		if ($players && is_array($players) && !empty($players)) {
			$randomKey = array_rand($players);
			$selected_player_id = $players[$randomKey];
		} else {
			$selected_player_id = 0;
		}
	} else {
		$selected_player_id = intval(get_user_meta($current_user_id, 'doroto_filter_results', true));
		if (!in_array($selected_player_id, $players)) {
			$selected_player_id = 0;
		}
	}

	if ($selected_player_id == 0) {
		$output = '<p id="doroto-statistical-data">' . esc_html__('Statistical data cannot be displayed without using a filter.', 'doubles-rotation-tournament') . '</p>';
		return $output;
	}

	$user = get_userdata($selected_player_id);
	$output = '<p>' . esc_html__('Tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . ': ' . esc_html__('Statistical data for', 'doubles-rotation-tournament') . ' <b>';
	if ($whole_names == 0) {
		$player_name = doroto_display_short_name($user->display_name);
	} else {
		$player_name = $user->display_name;
	}
	$output .= esc_html($player_name);
	$output .= '</b>.</p>';

	$selectedPlayerData = null;
	foreach ($statistics as $playerData) {
		if ($playerData['player_id'] == $selected_player_id) {
			$selectedPlayerData = $playerData;
			break;
		}
	}

	$output .= "<div class='doroto-table-responsive' id='doroto-statistical-data'>";
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th id="doroto-statistical-data-player">' . esc_html__('Player', 'doubles-rotation-tournament') . '</th>';

	if (doroto_check_if_doubles($tournament)) {
		$output .= '<th id="doroto-statistical-data-teammate-L">' . esc_html__('Teammate L', 'doubles-rotation-tournament') . '</th>';
		$output .= '<th id="doroto-statistical-data-teammate-R">' . esc_html__('Teammate R', 'doubles-rotation-tournament') . '</th>';
		$output .= '<th id="doroto-statistical-data-opponent">' . esc_html__('Opponent', 'doubles-rotation-tournament') . '</th>';
	} else {
		$output .= '<th>' . esc_html__('Opponent L', 'doubles-rotation-tournament') . '</th>';
		$output .= '<th>' . esc_html__('Opponent R', 'doubles-rotation-tournament') . '</th>';
	}

	$output .= '<th id="doroto-statistical-data-total">' . esc_html__('Total', 'doubles-rotation-tournament') . '</th>';
	$output .= '</tr>';

	$playmates_L_count = 0;
	$playmates_P_count = 0;
	$opponents_count = 0;

	foreach ($statistics as $player) {

		if ($player['player_id'] != $selected_player_id) {

			if ((in_array($player['player_id'], $special_group))) {
				$output .= '<tr class="doroto-special-group-text">';
			} else {
				$output .= '<tr>';
			}

			$output .= '<td>' . esc_html(doroto_find_player_name($player['player_id'], $whole_names)) . '</td>';

			foreach ($selectedPlayerData['playmates_L'] as $playmates_L) {
				if ($playmates_L['player_id'] == $player['player_id']) {
					$playmates_L_count = $playmates_L['count'];
				}
			}
			foreach ($selectedPlayerData['playmates_P'] as $playmates_P) {
				if ($playmates_P['player_id'] == $player['player_id']) {
					$playmates_P_count = $playmates_P['count'];
				}
			}
			foreach ($selectedPlayerData['opponents'] as $opponents) {
				if ($opponents['player_id'] == $player['player_id']) {
					$opponents_count = $opponents['count'];
				}
			}

			if (doroto_check_if_doubles($tournament)) {
				$count_together = $playmates_L_count + $playmates_P_count + $opponents_count;
			} else {
				$count_together = $playmates_L_count + $playmates_P_count;
			}

			if (doroto_check_if_doubles($tournament)) {
				$output .= '<td>' . esc_html($playmates_L_count) . '</td>';
				$output .= '<td>' . esc_html($playmates_P_count) . '</td>';
				$output .= '<td>' . esc_html($opponents_count) . '</td>';
			} else {
				$output .= '<td>' . esc_html($playmates_L_count) . '</td>';
				$output .= '<td>' . esc_html($playmates_P_count) . '</td>';
			}

			$output .= '<td>' . esc_html($count_together) . '</td>';

			$output .= '</tr>';
		}
	}

	$output .= '</tbody>';
	$output .= '</table></div>';
	return $output;
}

add_shortcode('doroto_display_player_statistics', 'doroto_display_player_statistics');


/**
 * change wrong written results of matches
 * @since 1.0.0
 * @version 1.3.6 (not only web admin can change game results)
 */
function doroto_change_game_shortcode()
{
	global $wpdb;

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$current_user = wp_get_current_user();

	$tournament_id = doroto_getTournamentId();

	if (!isset($tournament_id)) {
		return (esc_html__('The tournament was not found.', 'doubles-rotation-tournament'));
	}

	if ($tournament_id == 0) {
		$output = "<div id='doroto-change-match-result'>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div id='doroto-change-match-result'>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$whole_names = intval($tournament->whole_names);

	$admin_users = maybe_unserialize($tournament->admin_users);
	$output = "<div id='doroto-change-match-result'>" . esc_html__("You do not have permission to modify tournament results.", "doubles-rotation-tournament") . "</div>";

	if (!(doroto_is_admin($tournament_id) > 0)) {
		return $output;
	}

	if (!doroto_match_results_editable($tournament)) {
		$output = "<div id='doroto-change-match-result'>" . esc_html__("The option to modify the match results is closed.", "doubles-rotation-tournament") . "</div>";
		return $output;
	}

	if (empty($tournament->matches_list)) {
		return "";
	} else {
		$matches_list = maybe_unserialize($tournament->matches_list);
	}

	$output = "<div><b>" . esc_html__("Change of match result in tournament No.", "doubles-rotation-tournament") . " " . esc_html($tournament_id) . "</b>:</div>";
	$output .= "<div class='doroto-table-responsive' id='doroto-change-match-result'><table class='doroto-table'>";
	$output .= '<form id="doroto_change_game_result_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

	$output .= '<input type="hidden" name="action" value="doroto_change_game_result">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
	$nothing_to_change = true;

	if (is_array($matches_list)) {
		usort($matches_list, function ($a, $b) {
			$numA = $a['match_number'] ?? 0;
			$numB = $b['match_number'] ?? 0;
			return $numB - $numA;
		});
	}
	$output .= "<tr class='doroto-left-aligned'><td>";
	$output .= '<select name="match_to_change" class="doroto-select-width">';
	foreach ($matches_list as $game) {
		if ($tournament->close_tournament == '1' && $tournament->play_final_match == '1' && $tournament->final_result != '') {
			if ($nothing_to_change) {
				$match_number = 0;
				$final_four = maybe_unserialize($tournament->final_four);
				$players_output = doroto_find_player_name($final_four['l1'], $whole_names) . '+' . doroto_find_player_name($final_four['p1'], $whole_names) . " | " . doroto_find_player_name($final_four['l2'], $whole_names) . '+' . doroto_find_player_name($final_four['p2'], $whole_names);
				$output .= '<option value="' . esc_attr($match_number) . '">' . esc_html__("The final match", "doubles-rotation-tournament") . ': ' . esc_html($players_output) . '</option>';
			}
			$nothing_to_change = false;
		} elseif ($game['played'] == 1) {
			$players_output = doroto_find_player_name($game['player_1'], $whole_names) . '+' . doroto_find_player_name($game['player_2'], $whole_names);
			if (doroto_check_if_doubles($tournament)) {
				$players_output .= " | " . doroto_find_player_name($game['player_3'], $whole_names) . '+' . doroto_find_player_name($game['player_4'], $whole_names);
			}

			if ($game['hide'] == 0 && ($game['result_1'] != 0 || $game['result_2'] != 0)) {
				$output .= '<option value="' . esc_attr($game['match_number']) . '">' . esc_html__("ID", "doubles-rotation-tournament") . ' ' . esc_html($game['match_number']) . ': ' . esc_html($players_output) . '</option>';
				$nothing_to_change = false;
			} elseif ($game['hide'] == 1) {
				$output .= '<option value="' . esc_attr($game['match_number']) . '">' . "--" . esc_html__("ID", "doubles-rotation-tournament") . ' ' . esc_html($game['match_number']) . ': ' . esc_html($players_output) . '</option>';
				$nothing_to_change = false;
			}
		}
	}
	$output .= '</select>' . '</td><td class = "doroto-no-wrap">';

	$output .= '<select name="game_result_1">';
	for ($cnt = 0; $cnt < 100; $cnt++) {
		$output .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
	}
	$output .= '</select>';

	$output .= '&nbsp;' . ':' . '&nbsp;';
	$output .= '<select name="game_result_2">';
	for ($cnt = 0; $cnt < 100; $cnt++) {
		$output .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
	}
	$output .= '</select>' . '</td><td>';


	$output .= '<input type="submit" value="' . esc_html__("Edit result", "doubles-rotation-tournament") . '">';
	$output .= wp_nonce_field('doroto_change_game_form_nonce', '_wpnonce', true, false);
	$output .= '</form></td></tr></table></div>';

	if ($nothing_to_change) {
		$output = '<div>' . esc_html__("No matches have been played in the tournament yet, so there is nothing to edit.", "doubles-rotation-tournament") . '</div>';
	}

	return $output;
}

add_shortcode('doroto_change_game', 'doroto_change_game_shortcode');


/**
 * change wrong written results of matches after submitting form
 * match_to_change = 0 changes the result of the final match
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_change_result)
 */
function doroto_change_game_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_change_game_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$match_number = isset($_POST['match_to_change']) ? intval($_POST['match_to_change']) : -1;
	$result_1 = isset($_POST['game_result_1']) ? intval($_POST['game_result_1']) : -1;
	$result_2 = isset($_POST['game_result_2']) ? intval($_POST['game_result_2']) : -1;

	if ($match_number === 0 && $result_1 >= 0 && $result_2 >= 0) {
		$output = '';
		if (doroto_save_final_result($tournament_id, $result_1 . ':' . $result_2)) {
			$output = __('The result of the final match', 'doubles-rotation-tournament') . ' ' . __('was changed.', 'doubles-rotation-tournament');
		}
		doroto_tournament_progress($tournament_id);
	} else {
		$output = doroto_service_message(doroto_service_change_result($tournament_id, $match_number, $result_1, $result_2), $tournament_id);
	}
	doroto_info_messsages_save(sanitize_text_field($output));
	doroto_redirect_modify_url($tournament_id, "doroto-played-matches");
	exit;
}
add_action('admin_post_doroto_change_game_result', 'doroto_change_game_form_submit');
add_action('admin_post_nopriv_doroto_change_game_result', 'doroto_change_game_form_submit');


/**
 * needs to synchronize data with all users
 * @since 1.0.0
 */
function doroto_refresh_page_shortcode(array|string $atts)
{
	$atts = shortcode_atts(array(
		'seconds_to_refresh' => intval(doroto_read_settings('refresh_seconds', 120)),
	), $atts);
	$seconds = intval($atts['seconds_to_refresh']);

	//for presentation purpose
	global $wpdb;
	$current_user_id = intval(get_current_user_id());
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));
	if ($doroto_presentation && is_array($doroto_presentation)) {
		if ($doroto_presentation['allow_to_run'] == 1) {
			$seconds = intval(doroto_read_settings('show_next_seconds', 10));
		}
	}

	if ($seconds <= 0) {
		$seconds = 120;
	}

	ob_start();
?>
	<div id="doroto-refresh-container" data-seconds="<?php echo esc_attr($seconds); ?>"></div>
<?php
	return ob_get_clean();
}

add_shortcode('doroto_refresh_page', 'doroto_refresh_page_shortcode');


/**
 * display all registered players in the tournament
 * @since 1.0.0
 * @version 1.4.7 (usort security fix)
 */
function doroto_display_players_shortcode($atts = [])
{
	global $wpdb;
	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);
	$tournament_id = intval($doroto_atts['tournament_id']);
	if ($tournament_id <= 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament == null) {
		$output = esc_html__('The tournament was not found.', 'doubles-rotation-tournament');
		return $output;
	}

	$whole_names = intval($tournament->whole_names);
	$max_players = intval($tournament->max_players);
	$special_group_can_win = intval($tournament->special_group_can_win);
	$temp_suspend_winner = intval($tournament->temp_suspend_winner);
	$current_user_id = intval(get_current_user_id());
	$minimum_matches = intval($tournament->minimum_matches);
	$announce_round_end = intval($tournament->announce_round_end);
	$payment_done = [];

	//presentation doroto_display_players = 'doroto_display_players'
	$doroto_display_players = intval(doroto_read_settings('doroto_display_players', 1));
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));
	if ($doroto_presentation && is_array($doroto_presentation)) {
		if ($doroto_presentation['allow_to_run'] == 1 && ($doroto_presentation['slide'] != 'doroto_display_players' || !$doroto_display_players)) {
			return;
		}
	};

	$players = maybe_unserialize($tournament->players);
	$statistics = maybe_unserialize($tournament->statistics);

	if (is_array($statistics)) {
		usort($statistics, function ($a, $b) {
			$ratioA = $a['ratio'] ?? 0;
			$ratioB = $b['ratio'] ?? 0;
			return $ratioB <=> $ratioA;
		});
	}

	if (empty($players)) {
		return '<p>' . esc_html__("No one has registered for the tournament yet.", "doubles-rotation-tournament") . '</p>';
	}

	$player_results = $statistics;
	$open_registration = intval($tournament->open_registration);

	$tournament_name = sanitize_text_field($tournament->name);

	$special_group = maybe_unserialize($tournament->special_group);

	if (!is_array($special_group)) {
		$special_group = [];
	}

	$payment_display = intval($tournament->payment_display);

	$output = '<p><b>' . esc_html__("Players", "doubles-rotation-tournament") . '</b> (';

	if (!empty($special_group)) {
		$output .= esc_html(count($players) - count($special_group)) . ' + ' . esc_html(count($special_group)) . ' = ';
	}

	$output .= esc_html__("total", "doubles-rotation-tournament") . ' ' . esc_html(count($players));

	if ($max_players > 0 && $open_registration) {
		$output .= ' ' . esc_html__("out of maximum", "doubles-rotation-tournament") . ' ' . esc_html($max_players);
	}

	$output .= ') ' . esc_html__("tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . ' (<b><a href="#doroto-display-players" data-type="internal" data-id="#doroto-display-players">' . esc_html($tournament_name) . '</a></b>):';

	$output .= '</br><b>' . esc_html__("Registration", "doubles-rotation-tournament") . '</b> ' . esc_html__("to the tournament is", "doubles-rotation-tournament") . ' <b>';

	if ($open_registration) {
		$output .= esc_html__("open", "doubles-rotation-tournament");
	} else {
		$output .= esc_html__("closed", "doubles-rotation-tournament");
	}

	if (count($players) >= $max_players && $max_players > 0 && $open_registration) {
		$output .= esc_html__(", but the maximum number of players has already been reached", "doubles-rotation-tournament");
	}
	$output .= ".</b></p><div class='doroto-table-responsive'>";
	$output .= '<table class="doroto-table" id="doroto-table-player">';
	$output .= '<tr class="doroto-left-aligned">';

	$output .= '<th id="doroto-table-player-order">' . esc_html__("Order", "doubles-rotation-tournament") . '</th>';
	$output .= '<th id="doroto-table-player-state">' . esc_html__("State", "doubles-rotation-tournament") . '</th>';
	$output .= '<th id="doroto-table-player-name">' . esc_html__("Name", "doubles-rotation-tournament") . '</th>';

	if (!$open_registration) {
		$games_points = doroto_games_points($tournament);
		$output .= '<th id="doroto-table-player-match-count">' . esc_html__("Match count", "doubles-rotation-tournament") . '</th><th id="doroto-table-player-won">' . esc_html__("Won", "doubles-rotation-tournament") . ' ' . esc_html($games_points) . '</th><th id="doroto-table-player-lost">' . esc_html__("Lost", "doubles-rotation-tournament") . ' ' . esc_html($games_points) . '</th><th id="doroto-table-player-ratio">' . esc_html__("Ratio", "doubles-rotation-tournament") . '</th><th id="doroto-table-player-trend">' . esc_html__("Trend", "doubles-rotation-tournament") . '</th>';
	}

	if ($payment_display) {
		$output .= '<th id="doroto-table-player-payment">' . esc_html__("Payment", "doubles-rotation-tournament") . '</th>';
		$payment_done = maybe_unserialize($tournament->payment_done);
		if (!is_array($payment_done) || empty($payment_done)) {
			$payment_done = [];
		}
	}
	if ($announce_round_end != 0) {
		$output .= '<th id="doroto-table-player-residue">' . esc_html__("Residue", "doubles-rotation-tournament") . '</th>';
	}
	$output .= '</tr>';

	$ratio_current = 0;
	$ratio_max_special = 0;
	$ratio_max = 0;
	$ratio_order = 0;
	$ratio_last = 0;

	$trend = doroto_find_trend_for_players($tournament_id, $tournament);

	foreach ($player_results as $index => $player_result) {
		// Check if player ID is in special_group array and change row color accordingly
		if ((in_array($player_result['player_id'], $special_group)) && $special_group_can_win == 0) {
			if ($current_user_id == $player_result['player_id']) {
				$output .= '<tr class="doroto-special-group-text-underlined">';
			} else {
				$output .= '<tr class="doroto-special-group-text">';
			}
		} elseif ((in_array($player_result['player_id'], $special_group)) && $special_group_can_win == 2) {
			$ratio_current = $player_result['ratio'];
			if (
				$ratio_current >= $ratio_max_special && $tournament->close_tournament == 1 && !(!$temp_suspend_winner && $player_result['active'] == 0) &&
				$player_result['games'] >= $minimum_matches
			) {
				if ($current_user_id == $player_result['player_id']) {
					$output .= '<tr class="doroto-winner-background-special-underlined">';
				} else {
					$output .= '<tr class="doroto-winner-background-special">';
				}
				$ratio_max_special = $ratio_current;
			} else {
				if ((in_array($player_result['player_id'], $special_group))) {
					if ($current_user_id == $player_result['player_id']) {
						$output .= '<tr class="doroto-special-group-text-underlined">';
					} else {
						$output .= '<tr class="doroto-special-group-text">';
					}
				} else {
					if ($current_user_id == $player_result['player_id']) {
						$output .= '<tr class="doroto-text-underlined">';
					} else {
						$output .= '<tr">';
					}
				}
			}
		} else {
			$ratio_current = $player_result['ratio'];
			if (
				$ratio_current >= $ratio_max && $tournament->close_tournament == 1 && !(!$temp_suspend_winner && $player_result['active'] == 0) &&
				$player_result['games'] >= $minimum_matches
			) {
				if ($current_user_id == $player_result['player_id']) {
					$output .= '<tr class="doroto-winner-background-underlined">';
				} else {
					$output .= '<tr class="doroto-winner-background">';
				}
				$ratio_max = $ratio_current;
			} else {
				if ((in_array($player_result['player_id'], $special_group))) {
					if ($current_user_id == $player_result['player_id']) {
						$output .= '<tr class="doroto-special-group-text-underlined">';
					} else {
						$output .= '<tr class="doroto-special-group-text">';
					}
				} else {
					if ($current_user_id == $player_result['player_id']) {
						$output .= '<tr class="doroto-text-underlined">';
					} else {
						$output .= '<tr">';
					}
				}
			}
		}

		if ($player_result['ratio'] != $ratio_last) {
			$ratio_last = $player_result['ratio'];
			$ratio_order++;
		}
		if (!$open_registration) {
			$output .= '<td>' . esc_html($ratio_order) . '</td>';
		} else {
			$output .= '<td>' . esc_html($index + 1) . '</td>';
		}

		if ($player_result['active']) {
			$output .= '<td>' . esc_html('&#9745;') . '</td>'; //active player
		} else {
			$output .= '<td>' . esc_html('&#9744;') . '</td>'; //non active player
		}

		if (in_array($player_result['player_id'], $special_group)) {
			if ($current_user_id == $player_result['player_id']) {
				$output .= '<td class="doroto-special-group-text-underlined">';
			} else {
				$output .= '<td class="doroto-special-group-text">';
			}
		} else {
			if ($current_user_id == $player_result['player_id']) {
				$output .= '<td class="doroto-text-underlined">';
			} else {
				$output .= '<td>';
			}
		}

		$output .= esc_html(doroto_find_player_name($player_result['player_id'], $whole_names)) . '</td>';

		if (!$open_registration) {
			$output .= '<td>' . esc_html($player_result['games']);
			if ($minimum_matches > $player_result['games']) {
				$output .= '<b> !</b></td>';
			} else {
				$output .= '</td>';
			}

			$output .= '<td>' . esc_html($player_result['won']) . '</td>';
			$output .= '<td>' . esc_html($player_result['lost']) . '</td>';
			$output .= '<td>' . esc_html(round($player_result['ratio'], 2)) . '</td>';

			if (isset($trend[$player_result['player_id']])) {
				$trend_value = $trend[$player_result['player_id']]['trend'];
			} else {
				$trend_value = 0;
			}

			$output .= '<td>';

			if ($trend_value > 0) {
				$output .= '<span class="dashicons dashicons-arrow-up-alt"></span>';
			} elseif ($trend_value < 0) {
				$output .= '<span class="dashicons dashicons-arrow-down-alt"></span>';
			}

			if ($trend_value != 0) {
				$output .= esc_html('&nbsp;' . abs($trend_value));
			}
			$output .= '</td>';
		}

		if ($payment_display) {
			if (in_array($player_result['player_id'], $payment_done)) {
				$output .= '<td>' . '&check;' . '</td>'; //player with a payment
			} else {
				$output .= '<td>' . '' . '</td>'; //player with no payment	
			}
		}
		if ($announce_round_end != 0) {
			$output .= '<td>' . esc_html($player_result['rest']) . '</td>';
		}

		$output .= '</tr>';
	}
	$output .= '</table></div>';
	return $output;
}

add_shortcode('doroto_display_players', 'doroto_display_players_shortcode');


/**
 * filter to display data for a selected player
 * @since 1.0.0
 */
function doroto_player_filter_dropdown_shortcode(array|string $atts)
{
	global $wpdb;

	$a = shortcode_atts(array(
		'selected_player' => 0,
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$tournament_id = intval($a['tournament_id']) ?? intval(doroto_getTournamentId());

	if (!isset($tournament_id)) {
		return esc_html__('Invalid tournament ID.', 'doubles-rotation-tournament');
	}

	if ($tournament_id <= 0) {
		$output = '<div id="doroto-filter-by-player">' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$current_user_id = intval(get_current_user_id());
	if ($current_user_id == 0) {
		$output = '<div id="doroto-filter-by-player">' . esc_html__('Filtering by player is only available for logged in users.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	if (isset($_POST['doroto_submit'])) {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_filter_by_player_nonce')) {
			wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
		}

		$selected_player = intval($_POST['doroto_selected_player']);
		if ($selected_player >= 0) {
			update_user_meta($current_user_id, 'doroto_filter_results', $selected_player);
		}
	} else {
		$selected_player = intval(get_user_meta($current_user_id, 'doroto_filter_results', true));
	}

	$players = doroto_get_players_from_tournaments($tournament_id);
	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament == null) {
		$output = esc_html__('The tournament was not found.', 'doubles-rotation-tournament');
		return $output;
	}

	$open_registration = intval($tournament->open_registration);
	$whole_names = intval($tournament->whole_names);

	if ($open_registration) {
		return null;
	}

	$unique_players = array();
	foreach ($players as $player_id) {
		$unique_players[$player_id] = doroto_find_player_name($player_id, $whole_names);
	}

	$unique_players[0] = esc_html__('No Filter', 'doubles-rotation-tournament');

	$output = '<div id="doroto-filter-by-player"><p><form method="post" action="">';
	$output .= '<select name="doroto_selected_player">';

	foreach ($unique_players as $player_id => $display_name) {
		$selected = ($player_id == $selected_player) ? 'selected' : '';
		$output .= "<option value='" . esc_attr($player_id) . "' $selected>" . esc_html($display_name) . "</option>";
	}

	$output .= '</select> ';

	$output .= '<input type="submit" name="doroto_submit" value="' . esc_attr__('Filter by player', 'doubles-rotation-tournament') . '">';
	$output .= '<input type="hidden" name="player_id" value="' . esc_attr($player_id) . '">';
	$output .= wp_nonce_field('doroto_filter_by_player_nonce', '_wpnonce', true, false);
	$output .= '</form></p></div>';

	return $output;
}

add_shortcode('doroto_player_filter', 'doroto_player_filter_dropdown_shortcode');


/**
 * add player to the tournament
 * @since 1.0.0
 * @version 1.5.8 
 */
function doroto_add_player_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;

	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);
	if (doroto_check_if_presentation_on())
		return '';

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();
	$tournament_id = doroto_getTournamentId();
	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('Invalid tournament ID.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$admin_users = maybe_unserialize($tournament->admin_users);
	$output = "<div>" . esc_html__('You do not have permission to add a player from the database.', 'doubles-rotation-tournament') . "</div>";
	if ($only_web_admin == 3) {
		if (!(doroto_is_admin($tournament_id) == 2)) {
			return $output;
		}
	} else {
		if (!(doroto_is_admin($tournament_id) > 0)) {
			return $output;
		}
	}

	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__('The option to add a player is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$players = maybe_unserialize($tournament->players);
	$whole_names = intval($tournament->whole_names);

	/*
         only_admin_players or only_web_admin options:
     3: from the current tournament
     2: for whom they have already organized a tournament in the past
            1: that they have met at a tournament where was also another organizer
     0: all players w/o limitations
        */

	if ($only_web_admin == 2 || $only_web_admin == 1) {
		$participants = doroto_current_user_in_tournaments($only_web_admin);
		$new_players = array_diff($participants, $players);
	}

	if ($only_web_admin == 3 || $only_web_admin == 0 || doroto_is_admin($tournament_id) == 2) {
		if (!empty($players)) {
			$placeholders = implode(',', array_fill(0, count($players), '%d'));
			// prepare() and get_results() in one step, with the arguments passed as an array
			$users = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID NOT IN ($placeholders)", $players)
			);
		} else {
			$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");
		}
	} else {
		if (!empty($new_players)) {
			$placeholders = implode(',', array_fill(0, count($new_players), '%d'));
			$users = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID IN ($placeholders)", $new_players)
			);
		} else {
			$users = null;
		}
	}

	$doroto_users = $wpdb->get_results(
		"SELECT * FROM {$wpdb->users} WHERE user_email LIKE '%@DoRoTo-example.com'"
	);

	if (!is_array($users)) {
		$users = [];
	}
	$additional_users = array_merge($doroto_users ?? [], $created_users ?? []);

	if (!empty($additional_users)) {
		$existing_ids = wp_list_pluck($users, 'ID');

		foreach ($additional_users as $user_to_add) {
			if (!in_array($user_to_add->ID, $existing_ids) && !in_array($user_to_add->ID, $players)) {
				$users[] = $user_to_add;
				$existing_ids[] = $user_to_add->ID;
			}
		}
	}

	if ($users == null) {
		$output = "<div>" . esc_html__('Unable to add another player from the database.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (is_array($users)) {
		usort($users, function ($a, $b) {
			$nameA = $a->display_name ?? '';
			$nameB = $b->display_name ?? '';
			return strcasecmp($nameA, $nameB);
		});
	}

	$doroto_settings = get_option('doroto_settings');
	$player_name_length = intval($doroto_settings['player_name_length']);

	$output = "<div><b>" . esc_html__('Add a new player to the tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="add_player_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_add_player_to_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
	$output .= '<select name="player_to_add">';
	foreach ($users as $user) {
		if ($whole_names == 0) {
			$display_name = doroto_display_short_name($user->display_name);
		} else {
			$display_name = $user->display_name;
		}
		if (mb_strlen($display_name) > $player_name_length) {
			$display_name = mb_substr($display_name, 0, $player_name_length) . '...';
		}
		$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html($display_name) . '</option>';
	}
	$output .= '</select> ';
	$output .= '<input type="submit" value="' . esc_html__("Add player", "doubles-rotation-tournament") . '">';
	$output .= wp_nonce_field('doroto_add_player_form_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	return $output;
}
add_shortcode('doroto_add_player', 'doroto_add_player_shortcode');


/**
 * add player to the tournament after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_add_player_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_add_player_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['player_to_add']) ? intval($_POST['player_to_add']) : -1;

	$result = doroto_service_add_player($tournament_id, $player_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_add_player_to_tournament', 'doroto_add_player_form_submit');
add_action('admin_post_nopriv_doroto_add_player_to_tournament', 'doroto_add_player_form_submit');



/**
 * removing a player from special group
 * @since 1.0.0
 */
function doroto_remove_special_group_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();

	$tournament_id = doroto_getTournamentId();

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('Missing tournament ID', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}
	$whole_names = intval($tournament->whole_names);

	$output = "<div>" . esc_html__('You do not have permission to remove from a special group.', 'doubles-rotation-tournament') . "</div>";
	if (doroto_is_admin($tournament_id) < 1) {
		return $output;
	}

	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__('The option to remove from a special group is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$special_group = maybe_unserialize($tournament->special_group);
	$players = maybe_unserialize($tournament->players);

	if (is_array($players)) {
		if (!is_array($special_group)) {
			$special_group = [];
		}

		if (empty($special_group)) {
			$output = "<div>" . esc_html__('The special group is empty, so there is no one to subscribe to.', 'doubles-rotation-tournament') . "</div>";
			return $output;
		}
	} else {
		$output = "<div>" . esc_html__('No one has signed up for the tournament yet, so there is no one to remove from the special group.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}
	$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");

	$output = "<div><b>" . esc_html__('Removal from the special group of the tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="remove_special_group_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_remove_special_group_to_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	if (count($special_group) > 0) {
		$output .= '<select name="player_to_add">';
		foreach ($special_group as $player_id) {
			$user = get_userdata($player_id);
			if ($user) {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_find_player_name($user->ID, $whole_names)) . '</option>';
			}
		}
		$output .= '</select> ';
		$output .= '<input type="submit" value="' . esc_html__("Remove from special group", "doubles-rotation-tournament") . '">';
	} else {
		$output .= esc_html__('No one has signed up for the tournament yet, so there is no one to remove from the special group.', 'doubles-rotation-tournament');
	}
	$output .= wp_nonce_field('doroto_remove_special_group_form_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	return $output;
}

add_shortcode('doroto_remove_special_group', 'doroto_remove_special_group_shortcode');


/**
 * removing a player from special group after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_remove_special_group_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_remove_special_group_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['player_to_add']) ? intval($_POST['player_to_add']) : -1;

	$result = doroto_service_set_special_group($tournament_id, $player_id, false);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_remove_special_group_to_tournament', 'doroto_remove_special_group_form_submit');
add_action('admin_post_nopriv_doroto_remove_special_group_to_tournament', 'doroto_remove_special_group_form_submit');


/**
 * add a player to special group
 * @since 1.0.0
 * @version 1.3.3(Fix - Rights to add a special group)
 */
function doroto_add_special_group_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;

	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();

	$tournament_id = doroto_getTournamentId();

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('Missing tournament ID', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$whole_names = intval($tournament->whole_names);

	if (doroto_is_admin($tournament_id) < 1) {
		$output = "<div>" . esc_html__('You do not have permission to add to a special group.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__('The option to add to a special group is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$special_group = maybe_unserialize($tournament->special_group);
	$players = maybe_unserialize($tournament->players);

	if (is_array($players)) {
		if (!is_array($special_group)) {
			$special_group = [];
		}

		if (!empty($special_group)) {
			$players = array_diff($players, $special_group);
		}
	} else {
		$output = "<div>" . esc_html__('No one has signed up for the tournament yet, so there is no one to include in a special group.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$output = "<div><b>" . esc_html__('Addition to the special group of the tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="add_special_group_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_add_special_group_to_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	if (count($players) > 0) {
		$output .= '<select name="player_to_add">';
		foreach ($players as $player_id) {
			$user = get_userdata($player_id);
			if ($user) {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_find_player_name($user->ID, $whole_names)) . '</option>';
			}
		}
		$output .= '</select> ';
		$output .= '<input type="submit" value="' . esc_html__("Add to special group", "doubles-rotation-tournament") . '">';
	} else {
		$output .= "<div>" . esc_html__('No one has signed up for the tournament yet, so there is no one to include in a special group.', 'doubles-rotation-tournament') . "</div>";
	}
	$output .= wp_nonce_field('doroto_add_special_group_form_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';
	return $output;
}

add_shortcode('doroto_add_special_group', 'doroto_add_special_group_shortcode');


/**
 * add a player to special group after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_add_special_group_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_add_special_group_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['player_to_add']) ? intval($_POST['player_to_add']) : -1;

	$result = doroto_service_set_special_group($tournament_id, $player_id, true);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_add_special_group_to_tournament', 'doroto_add_special_group_form_submit');
add_action('admin_post_nopriv_doroto_add_special_group_to_tournament', 'doroto_add_special_group_form_submit');


/**
 * add a player to admin group
 * @since 1.0.0
 * @version 1.3.7 ($web admin can insert all players)
 */
function doroto_add_admin_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$output = '';
	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();

	$tournament_id = doroto_getTournamentId();

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('Missing tournament ID', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$admin_users = maybe_unserialize($tournament->admin_users);

	$tournament_name = sanitize_text_field($tournament->name);
	if (!is_array($admin_users)) {
		$admin_users = [];
	}

	$whole_names = intval($tournament->whole_names);
	$output = '<p>' . esc_html__('The organizer of tournament no.', 'doubles-rotation-tournament') . " " . esc_html($tournament_id) . " (<b>" . esc_html($tournament_name) . "</b>) " . esc_html__('is', 'doubles-rotation-tournament') . " <span class='doroto-info-text'>";
	$admin_names = [];
	foreach ($admin_users as $admin_id) {
		$admin_names[] = doroto_find_player_name($admin_id, $whole_names);
	}
	$output .= esc_html(implode(' ' . __("&", "doubles-rotation-tournament") . ' ', $admin_names));
	$output .= "</span>.</p>";

	if (!(doroto_is_admin($tournament_id) > 0)) {
		$output = "<div id='doroto-settings-organizer-rights'>" . esc_html__('You do not have permission to add organizer rights.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament->close_tournament == '1') {
		$output .= '<div id="doroto-settings-organizer-rights">' . esc_html__('The option to add organizer rights is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$players = maybe_unserialize($tournament->players);

	/*
		 only_admin_players or only_web_admin options:
	 3: from the current tournament
	 2: for whom they have already organized a tournament in the past
			1: that they have met at a tournament where was also another organizer
	 0: all players w/o limitations
		*/

	if (!empty($admin_users) && (!empty($players) || (empty($players) && $only_web_admin < 3))) {

		if (($only_web_admin == 2 || $only_web_admin == 1) && doroto_is_admin($tournament_id) < 2) {
			$user_ids = doroto_current_user_in_tournaments($only_web_admin);
			$players = array_diff($user_ids, $admin_users);
		} elseif (($only_web_admin > 0 && doroto_is_admin($tournament_id) == 2) || ($only_web_admin == 0 && doroto_is_admin($tournament_id) > 0)) {
			$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");
			if (is_array($users)) {
				usort($users, function ($a, $b) {
					$nameA = $a->display_name ?? '';
					$nameB = $b->display_name ?? '';
					return strcasecmp($nameA, $nameB);
				});
			}
			$user_ids = array_map(function ($user) {
				return $user->ID;
			}, $users);
			$players = array_diff($user_ids, $admin_users);
		} else {
			$players = array_diff($players, $admin_users);
		}
	} else {
		$output .= "<div id='doroto-settings-organizer-rights'>" . esc_html__('No one is currently registered for the tournament, so there is no one to grant organizer rights to.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}
	$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");

	$output .= "<div id='doroto-settings-organizer-rights'><b>" . esc_html__('Adding organizer rights in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:</div>";
	$output .= '<form id="add_admin_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_add_admin_to_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	if (count($players) > 0) {
		$output .= '<select name="player_to_add">';
		foreach ($players as $player_id) {
			$user = get_userdata($player_id);
			if ($user && !in_array($player_id, $admin_users)) {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_find_player_name($user->ID, $whole_names)) . '</option>';
			}
		}
		$output .= '</select> ';
		$output .= '<input type="submit" value="' . esc_html__("Add organizer rights", "doubles-rotation-tournament") . '">';
	} else {
		$output .= esc_html__('No one is currently registered for the tournament, so there is no one to grant organizer rights to.', "doubles-rotation-tournament");
	}
	$output .= wp_nonce_field('doroto_add_admin_form_nonce', '_wpnonce', true, false);
	$output .= '</form>';

	return $output;
}
add_shortcode('doroto_add_admin', 'doroto_add_admin_shortcode');
add_action('admin_post_doroto_add_admin_to_tournament', 'doroto_add_admin_form_submit');
add_action('admin_post_nopriv_doroto_add_admin_to_tournament', 'doroto_add_admin_form_submit');


/**
 * add a player to admin group after submitting form
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_add_admin)
 */
function doroto_add_admin_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_add_admin_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['player_to_add']) ? intval($_POST['player_to_add']) : -1;

	$result = doroto_service_add_admin($tournament_id, $player_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "doroto-tournament-editing");
	exit;
}


/**
 * temporary disable a player
 * @since 1.0.0
 * @version 1.3.3(Fix - Rights to Suspension)
 */
function doroto_temporary_disable_player_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$a = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);
	$tournament_id = intval($a['tournament_id']);
	if (doroto_check_if_presentation_on()) {
		return '';
	}

	if (!is_user_logged_in()) {
		$output = "<div>" . esc_html__('You must log in to temporarily suspend a player!', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id <= 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__('The option to suspend the player game is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (empty($statistics)) {
		return null;
	}
	$activePlayers = doroto_find_active_players($statistics);

	$user_id = intval(get_current_user_id());
	if (doroto_is_admin($tournament_id) < 1 && !in_array($user_id, unserialize($tournament->players))) {
		return null;
	}

	$output = "<div><b>" . esc_html__('Suspension of the game of the selected player in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="doroto_temporary_disable_player" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_disable_player_in_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	$output .= '<select name="disable_player">';
	$allow_to_display = false;
	foreach ($statistics as $player) {
		if (!in_array($player['player_id'], $activePlayers)) {
			continue;
		}
		$display_name = '';
		if (doroto_is_admin($tournament_id) > 0) {
			$display_name = doroto_find_player_name($player['player_id'], intval($tournament->whole_names));
		} elseif ($player['player_id'] == $user_id) {
			$display_name = doroto_find_player_name($player['player_id'], intval($tournament->whole_names));
		}

		if ($display_name != '') {
			$allow_to_display = true;
			$output .= '<option value="' . esc_attr($player['player_id']) . '">' . esc_html($display_name) . '</option>';
		}
	}
	if (doroto_is_admin($tournament_id) > 0) {
		$output .= '<option value=" 0 ">' . esc_html__('All players', 'doubles-rotation-tournament') . '</option>';
	}
	$output .= '</select> ';

	$output .= '<input type="submit" name="disable_temporary" value="' . esc_html__("Suspend participation", "doubles-rotation-tournament") . '">';
	$output .= wp_nonce_field('doroto_temporary_disable_player_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	if ($allow_to_display) {
		return $output;
	} else {
		return null;
	}
}

add_shortcode('doroto_temporary_disable_player', 'doroto_temporary_disable_player_shortcode');


/**
 * temporary disable a player (0 = all) after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_temporary_disable_player_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_temporary_disable_player_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['disable_player']) ? intval($_POST['disable_player']) : -1;

	$result = doroto_service_set_player_active($tournament_id, $player_id, false);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_disable_player_in_tournament', 'doroto_temporary_disable_player_form_submit');
add_action('admin_post_nopriv_doroto_disable_player_in_tournament', 'doroto_temporary_disable_player_form_submit');


/**
 * temporary enable a player
 * @since 1.0.0
 * @version 1.3.3(Fix - Rights to Resumption)
 */
function doroto_temporary_enable_player_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$a = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}
	$tournament_id = intval($a['tournament_id']);

	if (!is_user_logged_in()) {
		$output = "<div>" . esc_html__('You must log in to restore the game!', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__('The option to restore the player game is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (empty($statistics)) {
		return null;
	}
	$activePlayers = doroto_find_active_players($statistics);

	$user_id = intval(get_current_user_id());
	if (doroto_is_admin($tournament_id) < 1 && !in_array($user_id, unserialize($tournament->players))) {
		return null;
	}

	$output = "<div><b>" . esc_html__('Resuming the game of the selected player in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="doroto_temporary_enable_player" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_enable_player_in_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	$output .= '<select name="enable_player">';
	$allow_to_display = false;
	foreach ($statistics as $player) {
		if (in_array($player['player_id'], $activePlayers)) {
			continue;
		}
		$display_name = '';
		if (doroto_is_admin($tournament_id) > 0) {
			$display_name = doroto_find_player_name($player['player_id'], intval($tournament->whole_names));
		} elseif ($player['player_id'] == $user_id) {
			$display_name = doroto_find_player_name($player['player_id'], intval($tournament->whole_names));
		}

		if ($display_name != '') {
			$allow_to_display = true;
			$output .= '<option value="' . esc_attr($player['player_id']) . '">' . esc_html($display_name) . '</option>';
		}
	}
	if (doroto_is_admin($tournament_id) > 0) {
		$output .= '<option value=" 0 ">' . esc_html__('All players', 'doubles-rotation-tournament') . '</option>';
	}
	$output .= '</select> ';

	$output .= '<input type="submit" name="enable_temporary" value="' . esc_html__("Resume participation", "doubles-rotation-tournament") . '">';
	$output .= wp_nonce_field('doroto_temporary_enable_player_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	if ($allow_to_display) {
		return $output;
	} else {
		return esc_html__('All players are active.', 'doubles-rotation-tournament');
	}
}

add_shortcode('doroto_temporary_enable_player', 'doroto_temporary_enable_player_shortcode');


/**
 * temporary enable a player (0 = all) after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_temporary_enable_player_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_temporary_enable_player_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['enable_player']) ? intval($_POST['enable_player']) : -1;

	$result = doroto_service_set_player_active($tournament_id, $player_id, true);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_enable_player_in_tournament', 'doroto_temporary_enable_player_form_submit');
add_action('admin_post_nopriv_doroto_enable_player_in_tournament', 'doroto_temporary_enable_player_form_submit');


/**
 * remove a player from the tournament
 * @since 1.0.0
 * @version 1.3.0
 */
function doroto_remove_player_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);
	if (doroto_check_if_presentation_on())
		return '';

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();

	$tournament_id = doroto_getTournamentId();

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$whole_names = intval($tournament->whole_names);

	$admin_users = maybe_unserialize($tournament->admin_users);
	$output = "<div>" . esc_html__("You do not have permission to remove a player.", "doubles-rotation-tournament") . "</div>";
	if (doroto_is_admin($tournament_id) < 1) {
		return $output;
	}


	if ($tournament->close_tournament == '1') {
		$output = "<div>" . esc_html__("The option to remove a player is closed.", "doubles-rotation-tournament") . "</div>";
		return $output;
	}

	$players_raw = maybe_unserialize($tournament->players);
	$playing = maybe_unserialize($tournament->playing);
	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($playing)) {
		$playing = [];
	}
	$players = [];

	foreach ($statistics as $player_info) {
		if (in_array($player_info['player_id'], $players_raw) && $player_info['games'] == 0 && !in_array($player_info['player_id'], $playing)) {
			$players[] = $player_info['player_id'];
		}
	}

	if (!empty($players)) {
		$placeholders = implode(',', array_fill(0, count($players), '%d'));
		$users = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID IN ($placeholders)", $players)
		);

		if (is_array($users)) {
			usort($users, function ($a, $b) {
				$nameA = $a->display_name ?? '';
				$nameB = $b->display_name ?? '';
				return strcasecmp($nameA, $nameB);
			});
		}

		$output = "<div><b>" . esc_html__("Removing a player from tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . "</b>:";
		$output .= '<form id="remove_player_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		$output .= '<input type="hidden" name="action" value="doroto_remove_player_from_tournament">';
		$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
		$output .= '<select name="player_to_remove">';
		foreach ($users as $user) {
			if ($whole_names == 0) {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_display_short_name($user->display_name)) . '</option>';
			} else {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
			}
		}
		$output .= '</select> ';
		$output .= '<input type="submit" value="' . esc_html__("Remove player", "doubles-rotation-tournament") . '">';
		$output .= wp_nonce_field('doroto_remove_player_form_nonce', '_wpnonce', true, false);
		$output .= '</form></div>';
	} else {
		$output = '<div>' . esc_html__("No player can be removed now.", "doubles-rotation-tournament") . '</div>';
	}
	return $output;
}
add_shortcode('doroto_remove_player', 'doroto_remove_player_shortcode');


/**
 * remove a player from the tournament after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_remove_player_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_remove_player_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['player_to_remove']) ? intval($_POST['player_to_remove']) : -1;

	$result = doroto_service_remove_player($tournament_id, $player_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('admin_post_doroto_remove_player_from_tournament', 'doroto_remove_player_form_submit');
add_action('admin_post_nopriv_doroto_remove_player_from_tournament', 'doroto_remove_player_form_submit');


/**
 * remove a player from the tournament function (web: message and redirect)
 * @since 1.1.8
 * @version 2.0.0 (doroto_service_remove_player)
 */
function doroto_remove_player_from_tournament(int $tournament_id, int $player_id)
{
	$result = doroto_service_remove_player($tournament_id, $player_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}


/**
 * Whether the results of finished matches may still be changed: not while the
 * registration is open, not after a tournament without a final was closed and,
 * with a final, only after the final result and at most 24 hours after closing.
 * @since 1.6.2
 */
function doroto_match_results_editable(stdClass $tournament)
{
	if ($tournament->close_date === '9999-09-09 09:09:09') {
		$timestamp = PHP_INT_MAX;
	} else {
		$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', (string) $tournament->close_date);
		$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
	}

	return !(
		$tournament->open_registration == '1' || ($tournament->close_tournament == '1' && $tournament->play_final_match == '0') ||
		($tournament->close_tournament == '1' && $tournament->play_final_match == '1' && ($tournament->final_result == '' || $timestamp + 24 * 3600 < time()))
	);
}


/**
 * display matches of the tournament
 * @since 1.0.0
 * @version 1.6.2 (organizers get an "Edit result" link in every row)
 */
function doroto_display_games_func($atts = [])
{
	global $wpdb;
	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_played' => '1',
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	$current_user_id = intval(get_current_user_id());

	if (!doroto_check_need_to_display('doroto_display_games')) {
		return '';
	}

	$only_played = intval($doroto_atts['only_played']);
	$tournament_id = intval($doroto_atts['tournament_id']);
	if ($tournament_id == 0) {
		$tournament_id = doroto_getTournamentId();
	}
	if ($tournament_id == 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$whole_names = intval($tournament->whole_names);

	if ($tournament && property_exists($tournament, 'matches_list')) {
		$games = maybe_unserialize($tournament->matches_list);

		if (is_array($games)) {
			$tournament_name = sanitize_text_field($tournament->name);

			$matches_played_count = 0;
			$game_played_count = 0;
			foreach ($games as $match) {
				if ($match['played'] == 1 && $match['hide'] == 0 && ($match['result_1'] != 0 || $match['result_2'] != 0)) {
					$matches_played_count++;
					$game_played_count += $match['result_1'] + $match['result_2'];
				}
			}
			if ($matches_played_count > 0) {

				if (is_array($games)) {
					usort($games, function ($a, $b) {
						$numA = $a['match_number'] ?? 0;
						$numB = $b['match_number'] ?? 0;
						return $numB <=> $numA;
					});
				}

				$games_points = doroto_games_points($tournament);
				$output = '<p><div><b>' . esc_html__("Match results", "doubles-rotation-tournament") . '</b> ' . esc_html__("tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . ' (<b> <a href="#doroto-display-games" data-type="internal" data-id="#doroto-display-games">' . esc_html($tournament_name) . '</a></b>).</div>';
				$output .= '<div><b>' . esc_html__("Meantime", "doubles-rotation-tournament") . '</b>' . ' ' . esc_html__("were played", "doubles-rotation-tournament") . ' <b>' . esc_html($matches_played_count) . ' ' . esc_html__("matches and", "doubles-rotation-tournament") . ' ' . esc_html($game_played_count) . ' ' . esc_html($games_points) . '</b>.</div><b><div>';

				if ($tournament->close_tournament == '1') {
					$output .= esc_html__("Tournament is closed.", "doubles-rotation-tournament");
				} else {
					$output .= esc_html__("The tournament is still being played.", "doubles-rotation-tournament");
				}

				$output .= "</b></div></p><div class='doroto-table-responsive' id='doroto-played-matches-table'>";

				// Organizers get an "Edit result" button in every row. The change form
				// ([doroto_change_game]) was easy to miss above the table; the link
				// selects the match in it (doroto-frontend-scripts.js).
				$edit_links = doroto_is_admin($tournament_id) > 0
					&& doroto_match_results_editable($tournament)
					&& !doroto_check_if_presentation_on();

				$output .= "<table class='doroto-table'>";
				$output .= "<tr class='doroto-left-aligned'><th>" . esc_html__("ID", "doubles-rotation-tournament") . "</th><th>" . esc_html__("L1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R1", "doubles-rotation-tournament") . "</th>";
				if (doroto_check_if_doubles($tournament)) {
					$output .= "<th>" . esc_html__("L2", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R2", "doubles-rotation-tournament") . "</th><th>" . esc_html__("L1+R1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("L2+R2", "doubles-rotation-tournament") . "</th>";
				} else {
					$output .= "<th>" . esc_html__("Result", "doubles-rotation-tournament") . "</th>";
				}
				$output .= "</tr>";

				$special_group = maybe_unserialize($tournament->special_group);
				if (!is_array($special_group)) {
					$special_group = [];
				}

				$doroto_filter_results = get_user_meta($current_user_id, 'doroto_filter_results', true);
				if (empty($doroto_filter_results)) {
					$doroto_filter_results = 0;
				}
				$players = maybe_unserialize($tournament->players);
				if (!in_array($doroto_filter_results, $players)) {
					$doroto_filter_results = 0;
					update_user_meta($current_user_id, 'doroto_filter_results', 0);
				}

				foreach ($games as $game) {
					if ($game['hide'] == 1) {
						continue;
					}
					if ($only_played == 1 && $game['played'] == 0) {
						continue;
					}
					if ($game['result_1'] == 0 && $game['result_2'] == 0) {
						continue;
					}
					if ($game['player_1'] != $doroto_filter_results && $game['player_2'] != $doroto_filter_results && $game['player_3'] != $doroto_filter_results && $game['player_4'] != $doroto_filter_results && $doroto_filter_results != 0) {
						continue;
					}
					$output .= "<tr>";
					$output .= "<td class='doroto-no-wrap'>" . esc_html($game['match_number']);
					// In the first column: a last column was off screen in the wide table.
					if ($edit_links) {
						$output .= " <a href='#doroto-change-match-result' class='doroto-edit-result'"
							. " data-match='" . esc_attr(intval($game['match_number'])) . "'"
							. " data-result-1='" . esc_attr(intval($game['result_1'])) . "'"
							. " data-result-2='" . esc_attr(intval($game['result_2'])) . "'"
							. " title='" . esc_attr__("Edit result", "doubles-rotation-tournament") . "'"
							. " aria-label='" . esc_attr__("Edit result", "doubles-rotation-tournament") . "'>&#9998;</a>";
					}
					$output .= "</td>";

					doroto_output_player_data($output, $game, 'player_1', $special_group, $whole_names, $doroto_filter_results); //save variables (sanitized and escaped)
					doroto_output_player_data($output, $game, 'player_2', $special_group, $whole_names, $doroto_filter_results);
					if (doroto_check_if_doubles($tournament)) {
						doroto_output_player_data($output, $game, 'player_3', $special_group, $whole_names, $doroto_filter_results);
						doroto_output_player_data($output, $game, 'player_4', $special_group, $whole_names, $doroto_filter_results);
					}

					if (doroto_check_if_doubles($tournament)) {
						$output .= "<td>" . esc_html($game['result_1']) . "</td>";
						$output .= "<td>" . esc_html($game['result_2']) . "</td>";
					} else {
						$output .= "<td>" . esc_html($game['result_1']) . ' : ' . esc_html($game['result_2']) . "</td>";
					}
					$output .= "</tr>";
				}
				$output .= "</table>";
				$output .= "</div>";
			} else {
				$output = '';
			}
		} else {
			$output = "<div>" . esc_html__("Player registration is still in progress and the matches have not yet been drawn.", "doubles-rotation-tournament") . "</div>";
		}
	} else {
		$output = "<div>" . esc_html__("Tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . ' ' . esc_html__("was not found.", "doubles-rotation-tournament") . "</div>";
	}
	return $output;
}
add_shortcode('doroto_display_games', 'doroto_display_games_func');


/**
 * display matches ready to play of the tournament 
 * @since 1.0.0
 * @version 1.4.7 (new selectors)
 */
function doroto_games_to_play_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	global $doroto_output_form;

	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	$current_user_id = intval(get_current_user_id());

	if (!doroto_check_need_to_display('doroto_games_to_play')) {
		return null;
	}

	$tournament_id = intval($doroto_atts['tournament_id']);
	if ($tournament_id == 0) {
		$tournament_id = doroto_getTournamentId();
	}
	if ($tournament_id == 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$output = doroto_offer_games($tournament_id, 0);
	if ($output != '') {
		$no_new_match = true;
	} else {
		$no_new_match = false;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = '<div>' . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	$close_tournament = intval($tournament->close_tournament);
	if ($close_tournament == 1) {
		$tournament_name = sanitize_text_field($tournament->name);
		$output .= '<div>' . esc_html__('Tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . ' (<b>' . esc_html($tournament_name) . '</b>) ' . esc_html__('is closed.', 'doubles-rotation-tournament') . '</div>';
	}

	$current_user = wp_get_current_user();
	$whole_names = intval($tournament->whole_names);
	$open_registration = intval($tournament->open_registration);
	$players = maybe_unserialize($tournament->players);
	$final_four = maybe_unserialize($tournament->final_four);
	$final_result = maybe_unserialize($tournament->final_result);
	$play_final_match = intval($tournament->play_final_match);
	$allowed_html = doroto_allowed_html();

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group)) {
		$special_group = [];
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		$statistics = [];
	}

	if (is_array($statistics)) {
		usort($statistics, function ($a, $b) {
			$ratioA = $a['ratio'] ?? 0;
			$ratioB = $b['ratio'] ?? 0;
			return $ratioB <=> $ratioA;
		});
	}
	$player_results = $statistics;

	if ($close_tournament == 1) {
		$output .= '<p><div class="doroto-grey-background">';
	}
	if ($play_final_match && $close_tournament == 1 && empty($final_four)) {
		if ($open_registration) {
			$doroto_output_form = esc_html__('You cannot go to the finals while registration is open.', 'doubles-rotation-tournament');
		} else {
			$output .= doroto_select_final_doubles($player_results, $tournament, $current_user);
		}
	}

	$image_html = '<span class="dashicons dashicons-awards"></span>';

	if ($play_final_match && $close_tournament == 1 && !empty($final_four) && !empty($final_result)) {
		$output .= '<p id="doroto-winner-ideal-couple"><b>' . $image_html . ' ' . esc_html__('Winner in the category of the most ideal couple:', 'doubles-rotation-tournament') . ' ';

		if ($final_result['result_1'] > $final_result['result_2']) {
			$output .= wp_kses(doroto_class_name($final_four['l1'], $special_group, $current_user_id), $allowed_html) . esc_html(doroto_find_player_name($final_four['l1'], $whole_names)) . '</span></b> ' . esc_html__('&', 'doubles-rotation-tournament') . ' <b>' . wp_kses(doroto_class_name($final_four['p1'], $special_group, $current_user_id), $allowed_html) . esc_html(doroto_find_player_name($final_four['p1'], $whole_names)) . "</b></span>!</p>";
		} else {
			$output .= wp_kses(doroto_class_name($final_four['l2'], $special_group, $current_user_id), $allowed_html) . esc_html(doroto_find_player_name($final_four['l2'], $whole_names)) . '</span></b> ' . esc_html__('&', 'doubles-rotation-tournament') . ' <b>' . wp_kses(doroto_class_name($final_four['p2'], $special_group, $current_user_id), $allowed_html) . esc_html(doroto_find_player_name($final_four['p2'], $whole_names)) . "</b></span>!</p>";
		}
	}

	if ($play_final_match && $close_tournament == 1 && !empty($final_four)) {
		if (empty($final_result)) {
			$output .= '<p>' . esc_html__('The final match is being played.', 'doubles-rotation-tournament') . '</br>';
			if (doroto_is_admin($tournament_id) > 0) {
				$output .= esc_html__('If you want, you can adjust the Maximum number of games and Settings of the possible results of the match for this match.', 'doubles-rotation-tournament') . '</p>';
			}
		}
		$output .= doroto_result_final_doubles($player_results, $tournament, $current_user);
	}

	if ($close_tournament == 1) {
		$winners = doroto_get_winner($tournament_id, $whole_names);
		if (empty($winners)) {
			$winner_name = sanitize_text_field(__('Not enough matches have been played to declare a winner', 'doubles-rotation-tournament'));
		} else {
			$winner_name = '';
			for ($cnt = 0; $cnt < count($winners); $cnt++) {
				$winner_name .= doroto_class_name($winners[$cnt], $special_group, $current_user_id);
				$winner_name .= '<b>' . doroto_find_player_name($winners[$cnt], $whole_names) . "</b></span>";
				if ($cnt + 1 < count($winners)) {
					$winner_name .= ' ' . __("&", "doubles-rotation-tournament") . ' ';
				}
			}
		}
		$output .= '<span id="doroto-winner-best-player"><b>' . $image_html . ' ' . esc_html__('Best Player Winner:', 'doubles-rotation-tournament') . '</b> ' . wp_kses($winner_name, $allowed_html) . "!</span>";
		$output .= '</p></div>';
		return $output;
	}

	if (empty($tournament->matches_list) || $tournament->open_registration == 1) {
		return "";
	} else {
		$matches = maybe_unserialize($tournament->matches_list);
	}

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group)) {
		$special_group = [];
	}
	$allow_input_results = intval($tournament->allow_input_results);

	$tournament_name = sanitize_text_field($tournament->name);
	$whole_names = intval($tournament->whole_names);

	$output_temp = '<div><b>' . esc_html__('Currently, the following matches are being played in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . ' (<a href="#doroto-games-to-play" data-type="internal" data-id="#doroto-games-to-play">' . esc_html($tournament_name) . '</a>):</b></div>';

	$output_temp .= "<div class='doroto-table-responsive'>";
	$output_temp .= '<table class="doroto-table">';
	$output_temp .= "<tr class='doroto-left-aligned'><th id='doroto-games-to-play-id'>" . esc_html__("ID", "doubles-rotation-tournament") . "</th><th id='doroto-games-to-play-l1'>" . esc_html__("L1", "doubles-rotation-tournament") . "</th><th id='doroto-games-to-play-p1'>" . esc_html__("R1", "doubles-rotation-tournament") . "<sup>*</sup></th>";

	if (doroto_check_if_doubles($tournament)) {
		$output_temp .= "<th id='doroto-games-to-play-l2'>" . esc_html__("L2", "doubles-rotation-tournament") . "</th><th id='doroto-games-to-play-p2'>" . esc_html__("R2", "doubles-rotation-tournament") . "</th>";
	}

	$output_temp .= "<th id='doroto-games-to-play-hide'>" . esc_html__("Hide", "doubles-rotation-tournament") . "</th><th id='doroto-games-to-play-result'>" . esc_html__("Result", "doubles-rotation-tournament") . "</th>";
	$output_temp .= '<th id="doroto-games-to-play-save">' . esc_html__("Save", "doubles-rotation-tournament") . '</th>';
	$output_temp .= "</tr>";

	$no_old_match = true;
	foreach ($matches as $match) {
		if ($match['played'] == 1 && $match['hide'] == 0 && $match['result_1'] == 0 && $match['result_2'] == 0) {
			$no_old_match = false;
			$output_temp .= '<tr>';
			$output_temp .= "<td>" . esc_html($match['match_number']) . "</td>";

			doroto_output_player_data($output_temp, $match, 'player_1', $special_group, $whole_names, $current_user_id); //save and sanitized variables			
			doroto_output_player_data($output_temp, $match, 'player_2', $special_group, $whole_names, $current_user_id);

			if (doroto_check_if_doubles($tournament)) {
				doroto_output_player_data($output_temp, $match, 'player_3', $special_group, $whole_names, $current_user_id);
				doroto_output_player_data($output_temp, $match, 'player_4', $special_group, $whole_names, $current_user_id);
			}

			$players = [$match['player_1'], $match['player_2'], $match['player_3'], $match['player_4']];

			if ((in_array($current_user_id, $players) && $allow_input_results && $current_user_id > 0) || (doroto_is_admin($tournament_id) > 0)) {
				$output_temp .= '<td>';
				$output_temp .= '<form id="doroto_submit_match_result_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				$output_temp .= '<input type="hidden" name="hide_' . esc_attr($match['match_number']) . '" value="0">';
				$output_temp .= '<input type="hidden" name="action" value="doroto_submit_match_result">';
				$output_temp .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
				$checked = $match['hide'] == 1 ? 'checked' : '';
				$output_temp .= '<input type="checkbox" name="hide_' . esc_attr($match['match_number']) . '" value="1" ' . esc_attr($checked) . '>';
				$output_temp .= '</td>';

				$output_temp .= '<td class = "doroto-no-wrap">';
				$output_temp .= '<input type="hidden" name="match_number" value="' . esc_attr($match['match_number']) . '">';

				$output_temp .= '<select name="result_1" style="width: 9ch;">';
				for ($cnt = 0; $cnt < 100; $cnt++) {
					$output_temp .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
				}
				$output_temp .= '</select>';

				$output_temp .= ' : ';
				$output_temp .= '<select name="result_2" style="width: 9ch;">';
				for ($cnt = 0; $cnt < 100; $cnt++) {
					$output_temp .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
				}
				$output_temp .= '</select>';


				$output_temp .= '</td><td>';
				$output_temp .= '<input type="submit" value="' . esc_html(__('Save', 'doubles-rotation-tournament')) . '">';
				$output_temp .= wp_nonce_field('doroto_submit_match_result_nonce', '_wpnonce', true, false);
				$output_temp .= '</form>';
				$output_temp .= '</td>';
			} else {
				$output_temp .= '<td colspan = "3"></td>';
			}
			$output_temp .= '</tr>';
		}
	}

	$output_temp .= '</table>';
	$output_temp .= '<div><sup>*</sup><small>' . esc_html__("R1", "doubles-rotation-tournament") . ' ' . esc_html__("player starts the game by serving.", "doubles-rotation-tournament") . '</small></div>';
	$output_temp .= '</div></p>';

	if (!($no_new_match && $no_old_match)) {
		$output .= $output_temp;
	}
	return $output;
}

add_shortcode('doroto_games_to_play', 'doroto_games_to_play_shortcode');

add_action('admin_post_doroto_submit_match_result', 'doroto_update_match_result');
add_action('admin_post_nopriv_doroto_submit_match_result', 'doroto_update_match_result');

/**
 * edit tournament variables
 * @since 1.0.0
 * @version 1.5.8 
 */
function doroto_add_tournament_parameters()
{
	global $wpdb;
	$current_user = wp_get_current_user();
	$tournament_id = doroto_getTournamentId();

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__("No tournament has been created yet.", "doubles-rotation-tournament") . '</div>';
		return $output;
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = $tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	ob_start();
	$output = '<b>' . esc_html__("Edit tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . ':</b>';
	$output .= '<p><form method="post" action="' . esc_url(admin_url('admin-post.php?action=doroto_tournament_parameters_save')) . '">';

	$output .= '<input type="hidden" name="action" value="doroto_tournament_parameters">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	//start - Name and type, number of courts section 
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu" id="doroto_name_type_courts_main">' . '&rarr; ' . __('Name and type, number of courts', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container" id="doroto_name_type_courts">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("The name of the tournament can be edited here.", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$name_table = $wpdb->get_var(
		$wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<textarea id="doroto_tournament_parameters_name" name="doroto_tournament_parameters_name" style="width: 100%;height:100px;">';
	$output .= esc_attr($name_table);
	$output .= '</textarea>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Number of courts available for the tournament", "doubles-rotation-tournament") . '.' . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$courts_available = $wpdb->get_var(
		$wpdb->prepare("SELECT courts_available FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_courts_number" name="doroto_tournament_parameters_courts_number">';
	$minValue = 1;
	$maxValue = 10;
	for ($i = $minValue; $i <= $maxValue; $i++) {
		$selected = ($i == $courts_available) ? 'selected' : '';
		$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html($i) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("You can change the tournament type here, if the tournament hasn't started yet.", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$tournament_type = $wpdb->get_var(
		$wpdb->prepare("SELECT tournament_type FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_tournament_type" name="doroto_tournament_parameters_tournament_type">';

	$type_variables = doroto_types_variables();
	$tournament_types = doroto_tournament_types();
	foreach ($type_variables as $value => $name) {
		if (doroto_read_settings($name, 1)) {
			$selected = ($value == $tournament_type) ? ' selected="selected"' : '';
			$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($tournament_types[$value]) . "</option>";
		}
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Name and type, number of courts section

	//start - Match result options section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu" id="doroto_settings_match_result_main">' . '&rarr; ' . __('Match result options', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container" id="doroto_settings_match_result">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Average match result.", "doubles-rotation-tournament") . '<sup>(*)</sup>' . ':';
	$output .= '<p>' . esc_html__("If you play tennis and your matches end with an average score of 6:4, then enter a value of 10.", "doubles-rotation-tournament") . '</p>';
	$output .= '</td>';
	$output .= '<td>';
	$average_result_table = $wpdb->get_var(
		$wpdb->prepare("SELECT average_result FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_average_result" name="doroto_tournament_parameters_average_result" style="width: 100%;">';
	$minValue = 1;
	$maxValue = 100;
	for ($i = $minValue; $i <= $maxValue; $i++) {
		$selected = ($i == $average_result_table) ? 'selected' : '';
		$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html($i) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width" id="doroto-settings-round-end">';
	$output .= esc_html__("After the end of the entire tournament round, show the offer, what to do next?", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '<ol><li>' . esc_html__("No, do not announce the end of the round.", "doubles-rotation-tournament") . '</li>';
	$output .= '<li>' . esc_html__("Yes, but don't consider service rotation.", "doubles-rotation-tournament") . '</li>';
	$output .= '<li>' . esc_html__("Yes, and ensure service rotation.", "doubles-rotation-tournament") . '</li></ol>';
	$output .= '</td>';
	$output .= '<td>';
	$announce_round_end_table = $wpdb->get_var(
		$wpdb->prepare("SELECT announce_round_end FROM $table_name WHERE id = %d", $tournament_id)
	);
	if ($announce_round_end_table > 2) {
		$announce_round_end_table -= 2;
	}
	$output .= '<select id="doroto_tournament_parameters_announce_round_end" name="doroto_tournament_parameters_announce_round_end">';
	$options = array(
		0 => '1. ' . esc_html__("No", "doubles-rotation-tournament"),
		1 => '2. ' . esc_html__("Yes, w/o service", "doubles-rotation-tournament"),
		2 => '3. ' . esc_html__("Yes, with service", "doubles-rotation-tournament")
	);
	foreach ($options as $value => $name) {
		$selected = ($value == $announce_round_end_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("How many games (points) are played per hour on one court (table)?", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$games_hour = $wpdb->get_var(
		$wpdb->prepare("SELECT games_hour FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_games_hour" name="doroto_tournament_parameters_games_hour">';
	$minValue = 1;
	$maxValue = 999;
	for ($i = $minValue; $i <= $maxValue; $i++) {
		$selected = ($i == $games_hour) ? 'selected' : '';
		$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html($i) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Match result options section

	//start - Player rights section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu">' . '&rarr; ' . __('Player rights', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("This option determines whether player names will be displayed in full or if they will be obfuscated to protect personal data.", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$whole_names_table = $wpdb->get_var(
		$wpdb->prepare("SELECT whole_names FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_whole_names" name="doroto_tournament_parameters_whole_names" style="width: 100%;">';
	$options = array(0 => esc_html__("Abbreviated names", "doubles-rotation-tournament"), 1 => esc_html__("Full names", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $whole_names_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Maximum number of registered players.", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$max_players_table = $wpdb->get_var(
		$wpdb->prepare("SELECT max_players FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_max_players" name="doroto_tournament_parameters_max_players">';
	for ($i = 0; $i <= 99; $i++) {
		$selected = ($i == $max_players_table) ? ' selected="selected"' : '';
		if ($i == 0) {
			$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html__("No limit", "doubles-rotation-tournament") . "</option>";
		} elseif ($i >= 4) {
			$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html($i) . "</option>";
		}
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Can players independently enter game results?", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$allow_input_results_table = $wpdb->get_var(
		$wpdb->prepare("SELECT allow_input_results FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_allow_input_results" name="doroto_tournament_parameters_allow_input_results">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $allow_input_results_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Can other players see your tournament?", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$visibility_table = $wpdb->get_var(
		$wpdb->prepare("SELECT visibility FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_visibility" name="doroto_tournament_parameters_visibility">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $visibility_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Player rights section

	//start - Special group of players settings section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu" id="doroto_settings_special_group_main">' . '&rarr; ' . __('Special group of players', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container" id="doroto_settings_special_group">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Skip matches where 2 players would play together in a special group?", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$two_special_group_table = $wpdb->get_var(
		$wpdb->prepare("SELECT two_special_group FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_two_special_group" name="doroto_tournament_parameters_two_special_group">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $two_special_group_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Skip matches where 2 non-special group players would play together?", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$two_out_group_table = $wpdb->get_var(
		$wpdb->prepare("SELECT two_out_group FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_two_out_group" name="doroto_tournament_parameters_two_out_group">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $two_out_group_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Special group of players settings section

	//start - Setting up the winner selection
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu" id="doroto-settings-winner-main">' . '&rarr; ' . __('Winner', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container" id="doroto-settings-winner">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("The winner of the tournament will be:", "doubles-rotation-tournament") . '<ol>';
	$output .= '<li>' . esc_html__("1 outside the special group", "doubles-rotation-tournament") . '</li>';
	$output .= '<li>' . esc_html__("1 of all players", "doubles-rotation-tournament") . '</li>';
	$output .= '<li>' . esc_html__("2 separately (1 from the special group and 1 outside the special group)", "doubles-rotation-tournament") . '</li></ol>';
	$output .= '</td>';
	$output .= '<td>';
	$special_group_can_win_table = $wpdb->get_var(
		$wpdb->prepare("SELECT special_group_can_win FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_special_group_can_win" name="doroto_tournament_parameters_special_group_can_win" style="width: 100%;">';
	$options = array(
		0 => '1. ' . esc_html__("1 outside", "doubles-rotation-tournament"),
		1 => '2. ' . esc_html__("1 of all", "doubles-rotation-tournament"),
		2 => '3. ' . esc_html__("2 separately", "doubles-rotation-tournament")
	);
	foreach ($options as $value => $name) {
		$selected = ($value == $special_group_can_win_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width" id="doroto-settings-winner-minimum">';
	$output .= esc_html__("The minimum number of matches a player must play to be declared the winner.", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$minimum_matches = $wpdb->get_var(
		$wpdb->prepare("SELECT minimum_matches FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_minimum_matches" name="doroto_tournament_parameters_minimum_matches">';
	$minValue = 1;
	$maxValue = 10;
	for ($i = $minValue; $i <= $maxValue; $i++) {
		$selected = ($i == $minimum_matches) ? 'selected' : '';
		$output .= "<option value=\"" . esc_attr($i) . "\" $selected>" . esc_html($i) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width" id="doroto-settings-winner-suspended">';
	$output .= esc_html__("Can a player who is currently suspended be declared the winner?", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$temp_suspend_winner_table = $wpdb->get_var(
		$wpdb->prepare("SELECT temp_suspend_winner FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_temp_suspend_winner" name="doroto_tournament_parameters_temp_suspend_winner">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $temp_suspend_winner_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Setting up the winner selection

	//start - Setting up location selection
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu" id="doroto-settings-location-main">' . '&rarr; ' . __('Location', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container" id="doroto-settings-location">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';

	$tournament_coords = $wpdb->get_row(
		$wpdb->prepare("SELECT latitude, longitude FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id),
		ARRAY_A
	);
	$latitude = isset($tournament_coords['latitude']) ? floatval($tournament_coords['latitude']) : 50.0;
	$longitude = isset($tournament_coords['longitude']) ? floatval($tournament_coords['longitude']) : 15.0;

	$output .= '<div class="doroto-coordinates-copy">';
	$output .= '<label for="doroto-coordinates-input">' . esc_html__('Coordinates for sharing:', 'doubles-rotation-tournament') . '</label><br>';
	$output .= '<input type="text" id="doroto-coordinates-input" value="' . esc_attr($latitude) . ', ' . esc_attr($longitude) . '" readonly style="width: 250px; margin-right: 10px;">';
	$output .= '<button type="button" onclick="dorotoCopyCoordinates()">' . esc_html__('Copy', 'doubles-rotation-tournament') . '</button>';
	$output .= '</div>';


	$output .= '<tr>';
	$output .= '<td colspan="2">';
	$output .= '<p>' . esc_html__("Set tournament location on the map.", "doubles-rotation-tournament") . '</p>';
	$output .= '<div id="doroto-club-map" style="height: 300px; width: 100%; margin-bottom: 1em;"></div>';
	//$output .= '<input type="hidden" id="doroto_latitude" name="doroto_settings[latitude]" value="' . esc_attr($latitude) . '">';
	//$output .= '<input type="hidden" id="doroto_longitude" name="doroto_settings[longitude]" value="' . esc_attr($longitude) . '">';
	$output .= '<input type="hidden" id="doroto_latitude" name="doroto_tournament_parameters_latitude" value="' . esc_attr($latitude) . '">';
	$output .= '<input type="hidden" id="doroto_longitude" name="doroto_tournament_parameters_longitude" value="' . esc_attr($longitude) . '">';

	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Setting up location selection

	//start - other section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu">' . '&rarr; ' . __('Other settings', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("When enough players are available:", "doubles-rotation-tournament") . '<sup>(*)</sup>' . '<ol>';
	$output .= '<li>' . esc_html__("Wait to draw until all matches have been played.", "doubles-rotation-tournament") . '</li>';
	$output .= '<li>' . esc_html__("Draw a next match immediately.", "doubles-rotation-tournament");
	$output .= ' ' . esc_html__("This option reduces variability.", "doubles-rotation-tournament") . '</li></ol>';
	$output .= '</td>';
	$output .= '<td>';
	$min_not_playing_table = $wpdb->get_var(
		$wpdb->prepare("SELECT min_not_playing FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_min_not_playing" name="doroto_tournament_parameters_min_not_playing">';
	$options = array(0 => '1. ' . esc_html__("Wait", "doubles-rotation-tournament"), 1 => '2. ' . esc_html__("Draw", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $min_not_playing_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("After the tournament closes, allow a final match to determine the best pair?", "doubles-rotation-tournament") . '<sup>(*)</sup>';
	$output .= '</td>';
	$output .= '<td>';
	$play_final_match_table = $wpdb->get_var(
		$wpdb->prepare("SELECT play_final_match FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_play_final_match" name="doroto_tournament_parameters_play_final_match">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $play_final_match_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Show control over the paid entry fee?", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$payment_display_table = $wpdb->get_var(
		$wpdb->prepare("SELECT payment_display FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<select id="doroto_tournament_parameters_payment_display" name="doroto_tournament_parameters_payment_display">';
	$options = array(0 => esc_html__("No", "doubles-rotation-tournament"), 1 => esc_html__("Yes", "doubles-rotation-tournament"));
	foreach ($options as $value => $name) {
		$selected = ($value == $payment_display_table) ? ' selected="selected"' : '';
		$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($name) . "</option>";
	}
	$output .= '</select>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - other settings section

	$output .= '<hr>';

	//start - Post about the tournament section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu">' . '&rarr; ' . __('Post about the tournament', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("When you check the box, a new post dedicated only to this tournament will be created.", "doubles-rotation-tournament");
	$only_admin_posts = intval(doroto_read_settings('only_admin_posts', 1));
	if ($only_admin_posts)
		$output .= ' ' . esc_html__("(Available only for website administrators)", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$output .= '<input type="checkbox" id="doroto_tournament_parameters_new_post" name="doroto_tournament_parameters_new_post">';
	$output .= '</td>';
	$output .= '</tr>';
	$output .= '<tr>';
	$output .= '<td class="doroto-table-width">';
	$output .= esc_html__("Welcome text for the tournament. It will be inserted into a new post. HTML tags can be used.", "doubles-rotation-tournament");
	if ($only_admin_posts)
		$output .= ' ' . esc_html__("(Available only for website administrators)", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$invitation_table = $wpdb->get_var(
		$wpdb->prepare("SELECT invitation FROM $table_name WHERE id = %d", $tournament_id)
	);
	$output .= '<textarea id="doroto_tournament_parameters_invitation" name="doroto_tournament_parameters_invitation" style="width: 100%;height:100px;">';
	$output .= esc_attr($invitation_table);
	$output .= '</textarea>';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Post about the tournament section

	//start - Test or Delete section
	$output .= '<div class="doroto-content-main">';
	$output .= '<h5 class="doroto-clickable-submenu">' . '&rarr; ' . __('Test or Delete the tournament', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<div class="doroto-content-container">';

	$output .= '<div class="doroto-table-responsive">';
	$output .= '<table class="doroto-table">';
	$output .= '<tr class="doroto-left-aligned">';
	$output .= '<th class="doroto-table-width">';
	$output .= esc_html__("Variable", "doubles-rotation-tournament");
	$output .= '</th><th>';
	$output .= esc_html__("Value", "doubles-rotation-tournament");
	$output .= '</th></tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width-red">';
	$output .= esc_html__("Checking the box will delete all match results and put the tournament into open registration. Thanks to this option, you can test the course of the tournament with the option of returning to the default state. (Irreversible change!)", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$output .= '<input type="checkbox" id="doroto_tournament_parameters_empty_tournament" name="doroto_tournament_parameters_empty_tournament">';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '<tr>';
	$output .= '<td class="doroto-table-width-red">';
	$output .= esc_html__("Checking the box will delete the tournament record after saving. (Irreversible change!)", "doubles-rotation-tournament");
	$output .= '</td>';
	$output .= '<td>';
	$output .= '<input type="checkbox" id="doroto_tournament_parameters_delete_tournament" name="doroto_tournament_parameters_delete_tournament">';
	$output .= '</td>';
	$output .= '</tr>';

	$output .= '</table>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';
	//end - Test or Delete section

	$output .= '<p><sup id="doroto-variables-progress">(*)</sup><small> - ' . esc_html__("Variables used for estimating the end of the tournament.", "doubles-rotation-tournament") . '</small></p>';

	if (!(doroto_is_admin($tournament_id) > 0)) {
		$output .= "<div id='doroto-settings-save-button'>" . esc_html__("You are not authorized to change the parameters of tournament no.", "doubles-rotation-tournament") . ' ' . esc_html($tournament_id) . "." . "</div>";
	} else {
		if ($tournament->close_date === '9999-09-09 09:09:09') {
			$timestamp = PHP_INT_MAX;
		} else {
			$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', $tournament->close_date);
			$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
		}
		if (!($tournament->close_tournament == 1 && $timestamp + 24 * 3600 < time())) {
			$output .= '<input type="submit" id="doroto-settings-save-button" name="doroto_tournament_parameters_save" value="' . esc_html__("Save", "doubles-rotation-tournament") . '">';
		} else {
			$output .= "<div id='doroto-settings-save-button'>" . esc_html__("Tournament parameters cannot be changed more than 24 hours after closing.", "doubles-rotation-tournament") . "</div>";
		}
	}
	$output .= wp_nonce_field('doroto_tournament_parameters_form_nonce', '_wpnonce', true, false);
	$output .= '</form></p>';

	return $output;
}


/**
 * edit tournament variables after submitting form
 * @since 1.0.0
 * @version 1.4.7 (last update info,empty_tournament with correct value)
 */
function doroto_tournament_parameters_results()
{
	global $wpdb;
	global $doroto_output_form;
	$output = '';

	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_tournament_parameters_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	if (!is_user_logged_in()) {
		$output = sanitize_text_field(__("You must log in to change tournament parameters!", "doubles-rotation-tournament"));
		doroto_info_messsages_save($output);
		$tournament_id = doroto_getTournamentId();
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}
	if (isset($_POST['doroto_tournament_parameters_save'])) {
		$tournament_id = intval($_POST['tournament_id']);
		$tournament = doroto_prepare_tournament($tournament_id);

		if (isset($_POST['doroto_tournament_parameters_delete_tournament'])) {
			if ($tournament) {
				$page_id = intval($tournament->page_id);
				if ($page_id != null) {
					wp_delete_post($page_id, true);
				}
			}

			$wpdb->delete(
				$wpdb->prefix . 'doroto_tournaments',
				array('id' => $tournament_id)
			);
		} else {
			$allowed_html = doroto_allowed_html();

			$name = sanitize_text_field($_POST['doroto_tournament_parameters_name']);
			$name = substr($name, 0, 100);
			$invitation = wp_kses($_POST['doroto_tournament_parameters_invitation'], $allowed_html);
			$invitation = substr($invitation, 0, 5000);
			$average_result = intval($_POST['doroto_tournament_parameters_average_result']);
			$max_players = intval($_POST['doroto_tournament_parameters_max_players']);
			$whole_names = intval($_POST['doroto_tournament_parameters_whole_names']);
			$minimum_matches = intval($_POST['doroto_tournament_parameters_minimum_matches']);
			$temp_suspend_winner = intval($_POST['doroto_tournament_parameters_temp_suspend_winner']);
			$special_group_can_win = intval($_POST['doroto_tournament_parameters_special_group_can_win']);
			$two_special_group = intval($_POST['doroto_tournament_parameters_two_special_group']);
			$two_out_group = intval($_POST['doroto_tournament_parameters_two_out_group']);
			$allow_input_results = intval($_POST['doroto_tournament_parameters_allow_input_results']);
			$play_final_match = intval($_POST['doroto_tournament_parameters_play_final_match']);
			$courts_available = intval($_POST['doroto_tournament_parameters_courts_number']);
			$min_not_playing = intval($_POST['doroto_tournament_parameters_min_not_playing']);
			$table_name = $wpdb->prefix . 'doroto_tournaments';
			$payment_display = intval($_POST['doroto_tournament_parameters_payment_display']);
			$announce_round_end = intval($_POST['doroto_tournament_parameters_announce_round_end']);
			$games_hour = intval($_POST['doroto_tournament_parameters_games_hour']);
			$visibility = intval($_POST['doroto_tournament_parameters_visibility']);
			$latitude = floatval($_POST['doroto_tournament_parameters_latitude']);
			$longitude = floatval($_POST['doroto_tournament_parameters_longitude']);

			$games = maybe_unserialize($tournament->matches_list);
			$games = is_array($games) ? $games : $games = [];

			if (count($games) > 1) {
				$tournament_type = intval($tournament->tournament_type);
			} else {
				$options = doroto_tournament_types();
				$tournament_type = intval($tournament->tournament_type);
				$tournament_short_name_before = sanitize_text_field($options[$tournament_type]);
				$tournament_type = intval($_POST['doroto_tournament_parameters_tournament_type']);
				$tournament_short_name_after = sanitize_text_field($options[$tournament_type]);

				$invitation = str_replace($tournament_short_name_before, $tournament_short_name_after, $invitation);
				$name = str_replace($tournament_short_name_before, $tournament_short_name_after, $name);
			}


			$fields = array(
				'average_result' => $average_result,
				'max_players' => $max_players,
				'whole_names' => $whole_names,
				'minimum_matches' => $minimum_matches,
				'temp_suspend_winner' => $temp_suspend_winner,
				'special_group_can_win' => $special_group_can_win,
				'two_special_group' => $two_special_group,
				'two_out_group' => $two_out_group,
				'allow_input_results' => $allow_input_results,
				'play_final_match' => $play_final_match,
				'courts_available' => $courts_available,
				'min_not_playing' => $min_not_playing,
				'invitation' => $invitation,
				'payment_display' => $payment_display,
				'tournament_type' => $tournament_type,
				'announce_round_end' => $announce_round_end,
				'games_hour' => $games_hour,
				'visibility' => $visibility,
				'longitude' => $longitude,
				'latitude' => $latitude,
			);

			if (isset($_POST['doroto_tournament_parameters_empty_tournament'])) {
				$fields['statistics'] = '';
				$fields['matches_list'] = '';
				$fields['open_registration'] = 1;
				$fields['close_tournament'] = 0;
				$fields['final_result'] = '';
				$fields['final_four'] = '';
				$fields['playing'] = '';
				$fields['close_date'] = '9999-09-09 09:09:09';
			}

			if ($name != '') {
				$fields['name'] = $name;
			}
			$wpdb->update(
				$table_name,
				$fields,
				array('id' => $tournament_id)
			);

			$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id));

			$statistics = doroto_create_statistics_table($tournament, unserialize($tournament->players), intval($tournament->whole_names));
			$wpdb->update("{$wpdb->prefix}doroto_tournaments", [
				'statistics' => serialize($statistics),
				'last_update'  => round(microtime(true) * 1000)
			], ['id' => $tournament_id]);

			if (isset($_POST['doroto_tournament_parameters_new_post'])) {
				$current_user = wp_get_current_user();
				$only_admin_posts = intval(doroto_read_settings('only_admin_posts', 1));
				if ((($only_admin_posts == 1) && (doroto_is_admin($tournament_id) == 2)) || (($only_admin_posts == 0) && (doroto_is_admin($tournament_id) > 0))) {
					doroto_create_new_tournament_post($tournament_id);
				} else {
					$output .= sanitize_text_field(__('You do not have the necessary rights to create a post.', 'doubles-rotation-tournament')) . '<br>';
				}
			}
		}
		$output .= sanitize_text_field(__('Tournament parameters no.', 'doubles-rotation-tournament') . ' ' . $tournament_id . ' ' . __('were saved.', 'doubles-rotation-tournament'));
		doroto_tournament_progress($tournament_id);
		doroto_info_messsages_save($output);
		doroto_redirect_modify_url($tournament_id, "tournament-editing");
		exit;
	}
}

add_shortcode('doroto_tournament_parameters', 'doroto_add_tournament_parameters');
add_action('admin_post_doroto_tournament_parameters', 'doroto_tournament_parameters_results');
add_action('admin_post_nopriv_doroto_tournament_parameters', 'doroto_tournament_parameters_results');
add_action('admin_post_doroto_tournament_parameters_save', 'doroto_tournament_parameters_results');
add_action('admin_post_nopriv_doroto_tournament_parameters_save', 'doroto_tournament_parameters_results');


/**
 * display tournament status in a form with hyperlink
 * @since 1.0.0
 */
function doroto_add_link_to_tournament($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$a = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);
	$tournament_id = intval($a['tournament_id']);

	$current_user_id = intval(get_current_user_id());
	if (!doroto_check_need_to_display('doroto_tournament_log_link')) {
		return '';
	}

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$open_registration = intval($tournament->open_registration);
	$whole_names = intval($tournament->whole_names);
	$output = '';
	if (!$open_registration && !doroto_check_if_presentation_on()) {
		$output .= '<div class="doroto-content-main">';
		$output .= '<h4 class="doroto-clickable-title">' . esc_html__('Details about tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . ' ...</h4>';
		$output .= '<div class="doroto-content-container" id="details">';
	}
	$output .= doroto_not_logged_message();

	$invitation = wp_kses($tournament->invitation, doroto_allowed_html());
	if ($invitation != '') {
		$output .= '<p>' . $invitation . '</p>';
	}

	if ($open_registration) {
		$site_url = sanitize_text_field(wp_unslash(get_site_url()));
		$join_url = doroto_join_url(intval($tournament_id));
		$output .= "<p id='doroto-link-login-logout'>" . esc_html__("You can enter the tournament using this link:", "doubles-rotation-tournament") . " <a href=\"" . esc_url($join_url) . "\" data-type=\"URL\" data-id=\"" . esc_url($join_url) . "\">" . esc_html__("Sign up for the tournament", "doubles-rotation-tournament") . "</a>.";
		$current_players = maybe_unserialize($tournament->players);
		if (is_user_logged_in() && is_array($current_players) && in_array(get_current_user_id(), array_map('intval', $current_players), true)) {
			$output .= " <a href=\"" . esc_url(doroto_leave_url(intval($tournament_id))) . "\">" . esc_html__("Leave the tournament", "doubles-rotation-tournament") . "</a>.";
		}
		$output .= "</p>";
	}

	$admin_users = maybe_unserialize($tournament->admin_users);
	$tournament_name = sanitize_text_field($tournament->name);
	$output .= "<p id='doroto-tournament-name-and-organizer'>" . esc_html__("The organizer of tournament no.", "doubles-rotation-tournament") . " " . esc_html($tournament_id) . " (<b>" . esc_html($tournament_name) . "</b>) " . esc_html__("is", "doubles-rotation-tournament") . " <span class='doroto-info-text' id='doroto_invitation_organizer'>";
	$admin_names = [];
	foreach ($admin_users as $admin_id) {
		$admin_names[] = doroto_find_player_name($admin_id, $whole_names);
	}
	$output .= esc_html(implode(' ' . __("&", "doubles-rotation-tournament") . ' ', $admin_names));
	$output .= "</span>.</p>";

	$admin_users = maybe_unserialize($tournament->admin_users);

	if (doroto_is_admin($tournament_id) > 0) {
		$option = [];
		if ($tournament->close_tournament != '1') {
			$open_registration_text = $tournament->open_registration == '1' ? esc_html__("close registration", "doubles-rotation-tournament") : esc_html__("reopen registration", "doubles-rotation-tournament");
			$option[] = "<a href='" . esc_url(doroto_action_url('doroto_toggle_registration', intval($tournament->id))) . "'>" . esc_html($open_registration_text) . "</a>";
		}

		if ($tournament->close_date === '9999-09-09 09:09:09') {
			$timestamp = PHP_INT_MAX;
		} else {
			$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', $tournament->close_date);
			$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
		}
		if ($tournament->open_registration == '0' && !($tournament->close_tournament == '1' && $timestamp + 24 * 3600 < time())) {
			$play_final_match = intval($tournament->play_final_match);
			$final_result = maybe_unserialize($tournament->final_result);
			if ($play_final_match) {
				if (empty($final_result)) {
					$close_tournament_text = $tournament->close_tournament == '1' ? esc_html__("back to drawn matches", "doubles-rotation-tournament") : esc_html__("start the final match", "doubles-rotation-tournament");
					$option[] = "<a href='" . esc_url(doroto_action_url('doroto_toggle_tournament', intval($tournament_id))) . "'>" . esc_html($close_tournament_text) . "</a>";
				}
			} else {
				$close_tournament_text = $tournament->close_tournament == '1' ? esc_html__("reopen the tournament", "doubles-rotation-tournament") : esc_html__("end the tournament", "doubles-rotation-tournament");
				$option[] = "<a href='" . esc_url(doroto_action_url('doroto_toggle_tournament', intval($tournament_id))) . "'>" . esc_html($close_tournament_text) . "</a>";
			}
		}

		if (!empty($option)) {
			$output .= '<p id="doroto-invitation-registration-status">' . esc_html__("If you wish, you can as an administrator", "doubles-rotation-tournament") . ' ';
			$output .= implode(" " . esc_html__("or", "doubles-rotation-tournament") . " ", $option) . '.</p>';
		}
	}

	if (!$open_registration && !doroto_check_if_presentation_on()) {
		$output .= '</div></div>';
	}
	$output .= doroto_app_link_box(intval($tournament_id));
	return $output;
}
add_shortcode('doroto_tournament_log_link', 'doroto_add_link_to_tournament');


/**
 * Small box on the tournament page pointing players to the Android app.
 * The page is where players already follow the tournament, so it is the best
 * place to tell them about the app. Can be turned off in the plugin settings
 * (Mobile app -> show_app_link).
 * @since 1.6.0
 */
function doroto_app_link_box(int $tournament_id = 0)
{
	if (intval(doroto_read_settings('show_app_link', 1)) !== 1) {
		return '';
	}
	$store_url = 'https://play.google.com/store/apps/details?id=cz.doroto.app&referrer=utm_source%3Dplugin%26utm_medium%3Dtournament_page';
	$output = '<div class="doroto-app-link" style="margin:12px 0;padding:8px 12px;border-left:4px solid #ff9800;background:#fff8e1;">';
	$output .= '&#128241; ' . esc_html__('Follow the tournament and enter results on your phone:', 'doubles-rotation-tournament') . ' ';
	$output .= '<a href="' . esc_url($store_url) . '" target="_blank" rel="noopener">';
	$output .= esc_html__('Rotation Tournaments app for Android', 'doubles-rotation-tournament') . '</a>';
	if ($tournament_id > 0) {
		// Android opens only links of the verified central domain in the app, so
		// a link to this club site always stayed in the browser. An intent link
		// opens this tournament in the app, or Google Play when it is missing.
		// @since 1.6.1
		$app_link = add_query_arg(
			['tournament_id' => $tournament_id, 'doroto_site' => rawurlencode(untrailingslashit(home_url()))],
			'doroto.ltcchrast.cz/'
		);
		$intent = 'intent://' . $app_link . '#Intent;scheme=https;package=cz.doroto.app;S.browser_fallback_url=' . rawurlencode($store_url) . ';end';
		$output .= ' &middot; <a href="' . esc_attr($intent) . '" rel="nofollow"><b>' . esc_html__('Open in the app', 'doubles-rotation-tournament') . '</b></a>';
	}
	$output .= '</div>';
	return $output;
}


/**
 * display all available tournaments
 * @since 1.0.0
 * @version 1.3.9 (the selected tournament is always visible)
 */
function doroto_display_table(array|string $atts)
{
	global $wpdb;
	$a = shortcode_atts(array(
		'display_rows' => doroto_read_settings('display_rows', 20),
	), $atts);

	if (!doroto_check_need_to_display('doroto_table')) {
		return '';
	}
	$current_user = wp_get_current_user();

	$display_rows = intval($a['display_rows']);
	if ($display_rows <= 0) {
		$display_rows = 9999;
	}

	$tournament_id_selected = doroto_getTournamentId();

	$output = doroto_not_logged_message();

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$results = doroto_prepare_filtered_tournaments();
	$filter_option = doroto_read_filter_tournament();
	$num_records = count($results);
	$filter_option_update = false;

	if (!empty($results)) {
		$output .= "<p><div><b>" . esc_html__("List of available tournaments:", "doubles-rotation-tournament") . "</b></div>";
		$output .= "<div class='doroto-table-responsive'>";
		$output .= "<table class='doroto-table' id='doroto-tournament-list'>";
		$output .= "<tr class='doroto-left-aligned'><th>" . esc_html__("ID", "doubles-rotation-tournament") . "</th><th id='doroto_tournament_list_name'>" . esc_html__("Name", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Type", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Log in", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Players count", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Creation date", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Registration status", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Organizer", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Tournament status", "doubles-rotation-tournament") . "</th></tr>";

		if ($filter_option['first_id'] <= 0) {
			$filter_option['first_id'] = 1;
			$filter_option_update = true;
		}
		if ($filter_option['first_id'] > $num_records) {
			$filter_option['first_id'] = $num_records;
			$filter_option_update = true;
		}
		if ($filter_option['last_id'] == 0) {
			$filter_option['last_id'] = $display_rows;
			$filter_option_update = true;
		}
		if ($filter_option_update) {
			update_user_meta(get_current_user_id(), 'doroto_filter_tournaments', serialize($filter_option));
		}


		$row_count = 0;
		foreach ($results as $row) {
			$row_count++;
			if ($row_count < $filter_option['first_id'] && $row->id > $tournament_id_selected) {
				continue;
			}
			if ($row_count > $filter_option['last_id'] && $row->id < $tournament_id_selected) {
				break;
			}

			if ($row->id == $tournament_id_selected) {
				$output .= "<tr class='doroto-tournament-background' id='doroto-tournament-selected'>";
			} else {
				$output .= "<tr>";
			}

			$output .= "<td>" . esc_html($row->id) . "</td>";
			global $post;

			if ($row->id == $tournament_id_selected) {
				$output .= "<td id='doroto-tournament-selected-name'>";
			} else {
				$output .= "<td>";
			}

			$output .= "<a href='" . esc_url(admin_url('admin-ajax.php?action=doroto_choose_tournament&tournament_id=' . esc_html($row->id))) . "'>" . esc_html($row->name) . "</a></td>";
			$options = doroto_tournament_types();
			$output .= "<td>" . esc_html($options[intval($row->tournament_type)]) . "</td>";

			try {
				$players = unserialize($row->players);
			} catch (Exception $e) {
				$players = array();
			}

			$login_text = is_array($players) && in_array($current_user->ID, $players) ? esc_html__("Log out", "doubles-rotation-tournament") : esc_html__("Log in", "doubles-rotation-tournament");

			if ($row->id == $tournament_id_selected) {
				$output .= "<td id='doroto-tournament-selected-login'>";
			} else {
				$output .= "<td>";
			}
			if ($row->close_tournament != '1' && $row->open_registration != '0') {
				$is_registered = is_array($players) && in_array(get_current_user_id(), array_map('intval', $players), true);
				$login_url = $is_registered ? doroto_leave_url(intval($row->id)) : doroto_join_url(intval($row->id));
				$output .= "<a href='" . esc_url($login_url) . "'>" . esc_html($login_text) . "</a></td>";
			} else {
				$output .= esc_html($login_text) . "</td>";
			}

			if ($row->id == $tournament_id_selected) {
				$output .= "<td id='doroto-tournament-selected-count'>";
			} else {
				$output .= "<td>";
			}

			$output .= esc_html(count(unserialize($row->players)));
			$max_players = intval($row->max_players);
			if ($max_players > 0) {
				$output .= esc_html(" / " . $max_players);
			}
			$output .= "</td>";

			$output .= "<td>" . esc_html(gmdate('Y-m-d H:i', strtotime($row->create_date))) . "</td>";

			$whole_names = intval($row->whole_names);
			$admin_users = unserialize($row->admin_users);
			if ($row->id == $tournament_id_selected) {
				$output_registration_status = "<td id='doroto-tournament-selected-registration-status'>";
			} else {
				$output_registration_status = "<td>";
			}

			if (doroto_is_admin(intval($row->id)) > 0) {
				$open_registration_text = $row->open_registration == '1' ? esc_html__("close registration", "doubles-rotation-tournament") : esc_html__("reopen registration", "doubles-rotation-tournament");
				if ($row->close_tournament != '1') {
					$output .= $output_registration_status . "<a href='" . esc_url(doroto_action_url('doroto_toggle_registration', intval($row->id))) . "'>" . esc_html($open_registration_text) . "</a></td>";
				} else {
					$output .= $output_registration_status . esc_html($open_registration_text) . "</td>";
				}
			} else {
				$open_registration_text = $row->open_registration == '1' ? esc_html__("registration allowed", "doubles-rotation-tournament") : esc_html__("registration closed", "doubles-rotation-tournament");
				$output .= $output_registration_status . esc_html($open_registration_text) . "</td>";
			}

			if ($row->id == $tournament_id_selected) {
				$output .= "<td id='doroto-tournament-selected-organizer'>";
			} else {
				$output .= "<td>";
			}
			foreach ($admin_users as $admin_id) {
				$user_info = doroto_get_also_false_user($admin_id);
				$output .= esc_html(doroto_find_player_name($admin_id, $whole_names)) . ' ';
			}
			$output .= "</td>";

			if ($row->id == $tournament_id_selected) {
				$output_tournament_status = "<td id='doroto-tournament-selected-tournament-status'>";
			} else {
				$output_tournament_status = "<td>";
			}

			if ($row->close_date === '9999-09-09 09:09:09') {
				$timestamp = PHP_INT_MAX;
			} else {
				$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', $row->close_date);
				$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
			}
			if (doroto_is_admin(intval($row->id)) > 0 && !($row->close_tournament == '1' && $timestamp + 24 * 3600 < time())) {
				$tournament = doroto_prepare_tournament(intval($row->id));
				$play_final_match = intval($tournament->play_final_match);
				$final_result = maybe_unserialize($tournament->final_result);
				$tournament_id = intval($row->id);
				if ($play_final_match) {
					if (empty($final_result)) {
						$close_tournament_text = $row->close_tournament == '1' ? esc_html__("back to drawn matches", "doubles-rotation-tournament") : esc_html__("start the final match", "doubles-rotation-tournament");
						$output .= $output_tournament_status . "<a href='" . esc_url(doroto_action_url('doroto_toggle_tournament', intval($row->id))) . "'>" . esc_html($close_tournament_text) . "</a></td>";
					} else {
						$close_tournament_text = $row->close_tournament == '1' ? esc_html__("finished tournament", "doubles-rotation-tournament") : esc_html__("open tournament", "doubles-rotation-tournament");
						$output .= $output_tournament_status . esc_html($close_tournament_text) . "</td>";
					}
				} else {
					$close_tournament_text = $row->close_tournament == '1' ? esc_html__("reopen the tournament", "doubles-rotation-tournament") : esc_html__("end the tournament", "doubles-rotation-tournament");
					$output .= $output_tournament_status . "<a href='" . esc_url(doroto_action_url('doroto_toggle_tournament', intval($row->id))) . "'>" . esc_html($close_tournament_text) . "</a></td>";
				}
			} else {
				$close_tournament_text = $row->close_tournament == '1' ? esc_html__("finished tournament", "doubles-rotation-tournament") : esc_html__("open tournament", "doubles-rotation-tournament");
				$output .= $output_tournament_status . esc_html($close_tournament_text) . "</td>";
			}

			$output .= "</tr>";
		}

		if ($num_records > $display_rows && get_current_user_id() > 0) {
			$output .= '<tr><form id="move_among_tournaments_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"> ';
			$output .= wp_nonce_field('doroto_move_among_tournaments_nonce', '_wpnonce', true, false);
			$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id_selected) . '">';
			if ($filter_option['first_id'] > 1) {
				$output .= '<td ="text-align: left;" colspan="2"> <button type="submit" name="doroto_decrement_first_id"> << </button> </td>';
			} else {
				$output .= '<td colspan="2"></td>';
			}
			$output .= '<td colspan="5"></td>';
			$output .= '<input type="hidden" name="action" value="doroto_move_among_tournaments">';
			if ($num_records > $filter_option['last_id']) {
				$output .= '<td style ="text-align: left;" colspan="2"> <button type="submit" name="doroto_increment_first_id"> >> </button> </td>';
			} else {
				$output .= '<td></td>';
			}
			$output .= '</form></tr>';
		}

		$output .= "</table>";
		$output .= "</div></p>";
	} else {
		$output = esc_html__("No tournament has been found.", "doubles-rotation-tournament");
	}
	return $output;
}

add_shortcode('doroto_table', 'doroto_display_table');
add_action('admin_post_doroto_move_among_tournaments', 'doroto_move_among_tournaments');
add_action('admin_post_nopriv_doroto_move_among_tournaments', 'doroto_move_among_tournaments');


/**
 * create a new tournament
 * @since 1.0.0
 */
function doroto_add_tournament_shortcode()
{
	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$output = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_add_tournament_save">';

	if (isset($_SERVER['REQUEST_URI'])) {
		$uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
	} else {
		$uri = '';
	}
	$uri = '/' . ltrim($uri, '/');

	$output .= '<input type="hidden" name="redirect_uri" value="' . esc_url($uri) . '">';

	$output .= '<input type="submit" id="doroto-add-tournament-submit-button" value="' . esc_html__('Create a new tournament', 'doubles-rotation-tournament') . '">';

	$output .= ' ' . esc_html__('for', 'doubles-rotation-tournament') . ' ';
	$tournament_type = doroto_read_settings('tournament_type', 1);
	$output .= '<select id="doroto-add-tournament-tournament-type" name="doroto_add_tournament_tournament_type" >';
	$type_variables = doroto_types_variables();
	$tournament_types = doroto_tournament_types();

	foreach ($type_variables as $value => $name) {
		if (doroto_read_settings($name, 1)) {
			$selected = ($value == $tournament_type) ? ' selected="selected"' : '';
			$output .= "<option value=\"" . esc_attr($value) . "\"$selected>" . esc_html($tournament_types[$value]) . "</option>";
		}
	}
	$output .= '</select>';

	$output .= wp_nonce_field('doroto_add_tournament_nonce', '_wpnonce', true, false);
	$output .= '</form>';
	return $output;
}

add_shortcode('doroto_add_tournament', 'doroto_add_tournament_shortcode');


/**
 * confirm a payment for a player in the tournament
 * @since 1.0.0
 */
function doroto_enter_payment_manually_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$a = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	$tournament_id = intval($a['tournament_id']);
	$output = '';

	if (!is_user_logged_in()) {
		$output = "<div>" . esc_html__('You must log in to confirm a payment!', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$players = maybe_unserialize($tournament->players);
	$payment_done = maybe_unserialize($tournament->payment_done);
	$whole_names = intval($tournament->whole_names);
	if ($whole_names != 0 && $whole_names != 1) {
		$whole_names = 0;
	}
	$payment_display = intval($tournament->payment_display);
	if ($payment_display != 0 && $payment_display != 1) {
		$payment_display = 0;
	}

	if (!$payment_display) {
		$output = "<div>" . esc_html__('If you wish to record payments from players, enable in', 'doubles-rotation-tournament');
		$output .= " <b>" . esc_html__('Tournament Editing ...', 'doubles-rotation-tournament') . "</b>";
		$output .= " <i>" . esc_html__('Show control over the paid entry fee?', 'doubles-rotation-tournament') . "</i></div>";
		return $output;
	}

	if (!is_array($players)) {
		$players = [];
	}

	if (!is_array($payment_done)) {
		$payment_done = [];
	}

	$user_id = intval(get_current_user_id());
	if (doroto_is_admin($tournament_id) < 1) {
		$output .= "<div>" . esc_html__('You do not have permission to confirm a payment.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (!empty($players)) {
		$players = array_diff($players, $payment_done);
	} else {
		$output .= "<div>" . esc_html__('All players have already paid.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}
	$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");

	$output = "<div><b>" . esc_html__('Confirm the payment of the selected player in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="doroto_enter_payment_manually" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_enter_payment_in_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	if (count($players) > 0) {
		$output .= '<select name="confirm_payment">';
		foreach ($players as $player_id) {
			$user = get_userdata($player_id);
			if (!in_array($player_id, $payment_done)) {
				$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_find_player_name($user->ID, $whole_names)) . '</option>';
			}
		}
		$output .= '</select> ';
		$output .= '<input type="submit" name="enter_manually" value="' . esc_html__("Confirm payment", "doubles-rotation-tournament") . '">';
	} else {
		$output .= "<div>" . esc_html__('All players have already paid.', "doubles-rotation-tournament") . "</div>";
	}
	$output .= wp_nonce_field('doroto_enter_payment_manually_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	return $output;
}

add_shortcode('doroto_enter_payment_manually', 'doroto_enter_payment_manually_shortcode');


/**
 * confirm a payment for a player in the tournament after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_enter_payment_manually_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_enter_payment_manually_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['confirm_payment']) ? intval($_POST['confirm_payment']) : -1;

	$result = doroto_service_set_payment($tournament_id, $player_id, true);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_enter_payment_in_tournament', 'doroto_enter_payment_manually_form_submit');
add_action('admin_post_nopriv_doroto_enter_payment_in_tournament', 'doroto_enter_payment_manually_form_submit');


/**
 * remove a payment of a player in the tournament
 * @since 1.0.0
 */
function doroto_remove_payment_manually_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;
	$a = shortcode_atts(array(
		'tournament_id' => doroto_getTournamentId(),
	), $atts);

	$tournament_id = intval($a['tournament_id']);
	$output = '';

	if (doroto_check_if_presentation_on()) {
		return '';
	}

	if (!is_user_logged_in()) {
		$output = "<div>" . esc_html__('You must log in to remove a payment!', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$players = maybe_unserialize($tournament->players);
	$payment_done = maybe_unserialize($tournament->payment_done);
	$whole_names = intval($tournament->whole_names);
	if ($whole_names != 0 && $whole_names != 1) {
		$whole_names = 0;
	}
	$payment_display = intval($tournament->payment_display);
	if ($payment_display != 0 && $payment_display != 1) {
		$payment_display = 0;
	}

	if ($payment_display == 0) {
		return null;
	}

	if (!is_array($players)) {
		$players = [];
	}

	if (!is_array($payment_done)) {
		$payment_done = [];
	}

	$user_id = intval(get_current_user_id());
	if (doroto_is_admin($tournament_id) < 1) {
		$output .= "<div>" . esc_html__('You do not have permission to remove a payment.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (empty($payment_done)) {
		$output .= "<div>" . esc_html__('None of the players have paid yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}
	$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");

	$output = "<div><b>" . esc_html__('Remove the payment of the selected player in tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:";
	$output .= '<form id="doroto_remove_payment_manually" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_remove_payment_in_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	if (count($payment_done) > 0) {
		$output .= '<select name="remove_payment">';
		foreach ($payment_done as $player_id) {
			$user = get_userdata($player_id);
			$output .= '<option value="' . esc_attr($user->ID) . '">' . esc_html(doroto_find_player_name($user->ID, $whole_names)) . '</option>';
		}
		$output .= '</select> ';
		$output .= '<input type="submit" name="remove_manually" value="' . esc_html__("Remove payment", "doubles-rotation-tournament") . '">';
	} else {
		$output .= "<div>" . esc_html__('None of the players have paid yet.', "doubles-rotation-tournament") . "</div>";
	}
	$output .= wp_nonce_field('doroto_remove_payment_manually_nonce', '_wpnonce', true, false);
	$output .= '</form></div>';

	return $output;
}

add_shortcode('doroto_remove_payment_manually', 'doroto_remove_payment_manually_shortcode');


/**
 * remove a payment of a player in the tournament after submitting form
 * @since 1.0.0
 * @version 2.0.0 (services)
 */
function doroto_remove_payment_manually_form_submit()
{
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_remove_payment_manually_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$player_id = isset($_POST['remove_payment']) ? intval($_POST['remove_payment']) : -1;

	$result = doroto_service_set_payment($tournament_id, $player_id, false);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}
add_action('admin_post_doroto_remove_payment_in_tournament', 'doroto_remove_payment_manually_form_submit');
add_action('admin_post_nopriv_doroto_remove_payment_in_tournament', 'doroto_remove_payment_manually_form_submit');


/**
 * filter displayed tournaments 
 * @since 1.0.0
 */
function doroto_filter_tournaments_shortcode()
{
	$output = '';
	ob_start();

	if (doroto_check_if_presentation_on()) {
		return $output;
	}

	$filter = doroto_read_filter_tournament();
	$default_filter_option = intval($filter['selection']);
	$tournament_types = doroto_tournament_types();

	$output .= '<form method="post" action="">';
	$output .= '<select name="doroto_filter_option" id="doroto_filter_option">';
	$output .= '<option value="0" ' . selected(0, $default_filter_option, false) . '>' . esc_html__('No Filter', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="1" ' . selected(1, $default_filter_option, false) . '>' . esc_html__('Only Open Registration', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="2" ' . selected(2, $default_filter_option, false) . '>' . esc_html__('Still Playing', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="3" ' . selected(3, $default_filter_option, false) . '>' . esc_html__('Only Closed', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="4" ' . selected(4, $default_filter_option, false) . '>' . esc_html__('Where I Am Logged In', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="5" ' . selected(5, $default_filter_option, false) . '>' . esc_html__('Where I Am Not Logged In', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="6" ' . selected(6, $default_filter_option, false) . '>' . esc_html__('Where I Am Admin', 'doubles-rotation-tournament') . '</option>';
	$output .= '<option value="7" ' . selected(7, $default_filter_option, false) . '>' . esc_html__('Where I Am Not Admin', 'doubles-rotation-tournament') . '</option>';

	$type_variables = doroto_types_variables();
	$tournament_types = doroto_tournament_types();
	foreach ($type_variables as $value => $name) {
		if (doroto_read_settings($name, 1)) {
			$output .= '<option value="' . esc_attr($value) . '" ' . selected($value, $default_filter_option, false) . '>' . $tournament_types[$value] . '</option>';
		}
	}

	$output .= '</select> ';
	$output .= '<input type="submit" name="doroto_filter_submit" value="' . esc_html__('Filter Tournaments', 'doubles-rotation-tournament') . '">';
	$output .= wp_nonce_field('doroto_filter_tournaments_nonce', '_wpnonce', true, false);
	$output .= '</form>';

	return $output . ob_get_clean();
}
add_shortcode('doroto_filter_tournaments', 'doroto_filter_tournaments_shortcode');


/**
 * filter displayed tournaments after submitting form
 * @since 1.0.0
 */
function doroto_filter_tournaments_result()
{
	if (isset($_POST['doroto_filter_submit'])) {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_filter_tournaments_nonce')) {
			wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
		}

		if (isset($_POST['doroto_filter_option'])) {

			$filter_option = doroto_read_filter_tournament();
			$filter_new_selection = intval($_POST['doroto_filter_option']);

			if ($filter_option['selection'] != $filter_new_selection) {
				$filter_option['first_id'] = 0;
				$filter_option['last_id'] = 0;
			}

			$filter_option['selection'] = $filter_new_selection;
		}

		if (isset($filter_option)) {
			$filter_option_serialized = serialize($filter_option);
			update_user_meta(get_current_user_id(), 'doroto_filter_tournaments', $filter_option_serialized);
		}
	}
}

add_action('init', 'doroto_filter_tournaments_result');


/**
 * link to run a presentation
 * @since 1.0.0
 */
function doroto_allow_presentation_shortcode()
{
	$user_id = intval(get_current_user_id());
	if ($user_id == 0) {
		$output = "<div>" . esc_html__("If you log in, you can start the presentation of the selected tournament.", "doubles-rotation-tournament") . "</div>";
		return $output;
	}
	$doroto_presentation = maybe_unserialize(get_user_meta($user_id, 'doroto_presentation', true));

	if (empty($doroto_presentation) || !is_array($doroto_presentation)) {
		$doroto_presentation = array('allow_to_run' => 0, 'slide' => 'doroto_games_to_play');
	}
	$slide_value = $doroto_presentation['slide'];

	$text = sanitize_text_field(__("Click here to run the presentation.", "doubles-rotation-tournament"));
	$action_value = 1;

	if ($doroto_presentation['allow_to_run'] == 1) {
		$text = sanitize_text_field(__("Click here to stop the presentation.", "doubles-rotation-tournament"));
		$action_value = 0;
	}

	$output = "<div>" . '<a href="' . esc_url(add_query_arg('doroto_presentation_action', $action_value)) . '">' . esc_html($text) . '</a>' . "</div>";

	if (isset($_GET['doroto_presentation_action'])) {
		$action_value = intval($_GET['doroto_presentation_action']);
		update_user_meta($user_id, 'doroto_presentation', serialize(array('allow_to_run' => $action_value, 'slide' => $slide_value)));
	}
	return $output;
}

add_shortcode('doroto_allow_presentation', 'doroto_allow_presentation_shortcode');


/**
 * display div first
 * @since 1.0.0
 * @version 1.3.7
 */
function doroto_display_div_first_shortcode(array|string $atts)
{
	global $wpdb;
	$attributs = shortcode_atts(array(
		'div_id' => '',
		'label' => '',
	), $atts);

	$div_id = sanitize_text_field($attributs['div_id']);
	$div_id_main = $div_id . "-main";
	$label = sanitize_text_field($attributs['label']);

	if (!doroto_check_if_presentation_on()) {
		$output = '<div class="doroto-content-main">
				<h4 class="doroto-clickable-title" id="' . esc_attr($div_id_main) . '">' . esc_html($label) . '</h4>	
				<div class="doroto-content-container" id="' . esc_html($div_id) . '">';
	} else {
		$output = '';
	}
	return $output;
}
add_shortcode('doroto_display_div_first', 'doroto_display_div_first_shortcode');


/**
 * display div last
 * @since 1.0.0
 */
function doroto_display_div_last_shortcode()
{
	global $wpdb;

	if (!doroto_check_if_presentation_on()) {
		$output = '</div></div>';
	} else {
		$output = '';
	}
	return $output;
}
add_shortcode('doroto_display_div_last', 'doroto_display_div_last_shortcode');


/**
 * div with other background first
 * @since 1.0.0
 * @version 1.3.7 (add id selectors)
 */
function doroto_display_other_background_first_shortcode(array|string $atts)
{
	global $wpdb;
	$attributs = shortcode_atts(array(
		'label' => '',
		'div_id' => '',
	), $atts);
	$div_id = sanitize_text_field($attributs['div_id']);
	$div_id_main = $div_id . "-main";
	$label = sanitize_text_field($attributs['label']);
	if (!doroto_check_if_presentation_on()) {
		$output = '<div class="doroto-content-main">';
		$output .= '<h5 class="doroto-clickable-submenu" id="' . esc_attr($div_id_main) . '">' . '&rarr; ' . esc_attr($label) . '</h5>';
		$output .= '<div class="doroto-content-container" id="' . esc_html($div_id) . '">';
		$output .= '<div class="doroto-table-responsive">';
	} else {
		$output = '';
	}
	return $output;
}
add_shortcode('doroto_display_other_background_first', 'doroto_display_other_background_first_shortcode');


/**
 * div with other background last
 * @since 1.0.0
 */
function doroto_display_other_background_last_shortcode()
{
	global $wpdb;
	if (!doroto_check_if_presentation_on()) {
		$output = '</div>';
		$output .= '</div>';
		$output .= '</div>';
	} else {
		$output = '';
	}
	return $output;
}
add_shortcode('doroto_display_other_background_last', 'doroto_display_other_background_last_shortcode');

/**
 * Floating help icon
 * @since 1.3.7
 */
function doroto_floating_help_icon()
{
	$output = '<div id="doroto-floating-help-icon">';

	$tournament_id_1 = doroto_read_settings('tournament_example_1', '0');
	$tournament_id_2 = doroto_read_settings('tournament_example_2', '0');
	$tournament_id_3 = doroto_read_settings('tournament_example_3', '0');
	$tournament_id_4 = doroto_read_settings('tournament_example_4', '0');
	if ($tournament_id_1 == 0 || $tournament_id_2 == 0 || $tournament_id_3 == 0 || $tournament_id_4 == 0) {
		doroto_create_tournament_record();
		$output .= esc_html__('At least one tournament example is missing!', 'doubles-rotation-tournament');
		$output .= '</div>';
		return $output;
	}

	$output .= esc_html__('Help', 'doubles-rotation-tournament');
	$output .= '<ul id="doroto-help-dropdown">';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_create_tournament" data-tournament-id="' . esc_attr($tournament_id_1) . '">' . esc_html__('Create a new tournament', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_login_logout" data-tournament-id="' . esc_attr($tournament_id_1) . '">' . esc_html__('Tournament registration', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_setting_basic" data-tournament-id="' . esc_attr($tournament_id_2) . '">' . esc_html__('Basic tournament management', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_setting_advance" data-tournament-id="' . esc_attr($tournament_id_2) . '">' . esc_html__('Advance tournament management', 'doubles-rotation-tournament') . '</a></li>';

	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_example_1" data-tournament-id="' . esc_attr($tournament_id_1) . '">' . esc_html__('Example 1: open registration', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_example_2" data-tournament-id="' . esc_attr($tournament_id_2) . '">' . esc_html__('Example 2: during the tournament', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_example_3" data-tournament-id="' . esc_attr($tournament_id_3) . '">' . esc_html__('Example 3: singles completed', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li><a href="#" class="doroto-help-tour" doroto-data-tour="tour_tournament_example_4" data-tournament-id="' . esc_attr($tournament_id_4) . '">' . esc_html__('Example 4: doubles completed', 'doubles-rotation-tournament') . '</a></li>';

	$output .= '</ul>';
	$output .= '</div>';

	return $output;
}
add_shortcode('doroto_floating_help', 'doroto_floating_help_icon');


/**
 * AJAX handler that reads tournament_id
 * @since 1.3.7
 */
function doroto_get_tournament_id()
{
	if (!isset($_POST['tour'])) {
		wp_send_json_error(['message' => 'Missing tour parameter']);
	}

	$tour = sanitize_text_field($_POST['tour']);
	$tournament_id = '0';

	switch ($tour) {
		case 'tour_example_1':
			$tournament_id = doroto_read_settings('tournament_example_1', '0');
			break;
		case 'tour_example_2':
			$tournament_id = doroto_read_settings('tournament_example_2', '0');
			break;
		case 'tour_example_3':
			$tournament_id = doroto_read_settings('tournament_example_3', '0');
			break;
		case 'tour_example_4':
			$tournament_id = doroto_read_settings('tournament_example_4', '0');
			break;
		default:
			wp_send_json_error(['message' => 'Invalid tour']);
	}
	wp_send_json_success(['tournament_id' => $tournament_id]);
}
add_action('wp_ajax_get_tournament_id', 'doroto_get_tournament_id');
add_action('wp_ajax_nopriv_get_tournament_id', 'doroto_get_tournament_id');

/**
 * register and add player to the tournament
 * @since 1.4.4
 * @version 1.4.5 (follow a system setting if anyone can register) 
 */
function doroto_register_add_player_shortcode($atts = [], $content = null, $tag = '')
{
	global $wpdb;

	$atts = array_change_key_case((array) $atts, CASE_LOWER);
	$doroto_atts = shortcode_atts(array(
		'only_web_admin' => doroto_read_settings('only_admin_players', 1),
	), $atts);
	if (doroto_check_if_presentation_on())
		return '';

	$only_web_admin = intval($doroto_atts['only_web_admin']);

	$current_user = wp_get_current_user();
	$tournament_id = doroto_getTournamentId();
	if (!isset($tournament_id)) {
		$output = "<div>" . esc_html__('Invalid tournament ID.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if ($tournament_id == 0) {
		$output = "<div>" . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$admin_users = maybe_unserialize($tournament->admin_users);
	$output = "<div>" . esc_html__('You do not have permission to register and add a player.', 'doubles-rotation-tournament') . "</div>";
	if ($only_web_admin == 3) {
		if (!(doroto_is_admin($tournament_id) == 2)) {
			return $output;
		}
	} else {
		if (!(doroto_is_admin($tournament_id) > 0)) {
			return $output;
		}
	}

	if ($tournament->close_tournament == '1' || !get_option('users_can_register')) {
		$output = "<div>" . esc_html__('The option to register and add a player is closed.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$players = maybe_unserialize($tournament->players);
	$whole_names = intval($tournament->whole_names);

	/*
		 only_admin_players or only_web_admin options:
	 3: from the current tournament
	 2: for whom they have already organized a tournament in the past
			1: that they have met at a tournament where was also another organizer
	 0: all players w/o limitations
		*/

	if ($only_web_admin == 2 || $only_web_admin == 1) {
		$participants = doroto_current_user_in_tournaments($only_web_admin);
		$new_players = array_diff($participants, $players);
	}

	if ($only_web_admin == 3 || $only_web_admin == 0 || doroto_is_admin($tournament_id) == 2) {
		if (!empty($players)) {
			$placeholders = implode(',', array_fill(0, count($players), '%d'));
			$users = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID NOT IN ($placeholders)", $players)
			);
		} else {
			$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}");
		}
	} else {
		if (!empty($new_players)) {
			$placeholders = implode(',', array_fill(0, count($new_players), '%d'));
			$users = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID IN ($placeholders)", $new_players)
			);
		} else {
			$users = null;
		}
	}

	if ($users == null) {
		$output = "<div>" . esc_html__('Unable to register and add another player from the database.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	if (is_array($users)) {
		usort($users, function ($a, $b) {
			$nameA = $a->display_name ?? '';
			$nameB = $b->display_name ?? '';
			return strcasecmp($nameA, $nameB);
		});
	}


	$output = "<div><b>" . esc_html__('Register and add a new player to the tournament no.', 'doubles-rotation-tournament') . ' ' . esc_html($tournament_id) . "</b>:</div>";
	$output .= '<form id="register_add_player_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	$output .= '<input type="hidden" name="action" value="doroto_register_add_player_to_tournament">';
	$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';

	$output .= '<label>' . esc_html__('First Name', 'doubles-rotation-tournament') . ':</label>';
	$output .= '<input type="text" name="first_name" required><br>';

	$output .= '<label>' . esc_html__('Last Name', 'doubles-rotation-tournament') . ':</label>';
	$output .= '<input type="text" name="last_name" required><br>';

	$output .= '<label>' . esc_html__('Email', 'doubles-rotation-tournament') . ':</label>';
	$output .= '<input type="email" name="email" required><br>';

	$output .= '<input type="submit" value="' . esc_html__("Register and add player", "doubles-rotation-tournament") . '">';
	$output .= wp_nonce_field('doroto_register_add_player_form_nonce', '_wpnonce', true, false);
	$output .= '</form>';


	return $output;
}
add_shortcode('doroto_register_add_player', 'doroto_register_add_player_shortcode');


/**
 * register and add player to the tournament after submitting form
 * @since 1.4.4
 * @version 1.4.7 (correct player name when adding a new player) 
 */
function doroto_register_add_player_form_submit()
{
	global $wpdb;

	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_register_add_player_form_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
	$first_name = sanitize_text_field($_POST['first_name'] ?? '');
	$last_name = sanitize_text_field($_POST['last_name'] ?? '');
	$email = sanitize_email($_POST['email'] ?? '');

	if (empty($tournament_id) || empty($first_name) || empty($last_name) || !is_email($email)) {
		doroto_info_messsages_save(__('Invalid value entered.', 'doubles-rotation-tournament'));
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	if (!is_user_logged_in()) {
		doroto_info_messsages_save(__("You need to log in to add a player!", "doubles-rotation-tournament"));
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	$user = get_user_by('email', $email);
	if (!$user) {
		$username = sanitize_user(strtolower($first_name . '.' . $last_name));
		if (username_exists($username)) {
			$username .= wp_generate_password(4, false);
		}
		$password = wp_generate_password(12, true);
		$user_id = wp_create_user($username, $password, $email);
		if (is_wp_error($user_id)) {
			doroto_info_messsages_save(__('Could not create user.', 'doubles-rotation-tournament'));
			doroto_redirect_modify_url($tournament_id, "");
			exit;
		}

		$current_user_id = get_current_user_id();
		add_user_meta($user_id, 'doroto_creator', $current_user_id, true);

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
		]);
	} else {
		$user_id = $user->ID;
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		doroto_info_messsages_save(__('The tournament was not found.', 'doubles-rotation-tournament'));
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	$players = maybe_unserialize($tournament->players);
	if (!is_array($players)) {
		$players = [];
	}

	if (!in_array($user_id, $players)) {
		$players[] = $user_id;
	}

	$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
	$wpdb->update(
		$wpdb->prefix . 'doroto_tournaments',
		[
			'players' => serialize($players),
			'statistics' => serialize($statistics),
			'last_update'  => round(microtime(true) * 1000)
		],
		['id' => $tournament_id]
	);

	$output = sanitize_text_field(__('Player', 'doubles-rotation-tournament') . ' ' . doroto_find_player_name($user_id, intval($tournament->whole_names)) . ' ' . __('was added to the tournament.', 'doubles-rotation-tournament'));
	doroto_tournament_progress($tournament_id);
	doroto_info_messsages_save($output);
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}


add_action('admin_post_doroto_register_add_player_to_tournament', 'doroto_register_add_player_form_submit');
add_action('admin_post_nopriv_doroto_register_add_player_to_tournament', 'doroto_register_add_player_form_submit');
