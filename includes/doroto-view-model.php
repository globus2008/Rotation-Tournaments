<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * View model of a tournament for the blocks (2.0).
 *
 * One PHP array describes everything the tournament blocks show to the current user.
 * The blocks render it on the server (render.php, readable without JavaScript) and pass it
 * to the Interactivity API store; the store reloads it from GET doroto/v1/view/<id> when
 * check-update reports a change. Keeping one builder means the first render and every
 * refresh always show the same data.
 * @since 2.0.0
 */

/**
 * Player IDs of a match as two teams.
 * Doubles: player_1 + player_2 against player_3 + player_4; singles: player_1 against player_2.
 * @since 2.0.0
 */
function doroto_view_match_teams(array $match, bool $doubles, int $whole_names): array
{
	$ids = $doubles
		? [[$match['player_1'], $match['player_2']], [$match['player_3'], $match['player_4']]]
		: [[$match['player_1']], [$match['player_2']]];
	return array_map(function ($team) use ($whole_names) {
		return array_map(function ($id) use ($whole_names) {
			$id = intval($id);
			return ['id' => $id, 'name' => doroto_find_player_name($id, $whole_names)];
		}, $team);
	}, $ids);
}

/**
 * Progress of the tournament in percent and the estimated minutes to its end
 * (same formula as [doroto_display_tournament_progress]). Null values when unknown.
 * @since 2.0.0
 */
function doroto_view_progress(stdClass $tournament): array
{
	$planned = intval($tournament->planned_combinations);
	$rest = intval($tournament->rest_combinations);
	if (intval($tournament->close_tournament) === 1) {
		return ['percent' => 100, 'minutes_left' => 0];
	}
	if ($planned <= 0) {
		return ['percent' => null, 'minutes_left' => null];
	}
	$percent = intval(floor(($planned - $rest) * 100 / $planned));

	$games_hour = intval($tournament->games_hour);
	$courts = max(1, intval(doroto_matches_to_select_count($tournament, 0)));
	$draw_index = (intval($tournament->min_not_playing) === 0 && $rest > 4) ? 0.7 : 1;
	$players_coef = doroto_check_if_doubles($tournament) ? 2 : 1;
	$minutes = null;
	if ($games_hour > 0) {
		$hours = (($rest + intval($tournament->play_final_match) * $players_coef) * intval($tournament->average_result))
			/ ($players_coef * $games_hour * $courts * $draw_index);
		$minutes = max(0, intval(round($hours * 60)));
	}
	return ['percent' => $percent, 'minutes_left' => $minutes];
}

/**
 * Standings: statistics sorted by ratio, with rank and winner marks.
 * Winner rules of [doroto_display_players]: only after closing, with at least minimum_matches
 * games and (unless temp_suspend_winner) not suspended. special_group_can_win: 0 = special
 * group players cannot win, 1 = they compete with everybody, 2 = they have their own winner.
 * @since 2.0.0
 */
function doroto_view_standings(stdClass $tournament, int $current_user_id): array
{
	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		return [];
	}
	usort($statistics, function ($a, $b) {
		return ($b['ratio'] ?? 0) <=> ($a['ratio'] ?? 0);
	});

	$whole_names = intval($tournament->whole_names);
	$special = array_map('intval', (array) (maybe_unserialize($tournament->special_group) ?: []));
	$paid = array_map('intval', (array) (maybe_unserialize($tournament->payment_done) ?: []));
	$closed = intval($tournament->close_tournament) === 1;
	$minimum = intval($tournament->minimum_matches);
	$suspended_may_win = intval($tournament->temp_suspend_winner) === 1;
	$special_mode = intval($tournament->special_group_can_win);
	$trend = doroto_find_trend_for_players(intval($tournament->id), $tournament);

	$best = ['main' => null, 'special' => null];
	$open = intval($tournament->open_registration) === 1;
	$rank = 0;
	$last_ratio = null;
	$rows = [];
	foreach ($statistics as $index => $stat) {
		$id = intval($stat['player_id']);
		$ratio = floatval($stat['ratio'] ?? 0);
		// Dense rank by ratio (1, 2, 2, 3) once matches are played, plain order before.
		if ($open) {
			$rank = $index + 1;
		} elseif ($ratio !== $last_ratio) {
			$rank++;
			$last_ratio = $ratio;
		}
		$is_special = in_array($id, $special, true);
		$games = intval($stat['games']);
		$active = intval($stat['active']) === 1;

		$winner = null;
		$eligible = $closed && $games >= $minimum && ($active || $suspended_may_win);
		$category = $is_special ? ($special_mode === 2 ? 'special' : ($special_mode === 1 ? 'main' : null)) : 'main';
		if ($eligible && $category !== null && ($best[$category] === null || $ratio >= $best[$category])) {
			$best[$category] = $ratio;
			$winner = $category;
		}

		$rows[] = [
			'id' => $id,
			'name' => doroto_find_player_name($id, $whole_names),
			'rank' => $rank,
			'active' => $active,
			'games' => $games,
			'below_minimum' => $games < $minimum,
			'won' => intval($stat['won']),
			'lost' => intval($stat['lost']),
			'ratio' => round($ratio, 2),
			'rest' => intval($stat['rest'] ?? 0),
			'trend' => intval($trend[$id]['trend'] ?? 0),
			'special' => $is_special,
			'paid' => in_array($id, $paid, true),
			'winner' => $winner,
			'is_me' => $id === $current_user_id,
		];
	}
	return $rows;
}

