<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Wrap a REST callback so that it runs while holding the tournament lock.
 * Every endpoint that reads a tournament, changes it and writes it back must
 * hold the lock for the whole read-modify-write. Before, e.g. correcting an old
 * result while another court saved its result wrote back a stale matches_list:
 * the new result and the newly drawn match were lost and the players stayed
 * "on court" for good, so nothing was drawn any more.
 * @since 1.6.0
 */
function doroto_rest_locked(callable $callback)
{
	return function (WP_REST_Request $request) use ($callback) {
		$tournament_id = intval($request->get_param('tournament_id'));
		if ($tournament_id <= 0) {
			$tournament_id = intval($request->get_param('id'));
		}
		if ($tournament_id <= 0) {
			return $callback($request);
		}
		return doroto_with_tournament_lock($tournament_id, function () use ($callback, $request) {
			return $callback($request);
		});
	};
}

/**
 * REST API: player registration and log in
 * @since 1.4.7
 * @version 1.6.0 (respects "Anyone can register", no session for organizer-created accounts)
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

	// An organizer creating a player from the app sends their own token.
	// Self-registration (no token) must respect Settings -> General ->
	// "Anyone can register"; before, it ignored the site owner's choice.
	$creator_id = doroto_get_current_user_id_from_token();
	if (!get_option('users_can_register')) {
		// With registration disabled only an organizer may create accounts.
		// Before, any signed-in player could create accounts with their token.
		if ($creator_id === 0 || !doroto_user_is_organizer($creator_id)) {
			return new WP_REST_Response(['error_code' => 'registration_disabled'], 403);
		}
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

	// When an organizer creates the account from the app (request carries the
	// organizer's token), remember the creator. Without this meta the new player
	// never appeared in the organizer's "Add from database" list (users-all),
	// and could not be re-created either ("e-mail exists").
	if ($creator_id > 0 && $creator_id !== intval($user_id)) {
		update_user_meta($user_id, 'doroto_creator', $creator_id);
		// The organizer did not choose a password for the player, so let the
		// player set one; otherwise the account could never be used.
		if (empty($params['password'])) {
			doroto_send_account_created_email($user_id);
		}

		// No session for the new player: the tokens went to the organizer, who
		// could then act as the player for 90 days. Apps read only user.ID here.
		return new WP_REST_Response([
			'success' => true,
			'action' => 'user_registered',
			'user_id' => $user_id,
			'user' => [
				'ID' => $user_id,
				'email' => $email,
				'username' => $name . ' ' . $surname,
				'name' => $name,
				'surname' => $surname
			]
		], 200);
	}

	$accessToken = bin2hex(random_bytes(32));
	$accessTokenExpiration = time() + 86400;

	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // valid 90 days

	doroto_store_session($user_id, $accessToken, $accessTokenExpiration, $refreshToken, $refreshTokenExpiration);

	return new WP_REST_Response([
		'success' => true,
		'action' => 'user_registered',
		'access_token' => $accessToken,
		'refresh_token' => $refreshToken,
		'user_id' => $user_id,
		'user' => [
			'ID' => $user_id,
			'email' => $email,
			'username' => $name . ' ' . $surname, // display_name could be used too
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

	// 1. Access token (short lifetime)
	$accessToken = bin2hex(random_bytes(32));
	$accessTokenExpiration = time() + 86400; // 24 hours

	// 2. Refresh token (long lifetime)
	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // 90 days

	// Store both tokens in the database
	doroto_store_session($user->ID, $accessToken, $accessTokenExpiration, $refreshToken, $refreshTokenExpiration);

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
 * Registers the REST endpoint that refreshes the token.
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/refresh-token', [
		'methods' => 'POST',
		'callback' => 'doroto_handle_refresh_token',
		'permission_callback' => '__return_true', // Permission is checked inside the callback
	]);
});

/**
 * Handles a token refresh request.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function doroto_handle_refresh_token($request)
{
	$params = $request->get_json_params();
	$refreshToken = sanitize_text_field($params['refresh_token'] ?? '');

	// 1. Was a token sent?
	if (empty($refreshToken)) {
		return new WP_REST_Response(['error_code' => 'refresh_token_missing'], 400);
	}

	// 2. Find the user by the refresh token
	$users = get_users([
		'meta_key' => 'doroto_refresh_token',
		'meta_value' => $refreshToken,
		'number' => 1,
		'fields' => ['ID'],
	]);

	if (empty($users)) {
		return new WP_REST_Response(['error_code' => 'refresh_token_invalid'], 401);
	}

	$user_id = intval($users[0]->ID);

	// 3. Kontrola platnosti (expirace) refresh tokenu
	$expiration = doroto_refresh_token_expiration($user_id, $refreshToken);

	if (!$expiration || time() >= intval($expiration)) {
		doroto_remove_session($user_id, $refreshToken);
		return new WP_REST_Response(['error_code' => 'refresh_token_expired'], 401);
	}

	// 4. New access token for this device only; other devices stay signed in.
	$newAccessToken = bin2hex(random_bytes(32));
	$newAccessTokenExpiration = time() + 86400; // Platnost 24 hodin

	$newRefreshToken = $refreshToken;

	// 5. Store the new tokens
	doroto_store_session($user_id, $newAccessToken, $newAccessTokenExpiration, $refreshToken, intval($expiration));

	// 6. Return the new tokens to the app
	return new WP_REST_Response([
		'success' => true,
		'action' => 'tokens_refreshed',
		'access_token' => $newAccessToken,
		'refresh_token' => $newRefreshToken,
	], 200);
}

/**
 * Sessions: one pair of tokens per signed-in device.
 *
 * Before 1.6.0 every login overwrote the single token pair of the user, so
 * signing in on a second phone (or the organizer's tablet) logged out the
 * first one. Now each device keeps its own pair:
 *  - user meta 'doroto_access_token' / 'doroto_refresh_token' hold one row per
 *    device (multi-value meta), so the lookup by meta value keeps working;
 *  - user meta 'doroto_sessions' maps sha256(refresh token) to
 *    [access, access_exp, refresh_exp, created].
 * Tokens issued by older versions (single rows with the *_expiration metas)
 * remain valid until they expire.
 * @since 1.6.0
 */
