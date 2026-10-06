<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Admin page of the plugin (2.0): one React page (src/admin -> build/admin) with the tabs
 * Overview, Settings and Help. It replaces the nine tabs that saved the option through
 * options.php and accepted any value.
 *
 * The page reads and saves the site settings (option doroto_settings) through
 * GET/POST doroto/v1/admin-settings (manage_options, cookie + REST nonce). The settings are
 * described once, by doroto_admin_schema(): the React page renders the fields from it and
 * doroto_admin_validate() checks every value against it (ranges, options, URLs), so a value
 * outside the allowed range is refused with a message at the field.
 * Labels are the texts of the old admin tabs, so their translations stay.
 * @since 2.0.0
 */

/**
 * Yes/No options of a select stored as 1/0.
 * @since 2.0.0
 */
function doroto_admin_yes_no(bool $inverted = false): array
{
	$yes = ['value' => $inverted ? 0 : 1, 'label' => __('Yes', 'doubles-rotation-tournament')];
	$no = ['value' => $inverted ? 1 : 0, 'label' => __('No', 'doubles-rotation-tournament')];
	return [$yes, $no];
}

/**
 * Sections and fields of the settings tab.
 * Field types: number (min, max, step), select (options), toggle (0/1), text, url,
 * info (read only value), map (latitude + longitude), types (table of tournament types).
 * @since 2.0.0
 */
