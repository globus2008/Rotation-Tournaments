<?php
if (! defined('ABSPATH')) {
	exit;
}


/**
 * check if a user is log in
 * @since 1.0.0
 */
function doroto_not_logged_message()
{
	if (isset($_GET['log_in'])) {
		return '<p class="doroto-red-text"><b>' . esc_html__("If you want to register for the tournament, you must log in to your account!", "doubles-rotation-tournament") . '</b></p>';
	} else {
		return '';
	}
}


/**
 * display messages from forms
 * @since 1.0.0
 * @version 1.3.3 (do not show some info during a presentation)
 */
function doroto_info_messsages_shortcode()
{
	global $doroto_output_form;

	//support presentation
	doroto_change_presentation();

	$current_user_id = intval(get_current_user_id());
	if ($doroto_output_form == '') {
		if (!is_user_logged_in()) {
			$doroto_output_form = get_option('doroto_output_form');
			update_option('doroto_output_form', '');
		} else {
			$doroto_output_form = get_user_meta($current_user_id, 'doroto_output_form', true);
			update_user_meta($current_user_id, 'doroto_output_form', '');
		}
	}
	$output = '<div id="doroto-message-top">';
	if ($doroto_output_form != '') {
		$allowed_html = doroto_allowed_html();
		$output .= '<p class="doroto-red-text">' . wp_kses($doroto_output_form, $allowed_html) . '</p>';
	} else {
		$output .= '';
	}
	$doroto_output_form = '';
	$players = doroto_current_user_in_tournaments(0);

	if (!is_user_logged_in()) {
		$log_in_link = doroto_read_settings('log_in_link', '');
		if ($log_in_link != '') {
			$output .= '<p class="doroto-red-text" id="doroto-output-form-not-login">' . esc_html__("Warning: You are not logged in to your account.", 'doubles-rotation-tournament') . ' ';
			$login_url = sanitize_text_field(wp_unslash($log_in_link));
			$output .= '<a href="' . esc_url($login_url) . '">' . esc_html__('You can log in here.', 'doubles-rotation-tournament') . '</a></p>';
		}
	} elseif (empty($players) && !doroto_check_if_presentation_on()) {
		$output .= '<div class = "doroto-message-background">';
		$output .= esc_html__('You might need a little help. You can start by clicking the floating help icon on the right.', 'doubles-rotation-tournament');
		$output .= '</div>';
	}
	$output .= '</div>';
	return $output;
}
add_shortcode('doroto_info_messsages', 'doroto_info_messsages_shortcode');


/**
 * save output message to 'usermeta' table
 * @since 1.0.0
 */
function doroto_info_messsages_save(string $output)
{
	global $doroto_output_form;
	$doroto_output_form = $output;
	$allowed_html = doroto_allowed_html();

	if (!is_user_logged_in()) {
		update_option('doroto_output_form', wp_kses($output, $allowed_html));
	} else {
		$current_user = wp_get_current_user();
		update_user_meta(intval($current_user->ID), 'doroto_output_form', wp_kses($output, $allowed_html));
	}
}

/**
 * allow these html entities in strings and posts
 * @version 1.1.6
 * @since 1.0.0
 */
function doroto_allowed_html()
{
	$allowed_html = array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'target' => array()
		),
		'br' => array(),
		'em' => array(),
		'strong' => array(),
		'p' => array(
			'class' => array(),
			'id' => array(),
		),
		'div' => array(
			'class' => array(),
			'id' => array(),
		),
		'b' => array(
			'class' => array(),
			'id' => array(),
		),
		'h1' => array(
			'class' => array(),
			'id' => array(),
		),
		'h2' => array(
			'class' => array(),
			'id' => array(),
		),
		'h3' => array(
			'class' => array(),
			'id' => array(),
		),
		'h4' => array(
			'class' => array(),
			'id' => array(),
		),
		'h5' => array(
			'class' => array(),
			'id' => array(),
		),
		'h6' => array(
			'class' => array(),
			'id' => array(),
		),
		'li' => array(
			'class' => array(),
			'id' => array(),
		),
		'span' => array(
			'class' => array(),
			'id' => array(),
		),
		'ol' => array(
			'class' => array(),
			'id' => array(),
		),
		'ul' => array(
			'class' => array(),
			'id' => array(),
		),

	);

	return $allowed_html;
}


/**
 * get tournament ID
 * @since 1.0.0
 * @version 1.5.8
 */