const DOROTO_MAX_SESSIONS = 10;

function doroto_session_key(string $refresh_token)
{
	return hash('sha256', $refresh_token);
}

function doroto_get_sessions(int $user_id)
{
	$sessions = get_user_meta($user_id, 'doroto_sessions', true);
	if (!is_array($sessions)) {
		$sessions = [];
	}
	// One-time migration of the single token pair issued before 1.6.0, so a
	// device signed in with an old token is not logged out by a new login.
	if (empty($sessions)) {
		$legacy_access = get_user_meta($user_id, 'doroto_access_token', true);
		$legacy_refresh = get_user_meta($user_id, 'doroto_refresh_token', true);
		$legacy_rexp = intval(get_user_meta($user_id, 'doroto_refresh_token_expiration', true));
		if ($legacy_access && $legacy_refresh && $legacy_rexp > time()) {
			$key = doroto_session_key($legacy_refresh);
			$sessions[$key] = [
				'access' => $legacy_access,
				'access_exp' => intval(get_user_meta($user_id, 'doroto_access_token_expiration', true)),
				'refresh_exp' => $legacy_rexp,
				'created' => 0,
			];
			update_user_meta($user_id, 'doroto_sessions', $sessions);
			update_user_meta($user_id, 'doroto_refresh_tokens_plain', [$key => $legacy_refresh]);
		}
	}
	return $sessions;
}

/**
 * Run a callback while holding a per-user lock, with fresh user meta.
 * All sessions of a user live in one meta array that is read, changed and
 * written back. Two devices signing in or refreshing at the same moment
 * overwrote each other's session, so all but one device were signed out.
 * @since 1.6.0
 */
function doroto_with_user_lock(int $user_id, callable $callback)
{
	global $wpdb;
	$name = 'doroto_u_' . $wpdb->prefix . $user_id;
	$got = intval($wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 10))) === 1;
	// The meta may have been cached earlier in this request (token lookup).
	wp_cache_delete($user_id, 'user_meta');
	try {
		return $callback();
	} finally {
		if ($got) {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
		}
	}
}

/**
 * Store (or update) the session identified by the refresh token.
 */
function doroto_store_session(int $user_id, string $access_token, int $access_exp, string $refresh_token, int $refresh_exp)
{
	doroto_with_user_lock($user_id, function () use ($user_id, $access_token, $access_exp, $refresh_token, $refresh_exp) {
		doroto_store_session_locked($user_id, $access_token, $access_exp, $refresh_token, $refresh_exp);
	});
}