function doroto_admin_schema(): array
{
	$types = doroto_tournament_types();
	$type_rows = [];
	foreach (doroto_types_variables() as $type => $key) {
		$type_rows[] = [
			'type' => $type,
			'name' => html_entity_decode($types[$type] ?? $key, ENT_QUOTES, 'UTF-8'),
			'visible' => $key,
			'score' => $key . '_score',
			'hour' => $key . '_hour',
		];
	}
	$type_options = array_map(function ($row) {
		return ['value' => $row['type'], 'label' => $row['name']];
	}, $type_rows);

	return [
		[
			'id' => 'types',
			'title' => __('Tournament types', 'doubles-rotation-tournament'),
			'description' => __('Select the default tournament type and specify which tournament types you allow to be created.', 'doubles-rotation-tournament') . ' ' . __('Parameters that will be used to time estimate the progress of the tournament.', 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'tournament_type', 'type' => 'select', 'options' => $type_options, 'label' => __('Specify the default tournament type to select.', 'doubles-rotation-tournament'), 'help' => __('This option appears by default by the create tournament button.', 'doubles-rotation-tournament')],
				[
					'key' => 'types_table',
					'type' => 'types',
					'rows' => $type_rows,
					'score' => ['min' => 0, 'max' => 100],
					'hour' => ['min' => 0, 'max' => 1000],
					'columns' => [
						'name' => __('Tournament type', 'doubles-rotation-tournament'),
						'visible' => __('Visibility', 'doubles-rotation-tournament'),
						'score' => __('Total average points in one game', 'doubles-rotation-tournament'),
						'hour' => __('Average number of points played per hour', 'doubles-rotation-tournament'),
					],
					'help' => [
						'visible' => __('Specify whether this tournament type is visible for filtering and when creating a new tournament.', 'doubles-rotation-tournament'),
						'score' => __('Average total points per game. E.g. with an average result of 6:4, the average total is 10.', 'doubles-rotation-tournament'),
						'hour' => __('How many points are played on one court (field) in 1 hour on average.', 'doubles-rotation-tournament'),
					],
				],
			],
		],
		[
			'id' => 'environment',
			'title' => __('Environment', 'doubles-rotation-tournament'),
			'description' => __("Here you can set restrictions for tournament administration from the website administrator's point of view.", 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'display_rows', 'type' => 'number', 'min' => 0, 'max' => 100, 'label' => __('Maximum number of displayed tournaments in the table. (0 = no limit)', 'doubles-rotation-tournament'), 'help' => __('Enter a value between 0 and 100 for display rows.', 'doubles-rotation-tournament')],
				['key' => 'refresh_seconds', 'type' => 'number', 'min' => 0, 'max' => 1000, 'step' => 10, 'label' => __('Choose the time after which the page will be refreshed. Good especially when you are not the only one entering new data.. (0 = no refresh)', 'doubles-rotation-tournament'), 'help' => __('Enter a value between 0 and 1000 for the number of seconds.', 'doubles-rotation-tournament') . ' ' . __('Only the pages built from the shortcodes are reloaded. The tournament blocks update themselves every 30 seconds without reloading.', 'doubles-rotation-tournament')],
				['key' => 'player_name_length', 'type' => 'number', 'min' => 5, 'max' => 30, 'label' => __('Enter the maximum number of characters allowed in the players display name.', 'doubles-rotation-tournament'), 'help' => __('Enter a value between 5 and 30 for the number of characters.', 'doubles-rotation-tournament')],
				['key' => 'youtube_link', 'type' => 'url', 'label' => __('YouTube Video Link with a help.', 'doubles-rotation-tournament'), 'help' => __('Enter the YouTube video link.', 'doubles-rotation-tournament') . ' ' . __('If you leave the field blank, the help video will not be displayed.', 'doubles-rotation-tournament')],
				['key' => 'log_in_link', 'type' => 'url', 'label' => __('The URL of the page to log into your account.', 'doubles-rotation-tournament'), 'help' => __('If you leave the field blank, the log in prompt will not be displayed.', 'doubles-rotation-tournament')],
			],
		],
		[
			'id' => 'rights',
			'title' => __('Rights', 'doubles-rotation-tournament'),
			'description' => __('Select what data will define Rights of the tournament organizer and players.', 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'only_admin_players', 'type' => 'select', 'label' => __('In addition to the web administrator, the tournament organizer could also work with the player database.', 'doubles-rotation-tournament') . ' ' . __('Select the restriction level for working with the player database.', 'doubles-rotation-tournament'), 'help' => __('Tournament organizers can only find players in the database', 'doubles-rotation-tournament') . ' …', 'options' => [
					['value' => 3, 'label' => __('from the current tournament', 'doubles-rotation-tournament')],
					['value' => 2, 'label' => __('for whom they have already organized a tournament in the past', 'doubles-rotation-tournament')],
					['value' => 1, 'label' => __('that they have met at a tournament where was also another organizer', 'doubles-rotation-tournament')],
					['value' => 0, 'label' => __('all players w/o limitations', 'doubles-rotation-tournament')],
				]],
				['key' => 'only_admin_posts', 'type' => 'select', 'options' => doroto_admin_yes_no(true), 'label' => __('Allow the tournament organizer to create a post to promote and administer the tournament?', 'doubles-rotation-tournament'), 'help' => __('By default, this is enabled by the site administrator. Otherwise, anyone can create a post.', 'doubles-rotation-tournament')],
				['key' => 'only_admin_creates', 'type' => 'toggle', 'label' => __('Can only a website administrator create a new tournament?', 'doubles-rotation-tournament'), 'help' => __('By default, this is enabled by everybody who is logged in. Otherwise, only an administrator, editor or author can create a tournament.', 'doubles-rotation-tournament')],
				['key' => 'minimum_matches', 'type' => 'number', 'min' => 1, 'max' => 10, 'label' => __('The minimum number of matches a player must play to be declared the winner.', 'doubles-rotation-tournament'), 'help' => __('Enter a value between 1 and 10 for minimum matches.', 'doubles-rotation-tournament')],
			],
		],
		[
			'id' => 'presentation',
			'title' => __('Presentation', 'doubles-rotation-tournament'),
			'description' => __('The plugin allows presenting real-time tournament results on a large screen so that all players can stay informed. Here, you choose what information you want to display and how quickly the screens will transition.', 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'show_next_seconds', 'type' => 'number', 'min' => 1, 'max' => 120, 'label' => __('Enter the number of seconds as the time between transitions to show the next shortcode.', 'doubles-rotation-tournament'), 'help' => __('Enter a value between 1 and 120 for the number of seconds.', 'doubles-rotation-tournament') . ' ' . __('The Presentation button of the tournament block uses this time too (at least 5 seconds).', 'doubles-rotation-tournament')],
				['key' => 'doroto_tournament_log_link', 'type' => 'toggle', 'label' => __('Invitation link to the tournament.', 'doubles-rotation-tournament'), 'help' => '[doroto_tournament_log_link]'],
				['key' => 'doroto_games_to_play', 'type' => 'toggle', 'label' => __('Schedule of matches.', 'doubles-rotation-tournament'), 'help' => '[doroto_games_to_play]'],
				['key' => 'doroto_display_players', 'type' => 'toggle', 'label' => __('Table with players and their ranking.', 'doubles-rotation-tournament'), 'help' => '[doroto_display_players]'],
				['key' => 'doroto_display_games', 'type' => 'toggle', 'label' => __('Table with all played games.', 'doubles-rotation-tournament'), 'help' => '[doroto_display_games]'],
				['key' => 'doroto_display_player_statistics', 'type' => 'toggle', 'label' => __('Table with statistics of a randomly selected player.', 'doubles-rotation-tournament'), 'help' => '[doroto_display_player_statistics]'],
				['key' => 'doroto_table', 'type' => 'toggle', 'label' => __('Table with available tournaments.', 'doubles-rotation-tournament'), 'help' => '[doroto_table]'],
			],
		],
		[
			'id' => 'application',
			'title' => __('Mobile app', 'doubles-rotation-tournament'),
			'description' => __('This plugin powers the server-side functionality for the Rotation Tournament mobile app. Bring your tournaments to your pocket!', 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'website_address', 'type' => 'info', 'value' => (string) get_option('siteurl'), 'label' => __('Website address', 'doubles-rotation-tournament'), 'help' => __('The website that is usable for the mobile application.', 'doubles-rotation-tournament')],
				['key' => 'website_description', 'type' => 'text', 'label' => __('Website description', 'doubles-rotation-tournament'), 'help' => __('Description shown to users when selecting club websites in the app.', 'doubles-rotation-tournament')],
				['key' => 'website_visible', 'type' => 'toggle', 'label' => __('Show this website in the app', 'doubles-rotation-tournament'), 'help' => __('Should this club website be shown in the list of available servers?', 'doubles-rotation-tournament')],
				['key' => 'show_app_link', 'type' => 'toggle', 'label' => __('Show a link to the app on the tournament page?', 'doubles-rotation-tournament'), 'help' => __('A short note under the tournament details tells players about the Android app.', 'doubles-rotation-tournament')],
				['key' => 'anyone_can_register', 'type' => 'info', 'value' => get_option('users_can_register') ? __('Yes', 'doubles-rotation-tournament') : __('No', 'doubles-rotation-tournament'), 'label' => __('Anyone can register', 'doubles-rotation-tournament'), 'help' => __('This is a system-wide WordPress setting and cannot be changed here.', 'doubles-rotation-tournament'), 'link' => admin_url('options-general.php')],
				['key' => 'visibility', 'type' => 'toggle', 'label' => __('Default tournament visibility option in the list', 'doubles-rotation-tournament'), 'help' => __('Choose the default option for whether a newly created tournament will be listed in the public tournament list.', 'doubles-rotation-tournament')],
				['key' => 'map', 'type' => 'map', 'label' => __('Club location on the map', 'doubles-rotation-tournament'), 'help' => __("Click on the map to set the club's location. The selected coordinates will be saved.", 'doubles-rotation-tournament')],
			],
		],
		[
			'id' => 'uninstall',
			'title' => __('Uninstall', 'doubles-rotation-tournament'),
			'description' => __('Select what data will be deleted during the plugin uninstallation and what will happen upon its reactivation.', 'doubles-rotation-tournament'),
			'fields' => [
				['key' => 'delete_database', 'type' => 'toggle', 'label' => __('Uninstall the tournament database at the same time as the plugin?', 'doubles-rotation-tournament'), 'help' => __('If you ever want to return to the plugin, it will be a shame to lose your data.', 'doubles-rotation-tournament')],
				['key' => 'delete_pages', 'type' => 'toggle', 'label' => __('Uninstall tournament pages at the same time as the plugin?', 'doubles-rotation-tournament'), 'help' => __('If you have edited page settings, here you have the option to keep these changes even in the event of an update or uninstallation.', 'doubles-rotation-tournament')],
				['key' => 'delete_settings', 'type' => 'toggle', 'label' => __('Uninstall saved settings data at the same time as the plugin?', 'doubles-rotation-tournament'), 'help' => __('If you ever want to return to the plugin, it will be a shame to lose your data.', 'doubles-rotation-tournament')],
				['key' => 'update_activation', 'type' => 'toggle', 'label' => __('Update pages every time you activate the plugin?', 'doubles-rotation-tournament'), 'help' => __('This option keeps up with new plugin updates.', 'doubles-rotation-tournament')],
			],
		],
	];
}