/**
 * Teammate and opponent counts of one player.
 * @return array|null null when the player is not in the tournament statistics
 * @since 2.0.0 (data of [doroto_display_player_statistics])
 */
function doroto_view_player_statistics(stdClass $tournament, int $player_id): ?array
{
	$statistics = maybe_unserialize($tournament->statistics);
	$whole_names = intval($tournament->whole_names);
	foreach (is_array($statistics) ? $statistics : [] as $stat) {
		if (intval($stat['player_id']) !== $player_id) {
			continue;
		}
		$counts = [];
		foreach (['playmates_L' => 'left', 'playmates_P' => 'right', 'opponents' => 'opponent'] as $key => $label) {
			foreach ((array) ($stat[$key] ?? []) as $row) {
				$other = intval($row['player_id']);
				if ($other === $player_id) {
					continue;
				}
				if (!isset($counts[$other])) {
					$counts[$other] = ['id' => $other, 'name' => doroto_find_player_name($other, $whole_names), 'left' => 0, 'right' => 0, 'opponent' => 0];
				}
				$counts[$other][$label] += intval($row['count']);
			}
		}
		$rows = array_values($counts);
		usort($rows, function ($a, $b) {
			return strcoll($a['name'], $b['name']);
		});
		return [
			'id' => $player_id,
			'name' => doroto_find_player_name($player_id, $whole_names),
			'rows' => $rows,
		];
	}
	return null;
}

/**
 * The whole view model of a tournament for the current user.
 * Draws pending matches first (like tournament-detail), so a freshly freed court
 * shows its next match right away.
 * @return array|null null when the tournament does not exist
 * @since 2.0.0
 */
