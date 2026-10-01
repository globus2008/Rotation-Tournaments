<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * REST API: player registration and log in
 * @since 1.4.7
 * @version 1.5.6(password not generated) 
 */

add_action('rest_api_init', function () {
	register_rest_route('player', '/register', array(
		'methods' => 'POST',
		'callback' => 'doroto_handle_player_registration',
		'permission_callback' => '__return_true',
	));
});

function doroto_handle_player_registration(WP_REST_Request $request)
{
	$params = $request->get_json_params();

	$email = sanitize_email($params['email'] ?? '');
	$name = sanitize_text_field($params['name'] ?? '');
	$surname = sanitize_text_field($params['surname'] ?? '');

	if (!empty($params['password'])) {
		$password = $params['password'];
	} else {
		$password = wp_generate_password(12, false);
	}

	if (empty($email) || empty($name) || empty($surname) || empty($password)) {
		return new WP_REST_Response(['error_code' => 'registration_incomplete_data'], 400);
	}

	if (email_exists($email)) {
		return new WP_REST_Response(['error_code' => 'registration_email_exists'], 409);
	}

	$username = sanitize_user(explode('@', $email)[0] . '_' . wp_generate_password(4, false));
	$user_id = wp_create_user($username, $password, $email);

	if (is_wp_error($user_id)) {
		return new WP_REST_Response(['error_code' => 'registration_creation_failed'], 500);
	}

	wp_update_user([
		'ID' => $user_id,
		'first_name' => $name,
		'last_name' => $surname,
		'display_name' => $name . ' ' . $surname,
	]);

	$user = new WP_User($user_id);
	$user->set_role('subscriber');

	$accessToken = bin2hex(random_bytes(32));
	$accessTokenExpiration = time() + 86400;

	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // valid 90 days

	update_user_meta($user_id, 'doroto_access_token', $accessToken);
	update_user_meta($user_id, 'doroto_access_token_expiration', $accessTokenExpiration);
	update_user_meta($user_id, 'doroto_refresh_token', $refreshToken);
	update_user_meta($user_id, 'doroto_refresh_token_expiration', $refreshTokenExpiration);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'user_registered',
		'access_token' => $accessToken,
		'refresh_token' => $refreshToken,
		'user_id' => $user_id,
		'user' => [
			'ID' => $user_id,
			'email' => $email,
			'username' => $name . ' ' . $surname, // Můžeme použít display_name
			'name' => $name,
			'surname' => $surname
		]
	], 200);
}