/**
 * Every stored key with its rule: ['int', min, max], ['options', [values]], ['bool'],
 * ['text'], ['url'], ['float', min, max].
 * @since 2.0.0
 */
function doroto_admin_rules(): array
{
	$rules = [
		'latitude' => ['float', -90, 90],
		'longitude' => ['float', -180, 180],
	];
	foreach (doroto_admin_schema() as $section) {
		foreach ($section['fields'] as $field) {
			switch ($field['type']) {
				case 'number':
					$rules[$field['key']] = ['int', $field['min'], $field['max']];
					break;
				case 'select':
					$rules[$field['key']] = ['options', array_map('intval', array_column($field['options'], 'value'))];
					break;
				case 'toggle':
					$rules[$field['key']] = ['bool'];
					break;
				case 'text':
					$rules[$field['key']] = ['text'];
					break;
				case 'url':
					$rules[$field['key']] = ['url'];
					break;
				case 'types':
					foreach ($field['rows'] as $row) {
						$rules[$row['visible']] = ['bool'];
						$rules[$row['score']] = ['int', $field['score']['min'], $field['score']['max']];
						$rules[$row['hour']] = ['int', $field['hour']['min'], $field['hour']['max']];
					}
					break;
			}
		}
	}
	return $rules;
}

/**
 * Check the submitted values. Unknown keys are ignored.
 * @return array [clean values, errors key => message]
 * @since 2.0.0
 */