function doroto_store_session_locked(int $user_id, string $access_token, int $access_exp, string $refresh_token, int $refresh_exp)
{
	$sessions = doroto_get_sessions($user_id);
	$key = doroto_session_key($refresh_token);
	$now = time();

	// Milliseconds: logins within one second must still have a clear order,
	// otherwise the session cap dropped a random new device, not the oldest.
	// (Sessions stored in seconds sort as older, which they are.)
	$created = isset($sessions[$key]['created']) ? intval($sessions[$key]['created']) : doroto_now_ms();
	$is_new = !isset($sessions[$key]);
	$sessions[$key] = [
		'access' => $access_token,
		'access_exp' => $access_exp,
		'refresh_exp' => $refresh_exp,
		'created' => $created,
	];

	// Drop expired sessions and keep only the newest ones.
	$sessions = array_filter($sessions, function ($s) use ($now) {
		return intval($s['refresh_exp'] ?? 0) > $now;
	});
	uasort($sessions, function ($a, $b) {
		return intval($b['created']) <=> intval($a['created']);
	});
	$kept = array_slice($sessions, 0, DOROTO_MAX_SESSIONS, true);

	$refresh_of = get_user_meta($user_id, 'doroto_refresh_tokens_plain', true);
	$refresh_of = is_array($refresh_of) ? $refresh_of : [];
	if ($is_new) {
		$refresh_of[$key] = $refresh_token;
	}
	$refresh_of = array_intersect_key($refresh_of, $kept);

	// Store the session data first, then sync the lookup rows. Only rows that
	// changed are touched: deleting all rows and adding them back left a moment
	// in which the tokens of the user's other devices were not found, and their
	// requests failed with 401 (or their refresh with "invalid").
	update_user_meta($user_id, 'doroto_refresh_tokens_plain', $refresh_of);
	update_user_meta($user_id, 'doroto_sessions', $kept);
	doroto_sync_meta_rows($user_id, 'doroto_access_token', array_column($kept, 'access'));
	doroto_sync_meta_rows($user_id, 'doroto_refresh_token', array_values($refresh_of));
	delete_user_meta($user_id, 'doroto_access_token_expiration');
	delete_user_meta($user_id, 'doroto_refresh_token_expiration');
}

/**
 * Make the multi-row user meta $meta_key hold exactly $values, adding the
 * new rows before removing the old ones.
 * @since 1.6.0
 */
function doroto_sync_meta_rows(int $user_id, string $meta_key, array $values)
{
	$current = array_map('strval', (array) get_user_meta($user_id, $meta_key));
	$values = array_values(array_unique(array_map('strval', $values)));
	foreach (array_diff($values, $current) as $value) {
		add_user_meta($user_id, $meta_key, $value);
	}
	foreach (array_diff($current, $values) as $value) {
		delete_user_meta($user_id, $meta_key, $value);
	}
}

function doroto_remove_session(int $user_id, string $refresh_token)
{
	doroto_with_user_lock($user_id, function () use ($user_id, $refresh_token) {
		doroto_remove_session_locked($user_id, $refresh_token);
	});
}

function doroto_remove_session_locked(int $user_id, string $refresh_token)
{
	$sessions = doroto_get_sessions($user_id);
	$key = doroto_session_key($refresh_token);
	if (isset($sessions[$key])) {
		delete_user_meta($user_id, 'doroto_access_token', $sessions[$key]['access']);
		unset($sessions[$key]);
		update_user_meta($user_id, 'doroto_sessions', $sessions);
	}
	delete_user_meta($user_id, 'doroto_refresh_token', $refresh_token);
	$refresh_of = get_user_meta($user_id, 'doroto_refresh_tokens_plain', true);
	if (is_array($refresh_of)) {
		unset($refresh_of[$key]);
		update_user_meta($user_id, 'doroto_refresh_tokens_plain', $refresh_of);
	}
}

/**
 * Expiration timestamp of the given access token, 0 when unknown.
 */
function doroto_access_token_expiration(int $user_id, string $access_token)
{
	foreach (doroto_get_sessions($user_id) as $s) {
		if (isset($s['access']) && hash_equals($s['access'], $access_token)) {
			return intval($s['access_exp']);
		}
	}
	// Token issued before 1.6.0
	return intval(get_user_meta($user_id, 'doroto_access_token_expiration', true));
}

/**
 * Expiration timestamp of the given refresh token, 0 when unknown.
 */
function doroto_refresh_token_expiration(int $user_id, string $refresh_token)
{
	$sessions = doroto_get_sessions($user_id);
	$key = doroto_session_key($refresh_token);
	if (isset($sessions[$key])) {
		return intval($sessions[$key]['refresh_exp']);
	}
	// Token issued before 1.6.0: migrate it into a session on first use.
	$legacy = intval(get_user_meta($user_id, 'doroto_refresh_token_expiration', true));
	return $legacy;
}

/**
 * Read the raw Authorization header.
 * getallheaders() alone is not enough: on FPM/CGI hosts the header is often
 * only available in $_SERVER (HTTP_AUTHORIZATION or REDIRECT_HTTP_AUTHORIZATION).
 * @since 1.6.0
 */
function doroto_get_authorization_header()
{
	$candidates = [];
	if (function_exists('getallheaders')) {
		foreach ((array) getallheaders() as $name => $value) {
			if (strtolower($name) === 'authorization') {
				$candidates[] = $value;
			}
		}
	}
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitized by the caller
	if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
		$candidates[] = wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
	}
	if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
		$candidates[] = wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
	}
	// phpcs:enable
	foreach ($candidates as $value) {
		if (is_string($value) && $value !== '') {
			return $value;
		}
	}
	return '';
}

