<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Tournament list for the blocks (2.0): one page of tournaments as light rows.
 * Uses the query of GET doroto/v1/tournaments (visibility, filters, search), so the
 * app and the web list the same tournaments.
 * @since 2.0.0
 */

/**
 * Filters of the list: value => label (texts of [doroto_filter_tournaments]); 20-29 = types.
 * @since 2.0.0
 */
function doroto_view_list_filters(): array
{
	$filters = [
		0 => __('No Filter', 'doubles-rotation-tournament'),
		1 => __('Only Open Registration', 'doubles-rotation-tournament'),
		2 => __('Still Playing', 'doubles-rotation-tournament'),
		3 => __('Only Closed', 'doubles-rotation-tournament'),
	];
	if (get_current_user_id() > 0) {
		$filters += [
			4 => __('Where I Am Logged In', 'doubles-rotation-tournament'),
			5 => __('Where I Am Not Logged In', 'doubles-rotation-tournament'),
			6 => __('Where I Am Admin', 'doubles-rotation-tournament'),
			7 => __('Where I Am Not Admin', 'doubles-rotation-tournament'),
		];
	}
	foreach (doroto_tournament_types() as $type => $name) {
		$filters[intval($type)] = $name;
	}
	return $filters;
}

/**
 * One page of the tournament list.
 * @param string $page_url page that shows a tournament (tournament_id is added)
 * @return array {total, offset, limit, rows: [{id, name, type_name, state, players, date, url, is_player, is_admin}]}
 * @since 2.0.0
 */
function doroto_view_list(int $filter, string $search, int $offset, int $limit, string $page_url): array
{
	$limit = max(1, min(50, $limit));
	$offset = max(0, $offset);
	$request = new WP_REST_Request('GET', '/doroto/v1/tournaments');
	$request->set_param('filter', $filter);
	$request->set_param('search', $search);
	$request->set_param('limit', $limit);
	$request->set_param('offset', $offset);
	$data = rest_ensure_response(doroto_get_all_tournaments_optimized($request))->get_data();

	$types = doroto_tournament_types();
	$user_id = get_current_user_id();
	$rows = [];
	foreach ((array) ($data['data'] ?? []) as $row) {
		$players = array_map('intval', (array) (maybe_unserialize($row->players) ?: []));
		$admins = array_map('intval', (array) (maybe_unserialize($row->admin_users) ?: []));
		$state = intval($row->open_registration) === 1 ? 'registration' : (intval($row->close_tournament) === 1 ? 'closed' : 'running');
		$rows[] = [
			'id' => intval($row->id),
			'name' => (string) $row->name,
			'type_name' => $types[intval($row->tournament_type)] ?? '',
			'state' => $state,
			'state_text' => [
				'registration' => __('Registration open', 'doubles-rotation-tournament'),
				'running' => __('In progress', 'doubles-rotation-tournament'),
				'closed' => __('Closed', 'doubles-rotation-tournament'),
			][$state],
			'players' => count($players),
			'date' => mysql2date(get_option('date_format'), get_date_from_gmt((string) $row->create_date)),
			'url' => add_query_arg('tournament_id', intval($row->id), $page_url),
			'is_player' => $user_id > 0 && in_array($user_id, $players, true),
			'is_admin' => $user_id > 0 && in_array($user_id, $admins, true),
		];
	}
	$total = intval($data['total'] ?? 0);
	return [
		'total' => $total,
		'offset' => $offset,
		'limit' => $limit,
		'has_prev' => $offset > 0,
		'has_next' => $offset + $limit < $total,
		'page_text' => $total > 0
			? sprintf('%d–%d / %d', $offset + 1, min($offset + $limit, $total), $total)
			: '',
		'rows' => $rows,
	];
}

/**
 * REST: GET doroto/v1/view-list?filter=&search=&offset=&limit=&page=<page URL>
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/view-list', [
		'methods' => 'GET',
		'callback' => function (WP_REST_Request $request) {
			wp_set_current_user(doroto_get_current_user_id_from_token());
			$page = esc_url_raw((string) $request->get_param('page'));
			if ($page === '' || wp_validate_redirect($page, '') === '') {
				$page = doroto_tournament_page_url(0);
			}
			return new WP_REST_Response(doroto_view_list(
				intval($request->get_param('filter')),
				sanitize_text_field((string) $request->get_param('search')),
				intval($request->get_param('offset')),
				intval($request->get_param('limit') ?: 10),
				$page
			), 200);
		},
		'permission_callback' => '__return_true',
	]);
});
