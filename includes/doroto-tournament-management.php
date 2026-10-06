<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * display tournament progress
 * @since 1.3.6
 * @version 1.5.5 (PHP 8.0+ ready)
 */
function doroto_tournament_progress(int $tournament_id)
{
	return doroto_with_tournament_lock($tournament_id, function () use ($tournament_id) {
		return doroto_tournament_progress_locked($tournament_id);
	});
}

/**
 * Body of doroto_tournament_progress(); caller must hold the tournament lock.
 * @since 1.6.0
 */
function doroto_tournament_progress_locked(int $tournament_id)
{
	global $wpdb;

	$tournament = doroto_prepare_tournament($tournament_id);
	if ($tournament == null) {
		$output = '<div>' . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	$statistics = maybe_unserialize($tournament->statistics);
	$players = maybe_unserialize($tournament->players);
	$special_group = maybe_unserialize($tournament->special_group);
	$activePlayers = doroto_find_active_players($statistics);
	$announce_round_end = intval($tournament->announce_round_end);

	$two_special_group = intval($tournament->two_special_group);
	$two_out_group = intval($tournament->two_out_group);

	if (empty($statistics)) {
		$output = '<p>' . esc_html__('Once registration is closed, statistical data will be available.', 'doubles-rotation-tournament') . '</p>';
		return $output;
	}

	$matches_planned = 0;
	$matches_to_play = 0;

	foreach ($statistics as &$player) {
		$matches_to_play_beginning = $matches_to_play;
		if (in_array($player['player_id'], $activePlayers)) {
			$player_is_active = true;
		} else {
			$player_is_active = false;
		}
		if (!$player_is_active) {
			continue;
		}

		if ((in_array($player['player_id'], $special_group))) {
			$player_in_special = true;
		} else {
			$player_in_special = false;
		}

		foreach ($player['playmates'] as $playmates) {
			if (in_array($playmates['player_id'], $activePlayers)) {
				$opponent_is_active = true;
			} else {
				$opponent_is_active = false;
			}
			if (in_array($playmates['player_id'], $special_group)) {
				$opponent_in_special = true;
			} else {
				$opponent_in_special = false;
			}

			if ($two_special_group && $player_in_special && $opponent_in_special) {
				continue;
			}
			if ($two_out_group && !$player_in_special && !$opponent_in_special) {
				continue;
			}

			if (!$opponent_is_active) {
				continue;
			}

			if ($announce_round_end == 1) {
				$matches_planned++;
				if ($playmates['count'] == 0) {
					$matches_to_play++;
				}
			} elseif ($announce_round_end == 2) {
				$matches_planned += 2;
				if ($playmates['count'] == 0) {
					$matches_to_play += 2;
				} elseif ($playmates['count'] == 1) {
					$matches_to_play++;
				}
			} elseif ($announce_round_end == 0) {
				$matches_planned += 999;
				$matches_to_play += 999 - $playmates['count'];
			}
		}
		$player['rest'] = $matches_to_play - $matches_to_play_beginning;
	}

	$matches_planned_reduced = $matches_planned / 2;
	$matches_to_play_reduced = $matches_to_play / 2;

	$wpdb->update(
		"{$wpdb->prefix}doroto_tournaments",
		array(
			'statistics' => serialize($statistics),
			'planned_combinations' => intval(floor($matches_planned_reduced)),
			'rest_combinations' => intval(floor($matches_to_play_reduced)),
			'last_update' => doroto_now_ms(),
		),
		array('id' => $tournament_id)
	);
	return;
}

/**
 * offer new games to play
 * @version 1.6.2 (teammate and opposing team chosen by a score that includes previous meetings)
 * @since 1.0.0
 */
function doroto_offer_games(int $tournament_id, int $matches_to_select)
{
	return doroto_with_tournament_lock($tournament_id, function () use ($tournament_id, $matches_to_select) {
		return doroto_offer_games_locked($tournament_id, $matches_to_select);
	});
}

/**
 * Body of doroto_offer_games(); caller must hold the tournament lock.
 * @since 1.6.0
 */
function doroto_offer_games_locked(int $tournament_id, int $matches_to_select)
{
	global $wpdb;

	$tournament_id = intval($tournament_id);
	if ($tournament_id == 0) {
		$output = '<div>' . esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament') . '</div>';
		return $output;
	};

	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament == null) {
		$output = "<div>" . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . "</div>";
		return $output;
	}

	$statistics = maybe_unserialize($tournament->statistics);
	$activePlayers = doroto_find_active_players($statistics);

	$playing = $tournament->playing;
	if ($playing == '') {
		$playing = [];
	} else {
		$playing = maybe_unserialize($playing);
	}

	$matches_list = maybe_unserialize($tournament->matches_list);

	$special_group = $tournament->special_group;
	if ($special_group == '') {
		$special_group = [];
	} else {
		$special_group = maybe_unserialize($special_group);
	}

	$two_special_group = intval($tournament->two_special_group);
	$two_out_group = intval($tournament->two_out_group);
	$announce_round_end = intval($tournament->announce_round_end);

	if (!$matches_list || !$statistics) {
		$output = '<div class="doroto-warning-text">' . esc_html__('Match list not found!', 'doubles-rotation-tournament') . '</div>';
		return $output;
	}

	if (doroto_check_if_doubles($tournament)) {
		$players_on_court = 4;
	} else {
		$players_on_court = 2;
	}

	$currently_played_count = count($playing) / $players_on_court;

	if ($matches_to_select == 0) {
		$matches_to_select = doroto_matches_to_select_count($tournament, $currently_played_count);
	}

	if ($matches_to_select > 0) {
		// Step 1: Filter active players
		$active_players = array_filter($statistics, function ($player) {
			return $player['active'] == 1;
		});

		// Step 2: Filter the players who are occupied
		$active_players = array_filter($active_players, function ($player) use ($playing) {
			return !in_array($player['player_id'], $playing);
		});

		//Step 3 Check if there are enough active players
		if (count($active_players) < $players_on_court) {
			$output = '<p class="doroto-red-text">' . esc_html__('The small number of active players does not allow another match to be drawn.', 'doubles-rotation-tournament') . '</p>';
			return $output;
		}

		// Step 4: Make a copy of the $statistics array
		$statistics_temp = $active_players;

		// Step 5: Sort players by number of games played
		usort($statistics_temp, function ($a, $b) {
			return $a['games'] - $b['games'];
		});

		// Step 6: Shuffle the order, but only at the beginning of the tournament
		$tournament_start = true;
		foreach ($statistics_temp as $player) {
			if ($player['games'] > 0) {
				$tournament_start = false;
			}
		}

		if ($tournament_start) {
			shuffle($statistics_temp);
		}

		// Step 6.1: Sort players by number of rest combinations
		usort($statistics_temp, function ($a, $b) {
			return $b['rest'] - $a['rest'];
		});

		// Step 7: Select the player with the highest number of rest combinations
		$firstItem = reset($statistics_temp);
		$choosen_player = $firstItem['player_id'];

		$choosen_player_games = $firstItem['rest'];

		// Step 8: Select other players with the same number of rest combinations and randomly select
		// one of those who played the fewest games (a player who just finished a match used to be
		// picked as often as one who had been waiting)
		$fewest_games = null;
		foreach ($statistics_temp as $player) {
			if ($player['rest'] == $choosen_player_games && ($fewest_games === null || $player['games'] < $fewest_games)) {
				$fewest_games = $player['games'];
			}
		}
		$possible_player_array = [];
		foreach ($statistics_temp as $player) {
			if ($player['rest'] == $choosen_player_games && $player['games'] == $fewest_games) {
				$possible_player_array[] = $player['player_id'];
			}
		}

		if (!empty($possible_player_array)) {
			$random_key = array_rand($possible_player_array);
			$choosen_player = $possible_player_array[$random_key];
		}

		// Step 9: Create fields for 'left' and 'right' position to search for teammate
		$possible_players_L1 = array();
		$possible_players_P1 = array();
		$possible_players_LP1 = array();
		$possible_opponents_list1 = array();

		foreach ($statistics_temp as $player) {
			if ($player['player_id'] == $choosen_player) {
				$possible_players_L1 = $player['playmates_L'];
				$possible_players_P1 = $player['playmates_P'];
				$possible_players_LP1 = $player['playmates'];
				$possible_opponents_list1 = $player['opponents'];
				break;
			}
		}

		// Step 10: If two_special_group is on and the player is in the special_group field, remove him
		if ($two_special_group == 1 && in_array($choosen_player, $special_group)) {
			$possible_players_L1 = array_filter($possible_players_L1, function ($player) use ($special_group) {
				return !in_array($player['player_id'], $special_group);
			});

			$possible_players_P1 = array_filter($possible_players_P1, function ($player) use ($special_group) {
				return !in_array($player['player_id'], $special_group);
			});
			$possible_players_LP1 = array_filter($possible_players_LP1, function ($player) use ($special_group) {
				return !in_array($player['player_id'], $special_group);
			});
		}

		// Step 11: If two_out_group is on and both players are out of special_group, then remove that player
		if ($two_out_group == 1 && !in_array($choosen_player, $special_group)) {
			$possible_players_L1 = array_filter($possible_players_L1, function ($player) use ($special_group) {
				return in_array($player['player_id'], $special_group);
			});

			$possible_players_P1 = array_filter($possible_players_P1, function ($player) use ($special_group) {
				return in_array($player['player_id'], $special_group);
			});
			$possible_players_LP1 = array_filter($possible_players_LP1, function ($player) use ($special_group) {
				return in_array($player['player_id'], $special_group);
			});
		}

		// Step 12: Filter out inactive players
		$possible_players_L1 = array_filter($possible_players_L1, function ($player) use ($activePlayers) {
			return in_array($player['player_id'], $activePlayers);
		});

		$possible_players_P1 = array_filter($possible_players_P1, function ($player) use ($activePlayers) {
			return in_array($player['player_id'], $activePlayers);
		});
		$possible_players_LP1 = array_filter($possible_players_LP1, function ($player) use ($activePlayers) {
			return in_array($player['player_id'], $activePlayers);
		});

		//Step 12.1 display a notice that there is a round end
		if ($announce_round_end > 0 && $announce_round_end < 3) {
			if (!isset($all_same_count)) {
				$all_same_count = true;
			}
			$first_count = 0;

			if (count($possible_players_LP1) == 0) {
				$all_same_count = true;
			} else {
				foreach ($possible_players_LP1 as $player) {
					if ($first_count == 0) {
						$first_count = $player['count'];
					}

					if (($player['count'] != $first_count) ||
						($announce_round_end == 2 && ($player['count'] % 2 != 0 || $first_count % 2 != 0)) ||
						($player['count'] == 0 && $first_count == 0)
					) {
						$all_same_count = false;
						break;
					}
				}
			}

			if ($all_same_count && $first_count != 0) {
				$output = doroto_notice_round_end($tournament_id, $tournament, $choosen_player);
				return $output;
			}
		}

		// Step 13: Filter the players who are occupied
		$possible_players_L1 = array_filter($possible_players_L1, function ($player) use ($playing) {
			return !in_array($player['player_id'], $playing);
		});
		$possible_players_P1 = array_filter($possible_players_P1, function ($player) use ($playing) {
			return !in_array($player['player_id'], $playing);
		});
		$possible_players_LP1 = array_filter($possible_players_LP1, function ($player) use ($playing) {
			return !in_array($player['player_id'], $playing);
		});

		// Step 14: Check if a suitable teammate can be found and Line up players for 'left' and 'right' position
		if (count($possible_players_LP1) == 0) {
			$output = "<p><b><div class = 'doroto-warning-text'>" . esc_html__('The tournament settings do not allow the next match to be drawn.', 'doubles-rotation-tournament') . "</b></div> ";
			$output .= doroto_empty_special_group_notice($tournament);
			$output .= "</p>";
			return $output;
		}

		usort($possible_players_LP1, function ($a, $b) {
			return $a['count'] - $b['count'];
		});

		// Step 15: Determine the player with the fewest matches
		$firstItem_playmate = reset($possible_players_LP1);
		$firstItem_playmate_count = $firstItem_playmate['count'];
		$firstItem_playmate_player_id = $firstItem_playmate['player_id'];

		// Step 15.1: Check if the player has rest combinations
		if ($announce_round_end > 0) {
			foreach ($statistics_temp as $player) {
				if ($player['player_id'] == $firstItem_playmate_player_id && $player['rest'] == 0 && $player['games'] != 0) {
					$output = doroto_notice_round_end($tournament_id, $tournament, $firstItem_playmate_player_id);
					return $output;
				}
			}
		}

		// Step 16: Select another player with the same number of co-matches played
		$possible_playmates_together = [];
		foreach ($possible_players_LP1 as $player) {
			if ($player['count'] <= $firstItem_playmate_count) {
				$possible_playmates_together[] = $player['player_id'];
			}
		}


		// Step 17: Select other players with the same and more rest combinations and randomly select someone from them
		foreach ($statistics_temp as $player) {
			if ($player['player_id'] == $firstItem_playmate_player_id) {
				$firstItem_playmate_player_total_count = $player['rest'];
			}
		}

		$possible_co_player_array = [];
		foreach ($statistics_temp as $player) {
			if ($player['rest'] >= $firstItem_playmate_player_total_count && $choosen_player != $player['player_id']) {
				$possible_co_player_array[] = $player['player_id'];
			}
		}

		// Step 18: merge both different fields
		$intersection = array_intersect($possible_playmates_together, $possible_co_player_array);

		usort($statistics_temp, function ($a, $b) {
			return $b['rest'] - $a['rest'];
		});

		$choosen_co_player = $firstItem_playmate_player_id;

		if (!empty($intersection)) {
			foreach ($statistics_temp as $player) {
				if (in_array($player['player_id'], $intersection)) {
					$choosen_co_player = $player['player_id'];
					break;
				}
			}
		}

		// Step 18.1: in doubles, choose the teammate together with the opposing team
		// (see doroto_choose_opposing_team()), so a teammate is preferred whose match
		// brings together players who have not met yet.
		$opposing_team = null;
		if ($players_on_court == 4) {
			$co_candidates = !empty($intersection) ? $intersection : $possible_playmates_together;
			$best_key = null;
			foreach ($co_candidates as $co_candidate) {
				$co_row = null;
				foreach ($statistics_temp as $player) {
					if ($player['player_id'] == $co_candidate) {
						$co_row = $player;
						break;
					}
				}
				if ($co_row === null) {
					continue;
				}
				$others = array_filter($statistics_temp, function ($player) use ($choosen_player, $co_candidate) {
					return $player['player_id'] != $choosen_player && $player['player_id'] != $co_candidate;
				});
				$result = doroto_choose_opposing_team(
					array_values($others),
					intval($choosen_player),
					intval($co_candidate),
					$special_group,
					$two_special_group,
					$two_out_group,
					$announce_round_end
				);
				if ($result === null) {
					continue;
				}
				// Add the teammate's own share to the opposing team's score.
				$key = $result['key'];
				if ($announce_round_end > 0 && intval($co_row['rest']) == 0 && intval($co_row['games']) != 0) {
					$key[1]++;
				}
				$key[2] += intval($co_row['games']);
				$key[4] -= intval($co_row['rest']);
				if ($best_key === null || $key < $best_key) {
					$best_key = $key;
					$choosen_co_player = $co_candidate;
					$opposing_team = $result['team'];
				}
			}
		}

		//Step 19: define first_possible_players_L and first_possible_players_P
		foreach ($possible_players_L1 as $player) {
			if ($player['player_id'] == $choosen_co_player) {
				$first_possible_players_L = $player['count'];
				break;
			}
		}
		foreach ($possible_players_P1 as $player) {
			if ($player['player_id'] == $choosen_co_player) {
				$first_possible_players_P = $player['count'];
				break;
			}
		}

		//Step 20: the left or right position will be random if the teammate has the same number of positions
		if ($first_possible_players_L == $first_possible_players_P) {

			foreach ($statistics_temp as $player) {
				if ($player['player_id'] == $choosen_player) {
					break;
				}
			}
			$position_co_player_cnt_L = 0;
			foreach ($player['playmates_L'] as $co_players_on_L) {
				$position_co_player_cnt_L += $co_players_on_L['count'];
			}
			$position_co_player_cnt_P = 0;
			foreach ($player['playmates_P'] as $co_players_on_P) {
				$position_co_player_cnt_P += $co_players_on_P['count'];
			}

			if ($position_co_player_cnt_L ==  $position_co_player_cnt_P) {
				$randomNumber = wp_rand(0, 1) % 2;
				if ($randomNumber) {
					$first_possible_players_L++;
				} else {
					$first_possible_players_P++;
				}
			} else {
				$first_possible_players_L = $position_co_player_cnt_L;
				$first_possible_players_P = $position_co_player_cnt_P;
			}
		}

		if ($first_possible_players_L > $first_possible_players_P) {
			$both_possible_players = $possible_players_P1;
		} else {
			$both_possible_players = $possible_players_L1;
		}

		if ($first_possible_players_L > $first_possible_players_P) {
			$player_1 = $choosen_player;
			$player_2 = $choosen_co_player;
		} else {
			$player_2 = $choosen_player;
			$player_1 = $choosen_co_player;
		}

		if ($players_on_court == 4) {
			// Doubles: the same side rule as for the opposing team, which compares
			// both players instead of only the chosen one.
			$choosen_row = null;
			$co_row = null;
			foreach ($statistics_temp as $player) {
				if ($player['player_id'] == $choosen_player) {
					$choosen_row = $player;
				} elseif ($player['player_id'] == $choosen_co_player) {
					$co_row = $player;
				}
			}
			if ($choosen_row !== null && $co_row !== null) {
				[$player_1, $player_2] = doroto_team_sides($choosen_row, $co_row);
			}

			// Steps 21-48: the opposing team was chosen in step 18.1. The old
			// step-by-step choice ignored previous meetings in most cases, so with
			// two courts the same players kept meeting each other.
			if ($opposing_team === null) {
				$output = "<p><b><div class = 'doroto-warning-text'>" . esc_html__('The tournament settings do not allow the next match to be drawn.', 'doubles-rotation-tournament') . "</b></div> ";
				$output .= doroto_empty_special_group_notice($tournament);
				$output .= "</p>";
				return $output;
			}
			$player_3 = $opposing_team[0];
			$player_4 = $opposing_team[1];
		}

		// Step 49: Find who will serve on P1 position
		$player_2_cnt = 0;
		$player_4_cnt = 0;

		if ($players_on_court == 2) {
			$player_4 = $player_1;
		}

		foreach ($matches_list as $match) {
			if ($match['player_2'] == $player_2 && $match['hide'] != 1) {
				$player_2_cnt++;
			}
			if ($match['player_2'] == $player_4 && $match['hide'] != 1) {
				$player_4_cnt++;
			}
		}

		$loops = 0;
		foreach ($statistics as $player) {
			if ($player['player_id'] == $player_2) {
				$player_2_games = $player['games'];
				if ($player_2_games == 0) $player_2_games = 1;
				$loops++;
			}
			if ($player['player_id'] == $player_4) {
				$player_4_games = $player['games'];
				if ($player_4_games == 0) $player_4_games = 1;
				$loops++;
			}
			if ($loops >= 2) break;
		}

		if (abs($player_2_games - $player_4_games) <= 1) {
			$player_2_games = 1;
			$player_4_games = 1;
		}

		$randomNumber = random_int(0, 1);
		if (($player_2_cnt / $player_2_games) > ($player_4_cnt / $player_4_games) || (($player_2_cnt / $player_2_games) == ($player_4_cnt / $player_4_games) && $randomNumber == 1)) {
			$temp_player_P = $player_2;
			$player_2 = $player_4;
			$player_4 = $temp_player_P;

			if ($players_on_court == 4) {
				$temp_player_L = $player_1;
				$player_1 = $player_3;
				$player_3 = $temp_player_L;
			}
		}

		// Step 50: Add the player to the $playing array
		if ($players_on_court == 2) {
			$player_1 = $player_4;
			//$player_2 = $player_2;
			$player_3 = 0;
			$player_4 = 0;
		}

		$playing[] = $player_1;
		$playing[] = $player_2;
		if ($players_on_court == 4) {
			$playing[] = $player_3;
			$playing[] = $player_4;
		}

		// Step 50.1: Check if players have rest combinations
		if ($announce_round_end > 0) {
			foreach ($statistics as $player) {
				if ($player['rest'] == 0 && in_array($player['player_id'], $playing) && $player['games'] != 0) {
					$output = doroto_notice_round_end($tournament_id, $tournament, $player['player_id']);
					return $output;
				}
			}
		}

		// Step 51: Create a field for a new match
		$match = array(
			'match_number' => count($matches_list),
			'player_1' => $player_1,
			'player_2' => $player_2,
			'player_3' => $player_3,
			'player_4' => $player_4,
			'played' => 1,
			'hide' => 0,
			'result_1' => 0,
			'result_2' => 0
		);

		// Step 52 + 53: Save the $playing array and the new match in one write
		$matches_list[] = $match;
		$wpdb->update($wpdb->prefix . 'doroto_tournaments', array(
			'playing' => serialize($playing),
			'matches_list' => serialize($matches_list),
			'last_update'  => doroto_now_ms()
		), array('id' => $tournament_id));

		// Step 54: Repeat all steps as needed
		$matches_to_select--;
		if ($matches_to_select > 0) {
			doroto_offer_games_locked($tournament_id, $matches_to_select); // We recursively call the function for the next matches
		}
	}
	return '';
}