/**
 * REST API: player log in or log out
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('player', '/login', array(
		'methods' => 'POST',
		'callback' => 'doroto_handle_login',
		'permission_callback' => '__return_true',
	));
});

function doroto_handle_login(WP_REST_Request $request)
{
	$params = $request->get_json_params();

	$email = sanitize_email($params['email'] ?? '');
	$password = $params['password'] ?? '';

	if (empty($email) || empty($password)) {
		return new WP_REST_Response(['error_code' => 'login_missing_credentials'], 400);
	}

	$user = get_user_by('email', $email);
	if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
		return new WP_REST_Response(['error_code' => 'login_invalid_credentials'], 401);
	}

	// 1. Přístupový token (krátká platnost)
	$accessToken = bin2hex(random_bytes(32));
	$accessTokenExpiration = time() + 86400; // Stále 24 hodin

	// 2. Obnovovací token (dlouhá platnost)
	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // 90 dní

	// Uložení obou tokenů do databáze
	update_user_meta($user->ID, 'doroto_access_token', $accessToken);
	update_user_meta($user->ID, 'doroto_access_token_expiration', $accessTokenExpiration);
	update_user_meta($user->ID, 'doroto_refresh_token', $refreshToken);
	update_user_meta($user->ID, 'doroto_refresh_token_expiration', $refreshTokenExpiration);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'login_successful',
		'access_token' => $accessToken,
		'refresh_token' => $refreshToken,
		'user_id' => $user->ID,
		'user' => [
			'ID' => $user->ID,
			'email' => $user->user_email,
			'username' => $user->user_login,
			'name' => $user->first_name,
			'surname' => $user->last_name
		]
	], 200);
}

/**
 * Registruje REST API endpoint pro obnovení tokenu.
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/refresh-token', [
		'methods' => 'POST',
		'callback' => 'doroto_handle_refresh_token',
		'permission_callback' => '__return_true', // Oprávnění se kontroluje uvnitř funkce
	]);
});

/**
 * Zpracovává požadavek na obnovení tokenu.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function doroto_handle_refresh_token($request)
{
	$params = $request->get_json_params();
	$refreshToken = sanitize_text_field($params['refresh_token'] ?? '');

	// 1. Ověření, zda byl token poslán
	if (empty($refreshToken)) {
		return new WP_REST_Response(['error_code' => 'refresh_token_missing'], 400);
	}

	// 2. Nalezení uživatele podle refresh tokenu
	$users = get_users([
		'meta_key' => 'doroto_refresh_token',
		'meta_value' => $refreshToken,
		'number' => 1,
		'fields' => ['ID'],
	]);

	if (empty($users)) {
		return new WP_REST_Response(['error_code' => 'refresh_token_invalid'], 401);
	}

	$user_id = $users[0]->ID;

	// 3. Kontrola platnosti (expirace) refresh tokenu
	$expiration = get_user_meta($user_id, 'doroto_refresh_token_expiration', true);

	if (!$expiration || time() >= intval($expiration)) {
		// Token vypršel nebo neexistuje, pro jistotu ho smažeme
		delete_user_meta($user_id, 'doroto_refresh_token');
		delete_user_meta($user_id, 'doroto_refresh_token_expiration');
		return new WP_REST_Response(['error_code' => 'refresh_token_expired'], 401);
	}

	// 4. Pokud je vše v pořádku, generujeme nové tokeny
	$newAccessToken = bin2hex(random_bytes(32));
	$newAccessTokenExpiration = time() + 86400; // Platnost 24 hodin

	$newRefreshToken = $refreshToken;

	// 5. Aktualizace databáze s novými tokeny
	update_user_meta($user_id, 'doroto_access_token', $newAccessToken);
	update_user_meta($user_id, 'doroto_access_token_expiration', $newAccessTokenExpiration);

	// 6. Vrácení nových tokenů klientské aplikaci
	return new WP_REST_Response([
		'success' => true,
		'action' => 'tokens_refreshed',
		'access_token' => $newAccessToken,
		'refresh_token' => $newRefreshToken,
	], 200);
}

/**
 * REST API: provide user token
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_get_current_user_id_from_token()
{
	$headers = getallheaders();
	$token = '';

	if (isset($headers['Authorization'])) {
		$token = $headers['Authorization'];
	} elseif (isset($headers['HTTP_AUTHORIZATION'])) {
		$token = $headers['HTTP_AUTHORIZATION'];
	} elseif (function_exists('apache_request_headers')) {
		$apacheHeaders = apache_request_headers();
		if (isset($apacheHeaders['Authorization'])) {
			$token = $apacheHeaders['Authorization'];
		}
	}

	$token = sanitize_text_field(str_replace('Bearer ', '', $token));

	if (empty($token)) {
		return 0;
	}

	if (!empty($token)) {
		$users = get_users([
			'meta_key' => 'doroto_access_token',
			'meta_value' => $token,
			'number' => 1,
			'fields' => ['ID'],
		]);
		if (!empty($users)) {
			$user_id = $users[0]->ID;
			$expiration = get_user_meta($user_id, 'doroto_access_token_expiration', true);

			if ($expiration && time() < intval($expiration)) {
				return (int) $user_id;
			}
		}
	}
	return 0;
}

/**
 * REST API: list of all tournaments
 * @since 1.4.7
 * @version 1.4.8 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournaments', [
		'methods' => 'GET',
		'callback' => 'doroto_get_all_tournaments_optimized',
		'permission_callback' => '__return_true',
		'args' => [
			'filter' => [
				'required' => false,
				'validate_callback' => function ($param) {
					return is_numeric($param);
				},
			],
			'limit' => [
				'required' => false,
				'default' => 10,
				'validate_callback' => function ($param) {
					return is_numeric($param);
				},
			],
			'offset' => [
				'required' => false,
				'default' => 0,
				'validate_callback' => function ($param) {
					return is_numeric($param);
				},
			],
			'search' => [
				'required' => false,
				'validate_callback' => function ($param) {
					return is_string($param);
				},
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	]);
});


function doroto_get_all_tournaments_optimized(WP_REST_Request $request)
{
	global $wpdb;

	// Načtení parametrů - beze změny
	$filter_option = intval($request->get_param('filter') ?? 0);
	$limit = intval($request->get_param('limit') ?? 10);
	$offset = intval($request->get_param('offset') ?? 0);
	$search_term = $request->get_param('search');

	$current_user_id = doroto_get_current_user_id_from_token();
	$table_name = $wpdb->prefix . 'doroto_tournaments';

	$select_clause = "SELECT *";
	$count_clause = "SELECT COUNT(*)";
	$from_clause = "FROM $table_name";
	$where_clauses = [];
	$params = [];

	// Krok 1: Základní podmínka viditelnosti (vždy se aplikuje) - beze změny
	if ($current_user_id > 0) {
		$serialized_user_id_like = '%' . $wpdb->esc_like('i:' . $current_user_id . ';') . '%';
		$where_clauses[] = "(visibility = 1 OR (visibility = 0 AND (players LIKE %s OR admin_users LIKE %s)))";
		$params[] = $serialized_user_id_like;
		$params[] = $serialized_user_id_like;
	} else {
		$where_clauses[] = "visibility = 1";
	}

	// --- Krok 2: Aplikace dalších filtrů (OPRAVENÁ ČÁST) ---

	// --- ZMĚNA: Spojení filtrů podle typu a stavu do jedné if-else struktury ---
	if ($filter_option >= 20 && $filter_option <= 29) {
		// Filtr podle typu hry
		$where_clauses[] = "tournament_type = %d";
		$params[] = $filter_option;
	} else {
		// Filtr podle stavu (aplikuje se jen pokud to není filtr typu hry)
		switch ($filter_option) {
			case 1:
				$where_clauses[] = "open_registration = 1";
				break;
			case 2:
				$where_clauses[] = "open_registration = 0 AND close_tournament = 0";
				break;
			case 3:
				$where_clauses[] = "close_tournament = 1";
				break;
		}
	}
	// --- KONEC ZMĚNY ---

	// Filtr podle role uživatele (4-7) - beze změny
	if ($current_user_id > 0 && in_array($filter_option, [4, 5, 6, 7])) {
		$serialized_user_id_like = '%' . $wpdb->esc_like('i:' . $current_user_id . ';') . '%';
		switch ($filter_option) {
			case 4:
				$where_clauses[] = "players LIKE %s";
				$params[] = $serialized_user_id_like;
				break;
			case 5:
				$where_clauses[] = "players NOT LIKE %s";
				$params[] = $serialized_user_id_like;
				break;
			case 6:
				$where_clauses[] = "admin_users LIKE %s";
				$params[] = $serialized_user_id_like;
				break;
			case 7:
				$where_clauses[] = "admin_users NOT LIKE %s";
				$params[] = $serialized_user_id_like;
				break;
		}
	}

	// Filtr podle vyhledávacího textu - beze změny
	if (!empty($search_term)) {
		$where_clauses[] = "name LIKE %s";
		$params[] = '%' . $wpdb->esc_like($search_term) . '%';
	}

	// Zbytek funkce pro sestavení a vykonání dotazu - beze změny
	$where_sql = implode(' AND ', $where_clauses);

	$total_query = $wpdb->prepare("$count_clause $from_clause WHERE $where_sql", $params);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total = $wpdb->get_var($total_query);

	$order_by_sql = "ORDER BY id DESC";
	$limit_sql = "LIMIT %d OFFSET %d";
	$params[] = $limit;
	$params[] = $offset;

	$data_query = $wpdb->prepare("$select_clause $from_clause WHERE $where_sql $order_by_sql $limit_sql", $params);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$results = $wpdb->get_results($data_query);

	return rest_ensure_response([
		'total' => (int) $total,
		'data' => $results,
	]);
}

/**
 * REST API: settings for tournaments
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/settings', [
		'methods' => 'GET',
		'callback' => 'doroto_get_settings_rest',
		'permission_callback' => '__return_true',
	]);
});

function doroto_get_settings_rest()
{
	$settings = get_option('doroto_settings');
	$display_rows = isset($settings['display_rows']) ? intval($settings['display_rows']) : 10;
	return rest_ensure_response(['display_rows' => $display_rows]);
}


/**
 * REST API: provide data about a tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournaments/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_get_tournament_details',
		'permission_callback' => '__return_true',
	]);
});

function doroto_get_tournament_details(WP_REST_Request $data)
{
	global $wpdb;

	$tournament_id = intval($data['id']);
	$table_name = $wpdb->prefix . 'doroto_tournaments';

	$tournament = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $tournament_id),
		ARRAY_A
	);

	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	$players_raw = $tournament['players'];
	$players = maybe_unserialize($players_raw);
	if (!is_array($players)) {
		$players = [];
	}

	$tournament['players_count'] = count($players);
	$tournament['players'] = $players;
	$tournament['status'] = $tournament['close_tournament'] == 1 ? 'Closed' : 'Open';
	$tournament['registration_status'] = $tournament['open_registration'] == 1 ? 'Open' : 'Closed';

	$admin_names = [];
	$admin_users = maybe_unserialize($tournament['admin_users']);
	$whole_names = intval($tournament['whole_names']);
	foreach ($admin_users as $admin_id) {
		$admin_names[] = doroto_find_player_name($admin_id, $whole_names);
	}
	$tournament['admin_output'] = sanitize_text_field(implode(' ' . __("&", "doubles-rotation-tournament") . ' ', $admin_names));

	return $tournament;
}


/**
 * REST API: main endpoint for many functions, provide data from the tournament
 * @since 1.4.7
 * @version 1.5.5(add score unit) 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-detail/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_get_tournament_detail',
		'permission_callback' => '__return_true',
	]);
});

function doroto_get_tournament_detail(WP_REST_Request $data)
{
	global $wpdb;
	$tournament_id = intval($data['id']);
	$current_user_id = (int) doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);

	if ($tournament_id === 0) {
		return [
			'type_variables' => doroto_types_variables(),
			'tournament_types' => doroto_tournament_types(),
			'doroto_settings' => get_option('doroto_settings'),
		];
	}

	$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id), ARRAY_A);
	if (!$tournament) {
		return new WP_Error('not_found', 'Tournament not found', ['status' => 404]);
	}
	$tournament_diff = doroto_prepare_tournament($tournament_id);
	$offer_html = doroto_offer_games($tournament_id, 0);
	if ($offer_html != "")
		$offer_html = 'display warning';

	$fields_to_unserialize = [
		'players',
		'statistics',
		'payment_done',
		'special_group',
		'matches_list',
		'final_four',
		'final_result',
		'admin_users',
	];

	foreach ($fields_to_unserialize as $field) {
		if (isset($tournament[$field])) {
			$tournament[$field] = maybe_unserialize($tournament[$field]);
		} else {
			$tournament[$field] = [];
		}
	}
	if (!is_array($tournament['final_result'])) {
		$tournament['final_result'] = [];
	}

	if (isset($tournament['statistics']) && is_array($tournament['statistics'])) {
		usort($tournament['statistics'], function ($a, $b) {
			return $b['ratio'] <=> $a['ratio'];
		});
	}

	if (!is_array($tournament['special_group'])) {
		$tournament['special_group'] = [];
	}
	foreach ($tournament['statistics'] as &$stat) {
		$stat['player_id'] = intval($stat['player_id']);
		$stat['active'] = intval($stat['active']);
		$stat['games'] = intval($stat['games']);
		$stat['won'] = intval($stat['won']);
		$stat['lost'] = intval($stat['lost']);
		$stat['ratio'] = floatval($stat['ratio']);
		$stat['rest'] = isset($stat['rest']) ? intval($stat['rest']) : 0;
		$stat['display_name'] = doroto_find_player_name($stat['player_id'], $tournament['whole_names']);
	}

	if (doroto_check_if_doubles($tournament_diff)) {
		$players_on_court = 4;
	} else {
		$players_on_court = 2;
	}

	$games = $tournament['matches_list'];
	$matches_played_count = 0;
	$game_played_count = 0;
	if (is_array($games)) {
		foreach ($games as $match) {
			if ($match['played'] == 1 && $match['hide'] == 0 && ($match['result_1'] != 0 || $match['result_2'] != 0)) {
				$matches_played_count++;
				$game_played_count += $match['result_1'] + $match['result_2'];
			}
		}
	}

	$tournament['matches_played_count'] = $matches_played_count;
	$tournament['game_played_count'] = $game_played_count;
	$tournament['players_on_court'] = $players_on_court;
	$tournament['score_unit'] = doroto_games_points($tournament_diff);
	$tournament['open_registration'] = intval($tournament['open_registration']);
	$tournament['close_tournament'] = intval($tournament['close_tournament']);
	$tournament['max_players'] = intval($tournament['max_players']);
	$tournament['average_result'] = intval($tournament['average_result']);
	$tournament['announce_round_end'] = intval($tournament['announce_round_end']);
	$tournament['allow_input_results'] = intval($tournament['allow_input_results']);
	$tournament['play_final_match'] = intval($tournament['play_final_match']);
	$tournament['two_special_group'] = intval($tournament['two_special_group']);
	$tournament['two_out_group'] = intval($tournament['two_out_group']);
	$tournament['games_hour'] = intval($tournament['games_hour']);
	$tournament['minimum_matches'] = intval($tournament['minimum_matches']);
	$tournament['payment_display'] = intval($tournament['payment_display']);
	$tournament['min_not_playing'] = intval($tournament['min_not_playing']);
	$tournament['special_group_can_win'] = intval($tournament['special_group_can_win']);
	$tournament['temp_suspend_winner'] = intval($tournament['temp_suspend_winner']);
	$tournament['whole_names'] = intval($tournament['whole_names']);
	$tournament['visibility'] = intval($tournament['visibility']);
	$tournament['current_user_id'] = $current_user_id;
	$tournament['trend'] = doroto_find_trend_for_players($tournament_id, $tournament_diff);

	if ($tournament['close_tournament'] == 1) {
		$tournament['winners'] = doroto_get_winner($tournament_id, $tournament['whole_names']);
	} else {
		$tournament['winners'] = [];
	}

	$tournament['type_variables'] = doroto_types_variables();
	$tournament['tournament_types'] = doroto_tournament_types();
	$tournament['doroto_settings'] = get_option('doroto_settings');
	$tournament['special_group_message'] = doroto_empty_special_group_notice($tournament_diff);
	$tournament['round_end_notice_html'] = $offer_html;
	$tournament['is_admin'] = doroto_is_admin($tournament_id);
	//output 0: is not admin
	//output 1: is admin in DoRoTo
	//output 2: is admin in DoRoTo and a web administrator

	$super_admin_id = (is_array($tournament['admin_users']) && !empty($tournament['admin_users']))
		? (int) $tournament['admin_users'][0]
		: 0;

	// Upravená podmínka:
	$tournament['is_super_admin'] = ($current_user_id === $super_admin_id || $tournament['is_admin'] == 2) ? 1 : 0;

	$tournament['debug'] = [
		'current_user_id' => $current_user_id,
		'user_roles' => wp_get_current_user()->roles,
		'admin_users' => $tournament['admin_users'],
		'is_admin' => $tournament['is_admin'],
		'special_group_message' => $tournament['special_group_message'],
	];

	$admin_names = [];
	foreach ($tournament['admin_users'] as $admin_id) {
		$admin_names[] = doroto_find_player_name($admin_id, $tournament['whole_names']);
	}
	$tournament['admin_names'] = implode(' ' . __("&", "doubles-rotation-tournament") . ' ', $admin_names);

	$players_raw = maybe_unserialize($tournament['players']);
	$playing = maybe_unserialize($tournament['playing']);
	$statistics = maybe_unserialize($tournament['statistics']);

	if (!is_array($players_raw))
		$players_raw = [];
	if (!is_array($playing))
		$playing = [];
	if (!is_array($statistics))
		$statistics = [];

	$removable_ids = [];

	foreach ($statistics as $player_info) {
		if (
			in_array($player_info['player_id'], $players_raw) &&
			intval($player_info['games']) === 0 &&
			!in_array($player_info['player_id'], $playing)
		) {
			$removable_ids[] = intval($player_info['player_id']);
		}
	}

	$removable_users = [];

	if (!empty($removable_ids)) {
		$placeholders = implode(',', array_fill(0, count($removable_ids), '%d'));
		$sql = "SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ($placeholders)";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare($sql, ...$removable_ids);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$removable_users = $wpdb->get_results($sql, ARRAY_A);

		usort($removable_users, function ($a, $b) {
			return strcasecmp($a['display_name'], $b['display_name']);
		});
	}


	$tournament['users'] = $removable_users;
	return $tournament;
}


/**
 * REST API: save tournament data
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-save', [
		'methods' => 'POST',
		'callback' => 'doroto_tournament_save_via_api',
		'permission_callback' => '__return_true',
	]);
});

function doroto_tournament_save_via_api(WP_REST_Request $request)
{
	global $wpdb;

	$current_user_id = doroto_get_current_user_id_from_token();

	wp_set_current_user($current_user_id);
	$fields = [];

	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_logged_in'], 401);
	}

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$offer_html = doroto_offer_games($tournament_id, 0);

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$is_admin = doroto_is_admin($tournament_id);
	if ($is_admin == 0) {
		return new WP_REST_Response(['error_code' => 'not_admin_permission'], 403);
	}

	$is_final_pair = $request->get_param('l1') !== null
		&& $request->get_param('p1') !== null
		&& $request->get_param('l2') !== null
		&& $request->get_param('p2') !== null;

	$is_final_result = $request->get_param('result_1') !== null
		&& $request->get_param('result_2') !== null;

	$close_flag = intval($tournament->close_tournament);
	$close_date_str = $tournament->close_date; // format "YYYY-MM-DD HH:MM:SS"

	$allow_within_24h = false;
	if ($close_flag === 1 && !empty($close_date_str)) {
		$closed_ts = strtotime($close_date_str);
		if ($closed_ts !== false && (time() - $closed_ts) < 24 * 3600) {
			$allow_within_24h = true;
		}
	}

	if (
		$close_flag === 1
		&& !$allow_within_24h
		&& !$is_final_pair
		&& !$is_final_result
	) {
		return new WP_REST_Response(['error_code' => 'tournament_closed_for_changes'], 403);
	}


	$res1 = $request->get_param('result_1');
	$res2 = $request->get_param('result_2');
	if ($res1 !== null && $res2 !== null) {
		$fields['final_result'] = serialize([
			'result_1' => intval($res1),
			'result_2' => intval($res2),
		]);
		$fields['close_tournament'] = 1;
	}

	$delete_tournament = intval($request->get_param('delete_tournament'));
	$empty_tournament = intval($request->get_param('empty_tournament'));
	$new_post = intval($request->get_param('new_post'));

	$allowed_fields = [
		'name' => 'sanitize_text_field',
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
			$allowed_html = doroto_allowed_html();
			return wp_kses($val, $allowed_html);
		},
		'latitude' => 'floatval',
		'longitude' => 'floatval',
	];

	foreach ($allowed_fields as $key => $sanitizer) {
		if ($request->get_param($key) !== null) {
			$fields[$key] = is_callable($sanitizer)
				? $sanitizer($request->get_param($key))
				: $request->get_param($key);
		}
	}
	if ($request->get_param('result_1') !== null && $request->get_param('result_2') !== null) {
		$fields['final_result'] = serialize([
			'result_1' => intval($request->get_param('result_1')),
			'result_2' => intval($request->get_param('result_2')),
		]);
	}

	$l1 = intval($request->get_param('l1'));
	$p1 = intval($request->get_param('p1'));
	$l2 = intval($request->get_param('l2'));
	$p2 = intval($request->get_param('p2'));

	if ($l1 > 0 && $p1 > 0 && $l2 > 0 && $p2 > 0) {
		$fields['final_four'] = serialize([
			'l1' => $l1,
			'p1' => $p1,
			'l2' => $l2,
			'p2' => $p2,
		]);
		$fields['close_tournament'] = 1;
	}

	$new_last_update = round(microtime(true) * 1000);
	if ($delete_tournament) {
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
		return new WP_REST_Response([
			'success' => true,
			'action' => 'tournament_deleted'
		]);
	} else {
		if ($empty_tournament) {
			$fields['statistics'] = '';
			$fields['matches_list'] = '';
			$fields['open_registration'] = 1;
			$fields['close_tournament'] = 0;
			$fields['final_result'] = '';
			$fields['final_four'] = '';
			$fields['playing'] = '';
			$fields['close_date'] = '9999-09-09 09:09:09';
		}

		if (!empty($fields)) {
			$fields['last_update'] = $new_last_update;
			$wpdb->update(
				$wpdb->prefix . 'doroto_tournaments',
				$fields,
				['id' => $tournament_id]
			);
		}
		if (!$is_final_result) {
			$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id));

			$statistics = doroto_create_statistics_table($tournament, unserialize($tournament->players), intval($tournament->whole_names));
			$result = $wpdb->update("{$wpdb->prefix}doroto_tournaments", [
				'statistics' => serialize($statistics),
				'last_update' => $new_last_update
			], ['id' => $tournament_id]);
			if ($result === false) {
				return new WP_REST_Response(['error_code' => 'db_update_failed'], 500);
			}
			doroto_tournament_progress($tournament_id);
		}

		if ($new_post) {
			$current_user = wp_get_current_user();
			$only_admin_posts = intval(doroto_read_settings('only_admin_posts', 1));
			if ((($only_admin_posts == 1) && (doroto_is_admin($tournament_id) == 2)) || (($only_admin_posts == 0) && (doroto_is_admin($tournament_id) > 0))) {
				doroto_create_new_tournament_post($tournament_id);
			} else {
				return new WP_REST_Response(['error' => 'You do not have the necessary rights to create a post.'], 403);
			}
		}
	}

	$saved = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT final_result FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
			$tournament_id
		)
	);
	$saved_un = maybe_unserialize($saved);
	$wpdb->update($wpdb->prefix . 'doroto_tournaments', ['last_update' => $new_last_update], ['id' => $tournament_id]);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'tournament_updated',
		'last_update' => $new_last_update,
		'debug' => [
			'user_id' => $current_user_id,
			'headers' => getallheaders(),
			'is_admin' => doroto_is_admin($tournament_id),
			'closed' => intval($tournament->close_tournament),
			'updated_fields' => array_keys($fields),
			'json_params' => $request->get_json_params(),
			'all_params' => $request->get_params(),
			'fields' => $fields,
			'after_final_result' => $saved_un,
			'doroto_offer_games' => $offer_html,
		]
	]);
}

/**
 * REST API: create a tournament function
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/add-tournament', array(
		'methods' => 'POST',
		'callback' => 'doroto_rest_add_tournament',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	));
});


function doroto_rest_add_tournament(WP_REST_Request $request)
{
	global $wpdb;
	$result = doroto_get_current_user_id_from_token();
	if ($result instanceof WP_REST_Response) {
		return $result;
	}
	$current_user_id = $result;


	$headers = getallheaders();
	$raw_token = '';
	if (isset($headers['Authorization'])) {
		$raw_token = $headers['Authorization'];
	} elseif (isset($headers['HTTP_AUTHORIZATION'])) {
		$raw_token = $headers['HTTP_AUTHORIZATION'];
	}

	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authenticated'], 401);
	}

	wp_set_current_user($current_user_id);

	$params = $request->get_json_params();

	if (!isset($params['tournament_type'])) {
		return new WP_REST_Response(['error_code' => 'create_missing_type'], 400);
	}

	if (doroto_read_settings('only_admin_creates', 0) == 1) {
		$current_user = wp_get_current_user();
		if (
			!in_array('administrator', $current_user->roles) &&
			!in_array('editor', $current_user->roles) &&
			!in_array('author', $current_user->roles)
		) {
			return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
		}
	}

	$tournament_type = intval($params['tournament_type']);
	$tournament_id = doroto_insert_tournament($tournament_type);

	if ($tournament_id > 0) {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'tournament_created',
			'tournament_id' => $tournament_id
		], 200);
	} else {
		return new WP_REST_Response(['error_code' => 'create_failed'], 500);
	}
}

/**
 * REST API: log in a player to the tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-register/(?P<id>\d+)', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_register_player',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_register_player(WP_REST_Request $request)
{
	global $wpdb;

	$result = doroto_get_current_user_id_from_token();
	if ($result instanceof WP_REST_Response) {
		return $result;
	}
	$current_user_id = $result;
	wp_set_current_user($current_user_id);

	$tournament_id = intval($request['id']);
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_logged_in'], 401);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if ($tournament->open_registration != '1') {
		return new WP_REST_Response(['error_code' => 'registration_is_closed'], 403);
	}

	$players = maybe_unserialize($tournament->players);
	if (!is_array($players))
		$players = [];

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group))
		$special_group = [];

	$already_registered = in_array($current_user_id, $players);
	$new_last_update = round(microtime(true) * 1000);
	if ($already_registered) {
		$statistics = maybe_unserialize($tournament->statistics);
		$statistics_new = doroto_remove_player_from_statistics_table($tournament, $statistics, $current_user_id);

		if ($statistics === $statistics_new) {
			return new WP_REST_Response(['error_code' => 'unregister_player_has_played'], 400);
		}

		$players = array_values(array_diff($players, [$current_user_id]));
		$special_group = array_values(array_diff($special_group, [$current_user_id]));

		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'players' => maybe_serialize($players),
				'statistics' => serialize($statistics_new),
				'special_group' => maybe_serialize($special_group),
				'last_update' => $new_last_update // Použijeme novou hodnotu
			],
			['id' => $tournament_id]
		);

		doroto_tournament_progress($tournament_id);

		return new WP_REST_Response([
			'success' => true,
			'action' => 'unregistered',
			'message' => 'You have been removed from the tournament.',
			'last_update' => $new_last_update, // Použijeme novou hodnotu
			'player_id' => $current_user_id,
			'tournament_id' => $tournament_id,
		]);
	} else {
		$max_players = intval($tournament->max_players);
		if ($max_players > 0 && count($players) >= $max_players) {
			return new WP_REST_Response(['error_code' => 'registration_max_players'], 400);
		}

		$players[] = $current_user_id;
		$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));

		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'players' => serialize($players),
				'statistics' => serialize($statistics),
				'last_update' => $new_last_update
			],
			['id' => $tournament_id]
		);

		doroto_tournament_progress($tournament_id);

		return new WP_REST_Response([
			'success' => true,
			'action' => 'registered',
			'message' => 'You have successfully logged into the tournament.',
			'player_id' => $current_user_id,
			'tournament_id' => $tournament_id,
		]);
	}
}

/**
 * REST API: provide data about your website to the central point
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/website-info', [
		'methods' => 'GET',
		'callback' => 'doroto_get_website_info',
		'permission_callback' => '__return_true'
	]);
});

function doroto_get_website_info(WP_REST_Request $request)
{
	$site_url = get_option('siteurl');
	$privacy_page_id = get_option('doroto_privacy_policy_page_id');
	$terms_page_id = get_option('doroto_terms_of_service_page_id');

	$privacy_policy_url = $privacy_page_id ? rtrim($site_url, '/') . '/?page_id=' . $privacy_page_id : '';
	$terms_of_service_url = $terms_page_id ? rtrim($site_url, '/') . '/?page_id=' . $terms_page_id : '';
	$doroto_url = doroto_get_main_url();

	return [
		'website_address' => get_option('siteurl'),
		'website_description' => doroto_read_settings('website_description', get_option('blogdescription')),
		'website_visible' => doroto_read_settings('website_visible', 1),
		'website_allow_register' => get_option('users_can_register') ? 1 : 0,
		'website_latitude' => doroto_read_settings('latitude', 50),
		'website_longitude' => doroto_read_settings('longitude', 15),
		'singles_tennis' => intval(doroto_read_settings('singles_tennis', 1)),
		'doubles_tennis' => intval(doroto_read_settings('doubles_tennis', 1)),
		'singles_table_tennis' => intval(doroto_read_settings('singles_table_tennis', 1)),
		'doubles_table_tennis' => intval(doroto_read_settings('doubles_table_tennis', 1)),
		'singles_padel' => intval(doroto_read_settings('singles_padel', 1)),
		'doubles_padel' => intval(doroto_read_settings('doubles_padel', 1)),
		'beach_volleyball' => intval(doroto_read_settings('beach_volleyball', 1)),
		'squash' => intval(doroto_read_settings('squash', 1)),
		'singles_badminton' => intval(doroto_read_settings('singles_badminton', 1)),
		'doubles_badminton' => intval(doroto_read_settings('doubles_badminton', 1)),

		'privacy_policy_url' => esc_url_raw($privacy_policy_url ?: ''),
		'terms_of_service_url' => esc_url_raw($terms_of_service_url ?: ''),
		'doroto_url' => esc_url_raw($doroto_url ?: ''),

		'debug_privacy_id' => $privacy_page_id,
		'debug_terms_id' => $terms_page_id,
		'debug_privacy_url_raw' => $privacy_policy_url,
		'debug_terms_url_raw' => $terms_of_service_url,
	];
}


/**
 * REST API: get all users
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/users-all', [
		'methods' => 'GET',
		'callback' => 'doroto_get_users_all',
		'permission_callback' => function () {
			return true;
		},
	]);
});

function doroto_get_users_all(WP_REST_Request $request)
{
	global $wpdb;

	$tournament_id = intval($request->get_param('tournament_id') ?? 0);
	$doroto_settings = get_option('doroto_settings');
	$only_web_admin = isset($doroto_settings['only_admin_players']) ? intval($doroto_settings['only_admin_players']) : 1;
	$current_user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);

	if ($current_user_id == 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authenticated'], 401);
	}

	if (!$tournament_id) {
		return new WP_REST_Response(['error_code' => 'missing_tournament_id'], 400);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$admin = doroto_is_admin($tournament_id);
	if (($only_web_admin == 3 && $admin != 2) || ($only_web_admin < 3 && $admin == 0)) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$players = maybe_unserialize($tournament->players);
	if (!is_array($players))
		$players = [];

	$users = [];

	if ($only_web_admin == 2 || $only_web_admin == 1) {
		$participants = doroto_current_user_in_tournaments($only_web_admin);
		$new_players = array_diff($participants, $players);
		if (!isset($new_players)) {
			$new_players = [];
		}
	}

	if ($only_web_admin == 3 || $only_web_admin == 0 || $admin == 2) {
		if (!empty($players)) {
			$placeholders = implode(',', array_fill(0, count($players), '%d'));

			$query = $wpdb->prepare(
				"SELECT ID, display_name FROM {$wpdb->users} WHERE ID NOT IN ($placeholders)",
				$players
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$users = $wpdb->get_results($query);
		} else {
			$users = $wpdb->get_results("SELECT ID, display_name FROM {$wpdb->users}");
		}
	} else {
		if (!empty($new_players)) {
			$placeholders = implode(',', array_fill(0, count($new_players), '%d'));

			$query = $wpdb->prepare(
				"SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ($placeholders)",
				$new_players
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$users = $wpdb->get_results($query);
		}
	}

	$doroto_users = $wpdb->get_results("SELECT ID, display_name FROM {$wpdb->users} WHERE user_email LIKE '%@DoRoTo-example.com'");
	$existing_ids = wp_list_pluck($users, 'ID');

	foreach ($doroto_users as $du) {
		if (!in_array($du->ID, $existing_ids)) {
			$users[] = $du;
		}
	}

	$created_users = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT u.ID, u.display_name FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = 'doroto_creator' AND um.meta_value = %d",
			$current_user_id
		)
	);

	if (!empty($created_users)) {
		foreach ($created_users as $cu) {
			if (!in_array($cu->ID, $existing_ids)) {
				$users[] = $cu;
				$existing_ids[] = $cu->ID;
			}
		}
	}

	usort($users, function ($a, $b) {
		return strcasecmp($a->display_name, $b->display_name);
	});

	return [
		'success' => true,
		'current_user_id' => $current_user_id,
		'players' => $players,
		'is_admin' => $admin,
		'admin_users' => array_map(function ($user) {
			return [
				'id' => intval($user->ID),
				'display_name' => $user->display_name,
			];
		}, $users),
	];
}


/**
 * REST API: add a player to a tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/add-player', [
		'methods' => 'POST',
		'callback' => 'doroto_add_player_via_api',
		'permission_callback' => '__return_true',
	]);
});

function doroto_add_player_via_api(WP_REST_Request $request)
{
	global $wpdb;

	$tournament_id = intval($request->get_param('tournament_id'));
	$player_id = intval($request->get_param('player_to_add'));
	$current_user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);

	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}

	if (!$tournament_id || !$player_id) {
		return new WP_REST_Response(['error_code' => 'missing_tournament_or_player_id'], 400);
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'add_player_forbidden'], 403);
	}

	if ($tournament->close_tournament) {
		return new WP_REST_Response(['error_code' => 'add_player_tournament_closed'], 403);
	}


	$players = maybe_unserialize($tournament->players);
	if (!is_array($players)) {
		$players = [];
	}

	if (!in_array($player_id, $players)) {
		$players[] = $player_id;
	}

	$statistics = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));
	$new_last_update = round(microtime(true) * 1000);
	$result = $wpdb->update(
		$table_name,
		[
			'players' => serialize($players),
			'statistics' => serialize($statistics),
			'last_update' => $new_last_update
		],
		['id' => $tournament_id]
	);

	if ($result === false) {
		return new WP_REST_Response(['error_code' => 'db_update_failed'], 500);
	}

	$player_data = get_userdata($player_id);

	if ($player_data) {
		$to = $player_data->user_email;
		$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));
		$tournament_name = $tournament->name;

		$subject = sprintf(
			/* translators: %s: Tournament number. */
			__('Your account has been created and you have been added to the tournament: %s', 'doubles-rotation-tournament'),
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
			esc_html__('Your account has been successfully created in our application.', 'doubles-rotation-tournament')
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

		$headers = ['Content-Type: text/html; charset=UTF-8'];
		wp_mail($to, $subject, $body, $headers);
	}

	doroto_tournament_progress($tournament_id);
	$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));

	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_added_to_tournament',
		'player_name' => $player_name,
		'last_update' => $new_last_update,
	], 200);
}