function doroto_admin_validate(array $input): array
{
	$clean = [];
	$errors = [];
	foreach (doroto_admin_rules() as $key => $rule) {
		if (!array_key_exists($key, $input)) {
			continue;
		}
		$value = $input[$key];
		switch ($rule[0]) {
			case 'int':
				if (!is_numeric($value) || intval($value) != $value || $value < $rule[1] || $value > $rule[2]) {
					/* translators: 1: lowest allowed value, 2: highest allowed value */
					$errors[$key] = sprintf(__('Enter a whole number from %1$d to %2$d.', 'doubles-rotation-tournament'), $rule[1], $rule[2]);
				} else {
					$clean[$key] = intval($value);
				}
				break;
			case 'float':
				if (!is_numeric($value) || $value < $rule[1] || $value > $rule[2]) {
					/* translators: 1: lowest allowed value, 2: highest allowed value */
					$errors[$key] = sprintf(__('Enter a number from %1$d to %2$d.', 'doubles-rotation-tournament'), $rule[1], $rule[2]);
				} else {
					$clean[$key] = round(floatval($value), 6);
				}
				break;
			case 'options':
				if (!is_numeric($value) || !in_array(intval($value), $rule[1], true)) {
					$errors[$key] = __('Choose one of the options.', 'doubles-rotation-tournament');
				} else {
					$clean[$key] = intval($value);
				}
				break;
			case 'bool':
				$clean[$key] = empty($value) ? 0 : 1;
				break;
			case 'text':
				$clean[$key] = sanitize_text_field((string) $value);
				break;
			case 'url':
				$value = trim((string) $value);
				$url = $value === '' ? '' : esc_url_raw($value, ['http', 'https']);
				if ($value !== '' && ($url === '' || !wp_http_validate_url($url))) {
					$errors[$key] = __('Enter a full web address starting with https://, or leave the field empty.', 'doubles-rotation-tournament');
				} else {
					$clean[$key] = $url;
				}
				break;
		}
	}
	return [$clean, $errors];
}

/**
 * Current values of the settings (the keys of the rules).
 * @since 2.0.0
 */
function doroto_admin_values(): array
{
	$settings = get_option('doroto_settings', []);
	$settings = is_array($settings) ? $settings : [];
	$values = [];
	foreach (doroto_admin_rules() as $key => $rule) {
		$value = $settings[$key] ?? '';
		if ($rule[0] === 'float') {
			$values[$key] = floatval($value);
		} elseif (in_array($rule[0], ['int', 'options', 'bool'], true)) {
			$values[$key] = intval($value);
		} else {
			$values[$key] = (string) $value;
		}
	}
	return $values;
}

/**
 * Numbers of the overview (also the dashboard widget).
 * @since 2.0.0 (from doroto_render_dashboard_widget())
 */
function doroto_admin_stats(): array
{
	global $wpdb;
	$table = $wpdb->prefix . 'doroto_tournaments';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total = intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
	$playing = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE close_tournament = '0'"));
	$registration = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE open_registration = '1'"));
	$players_raw = $wpdb->get_col("SELECT players FROM $table WHERE players IS NOT NULL AND players != ''");
	// phpcs:enable
	$unique = [];
	foreach ($players_raw as $serialized) {
		$ids = maybe_unserialize($serialized);
		foreach (is_array($ids) ? $ids : [] as $id) {
			$unique[intval($id)] = true;
		}
	}
	return [
		['label' => __('Total Tournaments:', 'doubles-rotation-tournament'), 'value' => $total],
		['label' => __('Still playing:', 'doubles-rotation-tournament'), 'value' => $playing],
		['label' => __('With Open Registration:', 'doubles-rotation-tournament'), 'value' => $registration],
		['label' => __('Unique Players in Tournaments:', 'doubles-rotation-tournament'), 'value' => count($unique)],
	];
}

