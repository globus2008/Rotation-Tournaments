<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * REST route of the blocks: POST doroto/v1/block-action
 * body: {tournament_id, action, args: {...}}
 *
 * Runs one service for the user signed in to the website (cookie + X-WP-Nonce) and
 * answers with the translated message and the fresh view model, so the block updates
 * with a single request. The app keeps using its own routes.
 * Answer: {success, code, message, view} (view = null when the tournament was deleted).
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/block-action', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_block_action',
		'permission_callback' => function () {
			return get_current_user_id() > 0;
		},
	]);
});

/**
 * Call the service for a block action.
 * @return array|WP_Error
 * @since 2.0.0
 */
function doroto_block_action_dispatch(int $tid, string $action, array $args)
{
	$int = function ($key, $default = 0) use ($args) {
		return isset($args[$key]) ? intval($args[$key]) : $default;
	};
	switch ($action) {
		case 'enter_result':
			return doroto_service_enter_result($tid, $int('match'), $int('result_1', -1), $int('result_2', -1));
		case 'skip_matches':
			return doroto_service_skip_matches($tid, array_map('intval', (array) ($args['matches'] ?? [])));
		case 'change_result':
			return doroto_service_change_result($tid, $int('match'), $int('result_1', -1), $int('result_2', -1));
		case 'toggle_registration':
			return doroto_service_toggle_registration($tid);
		case 'toggle_tournament':
			return doroto_service_toggle_tournament($tid);
		case 'round_end':
			return doroto_service_round_end_action($tid, sanitize_key((string) ($args['choice'] ?? '')));
		case 'add_player':
			return doroto_service_add_player($tid, $int('player'));
		case 'create_player':
			return doroto_service_create_player($tid, (string) ($args['email'] ?? ''), (string) ($args['first_name'] ?? ''), (string) ($args['last_name'] ?? ''));
		case 'remove_player':
			return doroto_service_remove_player($tid, $int('player'));
		case 'set_active':
			return doroto_service_set_player_active($tid, $int('player'), !empty($args['active']));
		case 'special_group':
			return doroto_service_set_special_group($tid, $int('player'), !empty($args['add']));
		case 'payment':
			return doroto_service_set_payment($tid, $int('player'), !empty($args['paid']));
		case 'add_admin':
			return doroto_service_add_admin($tid, $int('player'));
		case 'remove_admin':
			return doroto_service_remove_admin($tid, $int('player'));
		case 'save_settings':
			return doroto_service_save_settings($tid, (array) ($args['settings'] ?? []));
		case 'final_four':
			return doroto_service_set_final_four($tid, $int('l1'), $int('p1'), $int('l2'), $int('p2'));
		case 'final_result':
			return doroto_service_set_final_result($tid, $int('result_1', -1), $int('result_2', -1));
		case 'join':
			return doroto_service_join($tid);
		case 'leave':
			return doroto_service_leave($tid);
		case 'add_tournament':
			return doroto_service_add_tournament($int('type', -1));
	}
	return doroto_service_error('invalid_action');
}

function doroto_rest_block_action(WP_REST_Request $request)
{
	$params = (array) $request->get_json_params();
	$tid = intval($params['tournament_id'] ?? 0);
	$action = sanitize_key((string) ($params['action'] ?? ''));
	$args = is_array($params['args'] ?? null) ? $params['args'] : [];

	$result = doroto_block_action_dispatch($tid, $action, $args);
	if (!is_wp_error($result) && isset($result['tournament_id'])) {
		$tid = intval($result['tournament_id']); // a new tournament
	}
	$message = doroto_service_message($result, $tid);
	$view = doroto_prepare_tournament($tid) ? doroto_view_model($tid) : null;

	if (is_wp_error($result)) {
		$data = $result->get_error_data();
		return new WP_REST_Response([
			'success' => false,
			'code' => $result->get_error_code(),
			'message' => $message !== '' ? $message : __('The action failed.', 'doubles-rotation-tournament'),
			'view' => $view,
		], is_array($data) && isset($data['status']) && $data['status'] >= 400 ? intval($data['status']) : 400);
	}
	return new WP_REST_Response([
		'success' => true,
		'code' => $result['action'],
		'message' => $message,
		'view' => $view,
		'tournament_id' => $tid,
	], 200);
}

/**
 * Users an organizer can add to the tournament or make organizers, for the blocks.
 * Same scope rules as users-all (setting only_admin_players).
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/block-candidates/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_block_candidates',
		'permission_callback' => function () {
			return get_current_user_id() > 0;
		},
	]);
});

function doroto_rest_block_candidates(WP_REST_Request $request)
{
	$tid = intval($request['id']);
	$tournament = doroto_prepare_tournament($tid);
	if (!$tournament) {
		return new WP_REST_Response(['code' => 'tournament_not_found'], 404);
	}
	if (doroto_is_admin($tid) < 1) {
		return new WP_REST_Response(['code' => 'auth_insufficient_permissions'], 403);
	}
	// users-all builds the list with the scope rules; reuse it.
	$inner = new WP_REST_Request('GET', '/doroto/v1/users-all');
	$inner->set_param('tournament_id', $tid);
	$response = rest_ensure_response(doroto_get_users_all($inner));
	$data = $response->get_data();
	$players = array_map('intval', (array) (maybe_unserialize($tournament->players) ?: []));
	$admins = array_map('intval', (array) (maybe_unserialize($tournament->admin_users) ?: []));
	$whole_names = intval($tournament->whole_names);

	$users = [];
	foreach ((array) ($data['admin_users'] ?? []) as $user) {
		$id = intval($user['id']);
		$users[] = [
			'id' => $id,
			'name' => doroto_find_player_name($id, $whole_names),
			'in_tournament' => in_array($id, $players, true),
			'organizer' => in_array($id, $admins, true),
		];
	}
	$organizers = [];
	foreach (array_values($admins) as $index => $id) {
		$organizers[] = ['id' => $id, 'name' => doroto_find_player_name($id, $whole_names), 'founder' => $index === 0];
	}
	return new WP_REST_Response(['users' => $users, 'organizers' => $organizers], 200);
}
