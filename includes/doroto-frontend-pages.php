<?php
if (! defined('ABSPATH')) {
	exit;
}


/**
 * run by hook to update voluntary DoRoTo pages
 * @since 1.0.0
 */
function doroto_update_pages()
{
	global $wpdb;
	$update_activation = intval(doroto_read_settings('update_activation', 1));
	if ($update_activation) {
		doroto_update_main_page_content_on_activation();
		doroto_update_help_page_content_on_activation();
		doroto_update_privacy_policy_page_on_activation();
		doroto_update_terms_of_service_page_on_activation();

		$table_name = $wpdb->prefix . 'doroto_tournaments';
		$query = "SELECT COUNT(*) FROM $table_name";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var($query);

		if ($count < 2) {
			doroto_update_example_page_content_on_activation();
		}
	}
}


/**
 * create the main page when activating the plugin
 * @since 1.0.0
 */
function doroto_create_main_page()
{
	$page_id = intval(get_option('doroto_main_page_id'));
	if ($page_id) {
		$page = get_post($page_id);
		if ($page) {
			return;
		}
	}
	$page_title = sanitize_text_field(__('Rotation Tournaments', 'doubles-rotation-tournament'));
	$page_content = doroto_main_page();

	$page = array(
		'post_title'    => $page_title,
		'post_content'  => $page_content,
		'post_status'   => 'publish',
		'post_type'     => 'page'
	);

	$page_id = wp_insert_post($page);
	update_option('doroto_main_page_id', $page_id);
}


/**
 * Function to create a help page when activating the plugin
 * @since 1.0.0
 */
function doroto_create_help_page()
{
	$page_id = intval(get_option('doroto_help_page_id'));
	if ($page_id) {
		$page = get_post($page_id);
		if ($page) {
			return;
		}
	}
	$page_title = sanitize_text_field(__('Rules of the Doubles Rotation Tournament', 'doubles-rotation-tournament'));
	$page_content = doroto_help_page();

	$page = array(
		'post_title'    => $page_title,
		'post_content'  => $page_content,
		'post_status'   => 'publish',
		'post_type'     => 'page'
	);

	$page_id = wp_insert_post($page);
	update_option('doroto_help_page_id', $page_id);
}


/**
 * content of main page
 * @since 1.0.0
 * @version 1.4.4 (added doroto_register_add_player)
 */
function doroto_main_page()
{
	$output = "";
	$output .= '[doroto_info_messsages]';
	$output .= '[doroto_tournament_log_link]';
	$output .= '<div id="doroto-games-to-play">';
	$output .= '[doroto_games_to_play]';
	$output .= '</div>';
	$output .= '<div id="doroto-display-players">';
	$output .= '[doroto_display_players]';
	$output .= '[doroto_display_tournament_progress]';
	$output .= '</div>';

	$output .= '[doroto_refresh_page]';

	$output .= '[doroto_display_div_first label="' . esc_html__('Played Matches ...', 'doubles-rotation-tournament') . '" div_id="doroto-played-matches"]
				[doroto_player_filter]
				[doroto_change_game]
				<div id="doroto-display-games">[doroto_display_games only_played="1"]</div>
				[doroto_display_player_statistics]
				[doroto_display_div_last]';

	$output .= '[doroto_display_div_first label="' . esc_html__('Player Management ...', 'doubles-rotation-tournament') . '" div_id="doroto-player-management"]';

	$output .= '[doroto_display_other_background_first label="' . esc_html__('Suspension and Resumption', 'doubles-rotation-tournament') . '" div_id="doroto-player-suspension"]
				[doroto_temporary_enable_player]
				[doroto_temporary_disable_player]
				[doroto_display_other_background_last]';

	$output .= '[doroto_display_other_background_first label="' . esc_html__('Add or Remove a Player', 'doubles-rotation-tournament') . '" div_id="doroto-player-add"]';
	$output .= '[doroto_add_player]
				[doroto_register_add_player]
				[doroto_remove_player]';
	$output .= '[doroto_display_other_background_last]';

	$output .= '[doroto_display_other_background_first label="' . esc_html__('Special group management', 'doubles-rotation-tournament') . '" div_id="doroto-player-special"]';
	$output .= '[doroto_add_special_group]
				[doroto_remove_special_group]';
	$output .= '[doroto_display_other_background_last]';

	$output .= '[doroto_display_other_background_first label="' . esc_html__('Payments from players', 'doubles-rotation-tournament') . '" div_id="doroto-player-payments"]';
	$output .= '[doroto_enter_payment_manually]
				[doroto_remove_payment_manually]';
	$output .= '[doroto_display_other_background_last]';

	$output .= '[doroto_display_div_last]';

	$output .= '[doroto_display_div_first label="' . esc_html__('Tournament Editing ...', 'doubles-rotation-tournament') . '" div_id="doroto-tournament-editing"]';
	$output .= '[doroto_tournament_parameters]
				[doroto_add_admin]';
	$output .= '[doroto_display_div_last]';

	$output .= '[doroto_display_div_first label="' . esc_html__('Tournament Selection ...', 'doubles-rotation-tournament') . '" div_id="doroto-tournament-selection"]';
	$output .= '[doroto_filter_tournaments]
				[doroto_table]
				[doroto_add_tournament]';
	$output .= '[doroto_display_div_last]';

	$output .= '[doroto_display_div_first label="' . esc_html__('Help ...', 'doubles-rotation-tournament') . '" div_id="doroto-help"]';

	$output .= '[doroto_help_main_page]';
	$output .= '[doroto_display_div_last]';
	$output .= '[doroto_allow_presentation]';
	$output .= '[doroto_floating_help]';

	$output = str_replace(["\n", "\r", "\t"], '', $output);
	return $output;
}