/**
 * Data of the Overview tab.
 * @since 2.0.0 (from doroto_home_page())
 */
function doroto_admin_overview(): array
{
	$pages = [];
	foreach ([
		'doroto_main_page_id' => __('The main page for the management of all tournaments.', 'doubles-rotation-tournament'),
		'doroto_help_page_id' => __('Help page for', 'doubles-rotation-tournament') . ' ' . __('Doubles Rotation Tournament', 'doubles-rotation-tournament'),
		'doroto_example_page_id' => __('A post with an example of a tournament presentation.', 'doubles-rotation-tournament'),
	] as $option => $label) {
		$page_id = intval(get_option($option));
		$status = $page_id ? get_post_status($page_id) : false;
		if ($status && $status !== 'trash') {
			$pages[] = ['label' => $label, 'url' => get_permalink($page_id), 'edit' => get_edit_post_link($page_id, 'raw')];
		}
	}

	$notices = [];
	if (!get_option('users_can_register')) {
		$notices[] = [
			'text' => __('You have the Anyone Can Register option disabled in your Wordpress settings, which could interfere with the functionality of this plugin!', 'doubles-rotation-tournament'),
			'link' => admin_url('options-general.php'),
			'linkText' => __('Allow anyone to register', 'doubles-rotation-tournament'),
		];
	}
	$users = count_users();
	if (intval($users['total_users']) < 4) {
		$notices[] = ['text' => __('Since the plugin only works with registered players, a very low number of registered users may affect the functionality.', 'doubles-rotation-tournament') . ' ' . __('The minimum number of registered players in Wordpress is 4.', 'doubles-rotation-tournament')];
	}
	$active = (array) get_option('active_plugins');
	foreach (['user-registration', 'user-role-editor', 'members', 'ultimate-member', 'wpfront-user-role-editor', 'hide-admin-bar-based-on-user-roles', 'user-menus', 'nav-menu-roles', 'paid-memberships-pro'] as $plugin) {
		if (in_array($plugin . '/' . $plugin . '.php', $active, true)) {
			$notices[] = ['text' => __('Plugins restricting user roles may limit the functionality of', 'doubles-rotation-tournament') . ' ' . __('Rotation Tournaments', 'doubles-rotation-tournament') . '. ' . __('Pay attention to the settings', 'doubles-rotation-tournament') . ' ' . $plugin . '.'];
		}
	}

	$examples = [];
	foreach (doroto_help_example_ids() as $number => $tournament_id) {
		$tournament = $tournament_id > 0 ? doroto_prepare_tournament($tournament_id) : null;
		if ($tournament) {
			$examples[] = ['label' => (string) $tournament->name, 'url' => doroto_tournament_page_url($tournament_id)];
		}
	}

	return [
		'stats' => doroto_admin_stats(),
		'pages' => $pages,
		'notices' => $notices,
		'mainPage' => [
			'exists' => intval(get_option('doroto_main_page_id')) > 0 && get_post(intval(get_option('doroto_main_page_id'))) !== null,
			'usesBlocks' => doroto_main_page_uses_blocks(),
		],
		'examples' => $examples,
		'terms' => [
			['term' => __('Singles Rotation Tournament (hereinafter SiRoTo)', 'doubles-rotation-tournament'), 'text' => __('is an alternative form of a Singles Tournament where players face each other without elimination rounds. Depending on the time options, everyone plays against everyone.', 'doubles-rotation-tournament')],
			['term' => __('Doubles Rotation Tournament (hereinafter DoRoTo)', 'doubles-rotation-tournament'), 'text' => __('is an alternative form of a Doubles Tournament, where players play each match with a different partner and in different positions (alternating left and right sides).', 'doubles-rotation-tournament')],
		],
		'storeUrl' => 'https://play.google.com/store/apps/details?id=cz.doroto.app&referrer=utm_source%3Dplugin%26utm_medium%3Dadmin',
	];
}

/**
 * Data of the Help tab: the blocks, the shortcodes for the classic editor, and the
 * shortcodes of the pages made before 2.0 (they keep working).
 * @since 2.0.0 (replaces the Shortcodes tab, doroto_menu_page_content())
 */