/**
 * Choose the opposing team for a drawn first team ($player_1 on the left, $player_2 on the right).
 * Every pair of free players that the special group settings allow is scored; lower is better:
 *   1. how often the two played together (new teammates first),
 *   2. with a round-end announcement: how many of them have no rest combinations left,
 *   3. games played by both (players who waited longer first),
 *   4. previous meetings with the first team (sum of squares, so meeting the same
 *      player a third time weighs more than two different repeats),
 *   5. rest combinations (more first), then random.
 * The player who played more often on the right side goes to the left and vice versa.
 * @since 1.6.2
 * @return array|null ['team' => [left id, right id], 'key' => score], or null when no pair is allowed
 */
function doroto_choose_opposing_team(array $candidates, int $player_1, int $player_2, array $special_group, int $two_special_group, int $two_out_group, int $announce_round_end)
{
	$special = array_map('intval', $special_group);

	// player_id => [list => [other_id => count]] for quick lookups
	$counts = [];
	foreach ($candidates as $player) {
		$id = intval($player['player_id']);
		foreach (['playmates', 'opponents'] as $list) {
			$counts[$id][$list] = [];
			if (!empty($player[$list]) && is_array($player[$list])) {
				foreach ($player[$list] as $entry) {
					$counts[$id][$list][intval($entry['player_id'])] = intval($entry['count'] ?? 0);
				}
			}
		}
	}
	$count = function (int $id, string $list, int $other) use ($counts) {
		return $counts[$id][$list][$other] ?? 0;
	};

	$best = null;
	$best_key = null;
	$total = count($candidates);
	for ($i = 0; $i < $total; $i++) {
		for ($j = $i + 1; $j < $total; $j++) {
			$a = $candidates[$i];
			$b = $candidates[$j];
			$id_a = intval($a['player_id']);
			$id_b = intval($b['player_id']);
			$a_special = in_array($id_a, $special, true);
			$b_special = in_array($id_b, $special, true);
			if ($two_special_group == 1 && $a_special && $b_special) {
				continue;
			}
			if ($two_out_group == 1 && !$a_special && !$b_special) {
				continue;
			}

			$no_rest = 0;
			if ($announce_round_end > 0) {
				foreach ([$a, $b] as $player) {
					if (intval($player['rest']) == 0 && intval($player['games']) != 0) {
						$no_rest++;
					}
				}
			}

			$meetings = 0;
			foreach ([$id_a, $id_b] as $id) {
				foreach ([$player_1, $player_2] as $opponent) {
					$meetings += $count($id, 'opponents', $opponent) ** 2;
				}
			}

			$key = [
				$count($id_a, 'playmates', $id_b),
				$no_rest,
				intval($a['games']) + intval($b['games']),
				$meetings,
				-(intval($a['rest']) + intval($b['rest'])),
				wp_rand(0, 1000000),
			];
			if ($best_key === null || $key < $best_key) {
				$best_key = $key;
				$best = [$a, $b];
			}
		}
	}

	if ($best === null) {
		return null;
	}
	return ['team' => doroto_team_sides($best[0], $best[1]), 'key' => $best_key];
}

/**
 * Put the two players of a team on the left and right side, alternating the sides.
 * @since 1.6.2
 * @return array [left player id, right player id]
 */
function doroto_team_sides(array $a, array $b)
{
	$id_a = intval($a['player_id']);
	$id_b = intval($b['player_id']);
	$count = function (array $player, string $list, int $other) {
		foreach ((array) ($player[$list] ?? []) as $entry) {
			if (intval($entry['player_id']) == $other) {
				return intval($entry['count'] ?? 0);
			}
		}
		return 0;
	};

	// Alternate sides within this pair first: playmates_L counts how often the
	// teammate stood on the left, so the one who was on the left goes right now.
	$b_was_left = $count($a, 'playmates_L', $id_b);
	$b_was_right = $count($a, 'playmates_P', $id_b);
	if ($b_was_left != $b_was_right) {
		return $b_was_left > $b_was_right ? [$id_a, $id_b] : [$id_b, $id_a];
	}

	// Then overall: right minus left appearances (teammate on the left = I was on the right).
	$side_balance = function (array $player) {
		$balance = 0;
		foreach ((array) ($player['playmates_L'] ?? []) as $entry) {
			$balance += intval($entry['count'] ?? 0);
		}
		foreach ((array) ($player['playmates_P'] ?? []) as $entry) {
			$balance -= intval($entry['count'] ?? 0);
		}
		return $balance;
	};
	$balance_a = $side_balance($a);
	$balance_b = $side_balance($b);
	if ($balance_a == $balance_b) {
		return wp_rand(0, 1) ? [$id_a, $id_b] : [$id_b, $id_a];
	}
	return $balance_a > $balance_b ? [$id_a, $id_b] : [$id_b, $id_a];
}


/**
 * create or upgrade wp database for DoRoTo
 * dbDelta() creates the table when missing and adds missing columns otherwise.
 * @version 1.6.0 (always run dbDelta so upgraded sites get new columns)
 * @since 1.0.0
 */