/**
 * REST API: remove a player from a tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/remove-player', [
		'methods' => 'POST',
		'callback' => 'doroto_remove_player_via_api',
		'permission_callback' => '__return_true',
	]);
});

function doroto_remove_player_via_api(WP_REST_Request $request)
{
	global $wpdb;

	$tournament_id = intval($request->get_param('tournament_id'));
	$player_id = intval($request->get_param('player_to_remove'));
	$current_user_id = (int) doroto_get_current_user_id_from_token();

	wp_set_current_user($current_user_id);

	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}

	if (!$tournament_id || !$player_id) {
		return new WP_REST_Response(['error_code' => 'missing_tournament_or_player_id'], 400);
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$players = maybe_unserialize($tournament->players);
	$special_group = maybe_unserialize($tournament->special_group);
	$statistics = maybe_unserialize($tournament->statistics);

	if (!is_array($players))
		$players = [];
	if (!is_array($special_group))
		$special_group = [];
	if (!is_array($statistics))
		$statistics = [];

	if (!in_array($player_id, $players)) {
		return new WP_REST_Response(['error_code' => 'remove_player_not_in_tournament'], 400);
	}

	$statistics_new = doroto_remove_player_from_statistics_table($tournament, $statistics, $player_id);
	if ($statistics === $statistics_new) {
		return new WP_REST_Response(['error_code' => 'remove_player_has_played'], 400);
	}
	$new_last_update = round(microtime(true) * 1000);

	$wpdb->update(
		$table_name,
		[
			'statistics' => serialize($statistics_new)
		],
		['id' => $tournament_id]
	);

	$tournament = doroto_prepare_tournament($tournament_id);

	$players = maybe_unserialize($tournament->players);
	$special_group = maybe_unserialize($tournament->special_group);

	if (!is_array($players))
		$players = [];
	if (!is_array($special_group))
		$special_group = [];

	$players = array_values(array_diff($players, [$player_id]));
	$special_group = array_values(array_diff($special_group, [$player_id]));

	$wpdb->update(
		$table_name,
		[
			'players' => maybe_serialize($players),
			'statistics' => serialize($statistics_new),
			'special_group' => maybe_serialize($special_group),
			'last_update' => $new_last_update
		],
		['id' => $tournament_id]
	);

	doroto_tournament_progress($tournament_id);

	$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));

	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_removed_from_tournament',
		'player_name' => $player_name,
		'last_update' => $new_last_update,
	], 200);
}

/**
 * REST API: input match result
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/match-result', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_update_match_result',
		'permission_callback' => function (WP_REST_Request $req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'match_number' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'result_1' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'result_2' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'hide' => [
				'required' => false,
				'default' => 0,
				'validate_callback' => 'rest_validate_request_arg',
			],
		],
	]);
});


function doroto_rest_update_match_result(WP_REST_Request $request)
{
	global $wpdb;
	$current_user_id = intval(doroto_get_current_user_id_from_token());
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($current_user_id);

	$tournament_id = intval($request->get_param('tournament_id'));
	$match_number = intval($request->get_param('match_number'));
	$result_1 = intval($request->get_param('result_1'));
	$result_2 = intval($request->get_param('result_2'));
	$hide = intval($request->get_param('hide'));

	if ($match_number <= 0 || $result_1 < 0 || $result_2 < 0) {
		return new WP_REST_Response(['error_code' => 'invalid_data'], 400);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	$is_admin = doroto_is_admin($tournament_id) > 0;
	$allow_input_results = intval($tournament->allow_input_results);

	if (!$is_admin) {
		$matches = maybe_unserialize($tournament->matches_list);
		$allowed = array_filter($matches, function ($m) use ($match_number, $current_user_id) {
			return $m['match_number'] == $match_number
				&& in_array($current_user_id, [
					$m['player_1'],
					$m['player_2'],
					$m['player_3'],
					$m['player_4']
				], true);
		});
		if (empty($allowed) || $allow_input_results == 0) {
			return new WP_REST_Response(['error_code' => 'edit_match_forbidden'], 403);
		}
	}

	$output_message = '';
	$new_last_update = round(microtime(true) * 1000);
	ob_start();
	$endpoint_request = true;
	$save_result = doroto_save_match_result(
		$match_number,
		$tournament_id,
		$tournament,
		$result_1,
		$result_2,
		$hide,
		$output_message,
		$new_last_update,
		$endpoint_request
	);
	ob_end_clean();
	if (is_wp_error($save_result)) {
		return $save_result;
	}
	doroto_tournament_progress($tournament_id);

	$tournament = doroto_prepare_tournament($tournament_id);

	$offer_html = doroto_offer_games($tournament_id, 0);
	$no_new_match = ($offer_html !== '');

	return rest_ensure_response([
		'success' => true,
		'action' => 'match_result_updated',
		'no_new_match' => $no_new_match,
		'last_update' => $new_last_update,
		'debug' => [
			'user_id' => $current_user_id,
			'is_admin' => $is_admin,
			'match_number' => $match_number,
			'result_1' => $result_1,
			'result_2' => $result_2,
			'hide' => $hide,
		],
	]);
}


/**
 * REST API: round end actions
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/round-end-action', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_round_end_action',
		'permission_callback' => '__return_true',
		'args' => [
			'tournament_id' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'action' => [
				'required' => true,
				'validate_callback' => function ($val) {
					return in_array($val, ['next', 'end', 'hide'], true);
				},
			],
		],
	]);
});

function doroto_rest_round_end_action(WP_REST_Request $req)
{
	global $wpdb;

	$user_id = intval(doroto_get_current_user_id_from_token());
	if ($user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($user_id);

	$tournament_id = intval($req->get_param('tournament_id'));
	$action = $req->get_param('action');

	if (doroto_is_admin($tournament_id) <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_forbidden'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$table = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);
	$action_code = '';
	switch ($action) {
		case 'next':
			$wpdb->update($table, ['announce_round_end' => '2', 'last_update' => $new_last_update], ['id' => $tournament_id]);
			$action_code = 'round_continued';
			break;
		case 'end':
			$wpdb->update($table, ['close_tournament' => '1', 'last_update' => $new_last_update], ['id' => $tournament_id]);
			$action_code = 'tournament_ended';
			break;
		case 'hide':
			$wpdb->update($table, ['announce_round_end' => '0', 'last_update' => $new_last_update], ['id' => $tournament_id]);
			$action_code = 'notification_hidden';
			break;
		default:
			return new WP_REST_Response(['error_code' => 'invalid_action'], 400);
	}

	$offer_html = doroto_offer_games($tournament_id, 0);
	doroto_tournament_progress($tournament_id);

	return rest_ensure_response([
		'success' => true,
		'action' => $action_code,
		'last_update' => $new_last_update,
	]);
}


/**
 * REST API: change tournament state
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-toggle', [
		'methods' => ['POST', 'OPTIONS'],
		'callback' => 'doroto_rest_toggle',
		'permission_callback' => '__return_true',
	]);
});

function doroto_rest_toggle(WP_REST_Request $req)
{
	global $wpdb;

	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		return new WP_REST_Response(null, 200);
	}

	$body = json_decode($req->get_body(), true);
	$tid = intval($body['tournament_id'] ?? 0);
	$atype = sanitize_text_field($body['action_type'] ?? '');

	$current_user = (int) doroto_get_current_user_id_from_token();
	if ($current_user === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized_or_expired'], 401);
	}
	wp_set_current_user($current_user);

	$tournament = doroto_prepare_tournament($tid);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	if (doroto_is_admin($tid) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$table = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);

	if ($atype === 'registration') {
		$open = intval($tournament->open_registration);

		if (intval($tournament->close_tournament) === 1) {
			return new WP_REST_Response(['error_code' => 'toggle_reg_when_tournament_closed'], 400);
		} else {
			if ($open === 1) {
				$players = maybe_unserialize($tournament->players);
				if (count($players) < 4) {
					return new WP_REST_Response(['error_code' => 'toggle_reg_not_enough_players'], 400);
				}

				$matches = maybe_unserialize($tournament->matches_list);
				if (empty($matches)) {
					$matches = doroto_generate_fake_match($players);
				}
				$stats = doroto_create_statistics_table($tournament, $players, intval($tournament->whole_names));

				$wpdb->update(
					$table,
					[
						'open_registration' => 0,
						'matches_list' => serialize($matches),
						'statistics' => serialize($stats),
						'last_update' => $new_last_update,
					],
					['id' => $tid]
				);
				wp_cache_delete('tournament_' . $tid, 'doroto_tournaments');
			} else {
				$wpdb->update($table, ['open_registration' => 1, 'last_update' => $new_last_update], ['id' => $tid]);
			}
		}

		return new WP_REST_Response([
			'success' => true,
			'action' => 'registration_toggled',
			'last_update' => $new_last_update,
		], 200);
	}

	if ($atype === 'tournament') {
		$open_reg = intval($tournament->open_registration);

		if ($open_reg === 1) {
			return new WP_REST_Response(['error_code' => 'toggle_tournament_when_reg_open'], 400);
		}

		$current_close = intval($tournament->close_tournament);
		$new_close = $current_close === 1 ? 0 : 1;
		$close_date = $new_close === 1
			? gmdate('Y-m-d H:i:s')
			: '9999-09-09 09:09:09';

		$wpdb->update(
			$table,
			[
				'close_tournament' => $new_close,
				'final_four' => '',
				'final_result' => '',
				'close_date' => $close_date,
				'last_update' => $new_last_update,
			],
			['id' => $tid]
		);

		return new WP_REST_Response([
			'success' => true,
			'action' => 'tournament_state_toggled',
			'last_update' => $new_last_update,
		], 200);
	}

	return new WP_REST_Response(['error_code' => 'toggle_unknown_action'], 400);
}

/**
 * REST API: add an admin to a tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-add-admin', [
		'methods' => ['POST', 'OPTIONS'],
		'callback' => 'doroto_rest_add_admin',
		'permission_callback' => function (WP_REST_Request $request) {
			if ($request->get_method() === 'OPTIONS') {
				return true;
			}

			$user_id = doroto_get_current_user_id_from_token();
			if ($user_id <= 0) {
				return new WP_Error('rest_forbidden', 'User is not logged in.', ['status' => 401]);
			}

			$params = $request->get_json_params();
			$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : 0;

			if ($tournament_id === 0) {
				return new WP_Error('rest_invalid_param', 'Tournament ID is missing.', ['status' => 400]);
			}

			global $wpdb;
			$admin_users_raw = $wpdb->get_var($wpdb->prepare(
				"SELECT admin_users FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
				$tournament_id
			));

			if (!$admin_users_raw) {
				return new WP_Error('rest_not_found', 'Tournament not found.', ['status' => 404]);
			}

			$admin_users = maybe_unserialize($admin_users_raw);
			$super_admin_id = (is_array($admin_users) && !empty($admin_users)) ? (int)$admin_users[0] : 0;
			wp_set_current_user($user_id);

			$admin_level = doroto_is_admin($tournament_id);

			if ($user_id === $super_admin_id || $admin_level == 2) {
				return true;
			}

			return new WP_Error('rest_forbidden', 'You do not have permissions (only Super Tournament Admin or Web Administrator).', ['status' => 401]);
		},
	]);
});

function doroto_rest_add_admin(WP_REST_Request $req)
{
	global $wpdb;

	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		return new WP_REST_Response(null, 200);
	}

	$body = json_decode($req->get_body(), true);
	$tid = intval($body['tournament_id'] ?? 0);
	$pid = intval($body['player_id'] ?? 0);

	$current = (int) doroto_get_current_user_id_from_token();
	if ($current === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized_or_expired'], 401);
	}
	wp_set_current_user($current);

	if (doroto_is_admin($tid) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$table = $wpdb->prefix . 'doroto_tournaments';
	$t = doroto_prepare_tournament($tid);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if (intval($t->close_tournament) === 1) {
		return new WP_REST_Response(['error_code' => 'add_admin_tournament_closed'], 400);
	}

	$admins = maybe_unserialize($t->admin_users);
	if (!is_array($admins)) {
		$admins = [];
	}
	$last_update_to_return = $t->last_update;

	if (!in_array($pid, $admins, true)) {
		$admins[] = $pid;
		$last_update_to_return = round(microtime(true) * 1000);
		$wpdb->update(
			$table,
			[
				'admin_users' => serialize($admins),
				'last_update' => $last_update_to_return
			],
			['id' => $tid]
		);
	}

	return new WP_REST_Response([
		'success' => true,
		'action' => 'organizer_added',
		'last_update' => (int) $last_update_to_return,
	], 200);
}


/**
 * REST API: provide all users suitable to be included in to admin group
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/admin-candidates', [
		'methods' => ['GET', 'OPTIONS'],
		'callback' => 'doroto_get_admin_candidates',
		'permission_callback' => '__return_true',
	]);
});

function doroto_get_admin_candidates(WP_REST_Request $request)
{
	global $wpdb;

	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		return new WP_REST_Response(null, 200);
	}

	$tid = intval($request->get_param('tournament_id') ?? 0);
	$doroto_settings = get_option('doroto_settings');
	$only_web_admin = isset($doroto_settings['only_admin_players']) ? intval($doroto_settings['only_admin_players']) : 1;
	$current_user = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user);

	if ($current_user === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authenticated'], 401);
	}
	if ($tid === 0) {
		return new WP_REST_Response(['error_code' => 'missing_tournament_id'], 400);
	}

	$t = doroto_prepare_tournament($tid);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$admin_level = doroto_is_admin($tid);
	if ($admin_level < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	if (intval($t->close_tournament) === 1) {
		return new WP_REST_Response(['error_code' => 'tournament_is_closed'], 400);
	}

	$admin_users = maybe_unserialize($t->admin_users);
	$players = maybe_unserialize($t->players);
	if (!is_array($admin_users))
		$admin_users = [];
	if (!is_array($players))
		$players = [];

	if (!empty($admin_users) && (!empty($players) || (empty($players) && $only_web_admin < 3))) {
		if (($only_web_admin === 2 || $only_web_admin === 1) && $admin_level < 2) {
			$user_ids = doroto_current_user_in_tournaments($only_web_admin);
			$candidates_ids = array_diff($user_ids, $admin_users);
		} elseif (($only_web_admin > 0 && $admin_level === 2) || ($only_web_admin === 0 && $admin_level > 0)) {
			$all_users = $wpdb->get_results("SELECT ID FROM {$wpdb->users}", ARRAY_A);
			$all_ids = array_column($all_users, 'ID');
			$candidates_ids = array_diff($all_ids, $admin_users);
		} else {
			$candidates_ids = array_diff($players, $admin_users);
		}
	} else {
		$all_users = $wpdb->get_results("SELECT ID FROM {$wpdb->users}", ARRAY_A);
		$candidates_ids = array_column($all_users, 'ID');
	}

	if (empty($candidates_ids)) {
		$candidates = [];
	} else {
		$placeholders = implode(',', array_fill(0, count($candidates_ids), '%d'));

		$prep = $wpdb->prepare(
			"SELECT ID, display_name
     FROM {$wpdb->users}
     WHERE ID IN ($placeholders)
     ORDER BY display_name ASC",
			$candidates_ids
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results($prep);
		$candidates = array_map(function ($u) {
			return [
				'id' => intval($u->ID),
				'display_name' => $u->display_name,
			];
		}, $rows);
	}

	$user_id = doroto_get_current_user_id_from_token();
	$super_admin_id = (!empty($admin_users)) ? (int) $admin_users[0] : 0;

	return rest_ensure_response([
		'success' => true,
		'current_user' => $current_user,
		'is_admin' => $admin_level,
		'is_super_admin' => ($user_id === $super_admin_id || $admin_level == 2) ? 1 : 0,
		'admin_users' => array_values($admin_users),
		'players' => array_values($players),
		'candidates' => $candidates,
	]);
}


/**
 * REST API: renew your password
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('player', '/forgot-password', [
		'methods' => 'POST',
		'callback' => 'doroto_handle_forgot_password',
		'permission_callback' => '__return_true',
	]);
});

function doroto_handle_forgot_password(WP_REST_Request $request)
{
	$params = $request->get_json_params();
	$email = sanitize_email($params['email'] ?? '');

	if (empty($email)) {
		return new WP_REST_Response(['error_code' => 'forgot_password_email_required'], 400);
	}

	$user = get_user_by('email', $email);

	if (!$user) {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'password_reset_sent'
		], 200);
	}

	$result = retrieve_password($user->user_login);
	if (is_wp_error($result)) {
		return new WP_REST_Response(['error_code' => 'forgot_password_send_failed'], 500);
	}

	return new WP_REST_Response([
		'success' => true,
		'message' => 'If this address is registered, you’ll receive an email with reset instructions.',
	], 200);
}


/**
 * REST API: google login
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('player', '/google-login', [
		'methods' => 'POST',
		'callback' => 'doroto_handle_google_login',
		'permission_callback' => '__return_true',
	]);
});

function doroto_handle_google_login(WP_REST_Request $request)
{
	$logs = [];

	$params = $request->get_json_params();
	$logs[] = 'Received request body: ' . json_encode($params);

	$id_token = sanitize_text_field($params['id_token'] ?? '');
	if (empty($id_token)) {
		$logs[] = 'ERROR: Missing id_token';
		return new WP_REST_Response(['error_code' => 'google_login_missing_token'], 400);
	}
	$logs[] = 'id_token present';

	$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
	$resp = wp_remote_get($url);
	$code = wp_remote_retrieve_response_code($resp);
	$body = wp_remote_retrieve_body($resp);
	$logs[] = "Google tokeninfo HTTP code: {$code}";
	$logs[] = "Google tokeninfo body: {$body}";

	if (is_wp_error($resp) || $code !== 200) {
		$logs[] = 'ERROR: Invalid Google ID token';
		return new WP_REST_Response(['error_code' => 'google_login_invalid_token'], 401);
	}

	$payload = json_decode($body, true);
	$payload_aud = $payload['aud'] ?? '';
	$logs[] = 'Audience in token: ' . $payload_aud;
	$logs[] = 'Full ID token payload: ' . json_encode($payload);
	$allowed_auds = [
		'465710345150-lpuimssha7iepkra9cl6ri1pc5rf5219.apps.googleusercontent.com', // Web Client ID
		'465710345150-v3j4gbci6qprnnu8regimj3e61b8fuo7.apps.googleusercontent.com', // Android Server Client ID
		'465710345150-9e733svc5ihlpdae0jrnb2gcla20u47u.apps.googleusercontent.com',
		'465710345150-dduhe0306ma5hcfrgpdkcb8jfpevncvh.apps.googleusercontent.com', // Android Server Client ID
		'465710345150-h576p3f83vtidetsef105a8fb23mrtih.apps.googleusercontent.com',
		'465710345150-bs4q5odcu9bbnodkod1op85vue1r31u9.apps.googleusercontent.com'
	];
	$logs[] = "Audience in token: {$payload_aud}";
	if (!in_array($payload_aud, $allowed_auds, true)) {
		$logs[] = 'ERROR: Audience does not match any allowed Client ID';
		return new WP_REST_Response(['error_code' => 'google_login_audience_mismatch'], 401);
	}
	$logs[] = 'Audience OK';

	$email = sanitize_email($payload['email'] ?? '');
	$logs[] = "Email in token: {$email}";
	if (empty($email)) {
		$logs[] = 'ERROR: Email missing in token payload';
		return new WP_REST_Response(['error_code' => 'google_login_missing_email'], 400);
	}

	$user = get_user_by('email', $email);

	if (!$user) {
		$logs[] = 'User not found, creating new user';

		$first_name = sanitize_text_field($payload['given_name'] ?? '');
		$last_name = sanitize_text_field($payload['family_name'] ?? '');
		$display_name = trim($first_name . ' ' . $last_name);
		$random_password = wp_generate_password();

		$user_id = wp_create_user($email, $random_password, $email);
		if (is_wp_error($user_id)) {
			$logs[] = 'ERROR: Could not create user: ' . $user_id->get_error_message();
			return new WP_REST_Response(['error_code' => 'google_login_user_creation_failed'], 500);
		}

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $display_name,
		]);

		$user = get_user_by('id', $user_id);
		$logs[] = "Created user ID {$user_id} with name {$display_name}";
	} else {
		$logs[] = "Found existing user ID {$user->ID}";
	}

	// 1. Generování obou tokenů (Access a Refresh)
	$accessToken = bin2hex(random_bytes(32));
	$accessTokenExpiration = time() + 3600; // Platnost 1 hodina

	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // Platnost 90 dní

	// 2. Uložení obou tokenů a jejich expirací do databáze
	update_user_meta($user->ID, 'doroto_access_token', $accessToken);
	update_user_meta($user->ID, 'doroto_access_token_expiration', $accessTokenExpiration);
	update_user_meta($user->ID, 'doroto_refresh_token', $refreshToken);
	update_user_meta($user->ID, 'doroto_refresh_token_expiration', $refreshTokenExpiration);

	$logs[] = "Generated new access and refresh tokens for user ID {$user->ID}";


	return new WP_REST_Response([
		'success' => true,
		'action' => 'google_login_successful',
		'access_token' => $accessToken,   // Nově se vrací access_token
		'refresh_token' => $refreshToken,
		'user' => [
			'ID' => $user->ID,
			'email' => $user->user_email,
			'username' => $user->user_login,
			'name' => $user->first_name,
			'surname' => $user->last_name,
		],
		'logs' => $logs,
	], 200);
}


/**
 * REST API: edit user profile
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/edit-profile', [
		'methods' => ['GET', 'POST'],
		'callback' => 'doroto_edit_user_profile',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_edit_user_profile(WP_REST_Request $request)
{
	$result = doroto_get_current_user_id_from_token();
	if ($result instanceof WP_REST_Response) {
		return $result;
	}

	$user_id = $result;
	$user = get_user_by('id', $user_id);
	if (!$user) {
		return new WP_REST_Response(['error_code' => 'user_not_found'], 404);
	}

	if ($request->get_method() === 'GET') {
		return new WP_REST_Response([
			'success' => true,
			'user' => [
				'email' => $user->user_email,
				'first_name' => get_user_meta($user_id, 'first_name', true),
				'last_name' => get_user_meta($user_id, 'last_name', true),
				'display_name' => $user->display_name,
			],
		], 200);
	}

	$params = $request->get_json_params();

	$first_name = sanitize_text_field($params['first_name'] ?? '');
	$last_name = sanitize_text_field($params['last_name'] ?? '');
	$display_name = sanitize_text_field($params['display_name'] ?? '');
	$password = trim($params['password'] ?? '');
	$password_repeat = trim($params['password_repeat'] ?? '');

	if (!empty($password) || !empty($password_repeat)) {
		if ($password !== $password_repeat) {
			return new WP_REST_Response(['error_code' => 'profile_passwords_do_not_match'], 400);
		}
		wp_set_password($password, $user_id);
	}

	wp_update_user([
		'ID' => $user_id,
		'first_name' => $first_name,
		'last_name' => $last_name,
		'display_name' => $display_name,
	]);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'profile_updated',
		'user' => [
			'email' => $user->user_email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $display_name,
		],
	], 200);
}


/**
 * REST API: load user profile
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/get-profile', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_profile',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_get_profile(WP_REST_Request $request)
{
	$user_id = doroto_get_current_user_id_from_token();
	if ($user_id instanceof WP_REST_Response) {
		return $user_id;
	}

	$user = get_userdata($user_id);
	return [
		'success' => true,
		'user' => [
			'email' => $user->user_email,
			'first_name' => get_user_meta($user_id, 'first_name', true),
			'last_name' => get_user_meta($user_id, 'last_name', true),
			'display_name' => $user->display_name,
		],
	];
}


/**
 * REST API: all users suitable to suspend their participation in the tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/disable-player-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_disable_player_candidates',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
	register_rest_route('doroto/v1', '/tournament-disable-player', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_tournament_disable_player',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => ['required' => true, 'type' => 'integer'],
			'disable_player' => ['required' => true, 'type' => 'integer'],
		],
	]);
});


function doroto_rest_get_disable_player_candidates(\WP_REST_Request $request)
{
	// Authenticate via JWT
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if ($tournament->close_tournament === '1') {
		return new WP_REST_Response(['error_code' => 'suspend_player_tournament_closed'], 403);
	}

	$statistics = maybe_unserialize($tournament->statistics);
	$activePlayers = doroto_find_active_players($statistics);
	$current_user = get_current_user_id();
	$is_admin = doroto_is_admin($tournament_id) > 0;

	$candidates = [];
	foreach ($statistics as $player) {
		$pid = intval($player['player_id']);
		if (!in_array($pid, $activePlayers, true)) {
			continue;
		}
		if ($is_admin || $pid === $current_user) {
			$candidates[] = [
				'id' => $pid,
				'display_name' => doroto_find_player_name($pid, intval($tournament->whole_names)),
			];
		}
	}
	if ($is_admin) {
		array_unshift($candidates, [
			'id' => 0,
			'display_name' => __('All players', 'doubles-rotation-tournament'),
		]);
	}

	return rest_ensure_response([
		'success' => true,
		'current_user_id' => $current_user,
		'is_admin' => $is_admin ? 1 : 0,
		'candidates' => $candidates,
		'debug' => [
			'query_params' => $request->get_query_params(),
			'auth_header' => $request->get_header('authorization'),
		],
	]);
}



function doroto_rest_post_tournament_disable_player(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	$player_id = intval($request->get_param('disable_player'));
	$current_user = get_current_user_id();

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if ($tournament->close_tournament === '1') {
		return new WP_REST_Response(['error_code' => 'suspend_player_tournament_closed'], 403);
	}

	$is_admin = doroto_is_admin($tournament_id) > 0;
	if (!$is_admin && $player_id !== $current_user) {
		return new WP_REST_Response(['error_code' => 'suspend_player_tournament_closed'], 403);
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_statistics'], 500);
	}

	foreach ($statistics as &$player) {
		if ($player_id === 0 || intval($player['player_id']) === $player_id) {
			$player['active'] = 0;
			if ($player_id !== 0)
				break;
		}
	}
	unset($player);

	global $wpdb;
	$table = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);
	$wpdb->update(
		$table,
		[
			'statistics' => serialize($statistics),
			'last_update' => $new_last_update
		],
		['id' => $tournament_id]
	);

	doroto_tournament_progress($tournament_id);

	if ($player_id === 0) {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'all_players_suspended'
		]);
	} else {
		// Stav 2: Pozastavuje se jeden hráč
		$name = doroto_find_player_name($player_id, intval($tournament->whole_names));
		return new WP_REST_Response([
			'success' => true,
			'action' => 'single_player_suspended',
			'player_name' => $name
		]);
	}

	return rest_ensure_response([
		'success' => true,
		'message' => $message,
		'last_update' => $new_last_update,
		'debug' => [
			'tournament_id' => $tournament_id,
			'player_id' => $player_id,
			'auth_header' => $request->get_header('authorization'),
			'body_params' => $request->get_body_params(),
		],
	]);
}


/**
 * REST API: all players that their participation can be restored
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/restore-player-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_restore_player_candidates',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
	register_rest_route('doroto/v1', '/tournament-restore-player', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_tournament_restore_player',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => ['required' => true, 'type' => 'integer'],
			'restore_player' => ['required' => true, 'type' => 'integer'],
		],
	]);
});

function doroto_rest_get_restore_player_candidates(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		return new WP_REST_Response(['error_code' => 'bad_data'], 500);
	}

	$is_admin = doroto_is_admin($tournament_id) > 0;

	$candidates = [];
	foreach ($statistics as $player) {
		$player_id = intval($player['player_id']);
		$is_inactive = ($player_id && $player['active'] == 0);

		if ($is_inactive && ($is_admin || $player_id === $uid)) {
			$candidates[] = [
				'id' => $player_id,
				'display_name' => doroto_find_player_name($player_id, intval($tournament->whole_names)),
			];
		}
	}

	if ($is_admin) {
		array_unshift($candidates, ['id' => 0, 'display_name' => __('All players', 'doubles-rotation-tournament')]);
	}

	return rest_ensure_response([
		'success' => true,
		'is_admin' => $is_admin ? 1 : 0,
		'candidates' => $candidates
	]);
}

function doroto_rest_post_tournament_restore_player(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	$player_id = intval($request->get_param('restore_player'));

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$statistics = maybe_unserialize($tournament->statistics);
	if (!is_array($statistics)) {
		return new WP_REST_Response(['error_code' => 'bad_data'], 500);
	}

	$is_admin = doroto_is_admin($tournament_id) > 0;
	if (!$is_admin && $player_id !== $uid) {
		return new WP_REST_Response(['error_code' => 'auth_forbidden'], 403);
	}

	foreach ($statistics as &$player) {
		if ($player_id === 0 || intval($player['player_id']) === $player_id) {
			$player['active'] = 1;
			if ($player_id !== 0)
				break;
		}
	}
	unset($player);

	global $wpdb;
	$new_last_update = round(microtime(true) * 1000);
	$wpdb->update(
		$wpdb->prefix . 'doroto_tournaments',
		[
			'statistics' => serialize($statistics),
			'last_update' => $new_last_update
		],
		['id' => $tournament_id]
	);
	doroto_tournament_progress($tournament_id);

	if ($player_id === 0) {
		return rest_ensure_response([
			'success' => true,
			'action' => 'all_players_restored', // Nový klíč
			'last_update' => $new_last_update,
		]);
	} else {
		$name = doroto_find_player_name($player_id, intval($tournament->whole_names));
		return rest_ensure_response([
			'success' => true,
			'action' => 'single_player_restored', // Nový klíč
			'player_name' => $name, // Potřebná data
			'last_update' => $new_last_update,
		]);
	}
}


/**
 * REST API: all players that can pay
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/payment-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_payment_candidates',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
	register_rest_route('doroto/v1', '/tournament-enter-payment', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_enter_payment',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => ['required' => true, 'type' => 'integer'],
			'confirm_payment' => ['required' => true, 'type' => 'integer'],
		],
	]);
});

function doroto_rest_get_payment_candidates(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$t = doroto_prepare_tournament($tournament_id);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$paid = maybe_unserialize($t->payment_done);
	if (!is_array($paid))
		$paid = [];

	$candidates = [];
	foreach (maybe_unserialize($t->players) as $pid) {
		$pid = intval($pid);
		if (!in_array($pid, $paid, true)) {
			$candidates[] = [
				'id' => $pid,
				'display_name' => doroto_find_player_name($pid, intval($t->whole_names)),
			];
		}
	}

	return rest_ensure_response([
		'success' => true,
		'is_admin' => doroto_is_admin($tournament_id) > 0 ? 1 : 0,
		'candidates' => $candidates,
	]);
}

function doroto_rest_post_enter_payment(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($uid);

	$tid = intval($request->get_param('tournament_id'));
	$pid = intval($request->get_param('confirm_payment'));

	$t = doroto_prepare_tournament($tid);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$paid = maybe_unserialize($t->payment_done);
	if (!is_array($paid))
		$paid = [];

	$last_update_to_return = $t->last_update;

	if (!in_array($pid, $paid, true)) {
		$paid[] = $pid;
		global $wpdb;
		$last_update_to_return = round(microtime(true) * 1000);
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'payment_done' => serialize($paid),
				'last_update' => $last_update_to_return
			],
			['id' => $tid]
		);
		doroto_info_messsages_save(
			sprintf(
				/* translators: %s: Players name. */
				__('Payment for %s recorded.', 'doubles-rotation-tournament'),
				doroto_find_player_name($pid, intval($t->whole_names))
			)
		);
		doroto_tournament_progress($tid);
	}

	return rest_ensure_response([
		'success' => true,
		'message' => __('Payment(s) recorded.', 'doubles-rotation-tournament'),
		'action' => 'payment_recorded',
		'last_update' => (int) $last_update_to_return,
	]);
}


