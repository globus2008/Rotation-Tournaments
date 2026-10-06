<?php

/**
 * Plugin Name: Rotation Tournaments
 * Plugin URI: https://doroto.ltcchrast.cz/
 * Description: Organize Rotation Tournaments where each player competes against every other player without eliminations. Suitable for sports like tennis, table tennis, squash, padel, and beach volleyball.
 * Version: 1.6.2
 * Author: globus2008
 * Author URI: https://doroto.ltcchrast.cz/
 * License: GPL-3.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: doubles-rotation-tournament
 * Requires at least: 6.5
 * Tested up to: 7.1.2
 * Requires PHP: 8.0
 * Stable tag: 1.6.0
 * 
 * @since             1.0.0
 * @package           doubles-rotation-tournament
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}
if (!defined('ABSPATH'))
	exit; // Exit if accessed directly

// GLOBALS AND CONSTANTS
if (!defined('doroto_VERSION')) {
	define('doroto_VERSION', '1.6.2');
}
if (!defined('doroto_PLUGIN_NAME')) {
	define('doroto_PLUGIN_NAME', 'doubles-rotation-tournament');
}
// Bump whenever the CREATE TABLE statement in doroto_create_tournaments_table() changes.
if (!defined('DOROTO_DB_VERSION')) {
	define('DOROTO_DB_VERSION', '1.6.0');
}
if (!defined('doroto_PATH')) {
	define('doroto_PATH', __DIR__);
}


include_once(ABSPATH . 'wp-admin/includes/plugin.php');

// HEADER STRINGS (For translation)
add_action('init', 'doroto_load_textdomain_strings');
function doroto_load_textdomain_strings()
{
	esc_html__('Organize Rotation Tournaments where each player competes against every other player without eliminations. Suitable for sports like tennis, table tennis, squash, padel, and beach volleyball.', 'doubles-rotation-tournament');
	esc_html__('Rotation Tournaments', 'doubles-rotation-tournament');
}

/**
 * What of the plugin does the current page show?
 * 'shortcodes': shortcodes of 1.x (they need the old styles and scripts);
 * 'any': also the 2.0 blocks and [doroto_tournament] / [doroto_tournament_list],
 * which load their own assets.
 * Used to load the shortcode assets and send no-cache headers only where needed;
 * before 2.0 both happened on every page of the site.
 * @since 2.0.0
 */
function doroto_page_content_uses(string $what): bool
{
	static $found = null;
	if ($found === null) {
		$found = ['shortcodes' => false, 'any' => false];
		if (is_singular()) {
			$post = get_queried_object();
			if ($post instanceof WP_Post) {
				$content = $post->post_content;
				$found['shortcodes'] = preg_match('/\[doroto_(?!tournament(?:_list)?[\s\]])/', $content) === 1;
				$found['any'] = $found['shortcodes'] || strpos($content, '[doroto_') !== false || strpos($content, '<!-- wp:doroto/') !== false;
			}
		}
	}
	return !empty($found[$what]);
}

/**
 * Does the current page show the plugin (blocks or shortcodes)?
 * @since 2.0.0
 */
function doroto_page_uses_plugin(): bool
{
	/**
	 * Filters whether the current page shows the plugin (e.g. a shortcode in a widget or template).
	 * @since 2.0.0
	 */
	return (bool) apply_filters('doroto_page_uses_plugin', doroto_page_content_uses('any'));
}

/**
 * Does the current page show shortcodes of 1.x (which need the old assets)?
 * @since 2.0.0
 */
function doroto_page_uses_shortcodes(): bool
{
	/**
	 * Filters whether the current page shows shortcodes of 1.x.
	 * @since 2.0.0
	 */
	return (bool) apply_filters('doroto_page_uses_shortcodes', doroto_page_content_uses('shortcodes'));
}

/**
 * Tournament pages show live, personal data (results, nonces of the forms): never cache them.
 * @since 1.0.0
 * @version 2.0.0 (only pages of the plugin)
 */
function doroto_no_cache_headers()
{
	if (doroto_page_uses_plugin()) {
		nocache_headers();
	}
}
add_action('template_redirect', 'doroto_no_cache_headers');

function doroto_frontend_styles()
{
	// The plugin version busts the browser cache after an update.
	wp_register_style('doroto-frontend-styles', plugins_url('includes/doroto-frontend-styles.css', __FILE__), [], doroto_VERSION);
	wp_enqueue_style('doroto-frontend-styles');
}