function doroto_create_tournaments_table()
{
	global $wpdb;
	ob_start();

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	{
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
			name text DEFAULT '',
			open_registration BOOLEAN DEFAULT 1,
			close_tournament BOOLEAN DEFAULT 0,
			admin_users text DEFAULT '',
			average_result INT UNSIGNED DEFAULT 10,
			max_players INT UNSIGNED DEFAULT 0,
			minimum_matches TINYINT DEFAULT 3,
			temp_suspend_winner BOOLEAN DEFAULT 1,
			special_group_can_win BOOLEAN DEFAULT 1,
			two_special_group BOOLEAN DEFAULT 1,
			two_out_group BOOLEAN DEFAULT 0,
			allow_input_results BOOLEAN DEFAULT 1,
			play_final_match BOOLEAN DEFAULT 1,
			final_result text DEFAULT '',
			whole_names BOOLEAN DEFAULT 0,
			courts_available INT UNSIGNED DEFAULT 2,
			min_not_playing INT UNSIGNED DEFAULT 0,
			final_four text DEFAULT '',
			players text DEFAULT '',
			playing text DEFAULT '',
			special_group text DEFAULT '',
			statistics mediumtext DEFAULT '',
      matches_list longtext DEFAULT '',
			create_date datetime DEFAULT '9999-09-09 09:09:09',
			close_date datetime DEFAULT '9999-09-09 09:09:09',
			page_id INT,
			invitation mediumtext DEFAULT '',
			payment_display BOOLEAN DEFAULT 0,
			payment_done text DEFAULT '',
			tournament_type TINYINT DEFAULT 21,
			announce_round_end TINYINT DEFAULT 0,
			planned_combinations INT UNSIGNED DEFAULT 0,
			rest_combinations INT UNSIGNED DEFAULT 0,
			games_hour INT UNSIGNED DEFAULT 15,
			latitude DOUBLE(10,7) DEFAULT NULL,
		  longitude DOUBLE(10,7) DEFAULT NULL,
		  visibility TINYINT(1) DEFAULT 1,
			last_update BIGINT(20) DEFAULT 0,
      PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	ob_end_clean();
}


/**
 * create a new tournament
 * @version 1.4.7 (add location and visibility)
 * @since 1.0.0
 */
function doroto_insert_tournament(int $tournament_type)
{
	global $wpdb;
	ob_start();
	$table_name = $wpdb->prefix . 'doroto_tournaments';
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		doroto_create_tournaments_table();
	}

	$current_user = wp_get_current_user();
	$admin_users[] = intval($current_user->ID);

	$options = doroto_tournament_types();
	$allowed_html = doroto_allowed_html();
	$tournament_short_name = sanitize_text_field($options[$tournament_type]);
	$invitation = wp_kses(__('I invite you to', 'doubles-rotation-tournament') . ' <b>' . ' ' . $tournament_short_name . ' ' . __('Rotation Tournament!', 'doubles-rotation-tournament') . '</b>', $allowed_html);
	// Site time zone: the UTC time in the name confused organizers (12:56 showed as 10-56).
	$name = sanitize_text_field($tournament_short_name . ' ' . wp_date('d-H-i'));

	$last_tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE admin_users LIKE %s ORDER BY id DESC LIMIT 1",
		'%' . $wpdb->esc_like('i:' . $current_user->ID . ';') . '%'
	));


	$special_group = [];
	$players = [];
	$payment_done = [];
	$statistics = [];

	$last_tournament_type = intval($last_tournament->tournament_type);
	$doroto_settings = get_option('doroto_settings');
	$type_variables = doroto_types_variables();
	$average_result = 10;
	$games_hour = 10;
	$games_hour = 10;

	foreach ($type_variables as $value => $name_type) {
		if ($tournament_type == $value) {
			if (doroto_read_settings($name_type . '_score', 10)) {
				$average_result = $doroto_settings[$name_type . '_score'];
			}
			if (doroto_read_settings($name_type . '_hour', 10)) {
				$games_hour = $doroto_settings[$name_type . '_hour'];
			}
		}
	}
	if ($last_tournament_type == $tournament_type) {
		$average_result = intval($last_tournament->average_result);
		$games_hour = intval($last_tournament->games_hour);
	}

	if ($last_tournament) {
		$wpdb->insert(
			$table_name,
			array(
				'admin_users' => serialize($admin_users),
				'create_date' => gmdate('Y-m-d H:i:s'),
				'name' => sanitize_text_field($name),
				'average_result' => intval($average_result),
				'max_players' => intval($last_tournament->max_players),
				'minimum_matches' => intval($last_tournament->minimum_matches),
				'temp_suspend_winner' => intval($last_tournament->temp_suspend_winner),
				'special_group_can_win' => intval($last_tournament->special_group_can_win),
				'two_special_group' => intval($last_tournament->two_special_group),
				'two_out_group' => intval($last_tournament->two_out_group),
				'allow_input_results' => intval($last_tournament->allow_input_results),
				'play_final_match' => intval($last_tournament->play_final_match),
				'whole_names' => intval($last_tournament->whole_names),
				'courts_available' => intval($last_tournament->courts_available),
				'min_not_playing' => intval($last_tournament->min_not_playing),
				'special_group' => serialize($special_group),
				'statistics' => serialize($statistics),
				'players' => serialize($players),
				'invitation' => $invitation,
				'payment_display' => intval($last_tournament->payment_display),
				'tournament_type' => intval($tournament_type),
				'announce_round_end' => intval($last_tournament->announce_round_end),
				'games_hour' => intval($games_hour),
				'visibility' => intval($last_tournament->visibility),
				'longitude' => floatval($last_tournament->longitude),
				'latitude' => floatval($last_tournament->latitude)
			)
		);
	} else {
		$wpdb->insert(
			$table_name,
			array(
				'admin_users' => serialize($admin_users),
				'create_date' => gmdate('Y-m-d H:i:s'),
				'name' => sanitize_text_field($name),
				'average_result' => intval($average_result),
				'special_group' => serialize($special_group),
				'players' => serialize($players),
				'statistics' => serialize($statistics),
				'invitation' => $invitation,
				'payment_done' => serialize($payment_done),
				'tournament_type' => intval($tournament_type),
				'games_hour' => intval($games_hour)
			)
		);
	}
	return $wpdb->insert_id;
}


/**
 * match number '0' for right array setting
 * @since 1.0.0
 */
function doroto_generate_fake_match(array|string $players)
{
	$matches = array();
	$players = array_values(maybe_unserialize($players));
	$match = array(
		'match_number' => 0,
		'player_1' => $players[0],
		'player_2' => $players[1],
		'player_3' => $players[2],
		'player_4' => $players[3],
		'played' => 0,
		'hide' => 1,
		'result_1' => 0,
		'result_2' => 0
	);
	$matches[] = $match;
	return $matches;
}


/**
 * find match by id
 * @since 1.0.0
 */
function doroto_get_match_by_id(int $match_id)
{
	global $wpdb;

	$table_name = $wpdb->prefix . 'doroto_tournaments';

	$result = $wpdb->get_var($wpdb->prepare(
		"SELECT matches_list FROM $table_name WHERE ID = %d",
		$match_id
	));

	if ($result !== null) {
		$array = explode(", ", trim($result, "{}"));
		$output = array(
			"ID" => (int) $array[0],
			"P1" => $array[1],
			"P2" => $array[2],
			"P3" => $array[3],
			"P4" => $array[4],
			"played" => (int) $array[5],
			"hide" => (int) $array[6],
			"result1" => (int) $array[7],
			"result2" => (int) $array[8]
		);

		return $output;
	}
}


/**
 * create an array with players statistics
 * @since 1.0.0
 * @version 1.4.7 (optimalization to reduce spent time)
 */
function doroto_create_statistics_table(?stdClass $tournament, array|string $players, bool $whole_names)
{
	global $wpdb;

	if (empty($players)) {
		return array();
	}

	// Normalize IDs to int: the strict comparison below used to treat "12" and 12
	// as different players and created duplicate statistics rows.
	$players = array_values(array_map('intval', (array) maybe_unserialize($players)));
	$statistics = maybe_unserialize($tournament->statistics);
	$statistics = empty($statistics) ? array() : doroto_add_player_statistics_table($statistics, $tournament, $players);

	$users_query = get_users(['include' => $players]);

	$users_by_id = [];
	foreach ($users_query as $user_obj) {
		$users_by_id[$user_obj->ID] = $user_obj;
	}

	foreach ($players as $player_id) {
		$existing_player = false;
		foreach ($statistics as $existing) {
			if (intval($existing['player_id']) === $player_id) {
				$existing_player = true;
				break;
			}
		}

		if (!$existing_player) {
			if (isset($users_by_id[$player_id])) {
				$user = $users_by_id[$player_id];
			} else {
				$user = new stdClass();
				$user->display_name = sanitize_text_field(__('Unknown player', 'doubles-rotation-tournament'));
				$user->ID = intval($player_id);
			}

			$playmates = array();
			foreach ($players as $p_inner) {
				if ($player_id == $p_inner) {
					continue;
				}
				$playmates[] = ['player_id' => $p_inner, 'count' => 0];
			}

			$player_info = array(
				'player_id' => $player_id,
				'active' => '1',
				'display_name' => $whole_names ? sanitize_text_field($user->display_name) : sanitize_text_field(doroto_display_short_name($user->display_name)),
				'games' => 0,
				'won' => 0,
				'lost' => 0,
				'ratio' => 0,
				'playmates' => $playmates,
				'playmates_L' => $playmates,
				'playmates_P' => $playmates,
				'opponents' => $playmates,
				'rest' => 0,
			);
			$statistics[] = $player_info;
		}
	}

	usort($statistics, function ($a, $b) {
		return $b['ratio'] <=> $a['ratio'];
	});

	return $statistics;
}




/**
 * remove a player from Statistic table
 * @since 1.1.8
 * @version 1.2.5
 */
function doroto_remove_player_from_statistics_table(?stdClass $tournament, array|string|bool $statistics, int $remove_player_id)
{
	global $wpdb;
	$players = $tournament->players;
	if (empty($players)) {
		$statistics = array();
		return $statistics;
	}
	$statistics_new = [];
	$permission_remove = true;
	foreach ($statistics as $player_info) {
		if ($player_info['player_id'] == $remove_player_id && $player_info['games'] > 0) {
			return $statistics;
		}

		if ($player_info['player_id'] != $remove_player_id) {

			$playmates_new = [];
			foreach ($player_info['playmates'] as $playmates_player) {
				if ($playmates_player['player_id'] != $remove_player_id) {
					$playmates_new[] = $playmates_player;
				}
			}

			$playmates_L_new = [];
			foreach ($player_info['playmates_L'] as $playmates_L_player) {
				if ($playmates_L_player['player_id'] != $remove_player_id) {
					$playmates_L_new[] = $playmates_L_player;
				}
			}

			$playmates_P_new = [];
			foreach ($player_info['playmates_P'] as $playmates_P_player) {
				if ($playmates_P_player['player_id'] != $remove_player_id) {
					$playmates_P_new[] = $playmates_P_player;
				}
			}

			$opponents_new = [];
			foreach ($player_info['opponents'] as $opponents_player) {
				if ($opponents_player['player_id'] != $remove_player_id) {
					$opponents_new[] = $opponents_player;
				}
			}

			$player_info = array(
				'player_id' => $player_info['player_id'],
				'active' => $player_info['active'],
				'display_name' => $player_info['display_name'],
				'games' => $player_info['games'],
				'won' => $player_info['won'],
				'lost' => $player_info['lost'],
				'ratio' => $player_info['ratio'],
				'playmates' => $playmates_new,
				'playmates_L' => $playmates_L_new,
				'playmates_P' => $playmates_P_new,
				'opponents' => $opponents_new,
			);
			$statistics_new[] = $player_info;
		}
	}

	usort($statistics_new, function ($a, $b) {
		return $b['ratio'] <=> $a['ratio'];
	});

	return $statistics_new;
}


/**
 * definition of global variable at the beginning
 * @since 1.0.0
 */
function doroto_define_permanent_tournament_id()
{
	global $doroto_pernament_tournament_id;
	$doroto_pernament_tournament_id = 0;
}

add_action('wp_loaded', 'doroto_define_permanent_tournament_id');


/**
 * create a new tournament after submitting form
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_add_tournament; works when no tournament exists yet)
 */
function doroto_add_tournament_result()
{
	if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_add_tournament_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}
	$type = isset($_POST['doroto_add_tournament_tournament_type']) ? intval($_POST['doroto_add_tournament_tournament_type']) : -1;
	$result = doroto_service_add_tournament($type);
	$tournament_id = is_wp_error($result) ? intval(doroto_getTournamentId()) : intval($result['tournament_id']);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('admin_post_doroto_add_tournament_save', 'doroto_add_tournament_result');
add_action('admin_post_nopriv_doroto_add_tournament_save', 'doroto_add_tournament_result');


/**
 * select 4 finalists
 * @since 1.0.0
 * @version 1.2.0
 */