/**
 * REST API: all players where the payments can be removed
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/remove-payment-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_remove_payment_candidates',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
	register_rest_route('doroto/v1', '/tournament-remove-payment', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_remove_payment',
		'permission_callback' => function ($req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => ['required' => true, 'type' => 'integer'],
			'remove_payment' => ['required' => true, 'type' => 'integer'],
		],
	]);
});

function doroto_rest_get_remove_payment_candidates(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$t = doroto_prepare_tournament($tournament_id);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$paid = maybe_unserialize($t->payment_done);
	if (!is_array($paid))
		$paid = [];

	$candidates = [];
	foreach ($paid as $pid) {
		$pid = intval($pid);
		$candidates[] = [
			'id' => $pid,
			'display_name' => doroto_find_player_name($pid, intval($t->whole_names)),
		];
	}

	return rest_ensure_response([
		'success' => true,
		'is_admin' => doroto_is_admin($tournament_id) > 0 ? 1 : 0,
		'candidates' => $candidates,
	]);
}

function doroto_rest_post_remove_payment(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid instanceof WP_Error || $uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	$tid = intval($request->get_param('tournament_id'));
	$pid = intval($request->get_param('remove_payment'));

	$t = doroto_prepare_tournament($tid);
	if (!$t) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$paid = maybe_unserialize($t->payment_done);
	if (!is_array($paid))
		$paid = [];

	if (in_array($pid, $paid, true)) {
		$paid = array_diff($paid, [$pid]);
		global $wpdb;
		$new_last_update = round(microtime(true) * 1000);

		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'payment_done' => serialize($paid),
				'last_update' => $new_last_update
			],
			['id' => $tid]
		);
		doroto_tournament_progress($tid);
		return rest_ensure_response([
			'success' => true,
			'action' => 'payment_removed',
			'player_name' => doroto_find_player_name($pid, intval($t->whole_names)),
			'last_update' => $new_last_update,
		]);
	}

	return new WP_REST_Response(['error_code' => 'payment_not_found'], 400);
}


/**
 * REST API: send info if there is a new update of the tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/check-update/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_check_update',
		'permission_callback' => '__return_true',
	]);
});

function doroto_check_update(WP_REST_Request $data)
{
	global $wpdb;
	$tournament_id = intval($data['id']);

	if ($tournament_id === 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id), ARRAY_A);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$tournament['last_update'] = intval($tournament['last_update']);
	return $tournament['last_update'];
}

add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/change-match-result', array(
		'methods' => 'POST',
		'callback' => 'doroto_api_change_match_result',
		'permission_callback' => '__return_true',
	));
});

/**
 * Handles the logic for the /change-match-result endpoint.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function doroto_api_change_match_result(WP_REST_Request $request)
{
	global $wpdb;

	$current_user_id = (int) doroto_get_current_user_id_from_token();
	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($current_user_id);
	$params = $request->get_json_params();
	$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : 0;
	$match_number = isset($params['match_number']) ? intval($params['match_number']) : 0;
	$result_1 = isset($params['result_1']) ? intval($params['result_1']) : -1;
	$result_2 = isset($params['result_2']) ? intval($params['result_2']) : -1;

	if ($tournament_id <= 0 || $match_number <= 0 || $result_1 < 0 || $result_2 < 0) {
		return new WP_REST_Response(['error_code' => 'invalid_data'], 400);
	}

	if (!doroto_is_admin($tournament_id)) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if ($tournament->close_date === '9999-09-09 09:09:09') {
		$timestamp = PHP_INT_MAX;
	} else {
		$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', $tournament->close_date);
		$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
	}

	if (
		$tournament->open_registration == '1' ||
		($tournament->close_tournament == '1' && $tournament->play_final_match == '0') ||
		($tournament->close_tournament == '1' && $tournament->play_final_match == '1' && ($tournament->final_result == '' || $timestamp + 24 * 3600 < time()))
	) {
		return new WP_REST_Response(['error_code' => 'edit_match_results_closed'], 403);
	}

	$matches_list = maybe_unserialize($tournament->matches_list);
	if (!is_array($matches_list)) {
		$matches_list = [];
	}

	$original_match = null;
	$match_key = null;
	foreach ($matches_list as $key => $game) {
		if ($game['match_number'] == $match_number) {
			$original_match = $game;
			$match_key = $key;
			break;
		}
	}

	if ($original_match === null) {
		return new WP_REST_Response(['error_code' => 'match_not_found'], 404);
	}


	$original_result_1 = intval($original_match['result_1']);
	$original_result_2 = intval($original_match['result_2']);


	$change_match = $original_match;
	$change_match['result_1'] = $result_1 - $original_result_1;
	$change_match['result_2'] = $result_2 - $original_result_2;


	doroto_update_statistics_by_result($tournament_id, $tournament, $change_match, true, false);

	$matches_list[$match_key]['result_1'] = $result_1;
	$matches_list[$match_key]['result_2'] = $result_2;
	$matches_list[$match_key]['hide'] = ($result_1 == 0 && $result_2 == 0) ? 1 : 0;

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);
	$updated = $wpdb->update(
		$table_name,
		[
			'matches_list' => maybe_serialize($matches_list),
			'last_update' => $new_last_update
		],
		['id' => $tournament_id]
	);

	if ($updated === false) {
		return new WP_REST_Response(['error_code' => 'db_save_result_failed'], 500);
	}

	doroto_tournament_progress($tournament_id);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'match_result_changed',
		'match_number' => $match_number,
		'last_update' => $new_last_update
	], 200);
}


/**
 * REST API: example tournament setup for help guides
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/setup-example-tournament', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_setup_example_tournament',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_setup_example_tournament(WP_REST_Request $request)
{
	global $wpdb;

	$current_user_id = doroto_get_current_user_id_from_token();
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($current_user_id);

	$tournament_id_1 = doroto_read_settings('tournament_example_1', '0');
	$tournament_id_2 = doroto_read_settings('tournament_example_2', '0');
	$tournament_id_3 = doroto_read_settings('tournament_example_3', '0');
	$tournament_id_4 = doroto_read_settings('tournament_example_4', '0');

	if ($tournament_id_1 == 0 || $tournament_id_2 == 0 || $tournament_id_3 == 0 || $tournament_id_4 == 0) {
		doroto_create_tournament_record();
	}

	$params = $request->get_json_params();
	$example_number = isset($params['example_number']) ? intval($params['example_number']) : 0;

	if ($example_number < 1 || $example_number > 4) {
		return new WP_REST_Response(['error_code' => 'invalid_example_number'], 400);
	}

	$setting_key = 'tournament_example_' . $example_number;
	$tournament_id = intval(doroto_read_settings($setting_key, '0'));

	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'example_tournament_not_found'], 404);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		doroto_create_tournament_record();
		$tournament_id = intval(doroto_read_settings($setting_key, '0'));
		$tournament = doroto_prepare_tournament($tournament_id);
	}

	if ($tournament) {
		if ($tournament->close_date === '9999-09-09 09:09:09') {
			$timestamp = PHP_INT_MAX;
		} else {
			$closeDate = DateTime::createFromFormat('Y-m-d H:i:s', $tournament->close_date);
			$timestamp = $closeDate ? $closeDate->getTimestamp() : 0;
		}
		if ($tournament->close_tournament == 1 && $timestamp + 24 * 3600 > time()) {
			doroto_create_tournament_record();
			$tournament_id = intval(doroto_read_settings($setting_key, '0'));
			$tournament = doroto_prepare_tournament($tournament_id);
		}
	}

	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_data_load_failed'], 404);
	}

	$admins = maybe_unserialize($tournament->admin_users);
	if (!is_array($admins)) {
		$admins = [];
	}

	if (!in_array($current_user_id, $admins, true)) {
		$admins[] = $current_user_id;
		if ($tournament->close_tournament == 1) {
			$close_date = gmdate('Y-m-d H:i:s');
		} else {
			$close_date = $tournament->close_date;
		}
		$wpdb->update(
			$wpdb->prefix . 'doroto_tournaments',
			[
				'admin_users' => serialize($admins),
				'last_update' => round(microtime(true) * 1000),
				'close_date' => $close_date
			],
			['id' => $tournament_id]
		);
	}

	return new WP_REST_Response([
		'success' => true,
		'action' => 'example_organizer_added',
		'tournament_id' => $tournament_id // Vrátíme ID, aby aplikace věděla, kam přejít
	], 200);
}


/**
 * REST API: all players that can be included in special group
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/special-group-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_special_group_candidates',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);

	register_rest_route('doroto/v1', '/add-to-special-group', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_add_to_special_group',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_get_special_group_candidates(WP_REST_Request $request)
{

	$user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($user_id);
	$tournament_id = intval($request->get_param('tournament_id'));

	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	if ($tournament->close_tournament == '1') {
		return new WP_REST_Response(['error_code' => 'special_group_add_closed'], 400);
	}

	$players = maybe_unserialize($tournament->players);
	$special_group = maybe_unserialize($tournament->special_group);
	$whole_names = intval($tournament->whole_names);

	if (!is_array($players))
		$players = [];
	if (!is_array($special_group))
		$special_group = [];

	$candidate_ids = array_diff($players, $special_group);

	$candidates = [];
	if (!empty($candidate_ids)) {
		foreach ($candidate_ids as $player_id) {
			$user = get_userdata($player_id);
			if ($user) {
				$candidates[] = [
					'id' => $user->ID,
					'display_name' => doroto_find_player_name($user->ID, $whole_names),
				];
			}
		}
	}

	return rest_ensure_response([
		'success' => true,
		'candidates' => $candidates,
	]);
}

/**
 * REST API: add a player in special group
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_rest_post_add_to_special_group(WP_REST_Request $request)
{
	global $wpdb;

	$user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($user_id);

	$params = $request->get_json_params();
	$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : 0;
	$player_id = isset($params['player_id']) ? intval($params['player_id']) : 0;

	if ($tournament_id <= 0 || $player_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_data'], 400);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group)) {
		$special_group = [];
	}

	if (!in_array($player_id, $special_group)) {
		$special_group[] = $player_id;
	} else {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'player_already_in_special_group',
			'last_update' => intval($tournament->last_update),
		], 200);
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);

	$wpdb->update(
		$table_name,
		[
			'special_group' => serialize($special_group),
			'last_update' => $new_last_update,
		],
		['id' => $tournament_id]
	);

	doroto_tournament_progress($tournament_id);

	$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));

	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_added_to_special_group',
		'player_name' => $player_name,
		'last_update' => $new_last_update,
	], 200);
}

/**
 * REST API: remove a player from special group
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/remove-special-group-candidates', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_get_remove_special_group_candidates',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);

	register_rest_route('doroto/v1', '/remove-from-special-group', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_post_remove_from_special_group',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_get_remove_special_group_candidates(WP_REST_Request $request)
{
	$user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($user_id);
	$tournament_id = intval($request->get_param('tournament_id'));

	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$special_group = maybe_unserialize($tournament->special_group);
	$whole_names = intval($tournament->whole_names);

	if (!is_array($special_group)) {
		$special_group = [];
	}

	$candidates = [];
	if (!empty($special_group)) {
		foreach ($special_group as $player_id) {
			$user = get_userdata($player_id);
			if ($user) {
				$candidates[] = [
					'id' => $user->ID,
					'display_name' => doroto_find_player_name($user->ID, $whole_names),
				];
			}
		}
	}

	return rest_ensure_response([
		'success' => true,
		'candidates' => $candidates,
	]);
}

function doroto_rest_post_remove_from_special_group(WP_REST_Request $request)
{
	global $wpdb;

	$user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($user_id);

	$params = $request->get_json_params();
	$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : 0;
	$player_id = isset($params['player_id']) ? intval($params['player_id']) : 0;

	if ($tournament_id <= 0 || $player_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_data'], 400);
	}

	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'auth_insufficient_permissions'], 403);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$special_group = maybe_unserialize($tournament->special_group);
	if (!is_array($special_group)) {
		$special_group = [];
	}

	if (in_array($player_id, $special_group)) {
		$special_group = array_values(array_diff($special_group, [$player_id]));
	} else {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'player_not_in_special_group',
			'last_update' => intval($tournament->last_update),
		], 200);
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$new_last_update = round(microtime(true) * 1000);

	$wpdb->update(
		$table_name,
		[
			'special_group' => serialize($special_group),
			'last_update' => $new_last_update,
		],
		['id' => $tournament_id]
	);

	doroto_tournament_progress($tournament_id);

	$player_name = doroto_find_player_name($player_id, intval($tournament->whole_names));

	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_removed_from_special_group',
		'player_name' => $player_name,
		'last_update' => $new_last_update,
	], 200);
}

/**
 * REST API: delete user profile
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', 'doroto_register_delete_profile_endpoint');

function doroto_register_delete_profile_endpoint()
{
	register_rest_route('doroto/v1', '/delete-profile', [
		'methods' => 'POST',
		'callback' => 'doroto_handle_delete_profile',
		'permission_callback' => 'doroto_delete_profile_permissions_check',
	]);
}

/**
 *
 * @param WP_REST_Request $request
 * @return bool
 */