function doroto_view_model(int $tournament_id): ?array
{
	if ($tournament_id <= 0 || !doroto_prepare_tournament($tournament_id)) {
		return null;
	}
	$draw_notice = doroto_offer_games($tournament_id, 0);
	$tournament = doroto_prepare_tournament($tournament_id);

	$user_id = get_current_user_id();
	$level = $user_id > 0 ? intval(doroto_is_admin($tournament_id)) : 0;
	$is_admin = $level > 0;
	$whole_names = intval($tournament->whole_names);
	$doubles = doroto_check_if_doubles($tournament);
	$players = array_map('intval', (array) (maybe_unserialize($tournament->players) ?: []));
	$is_player = $user_id > 0 && in_array($user_id, $players, true);
	$open = intval($tournament->open_registration) === 1;
	$closed = intval($tournament->close_tournament) === 1;
	$types = doroto_tournament_types();

	$ongoing = [];
	$played = [];
	$matches = maybe_unserialize($tournament->matches_list);
	foreach (is_array($matches) ? $matches : [] as $match) {
		$number = intval($match['match_number']);
		if ($number <= 0) {
			continue; // placeholder match created when the registration closes
		}
		$skipped = intval($match['hide']) === 1;
		$r1 = intval($match['result_1']);
		$r2 = intval($match['result_2']);
		$row = ['number' => $number, 'teams' => doroto_view_match_teams($match, $doubles, $whole_names)];
		if (!$skipped && $r1 === 0 && $r2 === 0) {
			$row['can_enter'] = doroto_service_may_enter_result($tournament, $number);
			$ongoing[] = $row;
		} else {
			$played[] = $row + ['result_1' => $r1, 'result_2' => $r2, 'skipped' => $skipped];
		}
	}
	usort($played, function ($a, $b) {
		return $b['number'] <=> $a['number'];
	});

	$four = maybe_unserialize($tournament->final_four);
	$final_result = maybe_unserialize($tournament->final_result);
	$final = null;
	if ($closed && intval($tournament->play_final_match) === 1) {
		$finalists = is_array($four) ? array_map('intval', array_values($four)) : [];
		$has_result = is_array($final_result) && isset($final_result['result_1']);
		$final = [
			'teams' => count($finalists) === 4 ? doroto_view_match_teams([
				'player_1' => $finalists[0], 'player_2' => $finalists[1],
				'player_3' => $finalists[2], 'player_4' => $finalists[3],
			], true, $whole_names) : null,
			'result_1' => $has_result ? intval($final_result['result_1']) : null,
			'result_2' => $has_result ? intval($final_result['result_2']) : null,
			'can_choose' => $is_admin,
			'can_enter' => count($finalists) === 4 && ($is_admin
				|| (intval($tournament->allow_input_results) === 1 && in_array($user_id, $finalists, true))),
		];
	}

	$settings = null;
	if ($is_admin) {
		$settings = [];
		foreach (array_keys(doroto_service_settings_fields()) as $key) {
			$settings[$key] = in_array($key, ['name', 'invitation'], true)
				? (string) $tournament->$key
				: (in_array($key, ['latitude', 'longitude'], true) ? floatval($tournament->$key) : intval($tournament->$key));
		}
	}

	return [
		'id' => $tournament_id,
		'name' => (string) $tournament->name,
		'invitation' => wp_kses((string) $tournament->invitation, doroto_allowed_html()),
		'type' => intval($tournament->tournament_type),
		'type_name' => $types[intval($tournament->tournament_type)] ?? '',
		'types' => $types,
		'doubles' => $doubles,
		'score_unit' => doroto_games_points($tournament),
		'state' => $open ? 'registration' : ($closed ? 'closed' : 'running'),
		'last_update' => intval($tournament->last_update),
		'player_count' => count($players),
		'max_players' => intval($tournament->max_players),
		'courts' => intval($tournament->courts_available),
		'payment_display' => intval($tournament->payment_display) === 1,
		'progress' => doroto_view_progress($tournament),
		'user' => [
			'id' => $user_id,
			'logged_in' => $user_id > 0,
			'is_player' => $is_player,
			'level' => $level,
			'is_admin' => $is_admin,
			'is_founder' => $user_id > 0 && doroto_service_is_founder($tournament),
			'can_create' => doroto_service_may_create_tournament(),
		],
		'standings' => doroto_view_standings($tournament, $user_id),
		'ongoing' => $ongoing,
		'played' => $played,
		'results_editable' => $is_admin && doroto_match_results_editable($tournament),
		'draw_notice' => $draw_notice !== '' ? wp_kses_post($draw_notice) : '',
		'final' => $final,
		'settings' => $settings,
		'links' => [
			'page' => doroto_tournament_page_url($tournament_id),
			'join' => doroto_join_url($tournament_id),
			'leave' => ($is_player && $open) ? doroto_leave_url($tournament_id) : '',
			'login' => wp_login_url(doroto_tournament_page_url($tournament_id)),
		],
	];
}

/**
 * REST API for the blocks: the view model and one player's statistics.
 * Readable by guests (like tournament-detail); private tournaments only by their players and organizers.
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/view/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_view',
		'permission_callback' => '__return_true',
	]);
	register_rest_route('doroto/v1', '/view/(?P<id>\d+)/player/(?P<player>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_view_player',
		'permission_callback' => '__return_true',
	]);
});

/**
 * May the current user see this tournament? Hidden ones (visibility 0) only for their people.
 * @since 2.0.0
 */
function doroto_view_visible(stdClass $tournament): bool
{
	if (intval($tournament->visibility) === 1) {
		return true;
	}
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return false;
	}
	$players = array_map('intval', (array) (maybe_unserialize($tournament->players) ?: []));
	return in_array($user_id, $players, true) || doroto_is_admin(intval($tournament->id)) > 0;
}

function doroto_rest_view(WP_REST_Request $request)
{
	wp_set_current_user(doroto_get_current_user_id_from_token());
	$tournament_id = intval($request['id']);
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament || !doroto_view_visible($tournament)) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	return new WP_REST_Response(doroto_view_model($tournament_id), 200);
}

function doroto_rest_view_player(WP_REST_Request $request)
{
	wp_set_current_user(doroto_get_current_user_id_from_token());
	$tournament = doroto_prepare_tournament(intval($request['id']));
	if (!$tournament || !doroto_view_visible($tournament)) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	$statistics = doroto_view_player_statistics($tournament, intval($request['player']));
	if ($statistics === null) {
		return new WP_REST_Response(['error_code' => 'remove_player_not_in_tournament'], 404);
	}
	return new WP_REST_Response($statistics, 200);
}