function doroto_getTournamentId()
{
	global $wpdb;
	global $doroto_pernament_tournament_id;

	$current_user = wp_get_current_user();
	$userId = intval($current_user->ID);
	$table_name = $wpdb->prefix . 'doroto_tournaments';

	// We will try to get the tournament_id from the URL if it is available
	if (isset($_GET['tournament_id'])) {
		$tournament_id = intval($_GET['tournament_id']);
		$exists = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$tournament_id
		));
		if ($exists) {
			return $tournament_id;
		}
	}

	if ($doroto_pernament_tournament_id != 0) {
		return $doroto_pernament_tournament_id; //returning a global variable
	}

	// We will try to get the highest tournament ID where close_tournament = 0 and the player is present    
	$results = $wpdb->get_results("
        SELECT id, players FROM $table_name 
        WHERE close_tournament = 0 
        OR (play_final_match = 1 AND (final_result = '' OR final_result IS NULL))
    ");

	if (!empty($results)) {
		foreach ($results as $result) {
			$players = maybe_unserialize($result->players);
			if (is_array($players) && in_array($userId, $players)) {
				$doroto_pernament_tournament_id = intval($result->id);
				return $doroto_pernament_tournament_id;
			}
		}
	}

	// If that fails, we take the highest ID where close_tournament = 0    
	$result = $wpdb->get_row("SELECT MAX(id) as max_id FROM $table_name WHERE close_tournament = 0");
	if ($result && $result->max_id != null) {
		return intval($result->max_id);
	}

	// If all else fails, let's take the highest ID ever
	$result = $wpdb->get_row("SELECT MAX(id) as max_id FROM $table_name");
	if ($result && $result->max_id != null) {
		return intval($result->max_id);
	} else {
		// If the table is empty, we return '-1'
		return -1;
	}

	return 0;
}


/**
 * return diplay_name in full or short version
 * @since 1.0.0
 */
function doroto_find_player_name(int $player_id, int $whole_names)
{
	global $wpdb;
	$user_data = get_userdata($player_id);
	if ($user_data) {
		$display_name = sanitize_text_field($user_data->display_name);
		if ($whole_names == 0) {
			$output = doroto_display_short_name($display_name);
		} else {
			$output = $display_name;
		}
	} else {
		$output = esc_html__('Unknown player', 'doubles-rotation-tournament');
	}
	$max_length = intval(doroto_read_settings('player_name_length', 16));
	if (strlen($output) > $max_length) {
		$output = substr($output, 0, $max_length);
	}
	return $output;
}


/**
 * shorten display name
 * @since 1.0.0
 */
function doroto_display_short_name(string $display_name)
{
	if (mb_strlen($display_name, 'UTF-8') > 8) {
		$first_part = mb_substr($display_name, 0, 4, 'UTF-8');
		$last_part = mb_substr($display_name, -4, null, 'UTF-8');
		$display_name = $first_part . $last_part;
		$display_name = str_replace(" ", "", $display_name);
	}
	return $display_name;
}


/**
 * in case of erased user name from a database then return unknown user
 * @since 1.0.0
 * @param string|int $player    Key for the player in the match array.
 */
function doroto_get_also_false_user(mixed $player)
{
	$user = get_user_by('id', $player);
	if ($user === false) {
		$user = new stdClass();
		$user->display_name = sanitize_text_field(__('Unknown player', 'doubles-rotation-tournament'));
		$user->ID = intval($player);
	}
	return $user;
}

/**
 * from id prepare tournament data
 * @since 1.0.0
 */
function doroto_prepare_tournament(int $tournament_id)
{
	global $wpdb;
	$tournament_id = intval($tournament_id);
	if ($tournament_id <= 0) {
		return null;
	}

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE id = %d",
		$tournament_id
	));
	return $tournament;
}


/**
 * work with url and reload the page
 * @since 1.0.0
 * @version 1.2.0
 */
function doroto_redirect_modify_url(int $tournament_id, $container = '')
{
	$tournament_id = intval($tournament_id);

	// Get the current URL
	if (isset($_SERVER['HTTP_REFERER'])) {
		$current_url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
	} else {
		// Get page id from options
		$doroto_main_page_id = intval(get_option('doroto_main_page_id'));

		// Checking if the page with this ID exists
		if ($doroto_main_page_id && get_post($doroto_main_page_id)) {
			// It exists, so we get the URL
			$current_url = get_permalink($doroto_main_page_id);
		} else {
			// The page with the given ID does not exist
			$current_url = home_url('/');
		}
	}

	// Add tournament_id parameter to the URL
	$new_url = add_query_arg('tournament_id', $tournament_id, $current_url);

	if (!empty($container)) {
		// Add container parameter to the URL
		$new_url .= '#' . $container;
	}

	// Redirect to the new URL
	wp_safe_redirect($new_url);
	exit;
}




/**
 * checking if the player is an administrator
 * @since 1.0.0
 * output 0: is not admin
 * output 1: is admin in DoRoTo
 * output 2: is admin in DoRoTo and a web administrator
 */
function doroto_is_admin(int $tournament_id)
{
	global $wpdb;
	$current_user = wp_get_current_user();

	if (!isset($tournament_id)) {
		wp_die(
			sprintf(
				/* translators: %s: The missing tournament ID or name. */
				esc_html__('Missing tournament ID: %s', 'doubles-rotation-tournament'),
				esc_html($tournament_id)
			)
		);
	}

	if ($tournament_id === 0) {
		wp_die(esc_html__('No tournament has been created yet.', 'doubles-rotation-tournament'));
	}

	$tournament = doroto_prepare_tournament($tournament_id);
	if (!isset($tournament)) {
		wp_die(esc_html__('The tournament was not found.', 'doubles-rotation-tournament'));
	}

	$admin_users = unserialize($tournament->admin_users);
	$roles = (array) $current_user->roles;
	$user_id = $current_user->ID;

	$has_web_role = in_array('administrator', $roles) || in_array('editor', $roles) || in_array('author', $roles);
	$is_admin_user = in_array($user_id, $admin_users);

	if (!$has_web_role && !$is_admin_user) {
		return 0;
	} elseif ($is_admin_user && !$has_web_role) {
		return 1;
	} else {
		return 2;
	}
}