function doroto_delete_profile_permissions_check(WP_REST_Request $request)
{
	return doroto_get_current_user_id_from_token() > 0;
}

/**
 * Zpracovává logiku smazání uživatelského profilu s použitím Vaší vlastní funkce.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function doroto_handle_delete_profile(WP_REST_Request $request)
{
	$user_id = doroto_get_current_user_id_from_token();

	if ($user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}

	wp_set_current_user($user_id);

	if (user_can($user_id, 'manage_options')) {
		return new WP_REST_Response(['error_code' => 'delete_profile_admin_forbidden'], 403);
	}

	require_once(ABSPATH . 'wp-admin/includes/user.php');

	$result = wp_delete_user($user_id);

	if ($result) {
		return new WP_REST_Response([
			'success' => true,
			'action' => 'profile_deleted'
		], 200);
	} else {
		return new WP_REST_Response(['error_code' => 'delete_profile_failed'], 500);
	}
}

/**
 * REST API: provide url for doroto tournaments
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_get_main_url()
{
	$doroto_main_page_id = intval(get_option('doroto_main_page_id'));

	if ($doroto_main_page_id && get_post($doroto_main_page_id)) {
		$current_url = get_permalink($doroto_main_page_id);
	} else {

		$current_url = home_url('/');
	}

	return $current_url;
}


add_action('rest_api_init', function () {
	// Endpoint pro získání aktuálních adminů
	register_rest_route('doroto/v1', '/tournament-admins', [
		'methods' => ['GET', 'OPTIONS'],
		'callback' => 'doroto_rest_get_tournament_admins',
		'permission_callback' => 'doroto_super_admin_check',
	]);

	// Endpoint pro odebrání admina
	register_rest_route('doroto/v1', '/tournament-remove-admin', [
		'methods' => ['POST', 'OPTIONS'],
		'callback' => 'doroto_rest_remove_admin',
		'permission_callback' => 'doroto_super_admin_check',
	]);
});

/**
 * Pomocná funkce pro kontrolu, zda je uživatel Super Admin (zakladatel)
 */
