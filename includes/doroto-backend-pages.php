<?php
if (! defined('ABSPATH')) {
	exit;
}


/**
 * The admin page itself (React, 2.0) is in includes/doroto-admin.php. This file keeps the
 * menu, the default settings, the review notice and the dashboard widget.
 */

/**
 * create admin menu
 * @since 1.0.0
 */
function doroto_create_menu()
{
	$icon_url = 'dashicons-awards';
	$menu_slug = 'doubles-rotation-tournament';


	add_menu_page(
		esc_html__('Rotation Tournaments', 'doubles-rotation-tournament'),
		esc_html__('Rotation Tournaments', 'doubles-rotation-tournament'),
		'manage_options',
		$menu_slug,
		'doroto_admin_page_html', //Callback to print html
		$icon_url
	);
}
add_action('admin_menu', 'doroto_create_menu');


/**
 * label for Mobile app admin page
 * @since 1.4.6
 * @version 1.5.1
 */
function doroto_application_section_callback()
{
	echo '<h3>' . esc_html__('Complete Your Tournament Experience with the Android App!', 'doubles-rotation-tournament') . '</h3>';
	echo '<p>' . esc_html__('This plugin powers the server-side functionality for the Rotation Tournament mobile app. Bring your tournaments to your pocket!', 'doubles-rotation-tournament') . '</p>';

	$store_url = 'https://play.google.com/store/apps/details?id=cz.doroto.app';
	$badge_url = 'https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png';
	$alt_text = esc_attr__('Get it on Google Play', 'doubles-rotation-tournament');

	echo '<a href="' . esc_url($store_url) . '" target="_blank" rel="noopener">';
	echo '<img src="' . esc_url($badge_url) . '" alt="' . esc_attr($alt_text) . '" style="height: 60px;">';
	echo '</a>';
}

/**
 * check if 'doroto_settings' exists in wp 'option' table
 * @since 1.0.0
 * @version 1.4.7 (add latitude,longitude,visibility)
 */
function doroto_settings_check_existence()
{
	$doroto_settings = get_option('doroto_settings');
	if (!is_array($doroto_settings)) {
		$doroto_settings = [];
	}

	// default values
	$default_values = array(
		'only_admin_players' => 1,
		'only_admin_posts' => 1,
		'display_rows' => 20,
		'refresh_seconds' => 120,
		'delete_database' => 1,
		'delete_pages' => 1,
		'delete_settings' => 1,
		'update_activation' => 1,
		'only_admin_creates' => 0,
		'minimum_matches' => 3,
		'show_next_seconds' => 10,
		'doroto_tournament_log_link' => 0,
		'doroto_games_to_play' => 1,
		'doroto_display_players' => 1,
		'doroto_display_games' => 1,
		'doroto_display_player_statistics' => 1,
		'doroto_table' => 0,
		'player_name_length' => 20,
		'youtube_link' => "https://www.youtube.com/watch?v=CdVC63XWr9E", //link with video help	
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.PassedToExecute
		'activation_date' => gmdate('Y-m-d H:i:s'),
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.PassedToExecute
		'promo_next' => gmdate('Y-m-d H:i:s', strtotime('+14 days')),
		'hide_promo' => 0,
		'tournament_type' => 21,
		'singles_tennis' => 1,
		'doubles_tennis' => 1,
		'singles_table_tennis' => 1,
		'doubles_table_tennis' => 1,
		'singles_padel' => 1,
		'doubles_padel' => 1,
		'beach_volleyball' => 1,
		'squash' => 1,
		'singles_badminton' => 1,
		'doubles_badminton' => 1,

		'singles_tennis_score' => 10,
		'doubles_tennis_score' => 10,
		'singles_table_tennis_score' => 19,
		'doubles_table_tennis_score' => 19,
		'singles_padel_score' => 10,
		'doubles_padel_score' => 10,
		'beach_volleyball_score' => 36,
		'squash_score' => 19,
		'singles_badminton_score' => 36,
		'doubles_badminton_score' => 36,

		'singles_tennis_hour' => 15,
		'doubles_tennis_hour' => 15,
		'singles_table_tennis_hour' => 175,
		'doubles_table_tennis_hour' => 175,
		'singles_padel_hour' => 30,
		'doubles_padel_hour' => 30,
		'beach_volleyball_hour' => 40,
		'squash_hour' => 90,
		'singles_badminton_hour' => 100,
		'doubles_badminton_hour' => 100,
		'log_in_link' => sanitize_text_field(wp_unslash(wp_login_url())),
		'tournament_example_1' => 0,
		'tournament_example_2' => 0,
		'tournament_example_3' => 0,
		'tournament_example_4' => 0,
		'website_description' => get_option('blogdescription'),
		'website_visible' => 0,
		'show_app_link' => 1,
		'visibility' => 1,
		'longitude' => 15,
		'latitude' => 50,
	);

	$new_values = array_diff_key($default_values, $doroto_settings);
	$doroto_settings = array_merge($doroto_settings, $new_values);
	update_option('doroto_settings', $doroto_settings);
}