function doroto_admin_help(): array
{
	return [
		'blocks' => [
			['name' => __('Rotation tournament', 'doubles-rotation-tournament'), 'text' => __('Matches, players, results, statistics and settings of one tournament on tabs. Every change is saved without reloading the page, and the page updates itself when somebody else enters a result. Without a fixed tournament it shows the tournament chosen in the list (address parameter tournament_id).', 'doubles-rotation-tournament')],
			['name' => __('Rotation tournament: standings, matches, presentation', 'doubles-rotation-tournament'), 'text' => __('Variations of the same block that show only some sections. The presentation variation shows them one after another, for a large screen at the club.', 'doubles-rotation-tournament')],
			['name' => __('Rotation tournaments list', 'doubles-rotation-tournament'), 'text' => __('Tournaments of the website with filters, search and pages, and the button to create a tournament.', 'doubles-rotation-tournament')],
			['name' => __('Rotation tournament invitation', 'doubles-rotation-tournament'), 'text' => __('A link that registers the visitor for a tournament. It disappears when the registration closes.', 'doubles-rotation-tournament')],
		],
		'steps' => [
			__('Open a page in the editor and add the block "Rotation tournament" (search for "tournament").', 'doubles-rotation-tournament'),
			__('In the block settings choose the tournament, or leave it empty to show the tournament chosen in the list.', 'doubles-rotation-tournament'),
			__('Add the block "Rotation tournaments list" under it, so visitors can choose another tournament.', 'doubles-rotation-tournament'),
			__('The Help button (?) of the block starts guided tours on the example tournaments.', 'doubles-rotation-tournament'),
		],
		'classic' => [
			['code' => '[doroto_tournament tournament_id="5" sections="players,results" presentation="1" seconds="20"]', 'text' => __('Shows the Rotation tournament block in the classic editor. All parameters are optional.', 'doubles-rotation-tournament')],
			['code' => '[doroto_tournament_list per_page="10" target_page="12"]', 'text' => __('Shows the Rotation tournaments list block in the classic editor.', 'doubles-rotation-tournament')],
		],
		'legacy' => [
			['code' => "[doroto_games_to_play tournament_id='1']", 'text' => __('Shows drawn games. Their number depends on the parameters of the tournament, such as the number of courts, the minimum number of non-playing players and, according to the permission to compare the number of matches. The tournament_id parameter is optional.', 'doubles-rotation-tournament')],
			['code' => "[doroto_display_players tournament_id='1']", 'text' => __('Displays the list of tournament participants and their running ranking. The format of displayed names depends on the setting of the display full names parameter. Add the tournament number after the id parameter if necessary.', 'doubles-rotation-tournament')],
			['code' => "[doroto_refresh_page seconds_to_refresh='120']", 'text' => __('It will refresh the page after a certain number of seconds.', 'doubles-rotation-tournament')],
			['code' => '[doroto_info_messsages]', 'text' => __('It will display red marked reports from submitted forms.', 'doubles-rotation-tournament')],
			['code' => "[doroto_display_games tournament_id='1' only_played='1']", 'text' => __('Displays a list of drawn games for the selected tournament. The only_played parameter determines whether only played games are listed.', 'doubles-rotation-tournament')],
			['code' => "[doroto_player_filter tournament_id='1']", 'text' => __('Displays a list of drawn games only for the selected player.', 'doubles-rotation-tournament')],
			['code' => '[doroto_change_game]', 'text' => __('Match results can be edited here. Especially useful when an error occurred when entering the result of a played match.', 'doubles-rotation-tournament')],
			['code' => '[doroto_display_player_statistics]', 'text' => __('Displays all game statistics for the selected player.', 'doubles-rotation-tournament')],
			['code' => "[doroto_add_player only_web_admin='1']", 'text' => __('Allows the organizer to insert players from the user database. The only_web_admin parameter will limit the range of users of this function to only web administrators with the function admin, editor and editor-in-chief.', 'doubles-rotation-tournament')],
			['code' => "[doroto_remove_player only_web_admin='0']", 'text' => __('Allows the tournament organizer to remove players from the tournament. When whole_names=1, the whole names are displayed, if whole_names=0, the first 4 characters of the first name and the last 4 characters of the last name are displayed. The only_web_admin parameter will limit the range of users of this function to only web administrators with the function admin, editor and editor-in-chief.', 'doubles-rotation-tournament')],
			['code' => "[doroto_add_special_group only_web_admin='1']", 'text' => __('If the player is included in a special group, then his name will be colored blue in the table. These other players are provided with information about a different implementation of the game.', 'doubles-rotation-tournament')],
			['code' => "[doroto_remove_special_group only_web_admin='1']", 'text' => __('Ability to cancel assignment to a special group for a player.', 'doubles-rotation-tournament')],
			['code' => "[doroto_temporary_disable_player only_web_admin='0' tournament_id='2']", 'text' => __('It will allow the tournament organizer or the participant himself to temporarily suspend his participation in further matches. Then this player is not included in the draw for further games.', 'doubles-rotation-tournament')],
			['code' => "[doroto_temporary_enable_player only_web_admin='0' tournament_id='2']", 'text' => __('It will allow the tournament organizer or participant to resume their participation in the tournament.', 'doubles-rotation-tournament')],
			['code' => "[doroto_enter_payment_manually only_web_admin='0' tournament_id='2']", 'text' => __('Here you can manually enter information about the payment made by a certain player.', 'doubles-rotation-tournament')],
			['code' => "[doroto_remove_payment_manually only_web_admin='0' tournament_id='2']", 'text' => __('Here you can manually delete the information about the completed payment of a certain player.', 'doubles-rotation-tournament')],
			['code' => '[doroto_tournament_parameters]', 'text' => __('A function that displays the optional parameters for the tournament and allows them to be changed. The whole_names parameter determines how player names will be displayed, and the only_web_admin parameter limits the range of users who are allowed to edit the tournament.', 'doubles-rotation-tournament')],
			['code' => "[doroto_tournament_log_link tournament_id='85']", 'text' => __('Displays the text for logging into the tournament by id. It works with the invitation text parameter. This link disappears when registration is closed.', 'doubles-rotation-tournament')],
			['code' => "[doroto_add_admin whole_names='0' only_web_admin='1']", 'text' => __('It will allow adding match organizer rights to other tournament participants.', 'doubles-rotation-tournament')],
			['code' => "[doroto_table display_rows='20']", 'text' => __('Displays a list of available tournaments. If the display_rows parameter is not specified, only the last 20 tournaments will be displayed.', 'doubles-rotation-tournament')],
			['code' => '[doroto_filter_tournaments]', 'text' => __('Filtering displayed tournaments in the table.', 'doubles-rotation-tournament')],
			['code' => '[doroto_add_tournament]', 'text' => __('Shows a button to create a new tournament.', 'doubles-rotation-tournament')],
			['code' => '[doroto_help_main_page]', 'text' => __('Shows help for using the main page.', 'doubles-rotation-tournament')],
			['code' => '[doroto_allow_presentation]', 'text' => __('Displays a link to start the presentation of the selected tournament. In the admin menu, you can set which tournament parameters will be shown on the screen in the selected time sequence. Especially suitable for displaying the running results of the tournament on a larger screen.', 'doubles-rotation-tournament')],
		],
	];
}