function doroto_select_final_doubles(array $player_results, ?stdClass $tournament, WP_User $current_user)
{
	$output = '';
	$current_user_id = intval($current_user->ID);

	$tournament_id = intval($tournament->id);

	if (doroto_is_admin($tournament_id) > 0) {
		$output .= '<p>' . esc_html__('Confirm the final pairings for the match that will determine the winning team.', 'doubles-rotation-tournament');
		$output .= "<div class='doroto-table-responsive'>";
		$output .= "<table class='doroto-table'>";
		$output .= "<tr><th>" . esc_html__("L1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("L2", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R2", "doubles-rotation-tournament") . "</th></tr>";

		$output .= '<form method="post" action="">';
		$output .= "<tr>";
		$output .= "<td>";
		$output .= '<select name="l1" id="l1">';
		foreach ($player_results as $player) {
			$selected = ($player['player_id'] == $player_results[0]['player_id']) ? 'selected' : '';
			$output .= '<option value="' . esc_attr($player['player_id']) . '" ' . $selected . '>' . esc_html(doroto_find_player_name($player['player_id'], $tournament->whole_names)) . '</option>';
		}
		$output .= '</select></td>';

		$output .= "<td>";
		$output .= '<select name="p1" id="p1">';
		foreach ($player_results as $player) {
			$selected = ($player['player_id'] == $player_results[1]['player_id']) ? 'selected' : '';
			$output .= '<option value="' . esc_attr($player['player_id']) . '" ' . $selected . '>' . esc_html(doroto_find_player_name($player['player_id'], $tournament->whole_names)) . '</option>';
		}
		$output .= '</select></td>';

		$output .= "<td>";
		$output .= '<select name="l2" id="l2">';
		foreach ($player_results as $player) {
			$selected = ($player['player_id'] == $player_results[2]['player_id']) ? 'selected' : '';
			$output .= '<option value="' . esc_attr($player['player_id']) . '" ' . $selected . '>' . esc_html(doroto_find_player_name($player['player_id'], $tournament->whole_names)) . '</option>';
		}
		$output .= '</select></td>';

		$output .= "<td>";
		$output .= '<select name="p2" id="p2">';
		foreach ($player_results as $player) {
			$selected = ($player['player_id'] == $player_results[3]['player_id']) ? 'selected' : '';
			$output .= '<option value="' . esc_attr($player['player_id']) . '" ' . $selected . '>' . esc_html(doroto_find_player_name($player['player_id'], $tournament->whole_names)) . '</option>';
		}
		$output .= '</select></td>';
		$output .= '</tr></table></div>';
		$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
		$output .= wp_nonce_field('doroto_final_doubles_' . $tournament_id, 'doroto_final_doubles_nonce', true, false);
		$output .= '<input type="submit" name="final_doubles" value="' . esc_html__("Save the composition of the final group", "doubles-rotation-tournament") . '">';
		$output .= '</form></p>';
	} else {
		$output .= '<p>' . esc_html__('You do not have permission to make a final group.', 'doubles-rotation-tournament') . '</p>';
	}
	return $output;
}


/**
 * save the final pairs after submitting the form (the form posts to the page itself)
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_set_final_four, redirect after saving)
 */
function doroto_save_final_doubles()
{
	if (!isset($_POST['final_doubles']) || !isset($_POST['tournament_id'])) {
		return '';
	}
	$tournament_id = intval($_POST['tournament_id']);
	$nonce = isset($_POST['doroto_final_doubles_nonce']) ? sanitize_text_field(wp_unslash($_POST['doroto_final_doubles_nonce'])) : '';
	if (!wp_verify_nonce($nonce, 'doroto_final_doubles_' . $tournament_id)) {
		$result = doroto_service_error('not_admin_permission', 403);
	} else {
		$result = doroto_service_set_final_four(
			$tournament_id,
			isset($_POST['l1']) ? intval($_POST['l1']) : 0,
			isset($_POST['p1']) ? intval($_POST['p1']) : 0,
			isset($_POST['l2']) ? intval($_POST['l2']) : 0,
			isset($_POST['p2']) ? intval($_POST['p2']) : 0
		);
	}
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('init', 'doroto_save_final_doubles'); //template_redirect


/**
 * result final match
 * @since 1.0.0
 * @version 1.3.6 (results input modification)
 */
function doroto_result_final_doubles($player_results, ?stdClass $tournament, WP_User $current_user)
{
	$output = '';
	$current_user_id = intval($current_user->ID);

	$admin_users = maybe_unserialize($tournament->admin_users);
	$double_players = maybe_unserialize($tournament->final_four);
	$whole_names = intval($tournament->whole_names);

	$players = maybe_unserialize($tournament->final_four);
	$tournament_id = intval($tournament->id);
	$final_result = maybe_unserialize($tournament->final_result);
	$allow_input_results = intval($tournament->allow_input_results);

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group)) {
		$special_group = [];
	}

	$output .= '<p>';
	$output .= "<div class='doroto-table-responsive'>";
	$output .= "<table class='doroto-table'>";
	$output .= "<tr class = 'doroto-left-aligned'><th>" . esc_html__("L1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R1", "doubles-rotation-tournament") . "</th><th>" . esc_html__("L2", "doubles-rotation-tournament") . "</th><th>" . esc_html__("R2", "doubles-rotation-tournament") . "</th><th>" . esc_html__("Result", "doubles-rotation-tournament") . "</th>";

	if (empty($final_result)) {
		$output .= "<th>" . esc_html__("Save", "doubles-rotation-tournament") . "</th>";
	}
	$output .= "</tr>";
	$output .= "<tr>";

	if (in_array($double_players['l1'], $special_group)) {
		$output .= '<td class="doroto-special-group-text">';
	} else {
		$output .= "<td>";
	}

	$output .= esc_html(doroto_find_player_name($double_players['l1'], $whole_names));
	$output .= '</td>';

	if (in_array($double_players['p1'], $special_group)) {
		$output .= '<td class="doroto-special-group-text">';
	} else {
		$output .= "<td>";
	}

	$output .= esc_html(doroto_find_player_name($double_players['p1'], $whole_names));
	$output .= '</td>';

	if (in_array($double_players['l2'], $special_group)) {
		$output .= '<td class="doroto-special-group-text">';
	} else {
		$output .= "<td>";
	}

	$output .= esc_html(doroto_find_player_name($double_players['l2'], $whole_names));
	$output .= '</td>';

	if (in_array($double_players['p2'], $special_group)) {
		$output .= '<td class="doroto-special-group-text">';
	} else {
		$output .= "<td>";
	}

	$output .= esc_html(doroto_find_player_name($double_players['p2'], $whole_names));
	$output .= '</td>';

	if ((in_array($current_user_id, $players) && $allow_input_results) || (doroto_is_admin($tournament_id) > 0)) {

		$output .= '<td class = "doroto-no-wrap">';
		if (empty($final_result)) {
			$output .= '<form id="doroto_submit_final_result_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

			$output .= '<input type="hidden" name="tournament_id" value="' . esc_attr($tournament_id) . '">';
			$output .= '<input type="hidden" name="action" value="doroto_submit_final_result">';

			$output .= '<select name="final_result_1">';
			for ($cnt = 0; $cnt < 100; $cnt++) {
				$output .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
			}
			$output .= '</select>';

			$output .= ' : ';
			$output .= '<select name="final_result_2">';
			for ($cnt = 0; $cnt < 100; $cnt++) {
				$output .= "<option value='" . esc_attr($cnt) . "'>" . esc_html($cnt) . "</option>";
			}
			$output .= '</select>';

			$output .= '</td><td>';
			$output .= '<input type="submit" value="' . esc_html__('Save', 'doubles-rotation-tournament') . '">';
			$output .= wp_nonce_field('doroto_submit_final_nonce', '_wpnonce', true, false);
			$output .= '</form>';
		} else {
			$output .= esc_html($final_result['result_1'] . ' : ' . $final_result['result_2']);
		}

		$output .= '</td>';
		$output .= '</tr></table></div>';
		$output .= '</p>';
	} else {
		if (!empty($final_result)) {
			$output .= '<td>';
			$output .= esc_html($final_result['result_1'] . ' : ' . $final_result['result_2']);
			$output .= '</td>';
		}
		$output .= '</tr></table></div>';
		$output .= '</p>';
	}
	return $output;
}


/**
 * save the result of the final match after submitting the form
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_set_final_result)
 */
function doroto_update_final_match_result()
{
	if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_submit_final_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}
	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
	$result = doroto_service_set_final_result(
		$tournament_id,
		isset($_POST['final_result_1']) ? intval($_POST['final_result_1']) : -1,
		isset($_POST['final_result_2']) ? intval($_POST['final_result_2']) : -1
	);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('admin_post_doroto_submit_final_result', 'doroto_update_final_match_result');
add_action('admin_post_nopriv_doroto_submit_final_result', 'doroto_update_final_match_result');


/**
 * save final match result 
 * @since 1.0.0
 * @version 1.4.7 (last update info)
 */
function doroto_save_final_result(int $tournament_id, string $final_result)
{
	global $wpdb;
	global $doroto_output_form;
	$tournament_id = intval($tournament_id);

	list($result_1, $result_2) = explode(':', sanitize_text_field($final_result));
	$results_array = array();

	$result_1 = intval($result_1);
	$result_1 = is_numeric($result_1) ? $result_1 : 0;
	$result_2 = intval($result_2);
	$result_2 = is_numeric($result_2) ? $result_2 : 0;
	if (($result_1 < 0) || ($result_2 < 0) || (($result_1 == 0) && ($result_2 == 0))) {
		$doroto_output_form = sanitize_text_field(__('Invalid value entered.', 'doubles-rotation-tournament'));
		return 0;
	}

	$results_array['result_1'] = $result_1;
	$results_array['result_2'] = $result_2;

	$tournament = doroto_prepare_tournament($tournament_id);

	if ($tournament) {
		$wpdb->update("{$wpdb->prefix}doroto_tournaments", [
			'final_result' => serialize($results_array),
			'last_update'  => round(microtime(true) * 1000)
		], ['id' => $tournament_id]);
		return 1;
	} else {
		return 0;
	}
}


/**
 * update selected 4 finalists
 * @since 1.0.0
 * @version 1.4.7 (last update info)
 */
function doroto_update_final_four(?stdClass $tournament, int $l1, int $p1, int $l2, int $p2)
{
	global $wpdb;

	$tournament_id = intval($tournament->id);
	$final_four_data = array(
		'l1' => $l1,
		'p1' => $p1,
		'l2' => $l2,
		'p2' => $p2,
	);
	$table_name = $wpdb->prefix . 'doroto_tournaments';

	$wpdb->update(
		$table_name,
		array(
			'final_four' => serialize($final_four_data),
			'last_update'  => round(microtime(true) * 1000)
		),
		array('id' => $tournament_id)
	);
}


/**
 * update match result after submitting the web form
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_enter_result: the next match is drawn right away)
 */
function doroto_update_match_result()
{
	if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_submit_match_result_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : intval(doroto_getTournamentId());
	$match_number = isset($_POST['match_number']) ? intval($_POST['match_number']) : 0;
	$hide = isset($_POST['hide_' . $match_number]) && intval($_POST['hide_' . $match_number]) === 1;
	$result_1 = isset($_POST['result_1']) ? intval($_POST['result_1']) : 0;
	$result_2 = isset($_POST['result_2']) ? intval($_POST['result_2']) : 0;

	$result = doroto_service_enter_result($tournament_id, $match_number, $result_1, $result_2, $hide);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}


/**
 * saving result, divided for floating help icon
 * @since 1.3.7
 * @version 1.6.0 (tournament lock, fresh read, last_update always written)
 */
function doroto_save_match_result(int $match_number_post, int $tournament_id, ?stdClass $tournament, int $result_1, int $result_2, bool $hide, string &$output, int $last_update, bool $endpoint_request)
{
	doroto_lock_tournament($tournament_id);
	try {
		// Re-read under the lock: the object passed in may be stale if another
		// court saved its result in the meantime.
		$fresh = doroto_prepare_tournament($tournament_id);
		if ($fresh) {
			$tournament = $fresh;
		}
		return doroto_save_match_result_locked($match_number_post, $tournament_id, $tournament, $result_1, $result_2, $hide, $output, $last_update, $endpoint_request);
	} finally {
		doroto_unlock_tournament($tournament_id);
	}
}

/**
 * Body of doroto_save_match_result(); caller must hold the tournament lock.
 * Kept for the old callers (web form, demo data); it turns the result of
 * doroto_store_match_result_locked() into the old $output text / redirect / WP_Error.
 * @since 1.6.0
 * @version 2.0.0 (doroto_store_match_result_locked)
 */
function doroto_save_match_result_locked(int $match_number_post, int $tournament_id, ?stdClass $tournament, int $result_1, int $result_2, bool $hide, string &$output, int $last_update, bool $endpoint_request)
{
	if (!$tournament) {
		return;
	}
	$result = doroto_store_match_result_locked($tournament, $match_number_post, $result_1, $result_2, $hide, $last_update);
	$output = doroto_service_message($result, $tournament_id);
	if (is_wp_error($result) && $result->get_error_code() === 'match_already_entered') {
		if (!$endpoint_request) {
			doroto_info_messsages_save($output);
			doroto_redirect_modify_url($tournament_id, "");
			exit;
		}
		// The app shows this message; it expects code "forbidden" with HTTP 200.
		return new WP_Error('forbidden', esc_html($output), ['status' => 200]);
	}
}