/**
 * REST API: provide user token
 * Without an Authorization header the user signed in to the website is used (browser
 * requests from the blocks). WordPress core accepts the login cookie in REST only with
 * a valid X-WP-Nonce, otherwise it has already reset the current user to 0.
 * A sent but unknown token still gives 0, the app relies on that.
 * @since 1.4.7
 * @version 2.0.0 (cookie + nonce fallback)
 */
function doroto_get_current_user_id_from_token()
{
	$header = doroto_get_authorization_header();
	if ($header === '') {
		return (int) get_current_user_id();
	}
	$token = sanitize_text_field(trim(preg_replace('/^Bearer\s+/i', '', $header)));

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
			$expiration = doroto_access_token_expiration(intval($user_id), $token);

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

	// Read the parameters
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

	// Step 1: basic visibility condition (always applied)
	if ($current_user_id > 0) {
		$serialized_user_id_like = '%' . $wpdb->esc_like('i:' . $current_user_id . ';') . '%';
		$where_clauses[] = "(visibility = 1 OR (visibility = 0 AND (players LIKE %s OR admin_users LIKE %s)))";
		$params[] = $serialized_user_id_like;
		$params[] = $serialized_user_id_like;
	} else {
		$where_clauses[] = "visibility = 1";
	}

	// --- Step 2: further filters ---

	// Filters by game type and by state in one if-else
	if ($filter_option >= 20 && $filter_option <= 29) {
		// Filter by game type
		$where_clauses[] = "tournament_type = %d";
		$params[] = $filter_option;
	} else {
		// Filter by state (only when it is not a game type filter)
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

	// Filter by the user's role (4-7)
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

	// Filter by the search text
	if (!empty($search_term)) {
		$where_clauses[] = "name LIKE %s";
		$params[] = '%' . $wpdb->esc_like($search_term) . '%';
	}

	// Build and run the query
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

	// Draw pending matches first and only then read the row, so the response
	// already contains the newly drawn match (previously the app saw it only
	// on the next poll, which looked like a long lag after saving a result).
	$offer_html = doroto_offer_games($tournament_id, 0);
	if ($offer_html != "")
		$offer_html = 'display warning';

	$tournament = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id), ARRAY_A);
	if (!$tournament) {
		return new WP_Error('not_found', 'Tournament not found', ['status' => 404]);
	}
	$tournament_diff = doroto_prepare_tournament($tournament_id);

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
	// These are plain ID lists. A removal with array_diff() used to leave gaps
	// in the keys and json_encode then sent an object ({"0":3,"2":7}), which
	// the app could not read as a list.
	foreach (['players', 'payment_done', 'special_group', 'admin_users'] as $field) {
		$tournament[$field] = is_array($tournament[$field]) ? array_values($tournament[$field]) : [];
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

	// Condition:
	$tournament['is_super_admin'] = ($current_user_id === $super_admin_id || $tournament['is_admin'] == 2) ? 1 : 0;

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
		'callback' => doroto_rest_locked('doroto_tournament_save_via_api'),
		'permission_callback' => '__return_true',
	]);
});

/**
 * tournament-save: settings, and also the final pairs (l1, p1, l2, p2) and the
 * final result (result_1, result_2) as the app sends them.
 * @version 2.0.0 (services)
 */
function doroto_tournament_save_via_api(WP_REST_Request $request)
{
	$current_user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);
	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_logged_in'], 401);
	}

	$tournament_id = intval($request->get_param('tournament_id'));
	if ($tournament_id <= 0) {
		return new WP_REST_Response(['error_code' => 'invalid_tournament_id'], 400);
	}

	$params = $request->get_params();
	$last_update = 0;
	$finals_only = true;

	$four = array_map('intval', [$params['l1'] ?? 0, $params['p1'] ?? 0, $params['l2'] ?? 0, $params['p2'] ?? 0]);
	if (min($four) > 0) {
		$result = doroto_service_set_final_four($tournament_id, ...$four);
		if (is_wp_error($result)) {
			return doroto_service_rest_response($result);
		}
		$last_update = $result['last_update'];
	}
	if (isset($params['result_1'], $params['result_2'])) {
		$result = doroto_service_set_final_result($tournament_id, intval($params['result_1']), intval($params['result_2']));
		if (is_wp_error($result)) {
			return doroto_service_rest_response($result);
		}
		$last_update = $result['last_update'];
	} else {
		$finals_only = min($four) > 0;
	}

	foreach (array_merge(array_keys(doroto_service_settings_fields()), ['delete_tournament', 'empty_tournament', 'new_post']) as $key) {
		if (isset($params[$key])) {
			$finals_only = false;
		}
	}
	if (!$finals_only) {
		$result = doroto_service_save_settings($tournament_id, $params);
		if (is_wp_error($result)) {
			return doroto_service_rest_response($result);
		}
		if ($result['action'] === 'tournament_deleted') {
			return new WP_REST_Response(['success' => true, 'action' => 'tournament_deleted']);
		}
		if ($result['post_not_allowed']) {
			return new WP_REST_Response(['error' => 'You do not have the necessary rights to create a post.'], 403);
		}
		$last_update = $result['last_update'];
	}

	return new WP_REST_Response([
		'success' => true,
		'action' => 'tournament_updated',
		'last_update' => $last_update,
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
	$current_user_id = doroto_get_current_user_id_from_token();
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authenticated'], 401);
	}
	wp_set_current_user($current_user_id);

	$params = (array) $request->get_json_params();
	if (!isset($params['tournament_type'])) {
		return new WP_REST_Response(['error_code' => 'create_missing_type'], 400);
	}
	return doroto_service_rest_response(doroto_service_add_tournament(intval($params['tournament_type'])));
}