/**
 * REST API of the admin page (manage_options).
 * GET  doroto/v1/admin-settings              schema, values, overview, help
 * POST doroto/v1/admin-settings {values}     validate and save; 400 with field messages
 * POST doroto/v1/admin-convert-main-page     main page content -> blocks (old content in a revision)
 * POST doroto/v1/admin-examples              create the example tournaments again
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	$admin = function () {
		return current_user_can('manage_options');
	};
	register_rest_route('doroto/v1', '/admin-settings', [
		['methods' => 'GET', 'callback' => 'doroto_rest_admin_settings_get', 'permission_callback' => $admin],
		['methods' => 'POST', 'callback' => 'doroto_rest_admin_settings_save', 'permission_callback' => $admin],
	]);
	register_rest_route('doroto/v1', '/admin-convert-main-page', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_admin_convert_main_page',
		'permission_callback' => $admin,
	]);
	register_rest_route('doroto/v1', '/admin-examples', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_admin_examples',
		'permission_callback' => $admin,
	]);
});

function doroto_rest_admin_settings_get()
{
	return new WP_REST_Response([
		'schema' => doroto_admin_schema(),
		'values' => doroto_admin_values(),
		'overview' => doroto_admin_overview(),
		'help' => doroto_admin_help(),
	], 200);
}

function doroto_rest_admin_settings_save(WP_REST_Request $request)
{
	$input = $request->get_param('values');
	[$clean, $errors] = doroto_admin_validate(is_array($input) ? $input : []);
	if ($errors) {
		return new WP_REST_Response(['error_code' => 'invalid_settings', 'fields' => $errors], 400);
	}
	$settings = get_option('doroto_settings', []);
	update_option('doroto_settings', array_merge(is_array($settings) ? $settings : [], $clean));
	return new WP_REST_Response(['success' => true, 'values' => doroto_admin_values()], 200);
}

function doroto_rest_admin_convert_main_page()
{
	$page_id = intval(get_option('doroto_main_page_id'));
	if (!$page_id || !get_post($page_id)) {
		return new WP_REST_Response(['error_code' => 'main_page_not_found'], 404);
	}
	wp_save_post_revision($page_id);
	wp_update_post(['ID' => $page_id, 'post_content' => doroto_main_page_blocks()]);
	return new WP_REST_Response(['success' => true, 'overview' => doroto_admin_overview()], 200);
}

function doroto_rest_admin_examples()
{
	doroto_create_tournament_record();
	return new WP_REST_Response(['success' => true, 'overview' => doroto_admin_overview()], 200);
}

/**
 * The admin page: an element for the React app. Its data come from the REST API; the
 * element carries only the addresses the app needs before the first request.
 * @since 2.0.0
 */
