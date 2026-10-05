<?php
if (! defined('ABSPATH')) {
	exit;
}


/**
 * content of the shortcode admin page 
 * @since 1.0.0
 * @version 1.3.6 (fixed change game results availability)
 */
function doroto_menu_page_content()
{
	$output = "<h1>" . esc_html__('Overview of the available shortcode functions of Rotation Tournaments plugin.', 'doubles-rotation-tournament') . "</h1>";
	$output .= "<p>" . esc_html__('This plugin is designed to support tournaments, where there is a frequent change of teammates and the game thus becomes more interesting.', 'doubles-rotation-tournament') . "</p>";

	doroto_main_page_info($output);
	doroto_help_page_info($output);
	$output .= "<div>";
	$output .= '<table class="widefat fixed striped">';
	$output .= "<tr>";
	$output .= '<th style="width: 50px;">' . esc_html__('Item', 'doubles-rotation-tournament') . '</th>';
	$output .= '<th style="width: 400px;">' . esc_html__('Shortcode for embedding on the page', 'doubles-rotation-tournament') . '</th>';
	$output .= "<th>" . esc_html__('Description of function', 'doubles-rotation-tournament') . "</th>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>1.1</td>";
	$output .= "<td><ul>[doroto_games_to_play]</ul><ul>[doroto_games_to_play tournament_id='1']</ul></td>";
	$output .= "<td>" . esc_html__('Shows drawn games. Their number depends on the parameters of the tournament, such as the number of courts, the minimum number of non-playing players and, according to the permission to compare the number of matches. The tournament_id parameter is optional.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>1.2</td>";
	$output .= "<td><ul>[doroto_display_players]</ul><ul>[doroto_display_players tournament_id='1']</ul></td>";
	$output .= "<td>" . esc_html__('Displays the list of tournament participants and their running ranking. The format of displayed names depends on the setting of the display full names parameter. Add the tournament number after the id parameter if necessary.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>1.3</td>";
	$output .= "<td><ul>[doroto_refresh_page]</ul><ul>[doroto_refresh_page seconds_to_refresh='120']</ul></td>";
	$output .= "<td>" . esc_html__('It will refresh the page after a certain number of seconds.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>1.4</td>";
	$output .= "<td>[doroto_info_messsages]</td>";
	$output .= "<td>" . esc_html__('It will display red marked reports from submitted forms.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>2.1</td>";
	$output .= "<td><ul>[doroto_display_games]</ul><ul>[doroto_display_games tournament_id='1' only_played='1']</ul></td>";
	$output .= "<td>" . esc_html__('Displays a list of drawn games for the selected tournament. The only_played parameter determines whether only played games are listed.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>2.2</td>";
	$output .= "<td><ul>[doroto_player_filter]</ul><ul>[doroto_player_filter tournament_id='1']</ul></td>";
	$output .= "<td>" . esc_html__('Displays a list of drawn games only for the selected player.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>2.3</td>";
	$output .= "<td><ul>[doroto_change_game]</ul></td>";
	$output .= "<td>" . esc_html__('Match results can be edited here. Especially useful when an error occurred when entering the result of a played match.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>2.4</td>";
	$output .= "<td>[doroto_display_player_statistics]</td>";
	$output .= "<td>" . esc_html__('Displays all game statistics for the selected player.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.1</td>";
	$output .= "<td><ul>[doroto_add_player]</ul><ul>[doroto_add_player only_web_admin='1']</ul></td>";
	$output .= "<td>" . esc_html__('Allows the organizer to insert players from the user database. The only_web_admin parameter will limit the range of users of this function to only web administrators with the function admin, editor and editor-in-chief.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.2</td>";
	$output .= "<td><ul>[doroto_remove_player]</ul><ul>[doroto_remove_player only_web_admin='0']</ul></td>";
	$output .= "<td>" . esc_html__('Allows the tournament organizer to remove players from the tournament. When whole_names=1, the whole names are displayed, if whole_names=0, the first 4 characters of the first name and the last 4 characters of the last name are displayed. The only_web_admin parameter will limit the range of users of this function to only web administrators with the function admin, editor and editor-in-chief.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.3</td>";
	$output .= "<td><ul>[doroto_add_special_group]</ul><ul>[doroto_add_special_group only_web_admin='1']</ul></td>";
	$output .= "<td>" . esc_html__('If the player is included in a special group, then his name will be colored blue in the table. These other players are provided with information about a different implementation of the game.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.4</td>";
	$output .= "<td><ul>[doroto_remove_special_group]</ul><ul>[doroto_remove_special_group only_web_admin='1']</ul></td>";
	$output .= "<td>" . esc_html__('Ability to cancel assignment to a special group for a player.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.5</td>";
	$output .= "<td><ul>[doroto_temporary_disable_player]</ul><ul>[doroto_temporary_disable_player only_web_admin='0' tournament_id='2']</ul></td>";
	$output .= "<td>" . esc_html__('It will allow the tournament organizer or the participant himself to temporarily suspend his participation in further matches. Then this player is not included in the draw for further games.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.6</td>";
	$output .= "<td><ul>[doroto_temporary_enable_player]</ul><ul>[doroto_temporary_enable_player only_web_admin='0' tournament_id='2']</ul></td>";
	$output .= "<td>" . esc_html__('It will allow the tournament organizer or participant to resume their participation in the tournament.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.7</td>";
	$output .= "<td><ul>[doroto_enter_payment_manually]</ul><ul>[doroto_enter_payment_manually only_web_admin='0' tournament_id='2']</ul></td>";
	$output .= "<td>" . esc_html__('Here you can manually enter information about the payment made by a certain player.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.8</td>";
	$output .= "<td><ul>[doroto_remove_payment_manually]</ul><ul>[doroto_remove_payment_manually only_web_admin='0' tournament_id='2']</ul></td>";
	$output .= "<td>" . esc_html__('Here you can manually delete the information about the completed payment of a certain player.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<td>4.1</td>";
	$output .= "<td>[doroto_tournament_parameters]</td>";
	$output .= "<td>" . esc_html__('A function that displays the optional parameters for the tournament and allows them to be changed. The whole_names parameter determines how player names will be displayed, and the only_web_admin parameter limits the range of users who are allowed to edit the tournament.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.2</td>";
	$output .= "<td>[doroto_tournament_log_link tournament_id='85']</td>";
	$output .= "<td>" . esc_html__('Displays the text for logging into the tournament by id. It works with the invitation text parameter. This link disappears when registration is closed.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.3</td>";
	$output .= "<td><ul>[doroto_add_admin]</ul><ul>[doroto_add_admin whole_names='0' only_web_admin='1']</ul></td>";
	$output .= "<td>" . esc_html__('It will allow adding match organizer rights to other tournament participants.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.4</td>";
	$output .= "<td><ul>[doroto_table]</ul><ul>[doroto_table display_rows='20']</ul></td>";
	$output .= "<td>" . esc_html__('Displays a list of available tournaments. If the display_rows parameter is not specified, only the last 20 tournaments will be displayed.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.5</td>";
	$output .= "<td>[doroto_filter_tournaments]</td>";
	$output .= "<td>" . esc_html__('Filtering displayed tournaments in the table.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.6</td>";
	$output .= "<td>[add_tournament]</td>";
	$output .= "<td>" . esc_html__('Shows a button to create a new tournament.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>5.1</td>";
	$output .= "<td>[doroto_help_main_page]</td>";
	$output .= "<td>" . esc_html__('Shows help for using the main page.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>6.1</td>";
	$output .= "<td>[doroto_allow_presentation]</td>";
	$output .= "<td>" . esc_html__('Displays a link to start the presentation of the selected tournament. In the admin menu, you can set which tournament parameters will be shown on the screen in the selected time sequence. Especially suitable for displaying the running results of the tournament on a larger screen.', 'doubles-rotation-tournament') . "</td>";
	$output .= "</tr>";

	$output .= "</table>";
	$output .= "</div>";

	return $output;
}


/**
 * shortcode for page with shortcodes
 * @since 1.0.0
 */
function doroto_shortcodes_menu_page_callback()
{
	$allowed_html = doroto_allowed_html();
	echo '<div class="wrap">';
	echo wp_kses(doroto_menu_page_content(), 'post');
	echo '</div>';
}


/**
 * callback for admin homepage
 * @since 1.0.0
 */
function doroto_home_menu_page_callback()
{
	$allowed_html = doroto_allowed_html();
	echo '<div class="wrap">';
	echo wp_kses(doroto_home_page(), $allowed_html);
	echo doroto_convert_main_page_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built (contains a form)
	echo '</div>';
}


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
 * create admin menu tables
 * @since 1.1.0
 * @version 1.4.6 (mobile app)
 */
function doroto_admin_page_html()
{
	if (! current_user_can('manage_options')) {
		return;
	}

	$menu_slug = 'doubles-rotation-tournament';
	$default_tab = null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab = isset($_GET['tab'])
		? sanitize_key(wp_unslash($_GET['tab']))
		: $default_tab;

?>
	<!-- Our admin page content should all be inside .wrap -->
	<div class="wrap">
		<!-- Print the page title -->
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		<!-- Here are our tabs -->
		<nav class="nav-tab-wrapper">
			<a href="?page=<?php echo esc_attr($menu_slug); ?>" class="nav-tab <?php if ($tab === null): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Home', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=types" class="nav-tab <?php if ($tab === 'types'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Tournament types', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=parameters" class="nav-tab <?php if ($tab === 'parameters'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Tournament parameters', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=environment" class="nav-tab <?php if ($tab === 'environment'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Environment', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=rights" class="nav-tab <?php if ($tab === 'rights'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Rights', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=uninstall" class="nav-tab <?php if ($tab === 'uninstall'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Uninstall', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=presentation" class="nav-tab <?php if ($tab === 'presentation'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Presentation', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=shortcode" class="nav-tab <?php if ($tab === 'shortcode'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Shortcodes', 'doubles-rotation-tournament'); ?></a>
			<a href="?page=<?php echo esc_attr($menu_slug); ?>&tab=application" class="nav-tab <?php if ($tab === 'application'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Mobile app', 'doubles-rotation-tournament'); ?></a>

		</nav>

		<div class="tab-content">
			<?php switch ($tab):
				case 'types':
					doroto_types_menu_page_callback();
					break;
				case 'parameters':
					doroto_parameters_menu_page_callback();
					break;
				case 'environment':
					doroto_settings_menu_page_callback();
					break;
				case 'rights':
					doroto_rights_menu_page_callback();
					break;
				case 'uninstall':
					doroto_uninstall_menu_page_callback();
					break;
				case 'presentation':
					doroto_presentation_menu_page_callback();
					break;
				case 'shortcode':
					doroto_shortcodes_menu_page_callback();
					break;
				case 'application':
					doroto_application_menu_page_callback();
					break;
				default:
					doroto_home_menu_page_callback();
					break;
			endswitch; ?>
		</div>
	</div>
<?php
}


/**
 * preparation for admin environmental settings page
 * @since 1.0.0
 * @version 1.3.6
 */
function doroto_register_settings()
{

	add_settings_section(
		'doroto_settings_section',
		sanitize_text_field(__("Environment settings", "doubles-rotation-tournament")),
		'doroto_settings_section_callback',
		'doubles-rotation-tournament-settings'
	);

	add_settings_field(
		'doroto_settings_options',
		sanitize_text_field(__("Environment settings", "doubles-rotation-tournament")),
		'doroto_settings_options_callback',
		'doubles-rotation-tournament-settings',   // name of an existing page
		'doroto_settings_section'
	);

	register_setting(
		'doroto_settings',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
			'show_in_rest'       => false,
		)
	);

	add_settings_section(
		'doroto_rights_section',
		sanitize_text_field(__("Rights", "doubles-rotation-tournament")),
		'doroto_rights_section_callback',
		'doubles-rotation-tournament-rights'
	);

	add_settings_field(
		'doroto_rights_data_options',
		sanitize_text_field(__("Rights of the tournament organizer and players", "doubles-rotation-tournament")),
		'doroto_rights_data_options_callback',
		'doubles-rotation-tournament-rights',
		'doroto_rights_section'
	);

	register_setting(
		'doroto_rights',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);

	add_settings_section(
		'doroto_uninstall_section',
		sanitize_text_field(__("Uninstall", "doubles-rotation-tournament")),
		'doroto_uninstall_section_callback',
		'doubles-rotation-tournament-uninstall'
	);

	add_settings_field(
		'doroto_uninstall_data_options',
		sanitize_text_field(__("Uninstall and activate", "doubles-rotation-tournament")),
		'doroto_uninstall_data_options_callback',
		'doubles-rotation-tournament-uninstall',
		'doroto_uninstall_section'
	);

	register_setting(
		'doroto_uninstall',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);

	add_settings_section(
		'doroto_presentation_section',
		sanitize_text_field(__("Presentation", "doubles-rotation-tournament")),
		'doroto_presentation_section_callback',
		'doubles-rotation-tournament-presentation'
	);

	add_settings_field(
		'doroto_presentation_data_options',
		sanitize_text_field(__("Presentation", "doubles-rotation-tournament")),
		'doroto_presentation_data_options_callback',
		'doubles-rotation-tournament-presentation',
		'doroto_presentation_section'
	);

	register_setting(
		'doroto_presentation',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);


	add_settings_section(
		'doroto_application_section',
		sanitize_text_field(__("Mobile app", "doubles-rotation-tournament")),
		'doroto_application_section_callback',
		'doubles-rotation-tournament-application'
	);

	add_settings_field(
		'doroto_application_data_options',
		sanitize_text_field(__("Mobile app", "doubles-rotation-tournament")),
		'doroto_application_data_options_callback',
		'doubles-rotation-tournament-application',
		'doroto_application_section'
	);

	register_setting(
		'doroto_application',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);

	add_settings_section(
		'doroto_types_section',
		sanitize_text_field(__("Types of Tournaments", "doubles-rotation-tournament")),
		'doroto_types_section_callback',
		'doubles-rotation-tournament-types'
	);

	add_settings_field(
		'doroto_types_data_options',
		sanitize_text_field(__("Tournament types for individual sports", "doubles-rotation-tournament")),
		'doroto_types_data_options_callback',
		'doubles-rotation-tournament-types',
		'doroto_types_section'
	);

	register_setting(
		'doroto_types',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);

	add_settings_section(
		'doroto_parameters_section',
		sanitize_text_field(__("Parameters of Tournaments", "doubles-rotation-tournament")),
		'doroto_parameters_section_callback',
		'doubles-rotation-tournament-parameters'
	);

	add_settings_field(
		'doroto_parameters_data_options',
		sanitize_text_field(__("Tournaments parameters", "doubles-rotation-tournament")),
		'doroto_parameters_data_options_callback',
		'doubles-rotation-tournament-parameters',
		'doroto_parameters_section'
	);

	register_setting(
		'doroto_parameters',
		'doroto_settings',
		array(
			'sanitize_callback' => 'doroto_sanitize_settings',
		)
	);
}


/**
 * sanitize attribute 'display_rows' for tournament´s functions
 * @since 1.0.0
 * @version 1.4.7 (longitude and latitude)
 * @param mixed $input Raw input data from the settings form.
 * @return array Sanitized settings array.
 */
function doroto_sanitize_settings(mixed $input): array
{
	$doroto_settings = get_option('doroto_settings', []);

	if (!is_array($doroto_settings)) {
		$doroto_settings = [];
	}

	$input = is_array($input) ? $input : [];

	foreach ($input as $key => $value) {
		if ($key === 'latitude' || $key === 'longitude') {
			$doroto_settings[$key] = sanitize_text_field(trim((string) $value));
		} elseif (isset($doroto_settings[$key])) {
			if (is_int($doroto_settings[$key])) {
				$doroto_settings[$key] = intval($value);
			} elseif (is_string($doroto_settings[$key])) {
				$doroto_settings[$key] = sanitize_text_field($value);
			}
		} else {
			$doroto_settings[$key] = is_numeric($value)
				? (strpos((string)$value, '.') !== false ? (string) $value : intval($value))
				: sanitize_text_field($value);
		}
	}

	return $doroto_settings;
}
/*
function doroto_sanitize_settings($input)
{
    $doroto_settings = get_option('doroto_settings', []);

    if (!is_array($doroto_settings)) {
        $doroto_settings = [];
    }

    $input = is_array($input) ? $input : [];
    foreach ($input as $key => $value) {
        if (isset($doroto_settings[$key])) {
            if (is_int($doroto_settings[$key])) {
                $doroto_settings[$key] = intval($value);
            } elseif (is_string($doroto_settings[$key])) {
                $doroto_settings[$key] = sanitize_text_field($value);
            }
        } else {
            $doroto_settings[$key] = $value;
        }
    }
    return $doroto_settings;
}
*/

/**
 * label for admin page
 * @since 1.0.0
 */
function doroto_settings_section_callback()
{
	echo '<p>' . esc_html__("Here you can set restrictions for tournament administration from the website administrator's point of view.", "doubles-rotation-tournament") . '</p>';
}


/**
 * label for uninstall admin page
 * @since 1.1.0
 */
function doroto_uninstall_section_callback()
{
	echo '<p>' . esc_html__("Select what data will be deleted during the plugin uninstallation and what will happen upon its reactivation.", "doubles-rotation-tournament") . '</p>';
}


/**
 * label for rights admin page
 * @since 1.1.7
 */
function doroto_rights_section_callback()
{
	echo '<p>' . esc_html__("Select what data will define Rights of the tournament organizer and players.", "doubles-rotation-tournament") . '</p>';
}


/**
 * label for presentation admin page
 * @since 1.1.0
 */
function doroto_presentation_section_callback()
{
	echo '<p>' . esc_html__("The plugin allows presenting real-time tournament results on a large screen so that all players can stay informed. Here, you choose what information you want to display and how quickly the screens will transition.", "doubles-rotation-tournament") . '<br>' . esc_html__("Display the output from these shortcodes while the tournament presentation is on?", "doubles-rotation-tournament") . '</p>';
}

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
 * label for tournament types page
 * @since 1.1.0
 */
function doroto_types_section_callback()
{
	echo '<p>' . esc_html__("Select the default tournament type and specify which tournament types you allow to be created.", "doubles-rotation-tournament") . '</p>';
}

/**
 * label for tournament parameters page
 * @since 1.3.6
 */
function doroto_parameters_section_callback()
{
	echo '<p>' . esc_html__("Parameters that will be used to time estimate the progress of the tournament.", "doubles-rotation-tournament") . '</p>';
}


/**
 * content of admin enviromental setting page
 * @since 1.0.0
 */
function doroto_settings_options_callback()
{

	$doroto_settings = get_option('doroto_settings');

	$display_rows = isset($doroto_settings['display_rows']) ? intval($doroto_settings['display_rows']) : '';
	$refresh_seconds = isset($doroto_settings['refresh_seconds']) ? intval($doroto_settings['refresh_seconds']) : '';
	$player_name_length = isset($doroto_settings['player_name_length']) ? intval($doroto_settings['player_name_length']) : '';
	$youtube_link = isset($doroto_settings['youtube_link']) ? sanitize_text_field(wp_unslash($doroto_settings['youtube_link'])) : '';
	$log_in_link = isset($doroto_settings['log_in_link']) ? sanitize_text_field(wp_unslash($doroto_settings['log_in_link'])) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// settings 'display_rows'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_display_rows">' . esc_html__('Maximum number of displayed tournaments in the table. (0 = no limit)', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select id="doroto_settings_display_rows" name="doroto_settings[display_rows]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($display_rows), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__('Enter a value between 0 and 100 for display rows.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'refresh_seconds'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_refresh_seconds">' . esc_html__('Choose the time after which the page will be refreshed. Good especially when you are not the only one entering new data.. (0 = no refresh)', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select id="doroto_settings_refresh_seconds" name="doroto_settings[refresh_seconds]">';
	for ($i = 0; $i <= 1000; $i += 10) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($refresh_seconds), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__('Enter a value between 0 and 1000 for the number of seconds.', 'doubles-rotation-tournament') . ' ' . esc_html__("This setting has lower priority than using an attribute in the shortcode.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';


	// settings 'display name maximal length'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_player_name_length">' . esc_html__('Enter the maximum number of characters allowed in the players display name.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select id="doroto_settings_player_name_length" name="doroto_settings[player_name_length]">';
	for ($i = 5; $i <= 30; $i += 1) {
		echo '<option value="' . esc_attr($i) . '" ' . selected($i, esc_attr($player_name_length), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__('Enter a value between 5 and 30 for the number of characters.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'youtube_link'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_youtube_link">' . esc_html__('YouTube Video Link with a help.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<input type="text" name="doroto_settings[youtube_link]" value="' . esc_attr($youtube_link) . '" />';
	echo '<p class="description">' . esc_html__('Enter the YouTube video link.', 'doubles-rotation-tournament') . ' ' .  esc_html__('If you leave the field blank, the help video will not be displayed.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'log_in_link'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_log_in_link">' . esc_html__('The URL of the page to log into your account.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<input type="text" name="doroto_settings[log_in_link]" value="' . esc_attr($log_in_link) . '" />';
	echo '<p class="description">' . esc_html__('The URL of the page to log into your account.', 'doubles-rotation-tournament') . ' ' . esc_html__('If you leave the field blank, the log in prompt will not be displayed.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';
	echo '</table>';
}


/**
 * content of admin enviromental setting page
 * @since 1.0.0
 * @version 1.3.6
 */
function doroto_types_data_options_callback()
{

	$doroto_settings = get_option('doroto_settings');

	$tournament_type = isset($doroto_settings['tournament_type']) ? intval($doroto_settings['tournament_type']) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// settings 'tournament_type'
	$type_variables = doroto_types_variables();
	$tournament_types = doroto_tournament_types();
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_types_tournament_type">' . esc_html__('Specify the default tournament type to select.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[tournament_type]">';
	foreach ($type_variables as $value => $name) {
		if (doroto_read_settings($name, 1)) {
			$selected = ($value == $tournament_type) ? ' selected="selected"' : '';
			echo "<option value=\"" . esc_attr($value) . "\"" . esc_attr($selected) . ">" . esc_html($tournament_types[$value]) . "</option>";
		}
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__("This option appears by default by the create tournament button.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
}

/**
 * content of tournament parameters setting page
 * @since 1.3.6
 */
function doroto_parameters_data_options_callback()
{

	$doroto_settings = get_option('doroto_settings');

	$singles_tennis = isset($doroto_settings['singles_tennis']) ? intval($doroto_settings['singles_tennis']) : '';
	$doubles_tennis = isset($doroto_settings['doubles_tennis']) ? intval($doroto_settings['doubles_tennis']) : '';
	$singles_table_tennis = isset($doroto_settings['singles_table_tennis']) ? intval($doroto_settings['singles_table_tennis']) : '';
	$doubles_table_tennis = isset($doroto_settings['doubles_table_tennis']) ? intval($doroto_settings['doubles_table_tennis']) : '';
	$singles_padel = isset($doroto_settings['singles_padel']) ? intval($doroto_settings['singles_padel']) : '';
	$doubles_padel = isset($doroto_settings['doubles_padel']) ? intval($doroto_settings['doubles_padel']) : '';
	$beach_volleyball = isset($doroto_settings['beach_volleyball']) ? intval($doroto_settings['beach_volleyball']) : '';
	$squash = isset($doroto_settings['squash']) ? intval($doroto_settings['squash']) : '';
	$singles_badminton = isset($doroto_settings['singles_badminton']) ? intval($doroto_settings['singles_badminton']) : '';
	$doubles_badminton = isset($doroto_settings['doubles_badminton']) ? intval($doroto_settings['doubles_badminton']) : '';

	$singles_tennis_score = isset($doroto_settings['singles_tennis_score']) ? intval($doroto_settings['singles_tennis_score']) : '';
	$singles_tennis_hour = isset($doroto_settings['singles_tennis_hour']) ? intval($doroto_settings['singles_tennis_hour']) : '';
	$doubles_tennis_score = isset($doroto_settings['doubles_tennis_score']) ? intval($doroto_settings['doubles_tennis_score']) : '';
	$doubles_tennis_hour = isset($doroto_settings['doubles_tennis_hour']) ? intval($doroto_settings['doubles_tennis_hour']) : '';
	$singles_table_tennis_score = isset($doroto_settings['singles_table_tennis_score']) ? intval($doroto_settings['singles_table_tennis_score']) : '';
	$singles_table_tennis_hour = isset($doroto_settings['singles_table_tennis_hour']) ? intval($doroto_settings['singles_table_tennis_hour']) : '';
	$doubles_table_tennis_score = isset($doroto_settings['doubles_table_tennis_score']) ? intval($doroto_settings['doubles_table_tennis_score']) : '';
	$doubles_table_tennis_hour = isset($doroto_settings['doubles_table_tennis_hour']) ? intval($doroto_settings['doubles_table_tennis_hour']) : '';
	$singles_padel_score = isset($doroto_settings['singles_padel_score']) ? intval($doroto_settings['singles_padel_score']) : '';
	$singles_padel_hour = isset($doroto_settings['singles_padel_hour']) ? intval($doroto_settings['singles_padel_hour']) : '';
	$doubles_padel_score = isset($doroto_settings['doubles_padel_score']) ? intval($doroto_settings['doubles_padel_score']) : '';
	$doubles_padel_hour = isset($doroto_settings['doubles_padel_hour']) ? intval($doroto_settings['doubles_padel_hour']) : '';
	$beach_volleyball_score = isset($doroto_settings['beach_volleyball_score']) ? intval($doroto_settings['beach_volleyball_score']) : '';
	$beach_volleyball_hour = isset($doroto_settings['beach_volleyball_hour']) ? intval($doroto_settings['beach_volleyball_hour']) : '';
	$squash_score = isset($doroto_settings['squash_score']) ? intval($doroto_settings['squash_score']) : '';
	$squash_hour = isset($doroto_settings['squash_hour']) ? intval($doroto_settings['squash_hour']) : '';
	$singles_badminton_score = isset($doroto_settings['singles_badminton_score']) ? intval($doroto_settings['singles_badminton_score']) : '';
	$singles_badminton_hour = isset($doroto_settings['singles_badminton_hour']) ? intval($doroto_settings['singles_badminton_hour']) : '';
	$doubles_badminton_score = isset($doroto_settings['doubles_badminton_score']) ? intval($doroto_settings['doubles_badminton_score']) : '';
	$doubles_badminton_hour = isset($doroto_settings['doubles_badminton_hour']) ? intval($doroto_settings['doubles_badminton_hour']) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// visibility of Singles tennis tournament parameters
	echo '<tr class="iedit">';
	echo '<th scope="row">' . esc_html__('Tournament type', 'doubles-rotation-tournament') . '</th>';
	echo '<th scope="row">' . esc_html__('Visibility', 'doubles-rotation-tournament') . '</th>';
	echo '<th scope="row">' . esc_html__('Total average points in one game', 'doubles-rotation-tournament') . '</th>';
	echo '<th scope="row">' . esc_html__('Average number of points played per hour', 'doubles-rotation-tournament') . '</th>';
	echo '</tr>';
	echo '<tr class="iedit">';
	echo '<td scope="row">' . esc_html__('Parameters related to the selected type of tournament.', 'doubles-rotation-tournament') . '</td>';
	echo '<td scope="row">' . esc_html__('Specify whether this tournament type is visible for filtering and when creating a new tournament.', 'doubles-rotation-tournament') . '</td>';
	echo '<td scope="row">' . esc_html__('Average total points per game. E.g. with an average result of 6:4, the average total is 10.', 'doubles-rotation-tournament') . '</td>';
	echo '<td scope="row">' . esc_html__('How many points are played on one court (field) in 1 hour on average.', 'doubles-rotation-tournament') . '</td>';
	echo '</tr>';

	echo '<tr><td><b>' . esc_html__('Singles Tennis', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[singles_tennis]">';
	echo '<option value="1" ' . selected(1, esc_attr($singles_tennis), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($singles_tennis), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_singles_tennis_score" name="doroto_settings[singles_tennis_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_tennis_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_singles_tennis_hour" name="doroto_settings[singles_tennis_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_tennis_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Doubles Tennis', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[doubles_tennis]">';
	echo '<option value="1" ' . selected(1, esc_attr($doubles_tennis), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doubles_tennis), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_doubles_tennis_score" name="doroto_settings[doubles_tennis_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_tennis_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_doubles_tennis_hour" name="doroto_settings[doubles_tennis_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_tennis_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Singles Table Tennis', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[singles_table_tennis]">';
	echo '<option value="1" ' . selected(1, esc_attr($singles_table_tennis), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($singles_table_tennis), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_singles_table_tennis_score" name="doroto_settings[singles_table_tennis_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_table_tennis_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_singles_table_tennis_hour" name="doroto_settings[singles_table_tennis_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_table_tennis_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Doubles Table Tennis', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[doubles_table_tennis]">';
	echo '<option value="1" ' . selected(1, esc_attr($doubles_table_tennis), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doubles_table_tennis), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_doubles_table_tennis_score" name="doroto_settings[doubles_table_tennis_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_table_tennis_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_doubles_table_tennis_hour" name="doroto_settings[doubles_table_tennis_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_table_tennis_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Singles Padel', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[singles_padel]">';
	echo '<option value="1" ' . selected(1, esc_attr($singles_padel), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($singles_padel), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_singles_padel_score" name="doroto_settings[singles_padel_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_padel_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_singles_padel_hour" name="doroto_settings[singles_padel_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_padel_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Doubles Padel', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[doubles_padel]">';
	echo '<option value="1" ' . selected(1, esc_attr($doubles_padel), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doubles_padel), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_doubles_padel_score" name="doroto_settings[doubles_padel_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_padel_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_doubles_padel_hour" name="doroto_settings[doubles_padel_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_padel_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Beach Volleyball', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[beach_volleyball]">';
	echo '<option value="1" ' . selected(1, esc_attr($beach_volleyball), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($beach_volleyball), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_beach_volleyball_score" name="doroto_settings[beach_volleyball_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($beach_volleyball_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_beach_volleyball_hour" name="doroto_settings[beach_volleyball_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($beach_volleyball_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Squash', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[squash]">';
	echo '<option value="1" ' . selected(1, esc_attr($squash), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($squash), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_squash_score" name="doroto_settings[squash_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($squash_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_squash_hour" name="doroto_settings[squash_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($squash_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Singles Badminton', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[singles_badminton]">';
	echo '<option value="1" ' . selected(1, esc_attr($singles_badminton), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($singles_badminton), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_singles_badminton_score" name="doroto_settings[singles_badminton_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_badminton_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_singles_badminton_hour" name="doroto_settings[singles_badminton_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($singles_badminton_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td><b>' . esc_html__('Doubles Badminton', 'doubles-rotation-tournament') . '</b></td>';
	echo '<td>';
	echo '<select name="doroto_settings[doubles_badminton]">';
	echo '<option value="1" ' . selected(1, esc_attr($doubles_badminton), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doubles_badminton), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '</td>';
	echo '<td>';
	echo '<select id="doroto_settings_doubles_badminton_score" name="doroto_settings[doubles_badminton_score]">';
	for ($i = 0; $i <= 100; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_badminton_score), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td><td>';
	echo '<select id="doroto_settings_doubles_badminton_hour" name="doroto_settings[doubles_badminton_hour]">';
	for ($i = 0; $i <= 1000; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($doubles_badminton_hour), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '</td></tr>';

	echo '</table>';
}

/**
 * content of admin presentation setting page
 * @since 1.0.0
 */
function doroto_presentation_data_options_callback()
{
	$doroto_settings = get_option('doroto_settings');

	$show_next_seconds = isset($doroto_settings['show_next_seconds']) ? intval($doroto_settings['show_next_seconds']) : '';
	$doroto_tournament_log_link = isset($doroto_settings['doroto_tournament_log_link']) ? intval($doroto_settings['doroto_tournament_log_link']) : '';
	$doroto_games_to_play = isset($doroto_settings['doroto_games_to_play']) ? intval($doroto_settings['doroto_games_to_play']) : '';
	$doroto_display_players = isset($doroto_settings['doroto_display_players']) ? intval($doroto_settings['doroto_display_players']) : '';
	$doroto_display_games = isset($doroto_settings['doroto_display_games']) ? intval($doroto_settings['doroto_display_games']) : '';
	$doroto_display_player_statistics = isset($doroto_settings['doroto_display_player_statistics']) ? intval($doroto_settings['doroto_display_player_statistics']) : '';
	$doroto_table = isset($doroto_settings['doroto_table']) ? intval($doroto_settings['doroto_table']) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// settings 'doroto_tournament_log_link'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_tournament_log_link"> [doroto_tournament_log_link] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_tournament_log_link]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_tournament_log_link), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_tournament_log_link), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Invitation link to the tournament.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_games_to_play'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_games_to_play"> [doroto_games_to_play] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_games_to_play]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_games_to_play), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_games_to_play), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Schedule of matches.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_display_players'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_display_players"> [doroto_display_players] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_display_players]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_display_players), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_display_players), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Table with players and their ranking.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_display_games'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_display_games"> [doroto_display_games] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_display_games]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_display_games), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_display_games), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Table with all played games.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_display_player_statistics'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_display_player_statistics"> [doroto_display_player_statistics] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_display_player_statistics]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_display_player_statistics), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_display_player_statistics), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Table with statistics of a randomly selected player.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_table'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_presentation_doroto_table"> [doroto_table] </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[doroto_table]">';
	echo '<option value="1" ' . selected(1, esc_attr($doroto_table), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($doroto_table), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Table with available tournaments.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'show_next_seconds'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_presentation_show_next_seconds">' . esc_html__('Enter the number of seconds as the time between transitions to show the next shortcode.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select id="doroto_presentation_settings_show_next_seconds" name="doroto_settings[show_next_seconds]">';
	for ($i = 1; $i <= 120; $i += 1) {
		echo '<option value="' . esc_attr($i) . '" ' . selected($i, esc_attr($show_next_seconds), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__('Enter a value between 1 and 120 for the number of seconds.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
}

/**
 * content of admin Mobile application setting page
 * @since 1.4.6
 * @version 1.4.7 (add visibility,longitude,latitude)
 */
function doroto_application_data_options_callback()
{
	$doroto_settings = get_option('doroto_settings');

	$website_address = get_option('siteurl');
	$website_description = isset($doroto_settings['website_description']) ? sanitize_text_field(wp_unslash($doroto_settings['website_description'])) : '';
	$website_visible = isset($doroto_settings['website_visible']) ? intval($doroto_settings['website_visible']) : 0;
	$show_app_link = isset($doroto_settings['show_app_link']) ? intval($doroto_settings['show_app_link']) : 1;
	$anyone_can_register = get_option('users_can_register');
	$latitude = isset($doroto_settings['latitude']) ? floatval($doroto_settings['latitude']) : 50;
	$longitude = isset($doroto_settings['longitude']) ? floatval($doroto_settings['longitude']) : 15;
	$visibility = isset($doroto_settings['visibility']) ? intval($doroto_settings['visibility']) : 1;

	$response = wp_remote_get('http://ip-api.com/json/');
	if (!is_wp_error($response) && $longitude == 0 && $latitude == 0) {
		$body = json_decode(wp_remote_retrieve_body($response), true);
		$latitude = isset($body['lat']) ? floatval($body['lat']) : 50.05;
		$longitude = isset($body['lon']) ? floatval($body['lon']) : 14.47;
	}

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// settings 'doroto_tournament_website_address'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_website_address"> Website address </label></th>';
	echo '<td>';
	echo '<input type="text" readonly disabled class="regular-text" value="' . esc_attr($website_address) . '" />';
	echo '<p class="description">' . esc_html__("The website that is usable for the mobile application.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_website_description'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_website_description"> Website description </label></th>';
	echo '<td>';
	echo '<input type="text" name="doroto_settings[website_description]" class="regular-text" value="' . esc_attr($website_description) . '" />';
	echo '<p class="description">' . esc_html__("Description shown to users when selecting club websites in the app.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_website_visible'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_website_visible"> Will Web address be visible in app? </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[website_visible]">';
	echo '<option value="1" ' . selected(1, esc_attr($website_visible), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($website_visible), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Should this club website be shown in the list of available servers?", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'show_app_link'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_show_app_link"> ' . esc_html__('Show a link to the app on the tournament page?', 'doubles-rotation-tournament') . ' </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[show_app_link]">';
	echo '<option value="1" ' . selected(1, esc_attr($show_app_link), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($show_app_link), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("A short note under the tournament details tells players about the Android app.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_anyone_can_register'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_anyone_can_register"> Anyone can register? </label></th>';
	echo '<td>';
	echo '<strong>' . ($anyone_can_register ? esc_html__("Yes", "doubles-rotation-tournament") : esc_html__("No", "doubles-rotation-tournament")) . '</strong>';
	echo '<p class="description">' . esc_html__("This is a system-wide WordPress setting and cannot be changed here.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'doroto_visibility'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_visibility"> Default tournament visibility option in the list? </label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[visibility]">';
	echo '<option value="1" ' . selected(1, esc_attr($visibility), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($visibility), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("Choose the default option for whether a newly created tournament will be listed in the public tournament list.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// Latitude & Longitude via Leaflet map
	echo '<tr class="iedit">';
	echo '<th scope="row"><label> Club Location on Map </label></th>';
	echo '<td>';
	echo '<div id="doroto-map" style="width: 100%; height: 400px;"></div>';
	echo '<input type="hidden" id="doroto_latitude" name="doroto_settings[latitude]" value="' . esc_attr($latitude) . '">';
	echo '<input type="hidden" id="doroto_longitude" name="doroto_settings[longitude]" value="' . esc_attr($longitude) . '">';
	echo '<p class="description">' . esc_html__("Click on the map to set the club's location. The selected coordinates will be saved.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';

	$clean_lat = (float) $latitude;
	$clean_lng = (float) $longitude;

	echo '<script>        
    var doroto_latitude = ' . wp_json_encode($clean_lat) . ';
    var doroto_longitude = ' . wp_json_encode($clean_lng) . ';
	</script>';
}


/**
 * content of admin rights of the tournament organizer and players setting page
 * @since 1.1.7
 * @version 1.3.2 (adding minimum_matches)
 */
function doroto_rights_data_options_callback()
{
	$doroto_settings = get_option('doroto_settings');

	$only_admin_players = isset($doroto_settings['only_admin_players']) ? intval($doroto_settings['only_admin_players']) : '';
	$only_admin_posts = isset($doroto_settings['only_admin_posts']) ? intval($doroto_settings['only_admin_posts']) : '';
	$only_admin_creates = isset($doroto_settings['only_admin_creates']) ? intval($doroto_settings['only_admin_creates']) : '';
	$minimum_matches = isset($doroto_settings['minimum_matches']) ? intval($doroto_settings['minimum_matches']) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';

	// settings 'only_admin_players'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_only_admin_players">' . esc_html__('In addition to the web administrator, the tournament organizer could also work with the player database.', 'doubles-rotation-tournament') . ' ' . esc_html__('Select the restriction level for working with the player database.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<p class="description">' . esc_html__("Tournament organizers can only find players in the database", "doubles-rotation-tournament") . '</p>';
	echo '<select name="doroto_settings[only_admin_players]">';
	echo '<option value="3" ' . selected(3, esc_attr($only_admin_players), false) . '>' . esc_html__("from the current tournament", "doubles-rotation-tournament") . '</option>';
	echo '<option value="2" ' . selected(2, esc_attr($only_admin_players), false) . '>' . esc_html__("for whom they have already organized a tournament in the past", "doubles-rotation-tournament") . '</option>';
	echo '<option value="1" ' . selected(1, esc_attr($only_admin_players), false) . '>' . esc_html__("that they have met at a tournament where was also another organizer", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($only_admin_players), false) . '>' . esc_html__("all players w/o limitations", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("This setting has lower priority than using an attribute in the shortcode.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'only_admin_posts'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_only_admin_posts">' . esc_html__('Allow the tournament organizer to create a post to promote and administer the tournament?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[only_admin_posts]">';
	echo '<option value="0" ' . selected(0, esc_attr($only_admin_posts), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="1" ' . selected(1, esc_attr($only_admin_posts), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("By default, this is enabled by the site administrator. Otherwise, anyone can create a post.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';


	// settings 'only_admin_creates'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_settings_only_admin_creates">' . esc_html__('Can only a website administrator create a new tournament?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[only_admin_creates]">';
	echo '<option value="1" ' . selected(1, esc_attr($only_admin_creates), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($only_admin_creates), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("By default, this is enabled by everybody who is logged in. Otherwise, only an administrator, editor or author can create a tournament.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'minimum_matches'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_settings_minimum_matches">' . esc_html__('The minimum number of matches a player must play to be declared the winner.', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select id="doroto_settings_minimum_matches" name="doroto_settings[minimum_matches]">';
	for ($i = 1; $i <= 10; $i++) {
		echo '<option value="' . esc_attr($i) . '" ' . selected(esc_attr($i), esc_attr($minimum_matches), false) . '>' . esc_html($i) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__('Enter a value between 1 and 10 for minimum matches.', 'doubles-rotation-tournament') . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
}


function doroto_uninstall_data_options_callback()
{
	$doroto_settings = get_option('doroto_settings');

	$delete_database = isset($doroto_settings['delete_database']) ? intval($doroto_settings['delete_database']) : '';
	$delete_pages = isset($doroto_settings['delete_pages']) ? intval($doroto_settings['delete_pages']) : '';
	$delete_settings = isset($doroto_settings['delete_settings']) ? intval($doroto_settings['delete_settings']) : '';
	$update_activation = isset($doroto_settings['update_activation']) ? intval($doroto_settings['update_activation']) : '';

	echo '<table class="wp-list-table widefat fixed striped table-view-list forms doroto-admin-enlarged-table">';
	// settings 'delete_database'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_uninstall_delete_database">' . esc_html__('Uninstall the tournament database at the same time as the plugin?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[delete_database]">';
	echo '<option value="1" ' . selected(1, esc_attr($delete_database), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($delete_database), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("If you ever want to return to the plugin, it will be a shame to lose your data.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'delete_pages'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_uninstall_delete_pages">' . esc_html__('Uninstall tournament pages at the same time as the plugin?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[delete_pages]">';
	echo '<option value="1" ' . selected(1, esc_attr($delete_pages), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($delete_pages), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("If you have edited page settings, here you have the option to keep these changes even in the event of an update or uninstallation.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'delete_settings'
	echo '<tr class="alternate">';
	echo '<th scope="row"><label for="doroto_uninstall_delete_settings">' . esc_html__('Uninstall saved settings data at the same time as the plugin?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[delete_settings]">';
	echo '<option value="1" ' . selected(1, esc_attr($delete_settings), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($delete_settings), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("If you ever want to return to the plugin, it will be a shame to lose your data.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	// settings 'update_activation'
	echo '<tr class="iedit">';
	echo '<th scope="row"><label for="doroto_uninstall_update_activation">' . esc_html__('Update pages every time you activate the plugin?', 'doubles-rotation-tournament') . '</label></th>';
	echo '<td>';
	echo '<select name="doroto_settings[update_activation]">';
	echo '<option value="1" ' . selected(1, esc_attr($update_activation), false) . '>' . esc_html__("Yes", "doubles-rotation-tournament") . '</option>';
	echo '<option value="0" ' . selected(0, esc_attr($update_activation), false) . '>' . esc_html__("No", "doubles-rotation-tournament") . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__("This option keeps up with new plugin updates.", "doubles-rotation-tournament") . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
}


/**
 * menu page callback
 * @since 1.0.0
 */
function doroto_settings_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_settings');
			do_settings_sections('doubles-rotation-tournament-settings');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}

add_action('admin_init', 'doroto_register_settings');


/**
 * menu page callback
 * @since 1.1.0
 */
function doroto_uninstall_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_uninstall');
			do_settings_sections('doubles-rotation-tournament-uninstall');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}


/**
 * menu page callback
 * @since 1.1.7
 */
function doroto_rights_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_rights');
			do_settings_sections('doubles-rotation-tournament-rights');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}


/**
 * menu page callback
 * @since 1.1.0
 */
function doroto_presentation_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_presentation');
			do_settings_sections('doubles-rotation-tournament-presentation');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}

/**
 * menu page callback
 * @since 1.4.6
 */
function doroto_application_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_presentation');
			do_settings_sections('doubles-rotation-tournament-application');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}

/**
 * menu page callback
 * @since 1.1.0
 */
function doroto_types_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_types');
			do_settings_sections('doubles-rotation-tournament-types');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
}

/**
 * menu page callback for tournament parameters
 * @since 1.3.6
 */
function doroto_parameters_menu_page_callback()
{
?>
	<div class="wrap">
		<form action="options.php" method="post">
			<?php
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
				echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'doubles-rotation-tournament') . '</p></div>';
			}
			?>
			<?php
			settings_fields('doroto_parameters');
			do_settings_sections('doubles-rotation-tournament-parameters');
			submit_button(esc_html__('Save Settings', 'doubles-rotation-tournament'));
			?>

		</form>
	</div>
<?php
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


/**
 * create home page for admin: not used now
 * @since 1.0.0
 */
function doroto_home_page()
{
	global $wpdb;
	$output = "";

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
	$output .= '<div>' . esc_html__('The database contains the following number of tournaments:', 'doubles-rotation-tournament') . ' ' . $count . '</div>';

	$output .= '<div><h2>' . esc_html__('This plugin created the following new pages for Rotation Tournaments:', 'doubles-rotation-tournament') . '</h2>';
	$output .= '<ol>';

	$page_id = intval(get_option('doroto_main_page_id'));
	$page_status = get_post_status($page_id);
	if ($page_id && $page_status && $page_status !== 'trash') {
		$page_url = sanitize_text_field(wp_unslash(get_permalink($page_id)));
		$output .= '<li><a href="' . esc_url($page_url) . '" data-type="URL" data-id="' . esc_url($page_url) . '" target="_blank" >' . esc_html__('The main page for the management of all tournaments.', 'doubles-rotation-tournament') . '</a></li>';
	}

	$page_id = intval(get_option('doroto_help_page_id'));
	$page_status = get_post_status($page_id);
	if ($page_id && $page_status && $page_status !== 'trash') {
		$page_url = sanitize_text_field(wp_unslash(get_permalink($page_id)));
		$output .= '<li><a href="' . esc_url($page_url) . '" data-type="URL" data-id="' . esc_url($page_url) . '" target="_blank" >' . esc_html__('Help page for', 'doubles-rotation-tournament') . ' ' . esc_html__('Doubles Rotation Tournament', 'doubles-rotation-tournament') . '.</a></li>';
	}

	$page_id = intval(get_option('doroto_example_page_id'));
	$page_status = get_post_status($page_id);
	if ($page_id && $page_status && $page_status !== 'trash') {
		$page_url = sanitize_text_field(wp_unslash(get_permalink($page_id)));
		$output .= '<li><a href="' . esc_url($page_url) . '" data-type="URL" data-id="' . esc_url($page_url) . '" target="_blank" >' . esc_html__('A post with an example of a tournament presentation.', 'doubles-rotation-tournament') . '</a>.</li>';
	}

	$output .= '</ol></div>';

	$users_can_register = intval(get_option('users_can_register'));
	$user_count = count_users();
	$total_users = $user_count['total_users'];

	if (!$users_can_register || $total_users < 4) {
		$output .= '<div>';
		$output .= '<h2>' . esc_html__('Notice', 'doubles-rotation-tournament') . '</h2><ol>';

		if (!$users_can_register) {
			$output .= '<p><li><b>' . esc_html__('You have the Anyone Can Register option disabled in your Wordpress settings, which could interfere with the functionality of this plugin!', 'doubles-rotation-tournament') . ' ' . esc_html__('Allow anyone to register', 'doubles-rotation-tournament') . ' ' . '<a target="_blank" href="' . admin_url('options-general.php') . '">' . esc_html__('here', 'doubles-rotation-tournament') . '</a>.' . '</b></li></p>';
		}

		if ($total_users < 4) {
			$output .= '<p><li><b>' . esc_html__('Since the plugin only works with registered players, a very low number of registered users may affect the functionality.', 'doubles-rotation-tournament') . ' ' . esc_html__('The minimum number of registered players in Wordpress is 4.', 'doubles-rotation-tournament') . ' ' . esc_html__('You only have', 'doubles-rotation-tournament') . ' ' . $total_users . '.</b></li></p>';
		}
		$output .= '</ol></div>';
	}

	$output .= '<p><b>' . esc_html__('Warning', 'doubles-rotation-tournament') . ':</b> ' . esc_html__('Plugins restricting user roles may limit the functionality of', 'doubles-rotation-tournament') . ' <b>' . esc_html__('Rotation Tournaments', 'doubles-rotation-tournament') . '</b>.' . '</p>';

	$installed_plugins = get_option('active_plugins');
	$target_plugins = array('user-registration', 'user-role-editor', 'members', 'ultimate-member', 'wpfront-user-role-editor', 'hide-admin-bar-based-on-user-roles', 'user-menus', 'nav-menu-roles', 'paid-memberships-pro');
	$output .= '<ol>';
	foreach ($target_plugins as $plugin) {
		$target_plugin_path = $plugin . '/' . $plugin . '.php';

		if (in_array($target_plugin_path, $installed_plugins)) {
			$output .= '<li>' . esc_html__('Pay attention to the settings', 'doubles-rotation-tournament') . ' <b>' . $plugin . '</b>.</li>';
		}
	}
	$output .= '</ol>';

	$output .= '<h2>' . esc_html__('Terms used:', 'doubles-rotation-tournament') . '</h2>';
	$output .= '<p><b>' . esc_html__('Singles Rotation Tournament (hereinafter SiRoTo)', 'doubles-rotation-tournament') . ' </b> ' . esc_html__('is an alternative form of a Singles Tournament where players face each other without elimination rounds. Depending on the time options, everyone plays against everyone.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p><b>' . esc_html__('Doubles Rotation Tournament (hereinafter DoRoTo)', 'doubles-rotation-tournament') . ' </b> ' . esc_html__('is an alternative form of a Doubles Tournament, where players play each match with a different partner and in different positions (alternating left and right sides).', 'doubles-rotation-tournament') . '</p>';

	return $output;
}

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
 */
function doroto_render_dashboard_widget()
{
	global $wpdb;

	$tournaments_table = $wpdb->prefix . 'doroto_tournaments';

	$total_tournaments = $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table");
	$open_tournaments = $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table WHERE close_tournament = '0'");
	$open_registration = $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table WHERE open_registration = '1'");
	$players_raw = $wpdb->get_col("SELECT players FROM $tournaments_table WHERE players IS NOT NULL AND players != ''");

	$unique_players = [];

	foreach ($players_raw as $serialized_players) {
		$player_ids = maybe_unserialize($serialized_players);
		if (is_array($player_ids)) {
			foreach ($player_ids as $id) {
				$unique_players[$id] = true;
			}
		}
	}

	$total_unique_players = count($unique_players);

	echo '<ul style="list-style-type: disc; margin-left: 1em;">';
	echo '<li><strong>' . esc_html__('Total Tournaments:', 'doubles-rotation-tournament') . '</strong> ' . intval($total_tournaments) . '</li>';
	echo '<li><strong>' . esc_html__('Still playing:', 'doubles-rotation-tournament') . '</strong> ' . intval($open_tournaments) . '</li>';
	echo '<li><strong>' . esc_html__('With Open Registration:', 'doubles-rotation-tournament') . '</strong> ' . intval($open_registration) . '</li>';
	echo '<li><strong>' . esc_html__('Unique Players in Tournaments:', 'doubles-rotation-tournament') . '</strong> ' . intval($total_unique_players) . '</li>';
	echo '</ul>';
}