/**
 * REST API: log in a player to the tournament
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-register/(?P<id>\d+)', [
		'methods' => 'POST',
		'callback' => doroto_rest_locked('doroto_rest_register_player'),
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_register_player(WP_REST_Request $request)
{
	$current_user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_logged_in'], 401);
	}
	$tournament_id = intval($request['id']);

	// Optional 'mode' (since 1.6.0): 'join' or 'leave' makes the call idempotent,
	// so a repeated request can no longer flip the registration back.
	// Without 'mode' the endpoint keeps the old toggle behaviour for old app versions.
	$mode = sanitize_key((string) $request->get_param('mode'));
	if ($mode !== 'join' && $mode !== 'leave') {
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
		}
		if ($tournament->open_registration != '1') {
			return new WP_REST_Response(['error_code' => 'registration_is_closed'], 403);
		}
		$players = maybe_unserialize($tournament->players);
		$registered = is_array($players) && in_array($current_user_id, array_map('intval', $players), true);
		$mode = $registered ? 'leave' : 'join';
	}

	$result = $mode === 'join' ? doroto_service_join($tournament_id) : doroto_service_leave($tournament_id);
	if (is_wp_error($result)) {
		return doroto_service_rest_response($result);
	}
	$messages = [
		'registered' => 'You have successfully logged into the tournament.',
		'unregistered' => 'You have been removed from the tournament.',
	];
	return new WP_REST_Response(array_merge($result, [
		'message' => $messages[$result['action']] ?? '',
		'player_id' => $current_user_id,
		'tournament_id' => $tournament_id,
	]));
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
		// Since 1.6.0: lets the app detect available endpoints (e.g. create-player)
		// and explain why self-registration is not possible.
		'api_version' => 2,
		'plugin_version' => doroto_VERSION,
		'users_can_register' => get_option('users_can_register') ? 1 : 0,
		'register_url' => get_option('users_can_register') ? esc_url_raw(wp_registration_url()) : '',
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
	$tournament_id = intval($request->get_param('tournament_id'));
	$player_id = intval($request->get_param('player_to_add'));
	$current_user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);

	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}

	$result = doroto_service_add_player($tournament_id, $player_id);
	if (is_wp_error($result)) {
		return doroto_service_rest_response($result);
	}

	// Backward compatibility with app versions before 1.1: they create the
	// account via player/register WITHOUT the organizer's token and add it right
	// after. Treat an account registered minutes ago and not yet claimed as
	// created by this organizer, so it shows up in his "Add from database" list,
	// and let the player set a password (otherwise nobody knows it).
	$player_data = $result['already_added'] ? false : get_userdata($player_id);
	if ($player_data && get_user_meta($player_id, 'doroto_creator', true) === '') {
		$registered = strtotime($player_data->user_registered . ' UTC');
		if ($registered && (time() - $registered) < 15 * MINUTE_IN_SECONDS && $player_id !== intval($current_user_id)) {
			update_user_meta($player_id, 'doroto_creator', intval($current_user_id));
			doroto_send_account_created_email($player_id);
		}
	}

	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_added_to_tournament',
		'player_name' => $result['player_name'],
		'last_update' => $result['last_update'],
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
		'callback' => doroto_rest_locked('doroto_remove_player_via_api'),
		'permission_callback' => '__return_true',
	]);
});

function doroto_remove_player_via_api(WP_REST_Request $request)
{
	$current_user_id = (int) doroto_get_current_user_id_from_token();
	wp_set_current_user($current_user_id);
	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}

	return doroto_service_rest_response(doroto_service_remove_player(
		intval($request->get_param('tournament_id')),
		intval($request->get_param('player_to_remove'))
	));
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
	$current_user_id = intval(doroto_get_current_user_id_from_token());
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($current_user_id);

	$tournament_id = intval($request->get_param('tournament_id'));
	$result = doroto_service_enter_result(
		$tournament_id,
		intval($request->get_param('match_number')),
		intval($request->get_param('result_1')),
		intval($request->get_param('result_2')),
		intval($request->get_param('hide')) === 1
	);

	if (is_wp_error($result) && $result->get_error_code() === 'match_already_entered') {
		// Answer of 1.4.7-1.6.x that the app shows as a message: code "forbidden", HTTP 200.
		return new WP_Error('forbidden', esc_html(doroto_service_message($result, $tournament_id)), ['status' => 200]);
	}
	if (is_wp_error($result)) {
		return doroto_service_rest_response($result);
	}
	return rest_ensure_response([
		'success' => true,
		'action' => 'match_result_updated',
		'no_new_match' => $result['no_new_match'],
		'last_update' => $result['last_update'],
	]);
}


/**
 * REST API: skip several ongoing matches at once, then draw new matches.
 * Skipping them one by one through match-result drew a new match after each
 * one, so only the players of the first skipped match were free for it.
 * Organizer only.
 * @since 1.6.2
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/skip-matches', [
		'methods' => 'POST',
		'callback' => doroto_rest_locked('doroto_rest_skip_matches'),
		'permission_callback' => function (WP_REST_Request $req) {
			return doroto_get_current_user_id_from_token() > 0;
		},
		'args' => [
			'tournament_id' => [
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'match_numbers' => [
				'required' => true,
				'validate_callback' => function ($val) {
					return is_array($val) && !empty($val);
				},
			],
		],
	]);
});

function doroto_rest_skip_matches(WP_REST_Request $request)
{
	$current_user_id = intval(doroto_get_current_user_id_from_token());
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($current_user_id);

	return doroto_service_rest_response(doroto_service_skip_matches(
		intval($request->get_param('tournament_id')),
		(array) $request->get_param('match_numbers')
	));
}


/**
 * REST API: round end actions
 * @since 1.4.7
 * @version 1.4.7 
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/round-end-action', [
		'methods' => 'POST',
		'callback' => doroto_rest_locked('doroto_rest_round_end_action'),
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
	$user_id = intval(doroto_get_current_user_id_from_token());
	if ($user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($user_id);

	return doroto_service_rest_response(doroto_service_round_end_action(
		intval($req->get_param('tournament_id')),
		sanitize_key((string) $req->get_param('action'))
	));
}


add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/tournament-toggle', [
		'methods' => ['POST', 'OPTIONS'],
		'callback' => doroto_rest_locked('doroto_rest_toggle'),
		'permission_callback' => '__return_true',
	]);
});

/**
 * REST API: change tournament state
 * body: tournament_id, action_type = registration | tournament
 * @since 1.4.7
 * @version 2.0.0 (services)
 */
