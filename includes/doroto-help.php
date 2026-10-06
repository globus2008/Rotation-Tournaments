<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Guided tours of the tournament block (2.0).
 *
 * The block has a Help menu: a tour of the current page and four tours that explain the
 * plugin on the example tournaments created by doroto_create_tournament_record():
 *   1. doubles tennis, open registration (special group, payments, a suspended player),
 *   2. doubles padel during the tournament (progress, match cards, standings columns),
 *   3. singles badminton, completed with a final match (winners, suspended players),
 *   4. beach volleyball, completed doubles (special group rules, statistics).
 * The tours replace the Shepherd tours of [doroto_floating_help] (that shortcode stays for
 * old pages). They are data: every step names an element of the block by its data-help
 * anchor, the tab that shows it and the text. The block loads them from GET doroto/v1/help
 * only when a tour starts and runs them with driver.js (src/blocks/tournament/help.js).
 * Texts of the old tours are reused where they still fit, so their translations stay.
 * @since 2.0.0
 */

/**
 * IDs of the example tournaments (0 when missing).
 * @return int[] example number (1-4) => tournament ID
 * @since 2.0.0
 */
function doroto_help_example_ids(): array
{
	$ids = [];
	for ($i = 1; $i <= 4; $i++) {
		$ids[$i] = intval(doroto_read_settings('tournament_example_' . $i, 0));
	}
	return $ids;
}

/**
 * Number of the example (1-4) shown by a tournament, 0 for other tournaments.
 * @since 2.0.0
 */
function doroto_help_example_number(int $tournament_id): int
{
	$number = array_search($tournament_id, doroto_help_example_ids(), true);
	return ($tournament_id > 0 && $number !== false) ? intval($number) : 0;
}

/**
 * Prepare an example tournament for a tour: create the examples when one is missing and
 * make the logged-in user its organizer, so they can try every organizer action there.
 * A closed example gets a fresh close date, so its results and settings stay editable.
 * Creating the examples adds 13 demo users and simulates about 100 draws, so visitors
 * may start it once a minute (as the old AJAX route of the Shepherd tours).
 * @return int|WP_Error tournament ID
 * @since 2.0.0
 */
function doroto_help_prepare_example(int $example, int $user_id)
{
	global $wpdb;
	if ($example < 1 || $example > 4) {
		return doroto_service_error('invalid_example_number', 400);
	}
	$ids = doroto_help_example_ids();
	$missing = false;
	foreach ($ids as $id) {
		if ($id <= 0 || !doroto_prepare_tournament($id)) {
			$missing = true;
		}
	}
	if ($missing) {
		if (get_transient('doroto_example_regenerated') && !current_user_can('manage_options')) {
			return doroto_service_error('help_example_busy', 429);
		}
		set_transient('doroto_example_regenerated', 1, MINUTE_IN_SECONDS);
		doroto_create_tournament_record();
		$ids = doroto_help_example_ids();
	}
	$tournament_id = $ids[$example];
	if ($tournament_id <= 0 || !doroto_prepare_tournament($tournament_id)) {
		return doroto_service_error('example_tournament_not_found', 404);
	}
	if ($user_id <= 0) {
		return $tournament_id;
	}

	doroto_with_tournament_lock($tournament_id, function () use ($wpdb, $tournament_id, $user_id) {
		$tournament = doroto_prepare_tournament($tournament_id);
		$admins = array_map('intval', (array) (maybe_unserialize($tournament->admin_users) ?: []));
		$data = ['last_update' => doroto_now_ms()];
		if (!in_array($user_id, $admins, true)) {
			$admins[] = $user_id;
			$data['admin_users'] = serialize($admins);
		}
		if (intval($tournament->close_tournament) === 1) {
			$data['close_date'] = gmdate('Y-m-d H:i:s');
		}
		$wpdb->update($wpdb->prefix . 'doroto_tournaments', $data, ['id' => $tournament_id]);
	});
	return $tournament_id;
}

/**
 * Titles of the help menu entries.
 * @since 2.0.0
 */
function doroto_help_menu(): array
{
	return [
		'page' => __('Tour of this page', 'doubles-rotation-tournament'),
		'1' => __('Example 1: open registration', 'doubles-rotation-tournament'),
		'2' => __('Example 2: during the tournament', 'doubles-rotation-tournament'),
		'3' => __('Example 3: singles completed', 'doubles-rotation-tournament'),
		'4' => __('Example 4: doubles completed', 'doubles-rotation-tournament'),
	];
}