function doroto_super_admin_check(WP_REST_Request $request)
{
	if ($request->get_method() === 'OPTIONS') {
		return true;
	}
	$user_id = doroto_get_current_user_id_from_token();
	$tournament_id = intval($request->get_param('tournament_id'));

	global $wpdb;
	$admin_users_raw = $wpdb->get_var($wpdb->prepare(
		"SELECT admin_users FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));

	$admin_users = maybe_unserialize($admin_users_raw);
	$super_admin_id = (is_array($admin_users) && !empty($admin_users)) ? (int) $admin_users[0] : 0;
	if ($user_id > 0) {
		wp_set_current_user($user_id);
	}

	$admin_level = doroto_is_admin($tournament_id);

	return ($user_id > 0 && ($user_id === $super_admin_id || $admin_level == 2));
}

function doroto_rest_get_tournament_admins(WP_REST_Request $request)
{
	$tournament_id = intval($request->get_param('tournament_id'));
	global $wpdb;

	$tournament = $wpdb->get_row($wpdb->prepare("SELECT admin_users FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id), ARRAY_A);
	$admin_ids = maybe_unserialize($tournament['admin_users']);

	if (!is_array($admin_ids)) $admin_ids = [];

	$admins_data = [];
	if (!empty($admin_ids)) {
		$placeholders = implode(',', array_fill(0, count($admin_ids), '%d'));
		$users = $wpdb->get_results($wpdb->prepare("SELECT ID as id, display_name FROM {$wpdb->users} WHERE ID IN ($placeholders)", ...$admin_ids), ARRAY_A);

		// Super admin (index 0) nebude v seznamu pro smazání
		$super_admin_id = $admin_ids[0];
		foreach ($users as $u) {
			if ((int)$u['id'] !== $super_admin_id) {
				$admins_data[] = $u;
			}
		}
	}

	$user_id = doroto_get_current_user_id_from_token();
	$super_admin_id = (!empty($admin_ids)) ? (int) $admin_ids[0] : 0;

	$admin_level = doroto_is_admin($tournament_id);

	$is_super_admin_flag = ($user_id === $super_admin_id || $admin_level == 2) ? 1 : 0;

	return new WP_REST_Response([
		'success' => true,
		'admins' => $admins_data,
		'is_super_admin' => $is_super_admin_flag
	], 200);
}

function doroto_rest_remove_admin(WP_REST_Request $request)
{
	$params = $request->get_json_params();
	$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : intval($request->get_param('tournament_id'));
	$admin_to_remove = intval($params['user_id'] ?? 0);

	global $wpdb;
	$admin_users_raw = $wpdb->get_var($wpdb->prepare("SELECT admin_users FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id));
	$admin_users = maybe_unserialize($admin_users_raw);

	if (($key = array_search($admin_to_remove, $admin_users)) !== false) {
		// Nikdy nesmíme odebrat Super Admina (index 0)
		if ($key === 0) {
			return new WP_REST_Response(['success' => false, 'message' => 'Cannot remove super admin'], 403);
		}
		unset($admin_users[$key]);
		$updated_admins = serialize(array_values($admin_users));

		$wpdb->update(
			"{$wpdb->prefix}doroto_tournaments",
			['admin_users' => $updated_admins],
			['id' => $tournament_id]
		);

		return new WP_REST_Response(['success' => true], 200);
	}

	return new WP_REST_Response(['success' => false, 'message' => 'Admin not found'], 404);
}