function doroto_admin_page_html()
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$config = [
		'leafletCss' => plugins_url('assets/css/leaflet.css', dirname(__FILE__)),
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a panel
		'panel' => isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '',
	];
	echo '<div class="wrap">';
	echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
	echo '<div id="doroto-admin-root" data-config="' . esc_attr(wp_json_encode($config)) . '">';
	echo '<noscript><p>' . esc_html__('The settings of the plugin need JavaScript.', 'doubles-rotation-tournament') . '</p></noscript>';
	// Replaced by the React app; shown only when build/admin/index.js did not load
	// (e.g. 403 after an FTP upload with wrong permissions).
	echo '<div id="doroto-admin-missing" class="notice notice-error inline" style="display:none"><p>'
		. esc_html__('The settings page could not be loaded. Check that the web server can read the folder build/ of the plugin (folders 755, files 644) and reload the page.', 'doubles-rotation-tournament')
		. '</p></div>';
	echo '</div></div>';
}

/**
 * Makes the files of the plugin readable for the web server. An FTP upload may create
 * folders without the read/execute bits for others (e.g. 700); Apache then answers 403
 * for build/ and the admin page and the blocks stay empty. Only adds missing bits
 * (FS_CHMOD_DIR / FS_CHMOD_FILE, default 0755 / 0644), never removes any; a chmod that
 * PHP is not allowed to do is skipped.
 * @since 2.0.0
 */
function doroto_fix_file_permissions()
{
	$dir_mode = defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755;
	$file_mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
	$skip = ['node_modules', 'src', '.git'];
	$stack = [rtrim(plugin_dir_path(dirname(__FILE__)), '/\\')];
	while ($stack) {
		$dir = array_pop($stack);
		$entries = @scandir($dir);
		if ($entries === false) {
			continue;
		}
		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if (is_link($path)) {
				continue;
			}
			$is_dir = is_dir($path);
			$mode = $is_dir ? $dir_mode : $file_mode;
			$perms = @fileperms($path);
			if ($perms !== false && ($perms & $mode) !== $mode) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- runs outside WP_Filesystem on purpose
				@chmod($path, ($perms & 0777) | $mode);
			}
			if ($is_dir) {
				$stack[] = $path;
			}
		}
	}
}

/**
 * Scripts of the admin page, only there.
 * @since 2.0.0
 */
function doroto_admin_enqueue($hook)
{
	if (!doroto_is_plugin_admin_page($hook)) {
		return;
	}
	// Files uploaded by FTP keep the version, so check them whenever the page opens.
	doroto_fix_file_permissions();
	// Its own handle, so it runs even when build/admin/index.js does not load.
	wp_register_script('doroto-admin-fallback', false, [], doroto_VERSION, true);
	wp_enqueue_script('doroto-admin-fallback');
	wp_add_inline_script('doroto-admin-fallback', "window.addEventListener('load',function(){"
		. "var r=document.getElementById('doroto-admin-root'),m=document.getElementById('doroto-admin-missing');"
		. "if(r&&m&&!r.dataset.mounted){m.style.display='block';}});");
	$dir = plugin_dir_path(dirname(__FILE__)) . 'build/admin/';
	if (!file_exists($dir . 'index.asset.php')) {
		return;
	}
	$asset = include $dir . 'index.asset.php';
	$url = plugins_url('build/admin/', dirname(__FILE__));
	wp_enqueue_script('doroto-admin', $url . 'index.js', $asset['dependencies'], $asset['version'], true);
	wp_set_script_translations('doroto-admin', 'doubles-rotation-tournament');
	wp_enqueue_style('wp-components');
	if (file_exists($dir . 'index.css')) {
		wp_enqueue_style('doroto-admin', $url . 'index.css', ['wp-components'], $asset['version']);
	}
}
add_action('admin_enqueue_scripts', 'doroto_admin_enqueue');