function doroto_rest_toggle(WP_REST_Request $req)
{
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

	if ($atype === 'registration') {
		return doroto_service_rest_response(doroto_service_toggle_registration($tid));
	}
	if ($atype === 'tournament') {
		return doroto_service_rest_response(doroto_service_toggle_tournament($tid));
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
		'callback' => doroto_rest_locked('doroto_rest_add_admin'),
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
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		return new WP_REST_Response(null, 200);
	}

	$body = json_decode($req->get_body(), true);
	$current = (int) doroto_get_current_user_id_from_token();
	if ($current === 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized_or_expired'], 401);
	}
	wp_set_current_user($current);

	return doroto_service_rest_response(doroto_service_add_admin(
		intval($body['tournament_id'] ?? 0),
		intval($body['player_id'] ?? 0)
	));
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
	$email_verified = $payload['email_verified'] ?? false;
	if ($email_verified !== true && $email_verified !== 'true') {
		// Never match an existing account by an unverified address.
		return new WP_REST_Response(['error_code' => 'google_login_missing_email'], 400);
	}
	if (empty($email)) {
		$logs[] = 'ERROR: Email missing in token payload';
		return new WP_REST_Response(['error_code' => 'google_login_missing_email'], 400);
	}

	$user = get_user_by('email', $email);

	if (!$user) {
		// A Google sign-in of an unknown address creates an account, which is a
		// self-registration: respect "Anyone can register" (since 1.6.0).
		if (!get_option('users_can_register')) {
			return new WP_REST_Response(['error_code' => 'registration_disabled'], 403);
		}
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

	// 1. Generate both tokens (access and refresh)
	$accessToken = bin2hex(random_bytes(32));
	// Same lifetime as e-mail login. 1 hour logged Google users out quickly,
	// because older app versions never use the refresh token.
	$accessTokenExpiration = time() + 86400;

	$refreshToken = bin2hex(random_bytes(64));
	$refreshTokenExpiration = time() + (90 * 24 * 3600); // Valid for 90 days

	// 2. Store both tokens and their expiry times
	doroto_store_session($user->ID, $accessToken, $accessTokenExpiration, $refreshToken, $refreshTokenExpiration);

	$logs[] = "Generated new access and refresh tokens for user ID {$user->ID}";


	return new WP_REST_Response([
		'success' => true,
		'action' => 'google_login_successful',
		'access_token' => $accessToken,   // access_token is returned too
		'refresh_token' => $refreshToken,
		'user' => [
			'ID' => $user->ID,
			'email' => $user->user_email,
			'username' => $user->user_login,
			'name' => $user->first_name,
			'surname' => $user->last_name,
		],
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
		'callback' => doroto_rest_locked('doroto_rest_post_tournament_disable_player'),
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
	]);
}



function doroto_rest_post_tournament_disable_player(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	return doroto_service_rest_response(doroto_service_set_player_active(
		intval($request->get_param('tournament_id')),
		intval($request->get_param('disable_player')),
		false
	));
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
		'callback' => doroto_rest_locked('doroto_rest_post_tournament_restore_player'),
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
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	return doroto_service_rest_response(doroto_service_set_player_active(
		intval($request->get_param('tournament_id')),
		intval($request->get_param('restore_player')),
		true
	));
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
		'callback' => doroto_rest_locked('doroto_rest_post_enter_payment'),
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
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($uid);

	return doroto_service_rest_response(doroto_service_set_payment(intval($request->get_param('tournament_id')), intval($request->get_param('confirm_payment')), true));
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
		'callback' => doroto_rest_locked('doroto_rest_post_remove_payment'),
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
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($uid);

	return doroto_service_rest_response(doroto_service_set_payment(intval($request->get_param('tournament_id')), intval($request->get_param('remove_payment')), false));
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

	// Polled by the app every 30 s: read only the one column it needs.
	$last_update = $wpdb->get_var($wpdb->prepare("SELECT last_update FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id));
	if ($last_update === null) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	return intval($last_update);
}

add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/change-match-result', array(
		'methods' => 'POST',
		'callback' => doroto_rest_locked('doroto_api_change_match_result'),
		'permission_callback' => '__return_true',
	));
});