/**
 * Store the result of an open match, or skip it ($hide). Caller must hold the tournament lock
 * and pass a fresh tournament row. Does not draw new matches.
 * @return array|WP_Error doroto_service_ok('match_result_updated', [match_number, result_1, result_2, hidden, last_update])
 *                        or match_already_entered (data: result_1, result_2), match_not_found, tournament_not_scheduled
 * @since 2.0.0 (from doroto_save_match_result_locked)
 */
function doroto_store_match_result_locked(stdClass $tournament, int $match_number, int $result_1, int $result_2, bool $hide, int $last_update = 0)
{
	global $wpdb;

	$tournament_id = intval($tournament->id);
	$announce_round_end = intval($tournament->announce_round_end);
	$matches = maybe_unserialize($tournament->matches_list);
	if (!is_array($matches)) {
		return doroto_service_error('tournament_not_scheduled');
	}

	$found_key = null;
	foreach ($matches as $match_key => $match) {
		if ($match['match_number'] == $match_number) {
			$found_key = $match_key;
			break;
		}
	}
	if ($found_key === null) {
		return doroto_service_error('match_not_found', 404);
	}

	$match = $matches[$found_key];
	if (!($match['result_1'] == 0 && $match['result_2'] == 0 && $match['hide'] == 0)) {
		return new WP_Error('match_already_entered', 'match_already_entered', [
			'status' => 409,
			'match_number' => intval($match['match_number']),
			'result_1' => intval($match['result_1']),
			'result_2' => intval($match['result_2']),
		]);
	}

	$match['played'] = 1;
	if ($hide) {
		$match['result_1'] = 0;
		$match['result_2'] = 0;
		$match['hide'] = 1;
	} else {
		$match['result_1'] = $result_1;
		$match['result_2'] = $result_2;
		$match['hide'] = 0;
		if ($announce_round_end > 2) {
			$announce_round_end -= 2;
		}
		doroto_update_statistics_by_result($tournament_id, $tournament, $match, false, true);
	}
	$matches[$found_key] = $match;

	// The Doroto app reloads only when last_update grows, so it must never be 0.
	if ($last_update == 0) {
		$last_update = doroto_now_ms();
	}
	$data = [
		'matches_list' => serialize($matches),
		'announce_round_end' => $announce_round_end,
		'last_update' => $last_update,
	];
	if ($hide) {
		// A finished match frees its players in doroto_update_statistics_by_result();
		// a skipped one frees them here.
		$players_in_match = [$match['player_1'], $match['player_2'], $match['player_3'], $match['player_4']];
		$playing = maybe_unserialize($tournament->playing);
		$playing = is_array($playing) ? $playing : [];
		foreach ($players_in_match as $player) {
			$key = array_search($player, $playing);
			if ($key !== false) {
				unset($playing[$key]);
			}
		}
		$data['playing'] = serialize(array_values($playing));
	}
	$wpdb->update("{$wpdb->prefix}doroto_tournaments", $data, ['id' => $tournament_id]);

	return doroto_service_ok('match_result_updated', [
		'match_number' => intval($match['match_number']),
		'result_1' => intval($match['result_1']),
		'result_2' => intval($match['result_2']),
		'hidden' => $hide ? 1 : 0,
		'last_update' => $last_update,
	]);
}


/**
 * when a match is finished then update statistics array
 * @since 1.0.0
 * @version 1.4.7 (last update info)
 */
function doroto_update_statistics_by_result(int $tournament_id, ?stdClass $tournament, array $match, bool $correct, bool $remove_players)
{
	global $wpdb;
	$statistics = maybe_unserialize($tournament->statistics);

	$playersInMatch = [$match['player_1'], $match['player_2'], $match['player_3'], $match['player_4']];
	$playing = maybe_unserialize($tournament->playing);

	foreach ($statistics as &$playerData) {
		if (in_array($playerData['player_id'], $playersInMatch)) {
			// a) Increase games by +1
			if (!$correct) {
				$playerData['games']++; //does not increase in case of correction of the result
			} elseif ($match['hide'] == 1) {
				$playerData['games']--;
			}

			// Index for players in the match
			// the index can only be equal to 0,1,2 or 3 depending on the position in the array
			$index = array_search($playerData['player_id'], $playersInMatch);
			if (doroto_check_if_doubles($tournament)) {
				$position_index = 2;
			} else {
				$position_index = 1;
			}

			if ($index !== false) {
				// b) and c) - Updating won and lost according to the results
				if ($index < $position_index) {
					$playerData['won'] += $match['result_1'];
					$playerData['lost'] += $match['result_2'];
				} else {
					$playerData['won'] += $match['result_2'];
					$playerData['lost'] += $match['result_1'];
				}

				// d) Update ratio
				if ($playerData['lost'] != 0) {
					$playerData['ratio'] = $playerData['won'] / $playerData['lost'];
				} else {
					$playerData['ratio'] = $playerData['won'];
				}

				// e) and f) - Update playmates and opponents
				if ($index < 2 && (!$correct || $match['hide'] == 1)) {	//it won't happen if it's a match fix				
					// Update playmates_L
					if ($playersInMatch[0] != $playerData['player_id']) { //teammate on the left
						$playmateIndex = array_search($playersInMatch[0], array_column($playerData['playmates_L'], 'player_id'));
						if ($playmateIndex !== false && !$correct) {
							$playerData['playmates_L'][$playmateIndex]['count']++;
							$playerData['playmates'][$playmateIndex]['count']++;
						} elseif ($playmateIndex !== false && $match['hide'] == 1) {
							$playerData['playmates_L'][$playmateIndex]['count']--;
							$playerData['playmates'][$playmateIndex]['count']--;
						}
					}

					// Update playmates_P
					if ($playersInMatch[1] != $playerData['player_id']) {
						$playmateIndex = array_search($playersInMatch[1], array_column($playerData['playmates_P'], 'player_id'));
						if ($playmateIndex !== false && !$correct) {
							$playerData['playmates_P'][$playmateIndex]['count']++;
							$playerData['playmates'][$playmateIndex]['count']++;
						} elseif ($playmateIndex !== false && $match['hide'] == 1) {
							$playerData['playmates_P'][$playmateIndex]['count']--;
							$playerData['playmates'][$playmateIndex]['count']--;
						}
					}

					// Update opponents
					$opponentIndex = array_search($playersInMatch[2], array_column($playerData['opponents'], 'player_id'));
					if ($opponentIndex !== false && !$correct) {
						$playerData['opponents'][$opponentIndex]['count']++;
					} elseif ($opponentIndex !== false && $match['hide'] == 1) {
						$playerData['opponents'][$opponentIndex]['count']--;
					}

					$opponentIndex = array_search($playersInMatch[3], array_column($playerData['opponents'], 'player_id'));
					if ($opponentIndex !== false && !$correct) {
						$playerData['opponents'][$opponentIndex]['count']++;
					} elseif ($opponentIndex !== false && $match['hide'] == 1) {
						$playerData['opponents'][$opponentIndex]['count']--;
					}
				} elseif (!$correct || $match['hide'] == 1) { //it won't happen if it's a match fix
					// Update playmates_L
					if ($playersInMatch[2] != $playerData['player_id']) {
						$playmateIndex = array_search($playersInMatch[2], array_column($playerData['playmates_L'], 'player_id'));
						if ($playmateIndex !== false && !$correct) {
							$playerData['playmates_L'][$playmateIndex]['count']++;
							$playerData['playmates'][$playmateIndex]['count']++;
						} elseif ($playmateIndex !== false && $match['hide'] == 1) {
							$playerData['playmates_L'][$playmateIndex]['count']--;
							$playerData['playmates'][$playmateIndex]['count']--;
						}
					}

					// Update playmates_P
					if ($playersInMatch[3] != $playerData['player_id']) {
						$playmateIndex = array_search($playersInMatch[3], array_column($playerData['playmates_P'], 'player_id'));
						if ($playmateIndex !== false && !$correct) {
							$playerData['playmates_P'][$playmateIndex]['count']++;
							$playerData['playmates'][$playmateIndex]['count']++;
						} elseif ($playmateIndex !== false && $match['hide'] == 1) {
							$playerData['playmates_P'][$playmateIndex]['count']--;
							$playerData['playmates'][$playmateIndex]['count']--;
						}
					}

					// Update opponents
					$opponentIndex = array_search($playersInMatch[0], array_column($playerData['opponents'], 'player_id'));
					if ($opponentIndex !== false && !$correct) {
						$playerData['opponents'][$opponentIndex]['count']++;
					} elseif ($opponentIndex !== false && $match['hide'] == 1) {
						$playerData['opponents'][$opponentIndex]['count']--;
					}

					$opponentIndex = array_search($playersInMatch[1], array_column($playerData['opponents'], 'player_id'));
					if ($opponentIndex !== false && !$correct) {
						$playerData['opponents'][$opponentIndex]['count']++;
					} elseif ($opponentIndex !== false && $match['hide'] == 1) {
						$playerData['opponents'][$opponentIndex]['count']--;
					}
				}
			}
		}
	}

	// Removing players from the $playersInMatch array
	if (!$correct && $remove_players) {
		foreach ($playersInMatch as $playerToRemove) {
			$key = array_search($playerToRemove, $playing);
			if ($key !== false) {
				unset($playing[$key]);
			}
		}
		// Reindexing an array after removing elements
		$playing = array_values($playing);
	}

	$wpdb->update(
		"{$wpdb->prefix}doroto_tournaments",
		array(
			'statistics' => serialize($statistics),
			'playing' => serialize($playing),
			'last_update'  => round(microtime(true) * 1000)
		),
		array('id' => $tournament_id)
	);
}


/**
 * check if there is a need to find a new match
 * @since 1.0.0
 */
function doroto_allow_to_find_new_match(?stdClass $tournament, array|string|bool $selected_matches, array|string|bool $currently_played_array)
{
	$players_count = count(maybe_unserialize($tournament->players));
	$min_not_playing_mode = intval($tournament->min_not_playing); // 0 = Wait, 1 = Draw 
	$currently_played_count = count($currently_played_array);
	$selected_games_count = count($selected_matches);

	if ($min_not_playing_mode === 0) {
		if ($currently_played_count > 0) {
			return false;
		}
	}

	if (doroto_check_if_doubles($tournament)) {
		$players_on_court = 4;
	} else {
		$players_on_court = 2;
	}

	if ($currently_played_count == 0) {
		return true;
	}

	$total_players_busy = ($selected_games_count + $currently_played_count) * $players_on_court;

	if ($players_count - $total_players_busy >= $players_on_court) {
		return true;
	}

	return false;
}


/**
 * reduction of the number of matches offered according to the number of courts and according to the number of players
 * @since 1.0.0
 */
function doroto_matches_to_select_count(?stdClass $tournament, int $currently_played_count)
{
	global $wpdb;
	$courts_available = intval($tournament->courts_available);
	$min_not_playing = intval($tournament->min_not_playing);
	$players_count = count(maybe_unserialize($tournament->players));

	if (doroto_check_if_doubles($tournament)) {
		$players_on_court = 4;
	} else {
		$players_on_court = 2;
	}

	$max_based_on_players = floor($players_count / $players_on_court);

	if ($courts_available <= $max_based_on_players) {
		$maximum_number_games = $courts_available;
	} else {
		$maximum_number_games = $max_based_on_players;
	}
	$matches_to_select = $maximum_number_games - $currently_played_count;

	if ($min_not_playing == 0) {
		$minimum_playing = $players_count;
	} else {
		if (doroto_check_if_doubles($tournament)) {
			$minimum_playing = 6;
		} else {
			$minimum_playing = 3;
		}
	}


	if ($currently_played_count == 0) {
		return $matches_to_select;
	} elseif ($players_count - ($currently_played_count * $players_on_court) >= $minimum_playing) {
		return $matches_to_select;
	} else {
		return 0;
	}
	return $matches_to_select;
}


/**
 * close or reopen a tournament (web link)
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_toggle_tournament)
 */