/**
 * One step of a tour.
 * @param string|null $el CSS selector inside the block, null = a centred popover
 * @param string|null $tab tab to open first
 * @param array $extra 'select' => [selector, value] sets a select first; 'admin' => true only for organizers
 * @since 2.0.0
 */
function doroto_help_step(?string $el, ?string $tab, string $title, string $text, array $extra = []): array
{
	// driver.js puts the texts in as HTML, so they are escaped here (translations are plain text).
	return array_merge(['el' => $el, 'tab' => $tab, 'title' => esc_html($title), 'text' => esc_html($text)], $extra);
}

/**
 * Steps that show the parts of the page every visitor sees, plus the organizer tools.
 * @since 2.0.0
 */
function doroto_help_page_steps(array $view): array
{
	$admin = !empty($view['flags']['admin']);
	$t = __('Tour of this page', 'doubles-rotation-tournament');
	$steps = [
		doroto_help_step('[data-help=head]', null, $t, __('The name of the tournament, its state, the type of the game and the number of players. While the tournament runs, the bar shows its progress and the estimated time to the end.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=organizers]', null, $t, __('The name of the tournament organizer, who can make all the settings, can be found here.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=join]', null, $t, __('For the selected tournament, you can log in or log out here.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=share]', null, $t, __('Share the tournament: a QR code and links for the players. The QR code opens the tournament in the Android app, or on this website when the app is not installed.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=present]', null, $t, __('The presentation shows the matches, the standings and the results one after another on the whole screen. Especially suitable for displaying the running results of the tournament on a larger screen.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=app]', null, $t, __('Follow the tournament and enter results on your phone:', 'doubles-rotation-tournament') . ' ' . __('Rotation Tournaments app for Android', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=tabs]', null, $t, __('The tournament is divided into tabs. The page updates itself every 30 seconds, so you do not need to reload it.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=matches]', 'matches', $t, __('Here, individual matches are drawn in a number corresponding to the selected number of available courts or playing surfaces.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=match] .doroto-score', 'matches', $t, __('Enter the score of both teams and press Save. The next match is drawn right away.', 'doubles-rotation-tournament') . ' ' . __('However, this can only be done by the player playing the game, the tournament organizer, or the website administrator.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=results]', 'results', $t, __('Here you can find a table with all the matches played.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=standings]', 'players', $t, __('The list of registered players and later the current ranking can be found here.', 'doubles-rotation-tournament')),
		doroto_help_step('[data-help=stats-player]', 'stats', $t, __('Choose a player to see how many times they played with each player as a teammate and against them.', 'doubles-rotation-tournament')),
	];
	if ($admin) {
		$steps = array_merge($steps, [
			doroto_help_step('[data-help=standings] .doroto-menu summary', 'players', $t, __('Pausing the game, adding players to the tournament, managing special groups and payments are all done in this section.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=add-player]', 'players', $t, __('Add a player from the player database, or create a new account for a player. The new player gets an e-mail with a link to set the password.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=settings-state]', 'settings', $t, __('If enough players have registered for the tournament, the organizer can close the registration of new players.', 'doubles-rotation-tournament') . ' ' . __('When enough matches have been played, the organizer can end the tournament here.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=field-courts_available]', 'settings', $t, __('Here you can enter the number of courts that are available, edit the name of the tournament and the type of tournament.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=field-average_result]', 'settings', $t, __('Variables that help estimate the length of the tournament.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=field-two_special_group]', 'settings', $t, __('Settings that allow you to create different pairs, such as man + woman, adult + child, etc.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=field-map]', 'settings', $t, __('Click on the map to set the place of the tournament.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=settings-save]', 'settings', $t, __('Changes must be saved using this button. Only the tournament administrator has this permission.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=settings-organizers]', 'settings', $t, __('Administrator privileges can be extended to other people.', 'doubles-rotation-tournament')),
			doroto_help_step('[data-help=settings-danger]', 'settings', $t, __('Checking the box will delete all match results and put the tournament into open registration. Thanks to this option, you can test the course of the tournament with the option of returning to the default state. (Irreversible change!)', 'doubles-rotation-tournament')),
		]);
	}
	$steps[] = doroto_help_step('[data-help=help]', null, $t, __('Click this icon to view help. It will guide you through the various options for how a rotation tournament works and how to set them up.', 'doubles-rotation-tournament'));
	return $steps;
}

/**
 * Steps of an example tour.
 * @since 2.0.0
 */
function doroto_help_example_steps(int $example, array $view): array
{
	$titles = doroto_help_menu();
	$t = $titles[(string) $example];
	$admin = ['admin' => true];
	$steps = [];
	if (empty($view['user']['logged_in'])) {
		$steps[] = doroto_help_step(null, null, $t, __('If you log in to your account and we run this tutorial again, I will grant you tournament organizer rights. If you can try out other options in this trial tournament.', 'doubles-rotation-tournament'));
	}

	// Players of the special group (women in the examples) and the others.
	$special = null;
	$other = null;
	foreach ($view['standings'] as $row) {
		if ($row['special'] && $special === null) {
			$special = $row;
		}
		if (!$row['special'] && $other === null) {
			$other = $row;
		}
	}

	switch ($example) {
		case 1:
			$steps = array_merge($steps, [
				doroto_help_step('[data-help=head]', null, $t, __('Registration for the tournament is still open.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=standings]', 'players', $t, __('13 world-class tennis players, 7 men and 6 women, have registered for this tournament.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=join]', null, $t, __('You can register for this tournament also via this link.', 'doubles-rotation-tournament') . ' ' . __('This link will only be available to other players when player registration is open.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=share]', null, $t, __('Share the tournament: a QR code and links for the players. The QR code opens the tournament in the Android app, or on this website when the app is not installed.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=organizers]', null, $t, __('The name of the tournament organizer, who can make all the settings, can be found here.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=standings] .doroto-tag--special:not([hidden])', 'players', $t, __('As a tournament organizer, you can place selected players in a special group. For example, here all women are in a special group. The name of the player in the special group will be highlighted in color.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=standings] .doroto-tag--suspended:not([hidden])', 'players', $t, __('Players can interrupt the game at any time during the tournament and will not be placed in matches during the interruption. Here the game was interrupted by the 4th player.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-paid]', 'players', $t, __('The tournament organizer can keep statistics on entry fee payments. Here, the 2nd, 4th, and 6th players paid.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=standings] .doroto-menu summary', 'players', $t, __('Pausing the game, adding players to the tournament, managing special groups and payments are all done in this section.', 'doubles-rotation-tournament') . ' ' . __('The administrator can pause and resume the game of any player. A player can only change himself. The interruption of the game by a player is shown in the table above.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=add-player]', 'players', $t, __('Here, the tournament organizer can add players to the tournament from the player database individually, even during the tournament. For reasons of personal data protection, access to the database may be restricted by the website administrator.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=settings-state]', 'settings', $t, __('If enough players have registered for the tournament, the organizer can close the registration of new players.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=field-two_special_group]', 'settings', $t, __('Settings that allow you to create different pairs, such as man + woman, adult + child, etc.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=field-payment_display]', 'settings', $t, __('If the tournament organizer records entry fee payments, the information about received payments is processed here.', 'doubles-rotation-tournament'), $admin),
			]);
			break;

		case 2:
			$steps = array_merge($steps, [
				doroto_help_step('[data-help=progress]', null, $t, __('However, if you request the end of the tournament round, an estimated time for the tournament will be displayed.', 'doubles-rotation-tournament') . ' ' . __('The estimate uses the average match result and the number of games played per hour from the settings.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=matches]', 'matches', $t, __('Here, individual matches are drawn in a number corresponding to the selected number of available courts or playing surfaces.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=match] .doroto-match__number', 'matches', $t, __('Every match has its number. You will find it in the results and in the statistics.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=match] .doroto-match__sides', 'matches', $t, __('Team 1 is on the left, team 2 on the right. In each team, the first player plays on the left side of the court and the second one on the right side. The right player of team 1 starts the game by serving.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=match] .doroto-score', 'matches', $t, __('Field for entering game results.', 'doubles-rotation-tournament') . ' ' . __('Button to save results. Pressing this button will draw the next match according to the settings.', 'doubles-rotation-tournament') . ' ' . __('However, this can only be done by the player playing the game, the tournament organizer, or the website administrator.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=skip]', 'matches', $t, __('The organizer can skip several matches at once, for example when players have left. The next matches are drawn from all free players.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=col-games]', 'players', $t, __("Each player has played 3 or 4 matches so far. For those who have only played 3 matches, a '!' appears after the number, indicating that the player has not yet played enough matches to be declared the winner.", 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-won]', 'players', $t, __('For each player, we will find the total number of games won, or points in another type of game.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-lost]', 'players', $t, __('Similarly, each player has a total of all lost games or points.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-ratio]', 'players', $t, __('The ratio between games won and lost (points) determines the overall ranking among players.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-trend]', 'players', $t, __('The trend shows how many matches in a row ended in a win or loss.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-rest]', 'players', $t, __('If the goal of the tournament is to close the round, the remaining number of matches for each player is displayed here.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=field-announce_round_end]', 'settings', $t, __('In the settings you can choose whether the tournament targets the end of the round and whether the service rotation is taken into account. The end of the round does not have to be tracked according to the settings.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=field-minimum_matches]', 'settings', $t, __('The required number of matches played for a player to be declared the winner is set here.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=field-courts_available]', 'settings', $t, __('Here you can enter the number of courts that are available, edit the name of the tournament and the type of tournament.', 'doubles-rotation-tournament'), $admin),
			]);
			break;

		case 3:
			$steps = array_merge($steps, [
				doroto_help_step('[data-help=standings]', 'players', $t, __('The 12 world-famous tennis players from the previous examples decided to play singles badminton, regardless of gender.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=winners]', 'matches', $t, __('At the same time, the winner in the best player of the tournament category was announced.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=final]', 'matches', $t, __('The tournament has already ended with the announcement of the winners in the most ideal couple category.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-games]', 'players', $t, __('The overview shows that the players played a different number of matches due to the fact that Iga Swiatek and Novak Djokovic decided to interrupt the tournament sometime in the middle.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=standings] .doroto-tag--suspended:not([hidden])', 'players', $t, __('The label "suspended" next to the names of Iga Swiatek and Novak Djokovic shows that their game is still suspended.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-ratio]', 'players', $t, __('It is important to understand that the total number of matches played does not affect the ranking. What matters is the ratio of points won and lost.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-rest]', 'players', $t, __('It is also not necessary to play all the matches to complete the round. Perhaps Iga Swiatek and Novak Djokovic will not be coming and so the round-robin game could not be completed.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=field-temp_suspend_winner]', 'settings', $t, __('To prevent an absent player from being declared the winner, this option can be disabled.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=results]', 'results', $t, __('Here you can find a table with all the matches played.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=filter]', 'results', $t, __('Results can be filtered by player.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=results] .doroto-icon-button:not([hidden])', 'results', $t, __('If someone incorrectly enters the result of a match, the tournament organizer can correct the result.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=stats-player]', 'stats', $t, __('Choose a player to see how many times they played with each player as a teammate and against them.', 'doubles-rotation-tournament')),
			]);
			break;

		case 4:
			$steps = array_merge($steps, [
				doroto_help_step('[data-help=standings]', 'players', $t, __('Tennis players, 3 men and 3 women, decided to play beach volleyball.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=head]', null, $t, __('The tournament has already been closed because the tournament round has ended.', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=winners]', 'matches', $t, __('The players of the special group have their own winner in this tournament (setting "The winner of the tournament will be").', 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=col-games]', 'players', $t, __("Now let's look at the number of matches played by individual players. There are significant differences. These are caused by the special group settings.", 'doubles-rotation-tournament')),
				doroto_help_step('[data-help=field-two_special_group]', 'settings', $t, __('This setting determines that there will be no 2 from the special group in the pair. That is, no woman + woman pairs will be created.', 'doubles-rotation-tournament'), $admin),
				doroto_help_step('[data-help=field-two_out_group]', 'settings', $t, __('Another setting allows you to create pairs between members outside of special groups. In our case, you can create male + male pairs.', 'doubles-rotation-tournament'), $admin),
			]);
			if ($special) {
				$steps = array_merge($steps, [
					doroto_help_step('[data-help=filter]', 'results', $t, __('One of the female players was randomly selected and a filter was set based on that.', 'doubles-rotation-tournament') . ' (' . $special['name'] . ')', ['select' => ['[data-help=filter] select', $special['id']]]),
					doroto_help_step('[data-help=results]', 'results', $t, __("Let's look at the matches played by a selected woman from a special group. She actually played matches only with male colleagues, but the opponent could be not only a man + woman group, but also a man + man.", 'doubles-rotation-tournament')),
					doroto_help_step('[data-help=stats-table]', 'stats', $t, __("Here are the statistics of this woman's games played.", 'doubles-rotation-tournament') . ' ' . __("The statistical data can be read line by line. The player's name is at the beginning.", 'doubles-rotation-tournament'), ['select' => ['[data-help=stats-player] select', $special['id']]]),
					doroto_help_step('[data-help=stats-left]', 'stats', $t, __('This column reports the number of matches played where the player listed in this row was a teammate of the selected woman on the left.', 'doubles-rotation-tournament')),
					doroto_help_step('[data-help=stats-right]', 'stats', $t, __('This column reports the number of matches played where the player listed in this row was a teammate of the selected woman on the right.', 'doubles-rotation-tournament')),
					doroto_help_step('[data-help=stats-opponent]', 'stats', $t, __('This column reports the number of matches played where the player listed in this row was in the opposing position.', 'doubles-rotation-tournament')),
					doroto_help_step('[data-help=stats-table]', 'stats', $t, __('This table shows that the woman who was actually selected did not pair up with another woman, but only with men. At the same time, she played one match with each man.', 'doubles-rotation-tournament') . ' ' . __('Thus, the tournament round was closed, from which we required everyone to play with everyone, provided that the settings allowed it.', 'doubles-rotation-tournament')),
				]);
			}
			$steps[] = doroto_help_step('[data-help=col-rest]', 'players', $t, __('The end of a tournament round can be recognized when the players have no matches left. However, in doubles, a situation is also allowed where a maximum of 3 players have 1 match left. In this case, it is not possible to find 4 players who have not played together before.', 'doubles-rotation-tournament'));
			if ($other) {
				$steps = array_merge($steps, [
					doroto_help_step('[data-help=filter]', 'results', $t, __("Let's set the filter to one of the men.", 'doubles-rotation-tournament') . ' (' . $other['name'] . ')', ['select' => ['[data-help=filter] select', $other['id']]]),
					doroto_help_step('[data-help=results]', 'results', $t, __('Unlike players from the special group (meaning women here), men played 2 more matches.', 'doubles-rotation-tournament')),
					doroto_help_step('[data-help=stats-table]', 'stats', $t, __('Looking at the game statistics of the selected man, we see that he actually played with every player. Moreover, the system ensured an even rotation of serve.', 'doubles-rotation-tournament'), ['select' => ['[data-help=stats-player] select', $other['id']]]),
				]);
			}
			$steps[] = doroto_help_step('[data-help=field-announce_round_end]', 'settings', $t, __('Do you know how many more matches would have to be played to ensure a rotation of serve? That is, each pair would play each other twice to alternate serving? Try changing this parameter and see.', 'doubles-rotation-tournament'), $admin);
			break;
	}
	return $steps;
}

/**
 * REST API of the tours.
 * GET  doroto/v1/help/<id>?tour=page|example  steps of a tour for this tournament and user
 * POST doroto/v1/help-example {example: 1-4}   prepare an example, answer its page address
 * Both accept guests; the website login comes with the REST nonce (cookie).
 * @since 2.0.0
 */
add_action('rest_api_init', function () {
	register_rest_route('doroto/v1', '/help/(?P<id>\d+)', [
		'methods' => 'GET',
		'callback' => 'doroto_rest_help_tour',
		'permission_callback' => '__return_true',
	]);
	register_rest_route('doroto/v1', '/help-example', [
		'methods' => 'POST',
		'callback' => 'doroto_rest_help_example',
		'permission_callback' => '__return_true',
	]);
});

function doroto_rest_help_tour(WP_REST_Request $request)
{
	wp_set_current_user(doroto_get_current_user_id_from_token());
	$tournament_id = intval($request['id']);
	$tournament = doroto_prepare_tournament($tournament_id);
	if (!$tournament || !doroto_view_visible($tournament)) {
		return new WP_REST_Response(['error_code' => 'tournament_not_found'], 404);
	}
	$view = doroto_view_model($tournament_id);
	$example = doroto_help_example_number($tournament_id);
	$steps = ($request->get_param('tour') === 'example' && $example > 0)
		? doroto_help_example_steps($example, $view)
		: doroto_help_page_steps($view);
	if (empty($view['flags']['admin'])) {
		$steps = array_values(array_filter($steps, function ($step) {
			return empty($step['admin']);
		}));
	}
	return new WP_REST_Response(['steps' => $steps], 200);
}

function doroto_rest_help_example(WP_REST_Request $request)
{
	$user_id = doroto_get_current_user_id_from_token();
	wp_set_current_user($user_id);
	$result = doroto_help_prepare_example(intval($request->get_param('example')), $user_id);
	if (is_wp_error($result)) {
		return doroto_service_rest_response($result);
	}
	return new WP_REST_Response([
		'success' => true,
		'tournament_id' => $result,
		'url' => add_query_arg('doroto_tour', 'example', doroto_tournament_page_url($result)),
	], 200);
}