//register_activation_hook(__FILE__, 'doroto_settings_check_existence');
add_action('admin_init', 'doroto_settings_check_existence');


/**
 * check if 'doroto_presentation' exists in wp 'user_meta' table
 * @since 1.0.0
 */
function doroto_presentation_check_existence()
{
	global $wpdb;
	$current_user_id = intval(get_current_user_id());
	$doroto_presentation = maybe_unserialize(get_user_meta($current_user_id, 'doroto_presentation', true));

	// default values
	$default_values = array(
		'allow_to_run' => 0,
	);

	$doroto_presentation = wp_parse_args($doroto_presentation, $default_values);
	update_user_meta($current_user_id, 'doroto_presentation', serialize($doroto_presentation));
}


/**
 * Asking the site administrator for a review on WordPress.org.
 *
 * The notice appears 14 days after activation, once the site has created a
 * tournament of its own (not only the examples). "Remind me in 7 days" brings
 * it back after a week, until the administrator rates the plugin or says it is
 * already done. Before 1.6.0 it was shown to every user who could open the
 * admin area, the long text was easy to skip, and "Hide this text" removed it
 * for good: after two years the plugin had no review at all.
 * @since 1.0.0
 * @version 1.6.0 (reminder every 7 days, only for administrators, nonce)
 */
const DOROTO_REVIEW_URL = 'https://wordpress.org/support/plugin/doubles-rotation-tournament/reviews/#new-post';
const DOROTO_REVIEW_FIRST_DAYS = 14;
const DOROTO_REVIEW_AGAIN_DAYS = 7;

function doroto_review_state()
{
	$state = get_option('doroto_review_notice');
	if (!is_array($state)) {
		$settings = get_option('doroto_settings');
		$activated = isset($settings['activation_date']) ? strtotime($settings['activation_date'] . ' UTC') : false;
		if (!$activated) {
			$activated = time();
		}
		$state = [
			'done' => 0,
			'next' => max($activated + DOROTO_REVIEW_FIRST_DAYS * DAY_IN_SECONDS, time()),
		];
		update_option('doroto_review_notice', $state, false);
	}
	return $state;
}

/**
 * The site really uses the plugin: at least one tournament created after the
 * activation (the example tournaments are created during the activation).
 */
function doroto_review_site_is_active()
{
	global $wpdb;
	$settings = get_option('doroto_settings');
	$activated = isset($settings['activation_date']) ? $settings['activation_date'] : '1970-01-01 00:00:00';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$count = intval($wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}doroto_tournaments WHERE create_date > %s",
		gmdate('Y-m-d H:i:s', strtotime($activated . ' UTC') + 3600)
	)));
	return $count > 0;
}

function doroto_review_action_url(string $choice)
{
	return wp_nonce_url(
		admin_url('admin-post.php?action=doroto_review_notice&choice=' . $choice),
		'doroto_review_notice'
	);
}