function doroto_toggle_tournament()
{
	$tournament_id = isset($_REQUEST['tournament_id']) ? intval($_REQUEST['tournament_id']) : 0;
	doroto_require_admin_action('doroto_toggle_tournament', $tournament_id);

	$result = doroto_service_toggle_tournament($tournament_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('wp_ajax_doroto_toggle_tournament', 'doroto_toggle_tournament');


/**
 * close or reopen a registration (web link)
 * @since 1.0.0
 * @version 2.0.0 (doroto_service_toggle_registration)
 */
function doroto_toggle_registration()
{
	$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
	doroto_require_admin_action('doroto_toggle_registration', $tournament_id);

	$result = doroto_service_toggle_registration($tournament_id);
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}


/**
 * creates new users for testing purposes
 * @since 1.1.6
 * @version 1.3.7(create new users always)
 */
function doroto_get_or_create_users()
{
	global $wpdb;

	$new_players = array(
		"Novak Djoković",
		"Iga Świątek",
		"Carlos Alcaraz",
		"Aryna Sabalenka",
		"Jannik Sinner",
		"Elena Rybakina",
		"Daniil Medvedev",
		"Coco Gauff",
		"Andrey Rublev",
		"Zheng Qinwen",
		"Fritz Taylor",
		"Pegula Jessica",
		"Ruud Casper"
	);

	$default_password = wp_generate_password();
	$user_ids = [];

	foreach ($new_players as $player_name) {
		$existing_user = get_user_by('login', sanitize_user($player_name));

		if ($existing_user) {
			$user_ids[] = $existing_user->ID;
		} else {
			$user_id = wp_create_user(
				sanitize_user($player_name),
				$default_password,
				sanitize_email($player_name . '@DoRoTo-example.com')
			);

			if (!is_wp_error($user_id)) {
				$user = new WP_User($user_id);
				$user->set_role('subscriber');
				$user_ids[] = $user_id;
			}
		}
	}
	return $user_ids;
}



/**
 * save the first new tournament into wp database as an example
 * @version 1.5.8 
 * @since 1.0.0
 */
function doroto_create_tournament_record()
{
	ob_start();
	global $wpdb;

	$result = -1;
	$user_ids = doroto_get_or_create_users();

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$count = $wpdb->get_var(
		$wpdb->prepare("SELECT COUNT(*) FROM $table_name", array()) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	$players = [];
	$playing = [];
	$special_group = [];
	$special_group_empty = [];
	$output = '';
	$hide = 0;

	$admin_users = [];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[0];
	if (isset($user_ids[3])) $admin_users[] = $user_ids[3];

	$cnt_members = 1;
	foreach ($user_ids as $user) {
		$players[] = intval($user);
		if ($cnt_members % 2 == 0) {
			$special_group[] = $user;
		}
		$cnt_members++;
	}

	$current_date = gmdate("Y-m-d H:i:s");
	$matches_list = doroto_generate_fake_match(serialize($players));
	$new_last_update = round(microtime(true) * 1000);

	$invitation = '<p>' . __("This tournament was created for you to understand the functionality of the system using examples of different tournament settings.", "doubles-rotation-tournament") . '</p>';
	$invitation .= '<p><div class = "doroto-warning-text">' . __("Several accounts of well-known tennis players were automatically created for testing purposes.", "doubles-rotation-tournament") . '</div></p>';
	$invitation .= '<p>' . __("You can do whatever you want with this tournament. Change its settings, enter match results, and eventually delete it.", "doubles-rotation-tournament") . '</p>';
	$invitation .= '<p>' . __("Each time you run the help, a new tournament with the same settings will be randomly generated.", "doubles-rotation-tournament") . '</p>';

	$allowed_html = doroto_allowed_html();
	$invitation_1 = wp_kses($invitation, $allowed_html);

	$payment_done = array_slice($special_group, 0, 3);

	//Example 1: Doubles Tennis, open registration
	$doroto_settings = get_option('doroto_settings');
	$tournament_id = isset($doroto_settings['tournament_example_1']) ? intval($doroto_settings['tournament_example_1']) : '0';

	$result_1 = 0;
	$result_2 = 0;


	$tournament_data = [
		'name' => sanitize_text_field(__("Example 1: Doubles Tennis, open registration", "doubles-rotation-tournament")),
		'admin_users' => serialize($admin_users),
		'players' => serialize($players),
		'special_group' => serialize($special_group),
		'average_result' => 10,
		'tournament_type' => 21,
		'max_players' => 0,
		'playing' => serialize($playing),
		'whole_names' => 1,
		'two_special_group' => 1,
		'two_out_group' => 1,
		'payment_display' => 1,
		'payment_done' => serialize($payment_done),
		'open_registration' => 1,
		'courts_available' => 2,
		'play_final_match' => 1,
		'matches_list' => serialize($matches_list),
		'create_date' => $current_date,
		'invitation' => $invitation_1
	];

	if ($tournament_id !== 0) {
		$wpdb->delete(
			"{$wpdb->prefix}doroto_tournaments",
			['id' => intval($tournament_id)]
		);
		$tournament_data['id'] = intval($tournament_id);
		$result = $wpdb->insert(
			"{$wpdb->prefix}doroto_tournaments",
			$tournament_data
		);
	} else {
		$result = $wpdb->insert("{$wpdb->prefix}doroto_tournaments", $tournament_data);
		$tournament_id = $wpdb->insert_id;
	}

	if ($result !== false) {
		$doroto_settings['tournament_example_1'] = $tournament_id;
		update_option('doroto_settings', $doroto_settings);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));

		$wpdb->update($table_name, [
			'statistics' => serialize($statistics),
			'last_update'  => $new_last_update
		], ['id' => $tournament_id]);


		foreach ($statistics as &$player) {
			if ($player['player_id'] == $special_group[1]) {
				$player['active'] = 0;
				break;
			}
		}
		unset($player);

		$wpdb->update(
			$table_name,
			array(
				'statistics' => serialize($statistics),
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}


	//Example 2: Doubles Padel, closed registration
	$doroto_settings = get_option('doroto_settings');
	$tournament_id = isset($doroto_settings['tournament_example_2']) ? intval($doroto_settings['tournament_example_2']) : 0;

	$admin_users = [];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[4];
	if (isset($user_ids[3])) $admin_users[] = $user_ids[5];

	$tournament_data = [
		'name' => sanitize_text_field(__("Example 2: Doubles Padel, closed registration", "doubles-rotation-tournament")),
		'admin_users' => serialize($admin_users),
		'players' => serialize($players),
		'special_group' => serialize($special_group),
		'average_result' => 10,
		'tournament_type' => 25, // doubles padel
		'max_players' => 14,
		'minimum_matches' => 4,
		'whole_names' => 0,
		'two_special_group' => 1,
		'two_out_group' => 1,
		'announce_round_end' => 1,
		'open_registration' => 0,
		'courts_available' => 1,
		'play_final_match' => 0,
		'matches_list' => serialize($matches_list),
		'create_date' => $current_date,
		'invitation' => $invitation_1,
	];

	if ($tournament_id !== 0) {
		$wpdb->delete(
			"{$wpdb->prefix}doroto_tournaments",
			['id' => intval($tournament_id)]
		);
		$tournament_data['id'] = intval($tournament_id);
		$result = $wpdb->insert(
			"{$wpdb->prefix}doroto_tournaments",
			$tournament_data
		);
	} else {
		$result = $wpdb->insert("{$wpdb->prefix}doroto_tournaments", $tournament_data);
		$tournament_id = $wpdb->insert_id;
	}

	if ($result !== false) {
		$doroto_settings['tournament_example_2'] = $tournament_id;
		update_option('doroto_settings', $doroto_settings);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
		$wpdb->update($table_name, [
			'statistics' => serialize($statistics),
			'last_update'  => $new_last_update
		], ['id' => $tournament_id]);

		$wpdb->update(
			$table_name,
			array(
				'statistics' => serialize($statistics),
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}

	for ($i = 0; $i < 12; $i++) {
		doroto_games_to_play_shortcode(array('tournament_id' => $tournament_id));
		$tournament = doroto_prepare_tournament($tournament_id);
		$matches = maybe_unserialize($tournament->matches_list);

		foreach ($matches as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && $match['result_1'] == 0 && $match['result_2'] == 0) {
				doroto_generate_random_results($result_1, $result_2, $tournament);
				$endpoint_request = false;
				doroto_save_match_result($match['match_number'], $tournament_id, $tournament, $result_1, $result_2, $hide, $output, 0, $endpoint_request);
			}
		}
		doroto_tournament_progress($tournament_id);
	}
	if ($tournament) {
		$wpdb->update(
			$table_name,
			array(
				'courts_available' => 3,
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}


	//Example 3: Singles Badminton, final match
	$doroto_settings = get_option('doroto_settings');
	$tournament_id = isset($doroto_settings['tournament_example_3']) ? intval($doroto_settings['tournament_example_3']) : '0';

	$admin_users = [];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[2];
	$first_users = array_slice($user_ids, 0, 12);

	$tournament_data = [
		'name' => sanitize_text_field(__("Example 3: Singles Badminton, final match", "doubles-rotation-tournament")),
		'admin_users' => serialize($admin_users),
		'players' => serialize($first_users),
		'special_group' => serialize($special_group_empty),
		'average_result' => 15,
		'tournament_type' => 28, //Singles Badminton
		'max_players' => 18,
		'minimum_matches' => 4,
		'whole_names' => 1,
		'temp_suspend_winner' => 0,
		'two_special_group' => 0,
		'play_final_match' => 1,
		'two_out_group' => 0,
		'announce_round_end' => 1,
		'open_registration' => 0,
		'courts_available' => 1,
		'matches_list' => serialize($matches_list),
		'create_date' => $current_date,
		'invitation' => $invitation_1
	];

	if ($tournament_id !== 0) {
		$wpdb->delete(
			"{$wpdb->prefix}doroto_tournaments",
			['id' => intval($tournament_id)]
		);
		$tournament_data['id'] = intval($tournament_id);
		$result = $wpdb->insert(
			"{$wpdb->prefix}doroto_tournaments",
			$tournament_data
		);
	} else {
		$result = $wpdb->insert("{$wpdb->prefix}doroto_tournaments", $tournament_data);
		$tournament_id = $wpdb->insert_id;
	}

	if ($result !== false) {
		$doroto_settings['tournament_example_3'] = $tournament_id;
		update_option('doroto_settings', $doroto_settings);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$statistics = doroto_create_statistics_table($tournament, $first_users, intval($tournament->whole_names));
		$wpdb->update($table_name, [
			'statistics' => serialize($statistics),
			'last_update'  => $new_last_update
		], ['id' => $tournament_id]);

		$wpdb->update(
			$table_name,
			array(
				'statistics' => serialize($statistics),
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}

	for ($i = 0; $i < 29; $i++) {
		doroto_games_to_play_shortcode(array('tournament_id' => $tournament_id));
		$tournament = doroto_prepare_tournament($tournament_id);
		$matches = maybe_unserialize($tournament->matches_list);

		foreach ($matches as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && $match['result_1'] == 0 && $match['result_2'] == 0) {
				doroto_generate_random_results($result_1, $result_2, $tournament);
				$endpoint_request = false;
				doroto_save_match_result($match['match_number'], $tournament_id, $tournament, $result_1, $result_2, $hide, $output, 0, $endpoint_request);
			}
		}
		doroto_tournament_progress($tournament_id);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$statistics = maybe_unserialize($tournament->statistics);
		foreach ($statistics as &$player) {
			if ($player['player_id'] == $user_ids[0] || $player['player_id'] == $user_ids[1]) {
				$player['active'] = 0;
			}
		}
		unset($player);

		$wpdb->update(
			$table_name,
			array(
				'statistics' => serialize($statistics),
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}

	for ($i = 30; $i < 53; $i++) {
		doroto_games_to_play_shortcode(array('tournament_id' => $tournament_id));
		$tournament = doroto_prepare_tournament($tournament_id);
		$matches = maybe_unserialize($tournament->matches_list);

		foreach ($matches as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && $match['result_1'] == 0 && $match['result_2'] == 0) {
				doroto_generate_random_results($result_1, $result_2, $tournament);
				$endpoint_request = false;
				doroto_save_match_result($match['match_number'], $tournament_id, $tournament, $result_1, $result_2, $hide, $output, 0, $endpoint_request);
			}
		}
		doroto_tournament_progress($tournament_id);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		$statistics = [];
	}
	usort($statistics, function ($a, $b) {
		return $b['ratio'] <=> $a['ratio'];
	});

	$final_four = [];
	if ($statistics[0]['player_id']) $final_four['l1'] = $statistics[0]['player_id'];
	if ($statistics[1]['player_id']) $final_four['p1'] = $statistics[1]['player_id'];
	if ($statistics[2]['player_id']) $final_four['l2'] = $statistics[2]['player_id'];
	if ($statistics[3]['player_id']) $final_four['p2'] = $statistics[3]['player_id'];

	$results_array = array();
	$results_array['result_1'] = 21;
	$results_array['result_2'] = 17;

	if ($tournament) {
		$wpdb->update(
			$table_name,
			array(
				'close_tournament' => 1,
				'final_four' => serialize($final_four),
				'final_result' => serialize($results_array),
				'courts_available' => 4,
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}


	//Example 4: Beach Volleyball, completed tournament
	$doroto_settings = get_option('doroto_settings');
	$tournament_id = isset($doroto_settings['tournament_example_4']) ? intval($doroto_settings['tournament_example_4']) : '0';

	$admin_users = [];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[0];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[2];
	if (isset($user_ids[0])) $admin_users[] = $user_ids[3];
	$first_users = array_slice($user_ids, 0, 6);

	$special_group_short = [];
	if (isset($first_users[1])) $special_group_short[] = $first_users[1];
	if (isset($first_users[3])) $special_group_short[] = $first_users[3];
	if (isset($first_users[5])) $special_group_short[] = $first_users[5];

	$payment_last = array_slice($user_ids, 0, 5);

	$tournament_data = [
		'name' => sanitize_text_field(__("Example 4: Beach Volleyball, completed tournament", "doubles-rotation-tournament")),
		'admin_users' => serialize($admin_users),
		'players' => serialize($first_users),
		'special_group' => serialize($special_group_short),
		'average_result' => 13,
		'tournament_type' => 26, //Beach Volleyball
		'max_players' => 0,
		'minimum_matches' => 1,
		'whole_names' => 1,
		'temp_suspend_winner' => 0,
		'special_group_can_win' => 2,
		'two_special_group' => 1,
		'two_out_group' => 0,
		'announce_round_end' => 1,
		'open_registration' => 0,
		'courts_available' => 1,
		'play_final_match' => 0,
		'payment_display' => 1,
		'matches_list' => serialize($matches_list),
		'payment_done' => serialize($payment_last),
		'create_date' => $current_date,
		'invitation' => $invitation_1
	];
	if ($tournament_id !== 0) {
		$wpdb->delete(
			"{$wpdb->prefix}doroto_tournaments",
			['id' => intval($tournament_id)]
		);
		$tournament_data['id'] = intval($tournament_id);
		$result = $wpdb->insert(
			"{$wpdb->prefix}doroto_tournaments",
			$tournament_data
		);
	} else {
		$result = $wpdb->insert("{$wpdb->prefix}doroto_tournaments", $tournament_data);
		$tournament_id = $wpdb->insert_id;
	}

	if ($result !== false) {
		$doroto_settings['tournament_example_4'] = $tournament_id;
		update_option('doroto_settings', $doroto_settings);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$statistics = doroto_create_statistics_table($tournament, $first_users, intval($tournament->whole_names));
		$wpdb->update($table_name, [
			'statistics' => serialize($statistics),
			'last_update'  => $new_last_update
		], ['id' => $tournament_id]);

		$wpdb->update(
			$table_name,
			array(
				'statistics' => serialize($statistics),
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}

	for ($i = 0; $i < 15; $i++) {
		doroto_games_to_play_shortcode(array('tournament_id' => $tournament_id));
		$tournament = doroto_prepare_tournament($tournament_id);
		$matches = maybe_unserialize($tournament->matches_list);

		foreach ($matches as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && $match['result_1'] == 0 && $match['result_2'] == 0) {
				doroto_generate_random_results($result_1, $result_2, $tournament);
				$endpoint_request = false;
				doroto_save_match_result($match['match_number'], $tournament_id, $tournament, $result_1, $result_2, $hide, $output, 0, $endpoint_request);
			}
		}
		doroto_tournament_progress($tournament_id);
	}

	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	if ($tournament) {
		$wpdb->update(
			$table_name,
			array(
				'close_tournament' => 1,
				'last_update'  => $new_last_update
			),
			array('id' => $tournament_id)
		);
	}

	ob_get_clean();
	return ($result);
}
/**
 * AJAX entry point used by the guided tour to regenerate the example tournaments.
 * Requires the tour nonce and is throttled, because regenerating creates users
 * and simulates ~100 draws (an open endpoint allowed cheap denial of service).
 * @since 1.6.0
 */
function doroto_ajax_create_tournament_record()
{
	if (!check_ajax_referer('doroto_help_tour', 'nonce', false)) {
		wp_send_json_error(['message' => 'Invalid nonce'], 403);
	}
	if (get_transient('doroto_example_regenerated') && !current_user_can('manage_options')) {
		wp_send_json_success(['throttled' => true]);
	}
	set_transient('doroto_example_regenerated', 1, MINUTE_IN_SECONDS);
	doroto_create_tournament_record();
	wp_send_json_success();
}
add_action('wp_ajax_doroto_create_tournament_record', 'doroto_ajax_create_tournament_record');
add_action('wp_ajax_nopriv_doroto_create_tournament_record', 'doroto_ajax_create_tournament_record');

/**
 * generate random result
 * @since 1.3.7
 */
function doroto_generate_random_results(int &$result_1, int &$result_2, ?stdClass $tournament)
{
	$tournament_type = intval($tournament->tournament_type);

	switch ($tournament_type) {
		case 20: //Singles Tennis           
			$result_max = 6;
			break;
		case 21:  //Doubles Tennis          
			$result_max = 6;
			break;
		case 22:  //Singles Table Tennis          
			$result_max = 11;
			break;
		case 23:  //Doubles Table Tennis          
			$result_max = 11;
			break;
		case 24: //Singles Padel            
			$result_max = 6;
			break;
		case 25: //Doubles Padel           
			$result_max = 6;
			break;
		case 26: //Beach Volleyball           
			$result_max = 21;
			break;
		case 27: //Squash           
			$result_max = 11;
			break;
		case 28: //Singles Badminton           
			$result_max = 21;
			break;
		case 29: //Doubles Badminton           
			$result_max = 21;
			break;
		default:
			$result_max = 11;
			break;
	}

	if (wp_rand(0, 1) === 0) {
		$result_1 = $result_max;
		$result_2 = wp_rand(0, $result_max - 2);
	} else {
		$result_2 = $result_max;
		$result_1 = wp_rand(0, $result_max - 2);
	}
}


/**
 * choose a tournament for displaying on the screen
 * @since 1.0.0
 */
function doroto_choose_tournament(string $tournament_id = '')
{
	global $wpdb;
	$output = '';

	if (isset($_GET['tournament_id'])) {
		$tournament_id = intval($_GET['tournament_id']);
	} else {
		$output = sanitize_text_field(__("Tournament ID was not provided.", "doubles-rotation-tournament"));
		doroto_info_messsages_save($output);
		doroto_redirect_modify_url($tournament_id, "");
		exit;
	}

	$output = sanitize_text_field(__("The data is being displayed for the tournament no.", "doubles-rotation-tournament") . ' ' . $tournament_id . '.');
	doroto_info_messsages_save($output);
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('wp_ajax_doroto_choose_tournament', 'doroto_choose_tournament');
add_action('wp_ajax_nopriv_doroto_choose_tournament', 'doroto_choose_tournament'); // This is for non-logged in users


/**
 * get filter option from user_meta
 * @since 1.0.0
 */
function doroto_read_filter_tournament()
{
	global $wpdb;
	$filter_option_first = array(
		'selection' => 0,
		'first_id' => 0,
		'last_id' => 0
	);
	$filter_option_serialized = get_user_meta(get_current_user_id(), 'doroto_filter_tournaments', true);
	if (!empty($filter_option_serialized)) {
		$filter_option = maybe_unserialize($filter_option_serialized);
		if (!is_array($filter_option)) {
			$filter_option = $filter_option_first;
		}
	} else {
		$filter_option = $filter_option_first;
	}
	return $filter_option;
}


/**
 * list of filtered tournaments
 * @since 1.0.0
 * @version 1.4.7 (add location and visibility)
 */
function doroto_prepare_filtered_tournaments()
{
	global $wpdb;

	$filter = doroto_read_filter_tournament();
	$filter_option = intval($filter['selection']);
	$current_user_id = intval(get_current_user_id());
	$tournaments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}doroto_tournaments");

	$filtered_tournaments = [];

	switch ($filter_option) {
		case 1:
			$where = "open_registration = 1";
			break;
		case 2:
			$where = "open_registration = 0 AND close_tournament = 0";
			break;
		case 3:
			$where = "close_tournament = 1";
			break;
		case 20:
		case 21:
		case 22:
		case 23:
		case 24:
		case 25:
		case 26:
		case 27:
		case 28:
		case 29:
			$where = "tournament_type = $filter_option";
			break;
		default:
			$where = "1=1";
	}

	if ($filter_option < 4 || $filter_option > 7) {
		$tournaments = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE $where", array()) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	foreach ($tournaments as $tournament) {
		$players = maybe_unserialize($tournament->players);
		if (
			intval($tournament->visibility) === 0 &&
			doroto_is_admin($tournament->id) === 0 &&
			(!is_array($players) || !in_array($current_user_id, $players))
		) {
			continue;
		}

		if (in_array($filter_option, [4, 5, 6, 7])) {
			$players = maybe_unserialize($tournament->players);
			$admins = maybe_unserialize($tournament->admin_users);

			switch ($filter_option) {
				case 4:
					if (!is_array($players) || !in_array($current_user_id, $players)) continue 2;
					break;
				case 5:
					if (is_array($players) && in_array($current_user_id, $players)) continue 2;
					break;
				case 6:
					if (!is_array($admins) || !in_array($current_user_id, $admins)) continue 2;
					break;
				case 7:
					if (is_array($admins) && in_array($current_user_id, $admins)) continue 2;
					break;
			}
		}

		$filtered_tournaments[] = $tournament;
	}

	$user_lat = 50.0;
	$user_lon = 15.0;

	$response = wp_remote_get('http://ip-api.com/json/' . $_SERVER['REMOTE_ADDR']);
	if (!is_wp_error($response)) {
		$data = json_decode(wp_remote_retrieve_body($response));
		if (!empty($data->lat) && !empty($data->lon)) {
			$user_lat = floatval($data->lat);
			$user_lon = floatval($data->lon);
		}
	}

	usort($filtered_tournaments, function ($a, $b) use ($user_lat, $user_lon) {
		$latA = floatval($a->latitude ?? 0);
		$lonA = floatval($a->longitude ?? 0);
		$latB = floatval($b->latitude ?? 0);
		$lonB = floatval($b->longitude ?? 0);

		$distA = doroto_haversine_distance($user_lat, $user_lon, $latA, $lonA);
		$distB = doroto_haversine_distance($user_lat, $user_lon, $latB, $lonB);

		if ($distA == $distB) {
			return $b->id - $a->id;
		}
		return ($distA < $distB) ? -1 : 1;
	});

	return $filtered_tournaments;
}

/**
 * Caunting distance between two points
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2)
{
	$earth_radius = 6371;
	$dLat = deg2rad($lat2 - $lat1);
	$dLon = deg2rad($lon2 - $lon1);
	$a = sin($dLat / 2) * sin($dLat / 2) +
		cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
		sin($dLon / 2) * sin($dLon / 2);
	$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
	return $earth_radius * $c;
}


/**
 * function that changes what is presented during a tournament
 * @since 1.0.0
 */
function doroto_change_presentation()
{
	global $wpdb;
	$current_user_id = intval(get_current_user_id());
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));
	$shortcodes = array('doroto_tournament_log_link', 'doroto_games_to_play', 'doroto_display_players', 'doroto_display_games', 'doroto_display_player_statistics', 'doroto_table');

	if (!$doroto_presentation || !is_array($doroto_presentation)) {
		$doroto_presentation = array(
			'allow_to_run' => 0,
			'slide' => $shortcodes[0],
		);
		update_user_meta($current_user_id, 'doroto_presentation', serialize($doroto_presentation));
	}

	$allow_to_run = intval($doroto_presentation['allow_to_run']);
	if ($allow_to_run != 1) {
		return null;
	}

	$doroto_settings = get_option('doroto_settings');
	if (!isset($doroto_settings)) {
		return null;
	}

	$shortcodes_validated = [];
	foreach ($shortcodes as $code) {
		if ($doroto_settings[$code] == 1) {
			$shortcodes_validated[] = $code;
		}
	}
	if (empty($shortcodes_validated)) {
		return null;
	}

	$current_slide = sanitize_text_field($doroto_presentation['slide']);

	$current_index = array_search($current_slide, $shortcodes_validated);
	if ($current_index === false || $current_index === count($shortcodes_validated) - 1) {
		$doroto_presentation['slide'] = $shortcodes_validated[0];
	} else {
		$doroto_presentation['slide'] = $shortcodes_validated[$current_index + 1];
	}

	update_user_meta($current_user_id, 'doroto_presentation', serialize($doroto_presentation));
}


/**
 * move among tournaments within tournaments list
 * @since 1.0.0
 * @version 1.3.9 (the selected tournament is always visible)
 */
function doroto_move_among_tournaments()
{
	if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'doroto_move_among_tournaments_nonce')) {
		wp_die(esc_html__('Invalid request.', 'doubles-rotation-tournament'));
	}

	$filter_option = [];

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$display_rows = intval(doroto_read_settings('display_rows', 20));
		if ($display_rows <= 0) {
			$display_rows = 9999;
		}

		$filter_option = doroto_read_filter_tournament();

		if (isset($_POST['doroto_decrement_first_id'])) {
			$filter_option['first_id'] -= $display_rows;
		} elseif (isset($_POST['doroto_increment_first_id'])) {
			$filter_option['first_id'] += $display_rows;
		}

		$filter_option['last_id'] = $filter_option['first_id'] + $display_rows - 1;
		$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : doroto_getTournamentId();
	}

	if (!isset($tournament_id)) {
		$tournament_id = doroto_getTournamentId();
	}

	update_user_meta(get_current_user_id(), 'doroto_filter_tournaments', serialize($filter_option));

	$output = sanitize_text_field(__("List of available tournaments was updated.", "doubles-rotation-tournament"));

	doroto_info_messsages_save($output);
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

/**
 * create array with type of tournaments
 * @since 1.1.0
 */
function doroto_tournament_types()
{
	$options = array(
		20 => esc_html__("Singles Tennis", "doubles-rotation-tournament"),
		21 => esc_html__("Doubles Tennis", "doubles-rotation-tournament"),
		22 => esc_html__("Singles Table Tennis", "doubles-rotation-tournament"),
		23 => esc_html__("Doubles Table Tennis", "doubles-rotation-tournament"),
		24 => esc_html__("Singles Padel", "doubles-rotation-tournament"),
		25 => esc_html__("Doubles Padel", "doubles-rotation-tournament"),
		26 => esc_html__("Beach Volleyball", "doubles-rotation-tournament"),
		27 => esc_html__("Squash", "doubles-rotation-tournament"),
		28 => esc_html__("Singles Badminton", "doubles-rotation-tournament"),
		29 => esc_html__("Doubles Badminton", "doubles-rotation-tournament")
	);
	return $options;
}

/**
 * create array with type of tournaments
 * @since 1.1.0
 */
function doroto_types_variables()
{
	$options = array(
		20 => "singles_tennis",
		21 => "doubles_tennis",
		22 => "singles_table_tennis",
		23 => "doubles_table_tennis",
		24 => "singles_padel",
		25 => "doubles_padel",
		26 => "beach_volleyball",
		27 => "squash",
		28 => "singles_badminton",
		29 => "doubles_badminton",
	);
	return $options;
}

/**
 * Tournament types the website administrator allows (type => name), as the forms before 2.0 offered them.
 * @param int $keep type always listed (the current type of a tournament)
 * @since 2.0.0
 */
function doroto_visible_tournament_types(int $keep = 0): array
{
	$types = doroto_tournament_types();
	$visible = [];
	foreach (doroto_types_variables() as $type => $key) {
		if ($type === $keep || doroto_read_settings($key, 1)) {
			$visible[$type] = $types[$type];
		}
	}
	return $visible ?: $types;
}

/**
 * Type preselected by the create buttons: the default of the settings when it is allowed.
 * @since 2.0.0
 */
function doroto_default_tournament_type(): int
{
	$visible = doroto_visible_tournament_types();
	$default = intval(doroto_read_settings('tournament_type', 21));
	return isset($visible[$default]) ? $default : intval(array_key_first($visible));
}

/**
 * create array with type of tournaments
 * @since 1.1.0
 * @version 1.3.6
 */
function doroto_check_if_doubles(?stdClass $tournament)
{
	$doubles = array(21, 23, 25, 26, 29);
	$tournament_type = intval($tournament->tournament_type);
	if (in_array($tournament_type, $doubles)) {
		return true;
	} else {
		return false;
	}
}

/**
 * find trend for players
 * @since 1.1.3
 */
function doroto_find_trend_for_players(int $tournament_id, ?stdClass $tournament)
{
	global $wpdb;

	if ($tournament && property_exists($tournament, 'matches_list')) {
		$games = maybe_unserialize($tournament->matches_list);

		if (is_array($games)) {
			usort($games, function ($a, $b) {
				return $b['match_number'] <=> $a['match_number'];
			});
			$players = maybe_unserialize($tournament->players);
			$trend = [];
			if (!is_array($players)) {
				return $trend;
			}

			foreach ($games as $match) {
				if ($match['played'] == 1 && $match['hide'] == 0 && ($match['result_1'] != 0 || $match['result_2'] != 0)) {
					if ($match['result_1'] > $match['result_2']) {
						$winner = 1;
					} elseif ($match['result_1'] < $match['result_2']) {
						$winner = -1;
					} else {
						$winner = 0;
					}

					foreach ($players as $player) {
						if (doroto_check_if_doubles($tournament)) {
							if ($player == $match['player_1'] || $player == $match['player_2']) {
								$position_coef = 1;
							} elseif ($player == $match['player_3'] || $player == $match['player_4']) {
								$position_coef = -1;
							} else {
								continue;
							}
						} else {
							if ($player == $match['player_1']) {
								$position_coef = 1;
							} elseif ($player == $match['player_2']) {
								$position_coef = -1;
							} else {
								continue;
							}
						}

						$winner_coef = $winner * $position_coef;

						if (!isset($trend[$player])) {
							$trend[$player]['trend'] = $winner_coef;
							$trend[$player]['continue'] = 1;
						} else {
							if (($trend[$player]['trend'] > 0 && $winner_coef > 0) ||
								($trend[$player]['trend'] < 0 && $winner_coef < 0)
							) {
								if ($trend[$player]['continue']) {
									$trend[$player]['trend'] += $winner_coef;
									$trend[$player]['continue'] = 1;
								} else {
									$trend[$player]['continue'] = 0;
								}
							} else {
								$trend[$player]['continue'] = 0;
							}
						}
					}
				}
			}
		} else {
			$trend = [];
		}
	} else {
		$trend = [];
	}
	return $trend;
}


/**
 * input corrent class for the name format
 * @since 1.1.4
 */
function doroto_class_name(int $winner, array $special_group, int $current_user_id)
{
	if ((in_array($winner, $special_group)) && $current_user_id == $winner) {
		$winner_class = "<span class='doroto-special-group-text-underlined'>";
	} elseif ((in_array($winner, $special_group)) && $current_user_id != $winner) {
		$winner_class = "<span class='doroto-special-group-text'>";
	} elseif ($current_user_id == $winner) {
		$winner_class = "<span class='doroto-winner-text-underlined'>";
	} else {
		$winner_class = "<span class='doroto-winner-text'>";
	}
	return $winner_class;
}


/**
 * info about round end
 * @since 1.1.6
 * @version 1.3.5 (round end message changed)
 */
function doroto_notice_round_end(int $tournament_id, ?stdClass $tournament, int $choosen_player)
{
	if (doroto_check_if_presentation_on() || $tournament->close_tournament == '1') {
		return '';
	}

	$whole_names = intval($tournament->whole_names);
	$announce_round_end = intval($tournament->announce_round_end);
	$player_name = doroto_find_player_name($choosen_player, $whole_names);

	$output = "<div class='doroto-message-background'>";
	$output .= esc_html__("Player", "doubles-rotation-tournament") . ' <b>' . esc_html($player_name) . '</b> ' . esc_html__("played the same number of matches with everyone.", "doubles-rotation-tournament") . ' ' . esc_html__("Maybe this means the end of the tournament round.", "doubles-rotation-tournament");

	if (doroto_is_admin($tournament_id) > 0) {
		$option = [];

		if ($announce_round_end == 1) {
			$show_it_again = esc_html__("continue to the next round", "doubles-rotation-tournament");
			$option[] = "<a href='" . esc_url(doroto_action_url('doroto_next_notice_round_end', intval($tournament_id))) . "'>" . esc_html($show_it_again) . "</a>";
		}

		$close_tournament_text = esc_html__("end the tournament", "doubles-rotation-tournament");
		$option[] = "<a href='" . esc_url(doroto_action_url('doroto_toggle_tournament', intval($tournament_id))) . "'>" . esc_html($close_tournament_text) . "</a>";

		$hide_notice_text = esc_html__("hide this message", "doubles-rotation-tournament");
		$option[] = "<a href='" . esc_url(doroto_action_url('doroto_hide_notice_round_end', intval($tournament_id))) . "'>" . esc_html($hide_notice_text) . "</a>";

		if (!empty($option)) {
			$output .= '<p>' . esc_html__("If you wish, you can as an administrator", "doubles-rotation-tournament") . ' ';
			$output .= implode(" " . esc_html__("or", "doubles-rotation-tournament") . " ", $option) . '.</p>';
		}
	}
	$output .= '</div>';
	return $output;
}


/**
 * hide the round end notices (web link)
 * @since 1.1.6
 * @version 2.0.0 (doroto_service_round_end_action)
 */
function doroto_hide_notice_round_end()
{
	$tournament_id = isset($_REQUEST['tournament_id']) ? intval($_REQUEST['tournament_id']) : 0;
	doroto_require_admin_action('doroto_hide_notice_round_end', $tournament_id);

	$result = doroto_service_round_end_action($tournament_id, 'hide');
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('wp_ajax_doroto_hide_notice_round_end', 'doroto_hide_notice_round_end');


/**
 * announce the end of the next round again (web link)
 * @since 1.1.6
 * @version 2.0.0 (doroto_service_round_end_action)
 */
function doroto_next_notice_round_end()
{
	$tournament_id = isset($_REQUEST['tournament_id']) ? intval($_REQUEST['tournament_id']) : 0;
	doroto_require_admin_action('doroto_next_notice_round_end', $tournament_id);

	$result = doroto_service_round_end_action($tournament_id, 'next');
	doroto_info_messsages_save(sanitize_text_field(doroto_service_message($result, $tournament_id)));
	doroto_redirect_modify_url($tournament_id, "");
	exit;
}

add_action('wp_ajax_doroto_next_notice_round_end', 'doroto_next_notice_round_end');

/**
 * Return games or points
 * @since 1.3.6
 */
function doroto_games_points(?stdClass $tournament)
{
	$tournament_type = intval($tournament->tournament_type);
	$games_types = array(20, 21, 24, 25);

	if (in_array($tournament_type, $games_types)) {
		$games_points = esc_html__("games", "doubles-rotation-tournament");
	} else {
		$games_points = esc_html__("points", "doubles-rotation-tournament");
	}
	return $games_points;
}


/**
 * Send tournament links that point to the site root to the tournament page.
 * Without the app installed, a shared link "<site>/?tournament_id=5" ended on
 * the home page, and the QR code of app versions before 1.1
 * ("<site>/tournament?id=5") on a 404 page.
 * @since 1.6.0
 */
// Priority 1: before redirect_canonical() rewrites the old link.
add_action('template_redirect', 'doroto_redirect_tournament_links', 1);

function doroto_redirect_tournament_links()
{
	global $wpdb;
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}

	$tournament_id = 0;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect
	if (isset($_GET['tournament_id']) && (is_front_page() || is_home())) {
		$tournament_id = absint(wp_unslash($_GET['tournament_id']));
	} elseif (is_404() && isset($_GET['id'])) {
		$path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '', PHP_URL_PATH);
		if (is_string($path) && preg_match('#/tournament/?$#', $path)) {
			$tournament_id = absint(wp_unslash($_GET['id']));
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ($tournament_id <= 0) {
		return;
	}

	$main_page_id = intval(get_option('doroto_main_page_id'));
	if (!$main_page_id || !get_post($main_page_id) || is_page($main_page_id)) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id));
	if (!$exists) {
		return;
	}

	wp_safe_redirect(add_query_arg('tournament_id', $tournament_id, get_permalink($main_page_id)), 302);
	exit;
}

/**
 * Host, port and path of a site URL, for comparing addresses written with or
 * without "www", a trailing slash or a different scheme.
 * @since 1.6.1
 */
function doroto_site_key(string $url)
{
	$parts = wp_parse_url(trim($url));
	if (!$parts || empty($parts['host'])) {
		return '';
	}
	$host = strtolower(preg_replace('/^www\./i', '', $parts['host']));
	$port = isset($parts['port']) ? ':' . intval($parts['port']) : '';
	$path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
	return $host . $port . $path;
}

/**
 * Tournament links of the Android app point to the central site
 * (doroto.ltcchrast.cz/?tournament_id=5&doroto_site=<club>): Android opens
 * only that verified domain in the app, the club sites would open in the
 * browser. Without the app, the browser lands here and is sent on to the
 * club. Before, the central site showed its own tournament with the same
 * number.
 * Only sites from the site directory (doroto-websites plugin) are redirected
 * automatically; any other address gets a page with a link, so the site
 * can't be used as an open redirect.
 * @since 1.6.1
 */
function doroto_forward_foreign_tournament_links()
{
	global $wpdb;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect
	if (is_admin() || wp_doing_ajax() || empty($_GET['doroto_site']) || empty($_GET['tournament_id'])) {
		return;
	}
	$site = esc_url_raw(trim(wp_unslash($_GET['doroto_site'])));
	$tournament_id = absint(wp_unslash($_GET['tournament_id']));
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$scheme = wp_parse_url($site, PHP_URL_SCHEME);
	if ($tournament_id <= 0 || !in_array($scheme, ['http', 'https'], true)) {
		return;
	}
	$key = doroto_site_key($site);
	if ($key === '' || $key === doroto_site_key(home_url('/'))) {
		return; // our own tournament: the normal handling applies
	}

	$target_base = '';
	$directory = $wpdb->prefix . 'doroto_websites';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $directory)) === $directory) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results("SELECT website, doroto_url FROM `$directory`");
		foreach ((array) $rows as $row) {
			if (doroto_site_key($row->website) === $key) {
				// The tournament page of the club when known, else its home page
				// (plugin 1.6.0+ forwards "/?tournament_id=" to the tournament page).
				$target_base = !empty($row->doroto_url) ? $row->doroto_url : $row->website;
				break;
			}
		}
	}
	$target = add_query_arg('tournament_id', $tournament_id, $target_base !== '' ? $target_base : trailingslashit($site));

	if ($target_base !== '') {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- target comes from the site directory
		wp_redirect($target, 302);
		exit;
	}

	status_header(200);
	nocache_headers();
	$host = wp_parse_url($site, PHP_URL_HOST);
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
	echo '<title>' . esc_html__('Rotation tournament', 'doubles-rotation-tournament') . '</title></head>';
	echo '<body style="font-family:sans-serif;max-width:32em;margin:3em auto;padding:0 1em;">';
	echo '<p>' . esc_html(sprintf(
		/* translators: 1: tournament number, 2: website address */
		__('Tournament no. %1$d is run on the website %2$s.', 'doubles-rotation-tournament'),
		$tournament_id,
		$host
	)) . '</p>';
	echo '<p><a href="' . esc_url($target) . '" rel="nofollow noopener">' . esc_html__('Continue to the tournament', 'doubles-rotation-tournament') . '</a></p>';
	echo '</body></html>';
	exit;
}
add_action('template_redirect', 'doroto_forward_foreign_tournament_links', 0);