/**
 * Handles the logic for the /change-match-result endpoint.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 * @version 2.0.0 (doroto_service_change_result)
 */
function doroto_api_change_match_result(WP_REST_Request $request)
{
	$current_user_id = (int) doroto_get_current_user_id_from_token();
	if ($current_user_id === 0) {
		return new WP_REST_Response(['error_code' => 'auth_not_authorized'], 401);
	}
	wp_set_current_user($current_user_id);
	$params = (array) $request->get_json_params();

	return doroto_service_rest_response(doroto_service_change_result(
		isset($params['tournament_id']) ? intval($params['tournament_id']) : 0,
		isset($params['match_number']) ? intval($params['match_number']) : 0,
		isset($params['result_1']) ? intval($params['result_1']) : -1,
		isset($params['result_2']) ? intval($params['result_2']) : -1
	));
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
		'tournament_id' => $tournament_id // The app opens this tournament
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
		'callback' => doroto_rest_locked('doroto_rest_post_add_to_special_group'),
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
 * @version 2.0.0 (services)
 */
function doroto_rest_post_add_to_special_group(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($uid);
	$params = (array) $request->get_json_params();

	return doroto_service_rest_response(doroto_service_set_special_group(intval($params['tournament_id'] ?? 0), intval($params['player_id'] ?? 0), true));
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
		'callback' => doroto_rest_locked('doroto_rest_post_remove_from_special_group'),
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

function doroto_rest_post_remove_from_special_group(\WP_REST_Request $request)
{
	$uid = doroto_get_current_user_id_from_token();
	if ($uid <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($uid);
	$params = (array) $request->get_json_params();

	return doroto_service_rest_response(doroto_service_set_special_group(intval($params['tournament_id'] ?? 0), intval($params['player_id'] ?? 0), false));
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
 * Deletes the user's profile with the plugin's own function.
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
	// Endpoint: current organizers
	register_rest_route('doroto/v1', '/tournament-admins', [
		'methods' => ['GET', 'OPTIONS'],
		'callback' => 'doroto_rest_get_tournament_admins',
		'permission_callback' => 'doroto_super_admin_check',
	]);

	// Endpoint: remove an organizer
	register_rest_route('doroto/v1', '/tournament-remove-admin', [
		'methods' => ['POST', 'OPTIONS'],
		'callback' => doroto_rest_locked('doroto_rest_remove_admin'),
		'permission_callback' => 'doroto_super_admin_check',
	]);
});

/**
 * Whether the user is the super admin (the founder of the tournament)
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

		// The super admin (index 0) is not offered for removal
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
	$params = (array) $request->get_json_params();
	$tournament_id = isset($params['tournament_id']) ? intval($params['tournament_id']) : intval($request->get_param('tournament_id'));
	$result = doroto_service_remove_admin($tournament_id, intval($params['user_id'] ?? 0));

	// Response format of 1.4.7: {success} or {success: false, message}.
	if (is_wp_error($result)) {
		$code = $result->get_error_code();
		if ($code === 'cannot_remove_founder') {
			return new WP_REST_Response(['success' => false, 'message' => 'Cannot remove super admin'], 403);
		}
		if ($code === 'admin_not_found') {
			return new WP_REST_Response(['success' => false, 'message' => 'Admin not found'], 404);
		}
		return doroto_service_rest_response($result);
	}
	return new WP_REST_Response(['success' => true, 'action' => 'organizer_removed', 'last_update' => $result['last_update']], 200);
}


/**
 * REST API: organizer creates a player account and adds it to the tournament in one step.
 * Replaces the two-step flow (player/register + add-player) of older app versions,
 * which left half-created players behind when the second step failed:
 * the account existed but was not in the tournament and could not be created again.
 * Idempotent: when the e-mail already exists the existing user is added instead.
 * Response contains 'created' (bool) and 'email_sent' (bool).
 * @since 1.6.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/create-player', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_create_player',
		'permission_callback' => function () {
			return doroto_get_current_user_id_from_token() > 0;
		},
	]);
});

function doroto_rest_create_player(WP_REST_Request $request)
{
	$current_user_id = doroto_get_current_user_id_from_token();
	if ($current_user_id <= 0) {
		return new WP_REST_Response(['error_code' => 'auth_unauthorized'], 401);
	}
	wp_set_current_user($current_user_id);

	$params = $request->get_json_params();
	if (!is_array($params)) {
		$params = $request->get_params();
	}
	$tournament_id = intval($params['tournament_id'] ?? 0);
	$email = sanitize_email($params['email'] ?? '');
	$name = sanitize_text_field($params['name'] ?? '');
	$surname = sanitize_text_field($params['surname'] ?? '');

	if ($tournament_id <= 0 || !doroto_prepare_tournament($tournament_id)) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	if (doroto_is_admin($tournament_id) < 1) {
		return new WP_REST_Response(['error_code' => 'add_player_forbidden'], 403);
	}
	if (empty($email) || !is_email($email) || empty($name) || empty($surname)) {
		return new WP_REST_Response(['error_code' => 'registration_incomplete_data'], 400);
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
			return new WP_REST_Response(['error_code' => 'registration_creation_failed'], 500);
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
		return new WP_REST_Response(['error_code' => 'registration_max_players', 'created' => $created], 400);
	}
	if ($status === 'not_found') {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	return new WP_REST_Response([
		'success' => true,
		'action' => 'player_added_to_tournament',
		'created' => $created,
		'already_in_tournament' => $status === 'already',
		'email_sent' => $email_sent,
		'player_id' => $user_id,
		'player_name' => doroto_find_player_name($user_id, intval($tournament->whole_names)),
		'last_update' => intval($tournament->last_update),
	], 200);
}


/**
 * E-mail a newly created player a link to set the password.
 * Accounts created by an organizer used to get a random password nobody knew.
 * @since 1.6.0
 */
function doroto_send_account_created_email(int $user_id)
{
	$user = get_userdata($user_id);
	if (!$user) {
		return false;
	}
	$key = get_password_reset_key($user);
	if (is_wp_error($key)) {
		return false;
	}
	$reset_url = network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
	$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$subject = sprintf(
		/* translators: %s: Site name. */
		__('[%s] Your player account has been created', 'doubles-rotation-tournament'),
		$site_name
	);
	$body = '<p>' . sprintf(
		/* translators: %s: Player name. */
		esc_html__('Hello %s,', 'doubles-rotation-tournament'),
		esc_html($user->display_name)
	) . '</p>';
	$body .= '<p>' . esc_html__('A tournament organizer has created a player account for you.', 'doubles-rotation-tournament') . '</p>';
	$body .= '<p>' . esc_html__('To sign in on the website or in the Doroto app, first set your password here:', 'doubles-rotation-tournament') . '<br>';
	$body .= '<a href="' . esc_url($reset_url) . '">' . esc_html($reset_url) . '</a></p>';
	$body .= '<p>' . esc_html__('Your login e-mail:', 'doubles-rotation-tournament') . ' ' . esc_html($user->user_email) . '</p>';
	$body .= '<p>' . esc_html__('Best regards,', 'doubles-rotation-tournament') . '<br>' . esc_html__('The Doroto Team', 'doubles-rotation-tournament') . '</p>';

	ob_start();
	$sent = wp_mail($user->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
	ob_end_clean();
	return (bool) $sent;
}


/**
 * Tournament data changes all the time; make sure page caches, CDNs and
 * caching plugins never serve a stale copy of the Doroto REST responses.
 * @since 1.6.0
 */
add_filter('rest_post_dispatch', function ($response, $server, $request) {
	$route = $request instanceof WP_REST_Request ? $request->get_route() : '';
	if ($response instanceof WP_REST_Response && (strpos($route, '/doroto/v1/') === 0 || strpos($route, '/player/') === 0)) {
		$response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
		$response->header('Pragma', 'no-cache');
	}
	return $response;
}, 10, 3);