function doroto_display_dashboard_message()
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$state = doroto_review_state();
	if (!empty($state['done']) || time() < intval($state['next'])) {
		return;
	}
	if (!doroto_review_site_is_active()) {
		return;
	}

	echo '<div class="notice notice-info" style="border-left-color:#ffb900;padding:12px 16px;">';
	echo '<p style="font-size:15px;margin:0 0 6px;"><span style="color:#ffb900;font-size:18px;">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <strong>';
	echo esc_html__('Do you find Rotation Tournaments useful?', 'doubles-rotation-tournament') . '</strong></p>';
	echo '<p style="margin:0 0 10px;">' . esc_html__('A short review on WordPress.org helps other clubs find the plugin and keeps its development going. It takes about a minute.', 'doubles-rotation-tournament') . '</p>';
	echo '<p style="margin:0;">';
	echo '<a class="button button-primary" href="' . esc_url(doroto_review_action_url('rate')) . '" target="_blank" rel="noopener">' . esc_html__('Rate the plugin', 'doubles-rotation-tournament') . '</a> ';
	echo '<a class="button" href="' . esc_url(doroto_review_action_url('later')) . '">' . esc_html__('Remind me in 7 days', 'doubles-rotation-tournament') . '</a> ';
	echo '<a class="button-link" style="margin-left:8px;" href="' . esc_url(doroto_review_action_url('done')) . '">' . esc_html__('I have already rated it', 'doubles-rotation-tournament') . '</a>';
	echo '<span style="margin-left:16px;">' . esc_html__('A problem or an idea?', 'doubles-rotation-tournament') . ' ';
	echo '<a href="' . esc_url('https://wordpress.org/support/plugin/doubles-rotation-tournament/') . '" target="_blank" rel="noopener">' . esc_html__('Support forum', 'doubles-rotation-tournament') . '</a></span>';
	echo '</p></div>';
}
add_action('admin_notices', 'doroto_display_dashboard_message');

/**
 * Buttons of the review notice.
 * @since 1.6.0
 */
function doroto_handle_review_notice()
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to perform this action.', 'doubles-rotation-tournament'), 403);
	}
	check_admin_referer('doroto_review_notice');
	$choice = isset($_GET['choice']) ? sanitize_key(wp_unslash($_GET['choice'])) : '';
	$state = doroto_review_state();
	if ($choice === 'later') {
		$state['next'] = time() + DOROTO_REVIEW_AGAIN_DAYS * DAY_IN_SECONDS;
	} elseif ($choice === 'rate' || $choice === 'done') {
		$state['done'] = 1;
	}
	update_option('doroto_review_notice', $state, false);

	if ($choice === 'rate') {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed wordpress.org address
		wp_redirect(DOROTO_REVIEW_URL);
		exit;
	}
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}
add_action('admin_post_doroto_review_notice', 'doroto_handle_review_notice');


add_action('wp_dashboard_setup', 'doroto_add_dashboard_widget');

/**
 * dashboard widget
 * @since 1.4.2
 */
function doroto_add_dashboard_widget()
{
	wp_add_dashboard_widget(
		'doroto_tournament_stats_widget',
		__('Rotation Tournaments Overview', 'doubles-rotation-tournament'),
		'doroto_render_dashboard_widget'
	);
}

/**
 * dashboard widget_render
 * @since 1.4.2
 * @version 2.0.0 (numbers of doroto_admin_stats())
 */
function doroto_render_dashboard_widget()
{
	echo '<ul style="list-style-type: disc; margin-left: 1em;">';
	foreach (doroto_admin_stats() as $doroto_stat) {
		echo '<li><strong>' . esc_html($doroto_stat['label']) . '</strong> ' . intval($doroto_stat['value']) . '</li>';
	}
	echo '</ul>';
	echo '<p><a href="' . esc_url(admin_url('admin.php?page=doubles-rotation-tournament')) . '">' . esc_html__('Rotation Tournaments', 'doubles-rotation-tournament') . '</a></p>';
}