/**
 * help main page content
 * @since 1.0.0
 * @version 1.3.6(help update)
 */
function doroto_help_main_page_shortcode()
{
	$output = '';
	if (doroto_check_if_presentation_on()) {
		return $output;
	}
	doroto_help_page_info($output);

	$youtube_link = doroto_read_settings("youtube_link", "");
	$youtube_link = sanitize_text_field(wp_unslash($youtube_link));

	if ($youtube_link != "") {
		$output .= '<p><a href=" ' . esc_attr($youtube_link) . ' " target="_blank">' . esc_html__('View the help video in an external window.', 'doubles-rotation-tournament') . '</a></p>';
	};

	$output .= '<p>' . esc_html__('This standalone page will guide you through the entire tournament in all of the following consecutive stages:', 'doubles-rotation-tournament') . '</p>';

	$output .= '<ol><li>' . esc_html__('Create a new tournament', 'doubles-rotation-tournament') . '<ul>
<li>' . esc_html__('The creator of the tournament becomes its organizer. Only the organizer has the right to close the registration and the tournament.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Individual players sign up (or withdraw) in the list of available tournaments separately.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Click on the tournament name to view its details.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('Editing tournament parameters', 'doubles-rotation-tournament') . '<ul>';

	$output .= '<li>' . esc_html__('You have the option to choose whether player names will be displayed in a shortened form to avoid disclosing sensitive information.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The Minimum Playing Players parameter determines the minimum number of players required to proceed to the next match. A higher number provides more variability but may also slow down the progress of the tournament.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The website administrator can add an invitation to the tournament, which will be displayed through a newly created post.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The tournament organizer can grant organizer rights to another player.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('Adding players and adding them to a special group', 'doubles-rotation-tournament') . '<ul>
<li>' . esc_html__('Adding players from the database can be restricted to the website administrator.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Adding to a special group will colorfully alert the player to the pre-agreed method of handling the special group. This can only be done by the match organizer.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('In the tournament settings, you can enable the possibility to track the payment of the entry fee.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Players can withdraw from the tournament on their own, or the match organizer can remove them from the match.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('The closing of player registration', 'doubles-rotation-tournament') . '<ul>
<li>' . esc_html__('Only the tournament organizer can close the player registration.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('Match scheduling and result entry', 'doubles-rotation-tournament') . '<ul>
<li>' . esc_html__('Once the player registration is closed, the scheduling of the first matches will be displayed.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The serving starts with the player at position R1.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The number of matches scheduled depends on the chosen number of courts and the number of players.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Only players participating in the match and the tournament organizer can enter match results.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('The option to hide a match is used only when, for example, one of the players is currently unavailable. This leads to the scheduling of another match, and this hidden match is not counted in the tournament results.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('Each of the players can suspend their participation in the tournament. After that, the player will not be assigned to matches. Suspension of participation in the tournament can also be done by the administrator.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('In the list of played matches, the organizer can make corrections in case of incorrectly entered results.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('In the table of registered players, the players with the best results are displayed at the top.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('Closing the tournament and announcing the winner.', 'doubles-rotation-tournament') . '<ul><li>' . esc_html__('Only the tournament organizer has the right to close the tournament. After closing, it is no longer possible to enter results or make edits.', 'doubles-rotation-tournament') . '</li>';

	$output .= '<li>' . esc_html__('After the tournament is closed, the winner of the tournament with the best ratio of games won to losses will be highlighted in color in the table of registered players.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '<li>' . esc_html__('Presentation of the interim results of the tournament.', 'doubles-rotation-tournament') . '<ul><li>' . esc_html__('You can start a presentation of the tournament on your computer screen, giving everyone the opportunity to easily get an overview of the progress of the tournament. Turn on full screen mode (F11) in the browser.', 'doubles-rotation-tournament') . '</li></ul></li>';

	$output .= '</ol>';
	return $output;
}
add_shortcode('doroto_help_main_page', 'doroto_help_main_page_shortcode');


/**
 * update the main page when activating the plugin
 * @since 1.0.0
 */
function doroto_update_main_page_content_on_activation()
{
	$doroto_main_page_id = intval(get_option('doroto_main_page_id'));

	if ($doroto_main_page_id) {
		$page_content = doroto_main_page();
		$page_title = sanitize_text_field(__('Rotation Tournaments', 'doubles-rotation-tournament'));

		$page_data = array(
			'ID'           => $doroto_main_page_id,
			'post_title'    => $page_title,
			'post_content' => $page_content
		);
		wp_update_post($page_data);
	}
}


/**
 * update help page when activating the plugin
 * @since 1.0.0
 */
function doroto_update_help_page_content_on_activation()
{
	$doroto_help_page_id = intval(get_option('doroto_help_page_id'));

	if ($doroto_help_page_id) {
		$page_content = doroto_help_page();
		$page_title = sanitize_text_field(__('Rules of the Doubles Rotation Tournament', 'doubles-rotation-tournament'));

		$page_data = array(
			'ID'           => $doroto_help_page_id,
			'post_title'    => $page_title,
			'post_content' => $page_content
		);
		wp_update_post($page_data);
	}
}


/**
 * create a post with example of a tournament when activating the plugin
 * @since 1.0.0
 * @version 1.3.7(flexible tournament id)
 */
function doroto_create_example_page()
{
	global $wpdb;
	$page_id = intval(get_option('doroto_example_page_id'));
	if ($page_id) {
		$page = get_post($page_id);
		if ($page) {
			return;
		}
	}

	$page_title = sanitize_text_field(__('Announcement of Doubles Rotation Tournament (Example)', 'doubles-rotation-tournament'));

	$doroto_settings = get_option('doroto_settings');
	$tournament_id = isset($doroto_settings['tournament_example_1']) ? intval($doroto_settings['tournament_example_1']) : '1';

	$page_content = doroto_tournament_post($tournament_id);
	$tournament = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d",
		$tournament_id
	));
	$invitation = sanitize_text_field($tournament->invitation);

	$page = array(
		'post_title'    => $page_title,
		'post_content'  => $page_content,
		'post_excerpt'  => $invitation,
		'post_status'   => 'publish',
		'post_type'     => 'post'
	);

	$page_id = wp_insert_post($page);
	update_option('doroto_example_page_id', $page_id);

	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$wpdb->update(
		$table_name,
		array(
			'page_id' => $page_id,
		),
		array('id' => $tournament_id)
	);
}


/**
 * content of the post with example of a tournament when activating the plugin
 * @since 1.0.0
 */
function doroto_tournament_post(int $tournament_id)
{
	$tournament_id = intval($tournament_id);
	$output = "
		[doroto_info_messsages]
		[doroto_tournament_log_link tournament_id=\"" . esc_html($tournament_id) . "\"]
		<div id='doroto-games-to-play'>
		[doroto_games_to_play tournament_id=\"" . esc_html($tournament_id) . "\"]
		</div><p>
		<div id='doroto-display-players'>
		[doroto_display_players tournament_id=\"" . esc_html($tournament_id) . "\"]
		</div></p>
		<p><div class='doroto-grey-background'>
		[doroto_temporary_enable_player tournament_id=\"" . esc_html($tournament_id) . "\"]
		[doroto_temporary_disable_player tournament_id=\"" . esc_html($tournament_id) . "\"]</div></p>
		<p>[doroto_player_filter tournament_id=\"" . esc_html($tournament_id) . "\"] 	
		<div id='doroto-display-games'>
		[doroto_display_games only_played=\"0\" tournament_id=\"" . esc_html($tournament_id) . "\"]
		</div></p>
		[doroto_refresh_page]";

	$output = str_replace(["\n", "\r", "\t"], '', $output);
	return $output;
}


/**
 * update a post with example of a tournament when activating the plugin
 * @since 1.0.0
 */
function doroto_update_example_page_content_on_activation()
{
	$doroto_example_page_id = intval(get_option('doroto_example_page_id'));

	if ($doroto_example_page_id) {
		$page_content = doroto_tournament_post(1);
		$page_title = sanitize_text_field(__('Announcement of Doubles Rotation Tournament (Example)', 'doubles-rotation-tournament'));

		$page_data = array(
			'ID'           => $doroto_example_page_id,
			'post_title'    => $page_title,
			'post_content' => $page_content
		);
		wp_update_post($page_data);
	}
}


/**
 * content of help page 
 * @version 1.3.4 (Pair selection mechanism)
 * @since 1.0.0
 */
function doroto_help_page()
{
	$output = "";
	doroto_main_page_info($output);

	$output .= '<p>' . esc_html__('Doubles Rotation Tournament is an alternative form of a doubles tournament, where players play each match with a different partner and in different positions (alternating left and right sides).', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('This type of tournament stands out for its dynamism and social component, and thus can serve as a suitable environment for all doubles tournaments where different groups of players meet on the court. The ratio of games won to games lost is calculated for each participant. The winner of the tournament is the player with this highest ratio. Such a player can best adapt to new circumstances and thus becomes an ideal teammate for doubles.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('Content:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul><li>1. <a href="#tournament-target" data-type="internal" data-id="#tournament-target">' . esc_html__('Objective of Doubles Rotation Tournament', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>2. <a href="#game-principle" data-type="internal" data-id="#game-principle">' . esc_html__('Principle of the game', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>3. <a href="#game-preparation" data-type="internal" data-id="#game-preparation">' . esc_html__('Preparing the game', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>4. <a href="#game-beginning" data-type="internal" data-id="#game-beginning">' . esc_html__('Start of the game', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>5. <a href="#course-tournament" data-type="internal" data-id="#course-tournament">' . esc_html__('Tournament Progress', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>6. <a href= "#evaluation-tournament" data-type="internal" data-id="#evaluation-tournament"">' . esc_html__('Evaluation of the tournament', 'doubles-rotation-tournament') . '</a></li>';
	$output .= '<li>7. <a href="#comparison-tournament" data-type="internal" data-id="#comparison-tournament">' . esc_html__('Comparison with the classic tournament', 'doubles-rotation-tournament') . '</a></li></ul>';
	$output .= '<h4 class="wp-block-heading" id="tournament-target">' . '1. ' . esc_html__('Objective of Doubles Rotation Tournament', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<ul><li>' . esc_html__('opening up participation to players of various skill levels (especially suitable for tournaments where it might be challenging to gather players of similar skill levels)', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('creating a pleasant atmosphere for social events', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('making new friends', 'doubles-rotation-tournament') . '</li></ul>';
	$output .= '<p><b>' . esc_html__('In DoRoTo', 'doubles-rotation-tournament') . ' ' . '</b>' . esc_html__('fully adheres to the official tennis rules except for the exceptions described below.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('Explanation of abbreviation: DoRoTo (DOuble ROtation TOurnament)', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h4 class="wp-block-heading" id="game-principle">' . '2. ' . esc_html__('Principle of the game', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('Participants of the tournament randomly change partners several times during the tournament and play against different pairs. For each participant, the ratio of games won to games lost is calculated.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('The winner of the tournament is the player with the highest ratio. Such a player can adapt best to new circumstances and becomes the ideal teammate for doubles matches.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('Optionally, a final match can be organized, from which 2 players will be announced as winners in the category of the most ideal pair.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h4 class="wp-block-heading" id="game-preparation">' . '3. ' . esc_html__('Game Preparation', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<h5 class="wp-block-heading">' . esc_html__('Agreement on the Game Format', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<p>' . esc_html__('Before the tournament begins, the game format is agreed upon. The following parameters play a decisive role:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul><li>' . esc_html__('the number of courts allocated for play', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('estimated duration of the tournament', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('the total agreed sum of games in each match', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('number of players', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__("amount of players in", "doubles-rotation-tournament") . ' <a href="#group" data-type="internal" data-id="#group">' . esc_html__("special group", "doubles-rotation-tournament") . '</a></li></ul>';

	$output .= '<p>' . esc_html__('The outcome of this agreement should be the determination of how many matches each player can play in different pairs and how many total gems each match will have.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h6 class="wp-block-heading">' . esc_html__('Example', 'doubles-rotation-tournament') . '</h6>';
	$output .= '<p><em>' . esc_html__('The tournament organizer has 2 courts available. They plan to organize a doubles tournament for 20 players within a time frame from 9:00 AM to 3:00 PM. It is estimated that 15 games can be played on 1 court within 1 hour. Altogether, it is possible to play approximately 6 hours x 15 games x 2 courts = 180 games.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p><em>' . esc_html__('Now, assuming a game with a total of 5 games, which allows for 36 matches to be played (180 / 5). Each player will participate in an average of 36 / (20 / 4) = 7 games.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p><em>' . esc_html__('If the organizer wanted to ensure quicker rotation of pairs, they could opt for a total of 3 games per match. Then, it would be possible to play 180 / 3 = 60 matches, and each player would participate in an average of 60 / (20 / 4) = 12 games.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p><em>' . esc_html__('With the aim of allowing players to play with more tennis partners during the tournament, we will try to choose more matches with a smaller number of played games.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p>' . esc_html__('The table determining who will play with whom and on which side of the court will be provided by dedicated software through random selection. With an increasing number of players, the number of match combinations grows significantly, making it difficult to manage the tournament successfully without computational techniques.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h5 class="wp-block-heading" id="group">' . esc_html__('Special group', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<p>' . esc_html__('Players of various skill levels will participate in the tournament. In order not to create a pair that is too weak or too strong due to random selection, less skilled or too strong players are placed in a special group. This special group, in addition to respectful treatment from players, ensures that pairs consisting of 2 players from this group or 2 players outside of this group are optionally not drawn. It is also possible to ensure that a player in this special group is not declared the winner.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h6 class="wp-block-heading">' . esc_html__('Example', 'doubles-rotation-tournament') . '</h6>';
	$output .= '<p><em>' . esc_html__('Parents of playing children can be placed in a special group. When it is set that neither 2 players in the group nor 2 players outside the group will be drawn into a pair, a parent-child pair is always ensured. When choosing that a player in a special group cannot win, it is ensured that the winner is not the parent, but only the highest placed child.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p>' . esc_html__('Using a special group is optional. If men of approximately the same level will play, the use of a special group loses its meaning.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h4 class="wp-block-heading" id="game-beginning">' . '4. ' . esc_html__('Start of the game', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The course of the game is determined by randomly drawn matches. The following can be read from the description of the drawn matches:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul><li>' . esc_html__('who will play against whom (L1 & R1)', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('against which pair they will play (against L2 & R2)', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('on which side the player will be standing while receiving (L - left or R - right side)', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('who starts with the serve in the first game (player with the index R1)', 'doubles-rotation-tournament') . '</li></ul>';

	$output .= '<h5 class="wp-block-heading" id="group">' . esc_html__('Pair selection mechanism', 'doubles-rotation-tournament') . '</h5>';
	$output .= '<p>' . esc_html__('The drawing of pairs takes place according to the following procedure:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ol><li>' . esc_html__('The active player with the fewest matches will be selected', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('For this player, the player with the least number of matches played together at the level of a teammate is preferentially sought', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('If the system finds more players with the same number of matches in common, then the next criterion, the smallest number of matches in total, decides', 'doubles-rotation-tournament') . '</li></ol>';

	$output .= '<h4 class="wp-block-heading" id="course-tournament">' . '5. ' . esc_html__('Tournament Progress', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('During the tournament, results are continuously recorded, allowing players to observe their overall ranking during the course of the event.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('At the beginning of the tournament, it can be agreed that matches that cannot be won anymore may be prematurely ended based on a request from the losing pair.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('It can also be agreed that only a result with a difference of 2 games will be accepted. A combination of a 2-game difference and an early exit option can also be set.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<h6 class="wp-block-heading">' . esc_html__('Example', 'doubles-rotation-tournament') . '</h6>';
	$output .= '<p><em>' . esc_html__('For example, if the total sum of games played is 5, the match can be concluded with scores like 0:3 or 1:3. If the total sum of gems played is 7, the match can be ended at scores like 0:4, 1:4, 2:4, or 1:5.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<p><em>' . esc_html__('If a result with a difference of 2 games is required when playing up to a total of 5 games, then the match cannot be ended at 3:2, but it is at 4:2. If the game is tied, the match concludes with a tiebreak, with a score of 4:3.', 'doubles-rotation-tournament') . '</em></p>';
	$output .= '<h4 class="wp-block-heading" id="evaluation-tournament">' . '6. ' . esc_html__('Evaluation of the tournament', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The winner of the tournament is announced as the player with the highest ratio of games won to games lost. This player may be proclaimed as', 'doubles-rotation-tournament') . ' <strong>' . esc_html__('Best Doubles Partner', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('If multiple players have the same score, multiple players may be declared winners of the tournament.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('If, optionally, a final match between the 4 selected participants with the highest score is arranged at the end, then 2 players will emerge from this final as the winners in the category of the Most Ideal Pair.', 'doubles-rotation-tournament') . '</p>';

	$output .= '<h4 class="wp-block-heading" id="comparison-tournament">' . '7. ' . esc_html__('Comparison with the classic tournament', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<ul><li>' . esc_html__('There is a rotation of teammates (DoRoTo tries, depending on the setup and the number of games, to play with everyone as much as possible, alternating the left and right sides of the court equally).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('An individual can enter the tournament without a teammate (thanks to rotation, you do not have a permanent partner anyway).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('The tournament can be organized even with an odd number of players and even with a minimum lineup of 4 players (this DoRoTo feature saves the tournament organizer a lot of trouble).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('DoRoTo allows you to define a special group of players for which special conditions can be set (if women are included in the special group, it can be set that only male+female pairs are drawn. Alternatively, male+male can be allowed but female+female prohibited. And vice versa (Similar combinations can be created in the relationship of parent + child or strong + weaker player).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('DoRoTo does not take place in the form of eliminations, so players are still in the game for the duration of the tournament and will play the same number of matches according to the settings.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Usually shorter matches are played and thus more will be played during the tournament (or, on the contrary, shorter matches allow more players to participate).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('The tournament can be interrupted or extended in time at any time and it will not disrupt its progress in any way.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Players can temporarily or permanently interrupt their game or enter the tournament in the middle, for example.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('It is not necessary for each player to play the same number of matches (the final ranking is determined only by the ratio of games won to games lost).', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('DoRoTo perfectly provides an overview of the quality of individual players, where the quality of a teammate does not affect the rating due to the frequent change of partners.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('There is no downtime during the tournament waiting for a match to be played to move the tournament forward.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('At the end of DoRoTo, 2 categories of winners will be announced: the Best player of the tournament and the Most ideal pair of the tournament (theoretically, this pair could play together for the first time during the final match).', 'doubles-rotation-tournament') . '</li></ul>';

	return $output;
}


/**
 * provides a link to help page
 * @since 1.0.0
 */
function doroto_help_page_info(string &$output)
{
	$page_id = intval(get_option('doroto_help_page_id'));

	if ($page_id && get_post_status($page_id)) {
		$page_url = sanitize_text_field(wp_unslash(get_permalink($page_id)));
		$output .= '<p><a href="' . esc_url($page_url) . '" data-type="URL" data-id="' . esc_url($page_url) . '">' . esc_html__('Rules for the Doubles Rotation Tournament can be found on a separate page', 'doubles-rotation-tournament') . '</a>.</p>';
	}
}


/**
 * provides a link to main page
 * @since 1.0.0
 */
function doroto_main_page_info(string &$output)
{
	$page_id = intval(get_option('doroto_main_page_id'));

	if ($page_id && get_post_status($page_id)) {
		$page_url = sanitize_text_field(wp_unslash(get_permalink($page_id)));
		$output .= '<p><a href="' . esc_url($page_url) . '" data-type="URL" data-id="' . esc_url($page_url) . '">' . esc_html__('The schedule of Rotation Tournaments can be found on a separate page', 'doubles-rotation-tournament') . '</a>.</p>';
	}
}


/**
 * creation of a new post with a tournament details by users
 * @since 1.0.0
 * @version 1.5.8
 */
function doroto_create_new_tournament_post(int $tournament_id)
{
	global $wpdb;
	$tournament = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM {$wpdb->prefix}doroto_tournaments WHERE id = %d", $tournament_id)
	);

	$tournament_name = sanitize_text_field($tournament->name);
	if (strlen($tournament_name) > 5) {
		$page_name = $tournament_name;
	} else {
		$page_name = sanitize_text_field(__('DoRoTo', 'doubles-rotation-tournament') . '-' . $tournament_name);
	}

	$page_id = intval($tournament->page_id);
	$invitation = sanitize_text_field($tournament->invitation);

	$page_content = doroto_tournament_post($tournament_id);

	$page = array(
		'post_title'    => $page_name,
		'post_content'  => $page_content,
		'post_excerpt'  => $invitation,
		'post_status'   => 'publish',
		'post_type'     => 'post'
	);
	$page_status = get_post_status($page_id);
	if ($page_id != 0 && $page_status) {
		$page['ID'] = $page_id;
		$page_id = wp_update_post($page);
	} else {
		$page_id = wp_insert_post($page);
		$wpdb->update("{$wpdb->prefix}doroto_tournaments", ['page_id' => $page_id], ['id' => $tournament_id]);
	}
}


/**
 * Privacy Policy
 * @since 1.4.7
 */
function doroto_privacy_policy_page()
{
	$output = "";

	// Introduction
	$output .= '<p>' . esc_html__('Last Revision: [2025-07-23]', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('This Privacy Policy explains how information about you is collected, used, and disclosed when you use the Rotation Tournaments plugin and associated mobile application (collectively, "the Service"). This policy applies to the data processed through the WordPress website where this plugin is installed.', 'doubles-rotation-tournament') . '</p>';

	// Section 1: Data Controller
	$output .= '<h4 class="wp-block-heading" id="data-controller">' . esc_html__('1. Data Controller', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The Rotation Tournaments plugin stores all its data, including personal information, on a server chosen by the administrator of the website where the plugin is installed. Therefore, the primary Data Controller is the Administrator of the website you are using. The developers of the Rotation Tournaments plugin do not have access to or control over your personal data.', 'doubles-rotation-tournament') . '</p>';

	// Section 2: Information We Collect
	$output .= '<h4 class="wp-block-heading" id="info-collected">' . esc_html__('2. Information We Collect', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The Service utilizes the existing WordPress user database. When you register on the website or your account is created, we collect and store the following information:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li>' . esc_html__('First Name and Last Name', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Email Address', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Phone Number', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Display Name: By default, this is created by combining your first and last name. You have full control to change this in your user profile.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';

	// Section 3: How We Use Your Information
	$output .= '<h4 class="wp-block-heading" id="info-usage">' . esc_html__('3. How We Use Your Information', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('Your information is used for the following purposes:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li>' . esc_html__('To register you as a player in tournaments.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('To display your name and progress within a tournament.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('To allow tournament organizers to manage events.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('To allow you and other authorized players to input match results.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';

	// Section 4: Data Security and Storage
	$output .= '<h4 class="wp-block-heading" id="data-security">' . esc_html__('4. Data Security and Storage', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('All data is stored in the WordPress database on the server chosen by the website administrator. The level of security for this database is the sole responsibility of the website administrator. The Rotation Tournaments plugin does not provide its own data hosting or additional security layers beyond standard WordPress practices.', 'doubles-rotation-tournament') . '</p>';

	// Section 5: Information Sharing and Disclosure
	$output .= '<h4 class="wp-block-heading" id="info-sharing">' . esc_html__('5. Information Sharing and Disclosure', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('Your visibility to other users depends on settings configured by the website administrator and individual tournament organizers.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p><strong>' . esc_html__('Administrator-Set Visibility:', 'doubles-rotation-tournament') . '</strong></p>';
	$output .= '<p>' . esc_html__('The website administrator chooses one of four levels of data access for regular users:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li>' . esc_html__('Level 1: You can only see the names of players from tournaments you are currently registered in.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Level 2: You can see the names of players from tournaments where you are listed as an organizer or are currently registered.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Level 3 (Default): You can see the names of players from any tournament you have ever participated in.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Level 4: You can see the names of all players in the website\'s user database.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';
	$output .= '<p><strong>' . esc_html__('Organizer-Set Visibility:', 'doubles-rotation-tournament') . '</strong></p>';
	$output .= '<p>' . esc_html__('A tournament organizer can make the following choices:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li>' . esc_html__('Make a tournament public or private. Public tournaments may be visible to anyone.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('Display player names as their full "Display Name" or in an obfuscated format (e.g., the first 4 and last 4 characters of the Display Name).', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';

	// Section 6: Your Rights and Choices
	$output .= '<h4 class="wp-block-heading" id="user-rights">' . esc_html__('6. Your Rights and Choices', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<ul>';
	$output .= '<li><strong>' . esc_html__('Edit Your Information:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('You can access and modify your personal information (name, email, phone) at any time through your user profile editor on the website.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li><strong>' . esc_html__('Control Your Display Name:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('You are responsible for your "Display Name". You can change it to a nickname or any other name you prefer not to correspond with your real name for enhanced privacy. By default, it is generated from the first and last name you provide during registration.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li><strong>' . esc_html__('Delete Your Account:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('You have the option to delete your user account. Please be aware that this action is irreversible and will permanently remove all records of your participation in past and current tournaments.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';

	// Section 7: Changes to This Policy
	$output .= '<h4 class="wp-block-heading" id="policy-changes">' . esc_html__('7. Changes to This Privacy Policy', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('We may change this Privacy Policy from time to time. If we make changes, we will notify you by revising the date at the top of the policy and, in some cases, we may provide you with additional notice (such as adding a statement to our homepage or sending you a notification).', 'doubles-rotation-tournament') . '</p>';

	return $output;
}

/**
 * Terms of Service
 * @since 1.4.7
 */
function doroto_terms_of_service_page()
{
	$output = "";

	// Introduction
	$output .= '<p>' . esc_html__('Last Revision: [2025-07-23]', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p>' . esc_html__('These Terms of Service ("Terms") govern your access to and use of the Rotation Tournaments plugin and associated mobile application (collectively, "the Service"). By accessing or using the Service, you agree to be bound by these Terms.', 'doubles-rotation-tournament') . '</p>';

	// Section 1: Use of the Service
	$output .= '<h4 class="wp-block-heading" id="service-use">' . esc_html__('1. Use of the Service', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The Service provides a platform for organizing and participating in doubles rotation tournaments. Users can act as organizers or players.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li><strong>' . esc_html__('Organizers:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('Any user can create and manage a tournament. The organizer is responsible for the tournament setup, including its privacy settings.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li><strong>' . esc_html__('Players:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('Players can participate in tournaments, view their progress, and, if enabled by the organizer, submit the results of their own matches.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';

	// Section 2: User Accounts
	$output .= '<h4 class="wp-block-heading" id="user-accounts">' . esc_html__('2. User Accounts', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('To use the Service, you must have a user account on the WordPress website where the plugin is installed. You are responsible for safeguarding your account and for all activities that occur under your account. You agree to provide accurate and complete information when creating and updating your account.', 'doubles-rotation-tournament') . '</p>';

	// Section 3: User Content and Responsibility
	$output .= '<h4 class="wp-block-heading" id="user-content">' . esc_html__('3. User Content and Responsibility', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('You are responsible for the content you post, including your Display Name and any match results you submit. By participating in a tournament, you acknowledge and agree that:', 'doubles-rotation-tournament') . '</p>';
	$output .= '<ul>';
	$output .= '<li>' . esc_html__('Your Display Name and tournament results will be visible to other users, subject to the visibility settings configured by the website administrator and the tournament organizer.', 'doubles-rotation-tournament') . '</li>';
	$output .= '<li>' . esc_html__('You are responsible for your Display Name. While your real name is collected for registration, you can change your Display Name to a nickname for privacy. For more details, see our Privacy Policy.', 'doubles-rotation-tournament') . '</li>';
	$output .= '</ul>';
	$output .= '<p>' . esc_html__('The website administrator and tournament organizers are responsible for the data and content related to their tournaments. The developers of the Rotation Tournaments plugin have no liability for user-generated content or its management.', 'doubles-rotation-tournament') . '</p>';

	// Section 4: Disclaimers
	$output .= '<h4 class="wp-block-heading" id="disclaimers">' . esc_html__('4. Disclaimers', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('The Service is provided "AS IS" without any warranties, express or implied. We do not warrant that the Service will be uninterrupted, secure, or error-free.', 'doubles-rotation-tournament') . '</p>';
	$output .= '<p><strong>' . esc_html__('Data Security:', 'doubles-rotation-tournament') . '</strong> ' . esc_html__('The security of your data depends on the practices of the administrator of the website you are using. The developers of the Rotation Tournaments plugin are not responsible for any data breaches or security incidents that occur on the hosting server.', 'doubles-rotation-tournament') . '</p>';

	// Section 5: Limitation of Liability
	$output .= '<h4 class="wp-block-heading" id="liability-limitation">' . esc_html__('5. Limitation of Liability', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('To the fullest extent permitted by law, the developers of the Rotation Tournaments plugin shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenues, whether incurred directly or indirectly, or any loss of data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the Service.', 'doubles-rotation-tournament') . '</p>';

	// Section 6: Account Termination
	$output .= '<h4 class="wp-block-heading" id="account-termination">' . esc_html__('6. Account Termination', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('You can terminate your account at any time. This action will result in the irreversible loss of your tournament history. We may also suspend or terminate your account if you violate these Terms, at the discretion of the website administrator.', 'doubles-rotation-tournament') . '</p>';

	// Section 7: Changes to Terms
	$output .= '<h4 class="wp-block-heading" id="terms-changes">' . esc_html__('7. Changes to These Terms', 'doubles-rotation-tournament') . '</h4>';
	$output .= '<p>' . esc_html__('We may revise these Terms from time to time. The most current version will always be posted on this page. By continuing to access or use the Service after revisions become effective, you agree to be bound by the revised Terms.', 'doubles-rotation-tournament') . '</p>';

	return $output;
}

/**
 * Creates the Privacy Policy page if it does not already exist.
 *
 * This function checks for a stored page ID in the options table.
 * If the page doesn't exist, it creates it using the content
 * from doroto_privacy_policy_page() and saves the new page ID.
 * @since 1.4.7
 */
function doroto_create_privacy_policy_page()
{
	// Check if the page ID is already stored and if the page exists
	$page_id = intval(get_option('doroto_privacy_policy_page_id'));
	if ($page_id) {
		$page = get_post($page_id);
		// If a valid page object is returned, it means the page exists.
		if ($page) {
			return;
		}
	}

	// Prepare page content and title
	$page_title   = sanitize_text_field(__('Privacy Policy (DoRoTo)', 'doubles-rotation-tournament'));
	$page_content = doroto_privacy_policy_page(); // Assumes this function exists

	// Create the page post object
	$page_data = array(
		'post_title'   => $page_title,
		'post_content' => wp_kses_post($page_content), // Use wp_kses_post for security
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => get_current_user_id(),
	);

	// Insert the page into the database
	$new_page_id = wp_insert_post($page_data);

	// If page creation was successful, save the ID
	if ($new_page_id) {
		update_option('doroto_privacy_policy_page_id', $new_page_id);
	}
}

/**
 * Creates the Terms of Service page if it does not already exist.
 *
 * This function checks for a stored page ID in the options table.
 * If the page doesn't exist, it creates it using the content
 * from doroto_terms_of_service_page() and saves the new page ID.
 * @since 1.4.7
 */
function doroto_create_terms_of_service_page()
{
	// Check if the page ID is already stored and if the page exists
	$page_id = intval(get_option('doroto_terms_of_service_page_id'));
	if ($page_id) {
		$page = get_post($page_id);
		// If a valid page object is returned, it means the page exists.
		if ($page) {
			return;
		}
	}

	// Prepare page content and title
	$page_title   = sanitize_text_field(__('Terms of Service (DoRoTo)', 'doubles-rotation-tournament'));
	$page_content = doroto_terms_of_service_page(); // Assumes this function exists

	// Create the page post object
	$page_data = array(
		'post_title'   => $page_title,
		'post_content' => wp_kses_post($page_content), // Use wp_kses_post for security
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => get_current_user_id(),
	);

	// Insert the page into the database
	$new_page_id = wp_insert_post($page_data);

	// If page creation was successful, save the ID
	if ($new_page_id) {
		update_option('doroto_terms_of_service_page_id', $new_page_id);
	}
}

/**
 * Updates the content of the Privacy Policy page on plugin activation.
 *
 * This function retrieves the stored page ID and updates its title and
 * content with the latest version from the doroto_privacy_policy_page() function.
 * This ensures the page content stays in sync with the plugin version.
 * @since 1.4.7
 */
function doroto_update_privacy_policy_page_on_activation()
{
	// Get the stored page ID
	$page_id = intval(get_option('doroto_privacy_policy_page_id'));

	// Proceed only if we have a valid page ID
	if ($page_id) {
		// Get the latest content and title from your functions
		$page_content = doroto_privacy_policy_page(); // Assumes this function exists
		$page_title = sanitize_text_field(__('Privacy Policy (DoRoTo)', 'doubles-rotation-tournament'));

		// Prepare the data array for the update
		$page_data = array(
			'ID'           => $page_id,
			'post_title'   => $page_title,
			'post_content' => wp_kses_post($page_content), // Sanitize content while preserving allowed HTML
		);

		// Update the post in the database
		wp_update_post($page_data);
	}
}

/**
 * Updates the content of the Terms of Service page on plugin activation.
 *
 * This function retrieves the stored page ID and updates its title and
 * content with the latest version from the doroto_terms_of_service_page() function.
 * This ensures the page content stays in sync with the plugin version.
 * @since 1.4.7
 */
function doroto_update_terms_of_service_page_on_activation()
{
	// Get the stored page ID
	$page_id = intval(get_option('doroto_terms_of_service_page_id'));

	// Proceed only if we have a valid page ID
	if ($page_id) {
		// Get the latest content and title from your functions
		$page_content = doroto_terms_of_service_page(); // Assumes this function exists
		$page_title = sanitize_text_field(__('Terms of Service (DoRoTo)', 'doubles-rotation-tournament'));

		// Prepare the data array for the update
		$page_data = array(
			'ID'           => $page_id,
			'post_title'   => $page_title,
			'post_content' => wp_kses_post($page_content), // Sanitize content while preserving allowed HTML
		);

		// Update the post in the database
		wp_update_post($page_data);
	}
}