/**
 * Styles and scripts of the shortcodes (the blocks load their own through block.json).
 * @since 2.0.0
 */
function doroto_enqueue_shortcode_assets()
{
	if (did_action('doroto_shortcode_assets')) {
		return;
	}
	do_action('doroto_shortcode_assets');
	doroto_frontend_styles();
	doroto_enqueue_frontend_scripts();
	doroto_enqueue_shepherd_assets();
	wp_enqueue_style('dashicons');
}

function doroto_maybe_enqueue_shortcode_assets()
{
	if (doroto_page_uses_shortcodes()) {
		doroto_enqueue_shortcode_assets();
	}
}
add_action('wp_enqueue_scripts', 'doroto_maybe_enqueue_shortcode_assets');

/**
 * A shortcode rendered outside the post content (widget, template) still gets its assets;
 * they are printed in the footer.
 * @since 2.0.0
 */
function doroto_shortcode_tag_assets($output, $tag)
{
	if (strpos((string) $tag, 'doroto_') === 0 && !in_array($tag, ['doroto_tournament', 'doroto_tournament_list'], true)) {
		doroto_enqueue_shortcode_assets();
	}
	return $output;
}
add_filter('do_shortcode_tag', 'doroto_shortcode_tag_assets', 10, 2);

/**
 * Is this the admin page of the plugin? Its scripts load only there (doroto_admin_enqueue()).
 * @since 2.0.0
 */
function doroto_is_plugin_admin_page($hook)
{
	return $hook === 'toplevel_page_doubles-rotation-tournament';
}


function doroto_enqueue_frontend_scripts()
{
	// Local Leaflet CSS
	wp_enqueue_style(
		'leaflet-css',
		plugins_url('assets/css/leaflet.css', __FILE__),
		[],
		'1.9.4'
	);

	// Local Leaflet JS
	wp_enqueue_script(
		'leaflet-js',
		plugins_url('assets/js/leaflet.js', __FILE__),
		[],
		'1.9.4',
		true
	);

	// Plugin script that needs Leaflet and jQuery
	wp_enqueue_script(
		'doroto-frontend-scripts',
		plugins_url('includes/doroto-frontend-scripts.js', __FILE__),
		['leaflet-js', 'jquery'],
		doroto_VERSION,
		true
	);
}



require_once plugin_dir_path(__FILE__) . 'includes/doroto-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-repeated-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-security.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-players-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-tournament-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-services.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-view-model.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-view-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-block-actions.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-blocks.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-help.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-frontend-pages.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-backend-pages.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/doroto-endpoints.php';

// Registering action hooks for page creation and deletion
register_activation_hook(__FILE__, 'doroto_check_version');
register_activation_hook(__FILE__, 'doroto_create_tournaments_table');
register_activation_hook(__FILE__, 'doroto_create_main_page');
register_activation_hook(__FILE__, 'doroto_create_help_page');
register_activation_hook(__FILE__, 'doroto_settings_check_existence');
register_activation_hook(__FILE__, 'doroto_create_tournament_record');
register_activation_hook(__FILE__, 'doroto_create_example_page');
register_activation_hook(__FILE__, 'doroto_update_pages');
register_activation_hook(__FILE__, 'doroto_plugin_activation');
register_activation_hook(__FILE__, 'doroto_create_privacy_policy_page');
register_activation_hook(__FILE__, 'doroto_create_terms_of_service_page');


// Global variable declaration
global $doroto_output_form;


/**
 * check if there is something to update
 * @since 1.0.0
 * @version 1.4.6 (min_not_playing sometimes wrong)
 */

function doroto_check_version()
{
	if (defined('IFRAME_REQUEST')) {
		return;
	}
	$old_version = get_option('doroto_version');
	if (!$old_version) {
		$old_version = '0.0.0';
		update_option('doroto_version', doroto_VERSION);
	}

	$version_installed_string = explode('.', $old_version);
	if (count($version_installed_string) === 3) {
		list($version_1, $version_2, $version_3) = array_map('intval', $version_installed_string);
	} else {
		$version_1 = 0;
		$version_2 = 0;
		$version_3 = 0;
	}
	$version_installed = $version_1 * 100 + $version_2 * 10 + $version_3;

	$version_plugin_string = explode('.', doroto_VERSION);
	if (count($version_plugin_string) === 3) {
		list($version_1, $version_2, $version_3) = array_map('intval', $version_plugin_string);
	} else {
		$version_1 = 0;
		$version_2 = 0;
		$version_3 = 0;
	}
	$version_plugin = $version_1 * 100 + $version_2 * 10 + $version_3;


	if ($version_installed <= 143 && $version_plugin > $version_installed) {
		doroto_update_pages();
	}

	if ($old_version !== doroto_VERSION) {
		update_option('doroto_version', doroto_VERSION);
		doroto_settings_check_existence();
		doroto_fix_file_permissions();
	}

	// Schema migration. Sites upgraded from old versions never received columns
	// added later (e.g. last_update, visibility), because the table was only
	// created when missing. Without last_update every save failed silently.
	if (get_option('doroto_db_version') !== DOROTO_DB_VERSION) {
		doroto_create_tournaments_table();
		update_option('doroto_db_version', DOROTO_DB_VERSION);
	}
}
add_action('init', 'doroto_check_version', 5);