/**
 * display player name as an output
 * @since 1.0.0
 * @param string $output        HTML output string passed by reference.
 * @param array  $match         Match data array.
 * @param string|int $player    Key for the player in the match array.
 * @param array  $special_group Array of special group player IDs.
 * @param int    $whole_names   Whether to return the whole name (1) or just a part (0).
 * @param mixed  $mark_user     ID of the user to be highlighted/marked.
 * @return void
 */
//function doroto_output_player_data(string &$output, $match, $player, $special_group, int $whole_names, $mark_user)
function doroto_output_player_data(string &$output, array $match, mixed $player, array $special_group, int $whole_names, mixed $mark_user): void
{
	$user = doroto_get_also_false_user($match[$player]);

	if ($mark_user == $match[$player]) {
		if (in_array($match[$player], $special_group)) {
			$output  .= '<td class="doroto-special-group-text-underlined">';
		} else {
			$output  .= '<td class="doroto-text-underlined">';
		}
	} else {
		if (in_array($match[$player], $special_group)) {
			$output  .= '<td class="doroto-special-group-text">';
		} else {
			$output  .= '<td>';
		}
	}
	$output .= esc_html(doroto_find_player_name(intval($user->ID), intval($whole_names))) . "</td>";
}


/**
 * read a variable from wp option table
 * @since 1.0.0
 * @param string $variable      
 * @param mixed  $default_value 
 * @return mixed
 */
function doroto_read_settings(string $variable, mixed $default_value): mixed
{
	$doroto_settings = get_option('doroto_settings');

	if (is_array($doroto_settings) && array_key_exists($variable, $doroto_settings)) {
		return $doroto_settings[$variable];
	}

	return $default_value;
}


/**
 * check if shortcode should be displayed or not because of a presentation
 * @since 1.0.0
 * @param string $shortcode 
 * @return int 
 */
function doroto_check_need_to_display(string $shortcode): int
{
	global $wpdb;
	$current_user_id = intval(get_current_user_id());
	$display_shortcode = intval(doroto_read_settings($shortcode, 1));
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));
	if ($doroto_presentation && is_array($doroto_presentation)) {
		if ($doroto_presentation['allow_to_run'] == 1 && ($doroto_presentation['slide'] != $shortcode || !$display_shortcode)) {
			return 0;
		}
	};
	return 1;
}


/**
 * is presentation running?
 * @since 1.0.0
 */
function doroto_check_if_presentation_on()
{
	global $wpdb;
	$current_user_id = intval(get_current_user_id());
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));
	if ($doroto_presentation && is_array($doroto_presentation)) {
		if ($doroto_presentation['allow_to_run'] == 1) {
			return 1;
		}
	};
	return 0;
}


/**
 * A Notice about an empty Special Group
 * @since 1.1.6
 * @version 1.4.7 (return at the end)
 * @param object $tournament The tournament database object.
 * @return string The notice HTML or text string.
 */
function doroto_empty_special_group_notice(object $tournament): string
{
	global $wpdb;
	$special_group = $tournament->special_group;
	if ($special_group == '') {
		$special_group = [];
	} else {
		$special_group = unserialize($special_group);
	}

	$two_special_group = intval($tournament->two_special_group);
	$two_out_group = intval($tournament->two_out_group);

	if (doroto_check_if_doubles($tournament)) {
		$min_count = 2;
	} else {
		$min_count = 1;
	}

	if ((count($special_group) < $min_count) && $two_out_group) {
		$allowed_html = doroto_allowed_html();
		$output_temp =  '<div class = "doroto-message-background"><b>' . __('The setup requires a player from a Special Group to be selected for each team.', "doubles-rotation-tournament") . ' ';
		$output_temp .=  __('The Special Group does not contain enough players.', "doubles-rotation-tournament") . '</b></div>';
		$output_temp .=  '<div class = "doroto-info-text">' . __('Do at least one of the following:', "doubles-rotation-tournament") . '<ol>';
		$output_temp .=  '<li>' . __('Add the required count of players to the Special Group.', "doubles-rotation-tournament") . '</li>';
		$output_temp .=  '<li>' . __('Change the setting of this parameter:', "doubles-rotation-tournament") . ' ' . __('Tournament Editing ...', "doubles-rotation-tournament") . '->' . __('Special group of players', "doubles-rotation-tournament") . '->' . __('Skip matches where 2 non-special group players would play together?', "doubles-rotation-tournament") . '</li></ol></div>';
		$output = wp_kses($output_temp, $allowed_html);
		return $output;
	}
	return '';
}