/**
 * guide tour library
 * @since 1.3.7
 * @version 1.3.7
 */
function doroto_enqueue_shepherd_assets()
{
	wp_enqueue_script('shepherd-js', plugins_url('lib/shepherd/shepherd.min.js', __FILE__), array(), '1.0.0', true);
	wp_enqueue_style('shepherd-css', plugins_url('lib/shepherd/shepherd.min.css', __FILE__), array(), '1.0.0');

	wp_enqueue_script('doroto-custom-help-script', plugins_url('includes/doroto-help-icon.js', __FILE__), array('shepherd-js', 'jquery'), doroto_VERSION, true);
	wp_localize_script('doroto-custom-help-script', 'dorotoAjax', [
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('doroto_help_tour'),
	]);


	wp_localize_script('doroto-custom-help-script', 'dorotoTranslations', array(
		'doroto_text_create_tournament' => __('Create a new tournament', 'doubles-rotation-tournament'),
		'doroto_text_finally_help' => __("End of preparation. Let's get started!", 'doubles-rotation-tournament'),
		'doroto_text_tournament_type' => __('First, select the type of tournament you want to create.', 'doubles-rotation-tournament'),
		'doroto_text_submit_button' => __('Press this button and the tournament will be created.', 'doubles-rotation-tournament'),
		'doroto_text_tournament_list' => __('The newly created tournament will appear at the top of the table of available tournaments.', 'doubles-rotation-tournament'),
		'doroto_text_tournament_selected' => __('The newly created tournament will have this background color. The tournament marked in this way is displayed in all tables above.', 'doubles-rotation-tournament'),
		'doroto_text_force_to_login' => __("If you were logged into your account, you can now see your name among the tournament organizers and have permission to make changes to the settings.", 'doubles-rotation-tournament'),
		'doroto_text_force_to_login_message' => __("You are not yet listed as an organizer in this tournament. To make things easier for you, I will list your name among the tournament organizers.", 'doubles-rotation-tournament'),
		'doroto_text_login' => __('Log in', 'doubles-rotation-tournament'),
		'doroto_text_register' => __('Register', 'doubles-rotation-tournament'),
		'doroto_text_next' => __('Next', 'doubles-rotation-tournament'),
		'doroto_text_back' => __('Back', 'doubles-rotation-tournament'),
		'doroto_text_close' => __('Close', 'doubles-rotation-tournament'),
		'doroto_text_cancel' => __('Cancel', 'doubles-rotation-tournament'),
		'doroto_text_reload' => __('Reload', 'doubles-rotation-tournament'),
		'doroto_text_reload_message' => __('The settings have been made, you just need to reload the page.', 'doubles-rotation-tournament'),
		'doroto_text_redirect' => __('Redirect', 'doubles-rotation-tournament'),
		'doroto_text_redirect_message' => __('We will demonstrate this tip on a selected tournament example.', 'doubles-rotation-tournament'),
		'doroto_text_redirect_message_extended' => __('Can I redirect you to tournament no.', 'doubles-rotation-tournament'),
		'doroto_text_not_login' => __('If you log in to your account and we run this tutorial again, I will grant you tournament organizer rights. If you can try out other options in this trial tournament.', 'doubles-rotation-tournament'),

		'doroto_text_login_logout' => __('Tournament registration', 'doubles-rotation-tournament'),
		'doroto_text_login_table' => __('For the selected tournament, you can log in or log out here.', 'doubles-rotation-tournament'),
		'doroto_text_login_not_active' => __('Because player registration for the tournament has already been closed, this link is inactive.', 'doubles-rotation-tournament'),
		'doroto_text_list_name' => __('You can select the tournament with which you will continue to work by clicking on its name. A colored background means that this tournament has already been selected.', 'doubles-rotation-tournament'),
		'doroto_text_list_count' => __('This shows the number of players logged in. If you also see a number after the slash, this indicates the maximum number of players set by the tournament organizer.', 'doubles-rotation-tournament'),
		'doroto_text_list_players' => __('The list of registered players and later the current ranking can be found here.', 'doubles-rotation-tournament'),

		'doroto_text_tournament_management_basic' => __('Basic tournament management', 'doubles-rotation-tournament'),
		'doroto_text_list_registration_status' => __('If enough players have registered for the tournament, the organizer can close the registration of new players.', 'doubles-rotation-tournament'),
		'doroto_text_list_organizer' => __('Only the tournament administrator can make these adjustments.', 'doubles-rotation-tournament'),
		'doroto_text_list_tournament_status' => __('When enough matches have been played, the organizer can end the tournament here.', 'doubles-rotation-tournament'),
		'doroto_text_other_option' => __('...or you can use this option....', 'doubles-rotation-tournament'),

		'doroto_text_tournament_management_advance' => __('Advance tournament management', 'doubles-rotation-tournament'),
		'doroto_text_advance_settings_introduction' => __('Under these tabs you will find all possible settings of the selected tournament.', 'doubles-rotation-tournament'),
		'doroto_text_settings_name_courts' => __('Here you can enter the number of courts that are available, edit the name of the tournament and the type of tournament.', 'doubles-rotation-tournament'),
		'doroto_text_settings_match_result' => __('Variables that help estimate the length of the tournament.', 'doubles-rotation-tournament'),
		'doroto_settings_special_group' => __('Settings that allow you to create different pairs, such as man + woman, adult + child, etc.', 'doubles-rotation-tournament'),
		'doroto_text_settings_save_button' => __('Changes must be saved using this button. Only the tournament administrator has this permission.', 'doubles-rotation-tournament'),
		'doroto_text_settings_organizer_rights' => __('Administrator privileges can be extended to other people.', 'doubles-rotation-tournament'),

		'doroto_text_example_1' => __('Example 1: open registration', 'doubles-rotation-tournament'),
		'doroto_text_example_2' => __('Example 2: during the tournament', 'doubles-rotation-tournament'),
		'doroto_text_example_3' => __('Example 3: singles completed', 'doubles-rotation-tournament'),
		'doroto_text_example_4' => __('Example 4: doubles completed', 'doubles-rotation-tournament'),

		'doroto_text_example_1_players' => __('13 world-class tennis players, 7 men and 6 women, have registered for this tournament.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_registration_open' => __('Registration for the tournament is still open.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_registration_closed' => __('Registration for the tournament is closed.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_login' => __('You can register for this tournament also via this link.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_organizer' => __('The name of the tournament organizer, who can make all the settings, can be found here.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_state' => __('Players can interrupt the game at any time during the tournament and will not be placed in matches during the interruption. Here the game was interrupted by the 4th player.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_payment' => __('The tournament organizer can keep statistics on entry fee payments. Here, the 2nd, 4th, and 6th players paid.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_player_management' => __('Pausing the game, adding players to the tournament, managing special groups and payments are all done in this section.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_suspension' => __("The administrator can pause and resume the game of any player. A player can only change himself. The interruption of the game by a player is shown in the table above.", 'doubles-rotation-tournament'),
		'doroto_text_example_1_add_players' => __("Here, the tournament organizer can add players to the tournament from the player database individually, even during the tournament. For reasons of personal data protection, access to the database may be restricted by the website administrator.", 'doubles-rotation-tournament'),
		'doroto_text_example_1_login_recommendation' => __('This link will only be available to other players when player registration is open.', 'doubles-rotation-tournament'),
		'doroto_text_example_1_special_group' => __("As a tournament organizer, you can place selected players in a special group. For example, here all women are in a special group. The name of the player in the special group will be highlighted in color.", 'doubles-rotation-tournament'),
		'doroto_text_example_1_add_payment' => __('If the tournament organizer records entry fee payments, the information about received payments is processed here.', 'doubles-rotation-tournament'),
		'doroto_text_need_help' => __('Do you need advice?', 'doubles-rotation-tournament'),
		'doroto_text_floating_icon' => __('Click this floating icon to view help. It will guide you through the various options for how a rotation tournament works and how to set them up.', 'doubles-rotation-tournament'),

		'doroto_text_example_2_match_count' => __("Each player has played 3 or 4 matches so far. For those who have only played 3 matches, a '!' appears after the number, indicating that the player has not yet played enough matches to be declared the winner.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_winner_minimum' => __("The required number of matches played for a player to be declared the winner is set here.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_won' => __("For each player, we will find the total number of games won, or points in another type of game.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_lost' => __("Similarly, each player has a total of all lost games or points.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_ratio' => __("The ratio between games won and lost (points) determines the overall ranking among players.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_trend' => __("The trend shows how many matches in a row ended in a win or loss.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_residue' => __("If the goal of the tournament is to close the round, the remaining number of matches for each player is displayed here.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_settings_round' => __("In the settings you can choose whether the tournament targets the end of the round and whether the service rotation is taken into account. The end of the round does not have to be tracked according to the settings.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_tournament_progress' => __("However, if you request the end of the tournament round, an estimated time for the tournament will be displayed.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_variables_progress' => __("To make the tournament prediction reliable, you must check and possibly adjust the parameters marked with *.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play' => __("Here, individual matches are drawn in a number corresponding to the selected number of available courts or playing surfaces.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_id' => __("In each column of the table you will find the match number.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_l1' => __("Player of team 1 on the left side of the court.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_p1' => __("Player of team 1 on the right side of the court. This player starts the game by serving.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_l2' => __("Player of team 2 on the left side of the court.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_p2' => __("Player of team 2 on the right side of the court.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_hide' => __("The match can be skipped by checking this box and pressing the Save button.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_result' => __("Field for entering game results.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_save' => __("Button to save results. Pressing this button will draw the next match according to the settings.", 'doubles-rotation-tournament'),
		'doroto_text_example_2_games_to_play_only_admin' => __("However, this can only be done by the player playing the game, the tournament organizer, or the website administrator.", 'doubles-rotation-tournament'),

		'doroto_text_example_3_players' => __('The 12 world-famous tennis players from the previous examples decided to play singles badminton, regardless of gender.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_winner_ideal_couple' => __('The tournament has already ended with the announcement of the winners in the most ideal couple category.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_winner_best_player' => __('At the same time, the winner in the best player of the tournament category was announced.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_match_count' => __('The overview shows that the players played a different number of matches due to the fact that Iga Swiatek and Novak Djokovic decided to interrupt the tournament sometime in the middle.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_state' => __('The fact that Iga Swiatek and Novak Djokovic still have a suspended game can be seen by the check box next to their names.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_ratio' => __('It is important to understand that the total number of matches played does not affect the ranking. What matters is the ratio of points won and lost.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_residue' => __('It is also not necessary to play all the matches to complete the round. Perhaps Iga Swiatek and Novak Djokovic will not be coming and so the round-robin game could not be completed.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_suspended' => __('To prevent an absent player from being declared the winner, this option can be disabled.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_played_matches' => __('Here you can find a table with all the matches played.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_filter' => __('Results can be filtered by player. You can only filter results after logging into your account.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_statistical_data' => __('If you have a filter set for a specific player, you can view their detailed statistical data.', 'doubles-rotation-tournament'),
		'doroto_text_example_3_change_match_result' => __('If someone incorrectly enters the result of a match, the tournament organizer can correct the result.', 'doubles-rotation-tournament'),

		'doroto_text_preparing_help' => __('Preparing help', 'doubles-rotation-tournament'),
		'doroto_text_example_4_preparation_filter' => __('One of the female players was randomly selected and a filter was set based on that.', 'doubles-rotation-tournament'),
		'doroto_text_example_4_sorry_login' => __('If you are not logged in to your account, you will not see an important part of this example.', 'doubles-rotation-tournament'),
		'doroto_text_example_4_players' => __('Tennis players, 3 men and 3 women, decided to play beach volleyball.', 'doubles-rotation-tournament'),
		'doroto_text_example_4_closed_tournament' => __('The tournament has already been closed because the tournament round has ended.', 'doubles-rotation-tournament'),
		'doroto_text_example_4_match_count' => __("Now let's look at the number of matches played by individual players. There are significant differences. These are caused by the special group settings.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_2_special' => __("This setting determines that there will be no 2 from the special group in the pair. That is, no woman + woman pairs will be created.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_2non_special' => __("Another setting allows you to create pairs between members outside of special groups. In our case, you can create male + male pairs.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_special_games' => __("Let's look at the matches played by a selected woman from a special group. She actually played matches only with male colleagues, but the opponent could be not only a man + woman group, but also a man + man.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_filter_woman' => __("I remind you that this woman was chosen to filter the matches and create statistics.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_woman' => __("Here are the statistics of this woman's games played.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_player' => __("The statistical data can be read line by line. The player's name is at the beginning.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_teammate_L' => __("This column reports the number of matches played where the player listed in this row was a teammate of the selected woman on the left.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_teammate_P' => __("This column reports the number of matches played where the player listed in this row was a teammate of the selected woman on the right.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_opponent' => __("This column reports the number of matches played where the player listed in this row was in the opposing position.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_total' => __("Here the number of matches played as a teammate and opponent is added up.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_woman_final' => __("This table shows that the woman who was actually selected did not pair up with another woman, but only with men. At the same time, she played one match with each man.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_round_final' => __("Thus, the tournament round was closed, from which we required everyone to play with everyone, provided that the settings allowed it.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_statistics_residue' => __("The end of a tournament round can be recognized when the players have no matches left. However, in doubles, a situation is also allowed where a maximum of 3 players have 1 match left. In this case, it is not possible to find 4 players who have not played together before.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_filter_man' => __("Let's set the filter to one of the men.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_man_selected' => __("This is a man who was randomly selected for us to look at his results.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_man_matches' => __("Unlike players from the special group (meaning women here), men played 2 more matches.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_man_statistics' => __("Looking at the game statistics of the selected man, we see that he actually played with every player. Moreover, the system ensured an even rotation of serve.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_last_word' => __("Do you know how many more matches would have to be played to ensure a rotation of serve? That is, each pair would play each other twice to alternate serving? Try changing this parameter and see.", 'doubles-rotation-tournament'),
		'doroto_text_example_4_last_word_filter' => __("Finally, your filter was canceled so that it would not unexpectedly affect other tournaments.", 'doubles-rotation-tournament'),

	));
}



/**
 * show a message after the plugin activation
 * @since 1.3.9
 */
function doroto_plugin_activation()
{
	add_option('doroto_show_activation_notice', true);
}

/**
 * content a message after the plugin activation
 * @since 1.3.9
 * @version 1.5.8
 */
function doroto_show_activation_notice()
{
	if (get_option('doroto_show_activation_notice')) {
		delete_option('doroto_show_activation_notice');

		$page_id     = intval(get_option('doroto_main_page_id'));
		$page_status = get_post_status($page_id);
		$page_link   = '';

		if ($page_id && $page_status && $page_status !== 'trash') {
			$page_url  = get_permalink($page_id);
			$page_link = '<a href="' . esc_url($page_url) . '" target="_blank">' . esc_html__('Rotation Tournaments', 'doubles-rotation-tournament') . '</a>';
		}

		$notice_content = sprintf(
			__('To quickly understand the capabilities of the Rotation tournaments plugin, start on this page: %s', 'doubles-rotation-tournament'),
			$page_link
		);

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><b>' . wp_kses_post($notice_content) . '</b></p>';
		echo '</div>';
	}
}
add_action('admin_notices', 'doroto_show_activation_notice');


add_action('admin_init', 'doroto_plugin_update_and_notice_logic');
add_action('admin_notices', 'doroto_display_app_notice');


/**
 * admin notice about android app, help function
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_plugin_update_and_notice_logic()
{
	if (isset($_GET['doroto_dismiss_app_notice']) && isset($_GET['_wpnonce'])) {
		if (wp_verify_nonce($_GET['_wpnonce'], 'doroto_dismiss_app_notice_nonce')) {
			update_option('doroto_show_app_notice', 'false');
		}
	}
}


/**
 * admin notice about android app
 * @since 1.4.7
 * @version 1.4.7 
 */
function doroto_display_app_notice()
{
	if (get_option('doroto_show_app_notice') === 'true' && current_user_can('manage_options')) {
		$nonce = wp_create_nonce('doroto_dismiss_app_notice_nonce');
		$dismiss_url = add_query_arg([
			'doroto_dismiss_app_notice' => 'true',
			'_wpnonce' => $nonce
		]);

		ob_start();
		doroto_application_section_callback();
		$content = ob_get_clean();

		echo '<div class="notice notice-info is-dismissible" style="padding-right: 40px;">';
		echo wp_kses_post($content);
		echo '<a href="' . esc_url($dismiss_url) . '" class="notice-dismiss" style="text-decoration: none;" title="' . esc_attr__('Dismiss this notice', 'doubles-rotation-tournament') . '"><span class="screen-reader-text">' . esc_html__('Dismiss this notice', 'doubles-rotation-tournament') . '</span></a>';
		echo '</div>';
	}
}
