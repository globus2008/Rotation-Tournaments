<?php
/**
 * Server render of the "Rotation tournament" block.
 *
 * Everything is printed from the view model (doroto_view_model()) and bound to it with
 * Interactivity API directives, so the page is readable without JavaScript and view.js
 * keeps it up to date. The view model lives in the block context (context.data.view);
 * the store configuration (REST address, nonce, texts) comes from doroto_block_config().
 * No JavaScript is printed here.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */
if (!defined('ABSPATH')) {
	exit;
}

$doroto_tid = intval($attributes['tournamentId'] ?? 0);
if ($doroto_tid <= 0) {
	$doroto_tid = intval(doroto_getTournamentId());
}
$doroto_view = $doroto_tid > 0 ? doroto_view_model($doroto_tid) : null;
if ($doroto_view !== null && intval($doroto_view['user']['level']) === 0 && !doroto_view_visible(doroto_prepare_tournament($doroto_tid))) {
	$doroto_view = null;
}

if ($doroto_view === null) {
	printf(
		'<div %s><p class="doroto-empty">%s</p></div>',
		get_block_wrapper_attributes(['class' => 'doroto-block']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__('The tournament was not found.', 'doubles-rotation-tournament')
	);
	return;
}

doroto_block_config();
$doroto_uid = wp_unique_id('doroto-');
$doroto_flags = $doroto_view['flags'];
$doroto_settings = $doroto_view['settings'] ?? [];

$doroto_context = [
	'data' => ['view' => $doroto_view],
	'ui' => [
		'ready' => false,
		'tab' => 'matches',
		'busy' => false,
		'message' => '',
		'error' => false,
		'drafts' => new stdClass(),
		'skipMode' => false,
		'skip' => [],
		'filter' => 0,
		'edit' => ['number' => 0, 'sides' => ['', ''], 'result_1' => 0, 'result_2' => 0],
		'confirm' => ['text' => '', 'action' => '', 'args' => new stdClass()],
		'stats' => null,
		'statsPlayer' => 0,
		'candidates' => null,
		'organizers' => [],
		'addPlayer' => 0,
		'addAdmin' => 0,
		'newPlayer' => ['first_name' => '', 'last_name' => '', 'email' => ''],
		'final' => ['l1' => 0, 'p1' => 0, 'l2' => 0, 'p2' => 0, 'result_1' => '', 'result_2' => ''],
		'settings' => $doroto_settings ?: new stdClass(),
		'settingsDirty' => false,
		'newPost' => false,
		'newType' => intval(array_key_first($doroto_view['types']) ?? 21),
	],
];

$doroto_tabs = [
	'matches' => __('Matches', 'doubles-rotation-tournament'),
	'results' => __('Results', 'doubles-rotation-tournament'),
	'players' => __('Players', 'doubles-rotation-tournament'),
	'stats' => __('Statistics', 'doubles-rotation-tournament'),
];
if ($doroto_flags['admin']) {
	$doroto_tabs['settings'] = __('Settings', 'doubles-rotation-tournament');
}
// Sections chosen in the block (variations: standings, matches, presentation); settings only for organizers.
$doroto_sections = array_values(array_intersect(
	array_map('strval', (array) ($attributes['sections'] ?? array_keys($doroto_tabs))),
	array_keys($doroto_tabs)
));
if (empty($doroto_sections)) {
	$doroto_sections = ['matches'];
}
$doroto_tabs = array_intersect_key($doroto_tabs, array_flip($doroto_sections));
$doroto_presentation = !empty($attributes['presentation']) ? max(5, intval($attributes['presentationSeconds'] ?? 15)) : 0;
$doroto_context['ui']['tab'] = $doroto_sections[0];
$doroto_context['ui']['sections'] = array_values(array_diff($doroto_sections, ['settings']));
$doroto_context['ui']['presentation'] = $doroto_presentation;
$doroto_context['ui']['fixed'] = intval($attributes['tournamentId'] ?? 0) > 0;
$doroto_context['ui']['presenting'] = false;
$doroto_context['ui']['share'] = ['qr' => false];
$doroto_youtube = (string) doroto_read_settings('youtube_link', '');
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'doroto-block' . (count($doroto_tabs) > 1 ? ' has-tabs' : '') . ($doroto_presentation ? ' is-presentation' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="doroto"
	<?php echo wp_interactivity_data_wp_context($doroto_context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-init="callbacks.init"
	data-wp-class--is-ready="context.ui.ready"
	data-wp-class--is-busy="context.ui.busy"
	data-wp-class--is-presenting="context.ui.presenting">

	<header class="doroto-head" data-help="head">
		<h2 class="doroto-head__title" data-wp-text="context.data.view.name"><?php echo esc_html($doroto_view['name']); ?></h2>
		<p class="doroto-head__meta" data-help="status">
			<span class="doroto-badge" data-wp-bind--hidden="!context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? '' : 'hidden'; ?>><?php esc_html_e('Registration open', 'doubles-rotation-tournament'); ?></span>
			<span class="doroto-badge doroto-badge--running" data-wp-bind--hidden="!context.data.view.flags.running" <?php echo $doroto_flags['running'] ? '' : 'hidden'; ?>><?php esc_html_e('In progress', 'doubles-rotation-tournament'); ?></span>
			<span class="doroto-badge doroto-badge--closed" data-wp-bind--hidden="!context.data.view.flags.closed" <?php echo $doroto_flags['closed'] ? '' : 'hidden'; ?>><?php esc_html_e('Closed', 'doubles-rotation-tournament'); ?></span>
			<span data-wp-text="context.data.view.type_name"><?php echo esc_html($doroto_view['type_name']); ?></span>
			&middot;
			<span><?php esc_html_e('Players', 'doubles-rotation-tournament'); ?>: <span data-wp-text="context.data.view.player_count"><?php echo esc_html((string) $doroto_view['player_count']); ?></span></span>
		</p>
		<p class="doroto-head__organizers" data-help="organizers" data-wp-bind--hidden="!state.organizerNames" <?php echo $doroto_view['organizers'] ? '' : 'hidden'; ?>>
			<?php esc_html_e('Organizer', 'doubles-rotation-tournament'); ?>: <span data-wp-text="state.organizerNames"><?php echo esc_html(implode(', ', $doroto_view['organizers'])); ?></span>
		</p>
		<div class="doroto-progress" data-help="progress" data-wp-bind--hidden="!context.data.view.progress.percent" <?php echo $doroto_view['progress']['percent'] ? '' : 'hidden'; ?>>
			<progress max="100" data-wp-bind--value="context.data.view.progress.percent" value="<?php echo esc_attr((string) intval($doroto_view['progress']['percent'])); ?>"></progress>
			<span><span data-wp-text="context.data.view.progress.percent"><?php echo esc_html((string) intval($doroto_view['progress']['percent'])); ?></span> %</span>
			<span class="doroto-progress__eta" data-wp-bind--hidden="!state.etaText" data-wp-text="state.etaText"></span>
		</div>

		<div class="doroto-actions doroto-head__actions">
			<span class="doroto-actions" data-help="join">
				<a class="wp-element-button doroto-button" href="<?php echo esc_url($doroto_view['links']['join']); ?>"
					data-wp-on--click="actions.join"
					data-wp-bind--hidden="!context.data.view.flags.can_join" <?php echo $doroto_flags['can_join'] ? '' : 'hidden'; ?>>
					<?php esc_html_e('Join the tournament', 'doubles-rotation-tournament'); ?>
				</a>
				<a class="doroto-button doroto-button--secondary" href="<?php echo esc_url($doroto_view['links']['leave'] ?: '#'); ?>"
					data-wp-on--click="actions.leave"
					data-wp-bind--hidden="!context.data.view.flags.can_leave" <?php echo $doroto_flags['can_leave'] ? '' : 'hidden'; ?>>
					<?php esc_html_e('Leave the tournament', 'doubles-rotation-tournament'); ?>
				</a>
				<?php if ($doroto_flags['guest']) : ?>
					<a class="doroto-button doroto-button--secondary" href="<?php echo esc_url($doroto_view['links']['login']); ?>"><?php esc_html_e('Log in', 'doubles-rotation-tournament'); ?></a>
				<?php endif; ?>
			</span>
			<?php // The buttons below need JavaScript, so they appear when the block is ready. ?>
			<button type="button" class="doroto-button doroto-button--secondary" data-help="share" data-wp-on--click="actions.openShare" hidden data-wp-bind--hidden="!context.ui.ready">
				<?php esc_html_e('Share', 'doubles-rotation-tournament'); ?>
			</button>
			<button type="button" class="doroto-button doroto-button--secondary" data-help="present" data-wp-on--click="actions.togglePresentation" hidden data-wp-bind--hidden="!context.ui.ready">
				<span data-wp-bind--hidden="context.ui.presenting"><?php esc_html_e('Presentation', 'doubles-rotation-tournament'); ?></span>
				<span data-wp-bind--hidden="!context.ui.presenting" hidden><?php esc_html_e('End the presentation', 'doubles-rotation-tournament'); ?></span>
			</button>
			<button type="button" class="doroto-button doroto-button--secondary doroto-help-button" data-help="help" data-wp-on--click="actions.openHelp" hidden data-wp-bind--hidden="!context.ui.ready"
				aria-label="<?php esc_attr_e('Help', 'doubles-rotation-tournament'); ?>">?</button>
		</div>
	</header>

	<?php if ($doroto_view['links']['app']) : ?>
		<p class="doroto-app" data-help="app">
			<span aria-hidden="true">&#128241;</span>
			<?php esc_html_e('Follow the tournament and enter results on your phone:', 'doubles-rotation-tournament'); ?>
			<a href="<?php echo esc_url($doroto_view['links']['store']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Rotation Tournaments app for Android', 'doubles-rotation-tournament'); ?></a>
			&middot;
			<a href="<?php echo esc_attr($doroto_view['links']['app']); ?>" rel="nofollow"><strong><?php esc_html_e('Open in the app', 'doubles-rotation-tournament'); ?></strong></a>
		</p>
	<?php endif; ?>

	<p class="doroto-toast" role="status" aria-live="polite"
		data-wp-text="context.ui.message"
		data-wp-class--is-error="context.ui.error"
		data-wp-bind--hidden="!context.ui.message" hidden></p>

	<?php if (count($doroto_tabs) > 1) : ?>
	<div class="doroto-tabs" data-help="tabs" role="tablist" aria-label="<?php esc_attr_e('Tournament', 'doubles-rotation-tournament'); ?>" data-wp-on--keydown="actions.tabKeys">
		<?php foreach ($doroto_tabs as $doroto_key => $doroto_label) : ?>
			<button type="button" role="tab" class="doroto-tab"
				id="<?php echo esc_attr($doroto_uid . '-tab-' . $doroto_key); ?>"
				aria-controls="<?php echo esc_attr($doroto_uid . '-panel-' . $doroto_key); ?>"
				data-tab="<?php echo esc_attr($doroto_key); ?>"
				data-wp-on--click="actions.selectTab"
				data-wp-bind--aria-selected="state.isTabSelected"
				data-wp-bind--tabindex="state.tabIndex"
				data-wp-class--is-active="state.isTabSelected"><?php echo esc_html($doroto_label); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if (in_array('matches', $doroto_sections, true)) : ?>
	<section class="doroto-panel" role="tabpanel" data-tab="matches"
		id="<?php echo esc_attr($doroto_uid . '-panel-matches'); ?>"
		aria-labelledby="<?php echo esc_attr($doroto_uid . '-tab-matches'); ?>"
		data-wp-class--is-active="state.isPanelActive">
		<h3 class="doroto-panel__title" data-help="matches"><?php esc_html_e('Matches', 'doubles-rotation-tournament'); ?></h3>

		<div class="doroto-notice" data-wp-bind--hidden="!context.data.view.flags.notice" <?php echo $doroto_flags['notice'] ? '' : 'hidden'; ?>>
			<p data-wp-text="context.data.view.draw_notice"><?php echo esc_html($doroto_view['draw_notice']); ?></p>
			<p class="doroto-actions" data-wp-bind--hidden="!context.data.view.round_end" <?php echo $doroto_view['round_end'] ? '' : 'hidden'; ?>>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.roundEnd" data-choice="next"><?php esc_html_e('continue to the next round', 'doubles-rotation-tournament'); ?></button>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.roundEnd" data-choice="end"><?php esc_html_e('end the tournament', 'doubles-rotation-tournament'); ?></button>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.roundEnd" data-choice="hide"><?php esc_html_e('hide this message', 'doubles-rotation-tournament'); ?></button>
			</p>
		</div>

		<p class="doroto-notice doroto-notice--info" data-wp-text="context.data.view.courts_note" data-wp-bind--hidden="!context.data.view.courts_note" <?php echo $doroto_view['courts_note'] ? '' : 'hidden'; ?>><?php echo esc_html($doroto_view['courts_note']); ?></p>

		<p class="doroto-empty" data-wp-bind--hidden="!context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? '' : 'hidden'; ?>>
			<?php esc_html_e('Matches are drawn after the organizer closes the registration.', 'doubles-rotation-tournament'); ?>
		</p>
		<p class="doroto-empty" data-wp-bind--hidden="!context.data.view.flags.no_ongoing" <?php echo ($doroto_flags['running'] && !$doroto_flags['has_ongoing']) ? '' : 'hidden'; ?>>
			<?php esc_html_e('No match is being played right now.', 'doubles-rotation-tournament'); ?>
		</p>

		<?php if ($doroto_flags['admin']) : ?>
			<div class="doroto-actions" data-help="skip" data-wp-bind--hidden="!context.data.view.flags.has_ongoing" <?php echo $doroto_flags['has_ongoing'] ? '' : 'hidden'; ?>>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.toggleSkipMode" data-wp-bind--aria-pressed="context.ui.skipMode"><?php esc_html_e('Skip matches', 'doubles-rotation-tournament'); ?></button>
				<button type="button" class="doroto-button" data-wp-on--click="actions.skipSelected" data-wp-bind--hidden="!context.ui.skipMode" hidden><?php esc_html_e('Skip the selected matches', 'doubles-rotation-tournament'); ?></button>
			</div>
		<?php endif; ?>

		<ul class="doroto-cards">
			<template data-wp-each--match="context.data.view.ongoing" data-wp-each-key="context.match.number">
				<li class="doroto-card doroto-match" data-help="match">
					<p class="doroto-match__number"><?php esc_html_e('Match no.', 'doubles-rotation-tournament'); ?> <span data-wp-text="context.match.number"></span></p>
					<div class="doroto-match__sides">
						<span class="doroto-match__side" data-wp-text="context.match.sides.0"></span>
						<span class="doroto-match__vs" aria-hidden="true">&times;</span>
						<span class="doroto-match__side" data-wp-text="context.match.sides.1"></span>
					</div>
					<div class="doroto-score" data-wp-bind--hidden="!context.match.can_enter">
						<label class="screen-reader-text" data-wp-bind--for="state.scoreId1"><?php esc_html_e('Score of the first team', 'doubles-rotation-tournament'); ?></label>
						<input type="number" min="0" max="999" inputmode="numeric" class="doroto-score__input"
							data-wp-bind--id="state.scoreId1" data-side="1"
							data-wp-bind--value="state.draftScore1" data-wp-on--input="actions.setDraft">
						<span aria-hidden="true">:</span>
						<label class="screen-reader-text" data-wp-bind--for="state.scoreId2"><?php esc_html_e('Score of the second team', 'doubles-rotation-tournament'); ?></label>
						<input type="number" min="0" max="999" inputmode="numeric" class="doroto-score__input"
							data-wp-bind--id="state.scoreId2" data-side="2"
							data-wp-bind--value="state.draftScore2" data-wp-on--input="actions.setDraft">
						<button type="button" class="doroto-button" data-wp-on--click="actions.enterResult"><?php esc_html_e('Save', 'doubles-rotation-tournament'); ?></button>
					</div>
					<label class="doroto-check" data-wp-bind--hidden="!context.ui.skipMode" hidden>
						<input type="checkbox" data-wp-bind--checked="state.isSkipSelected" data-wp-on--change="actions.toggleSkip">
						<?php esc_html_e('Skip', 'doubles-rotation-tournament'); ?>
					</label>
				</li>
			</template>
		</ul>

		<div class="doroto-final" data-help="final" data-wp-bind--hidden="!context.data.view.flags.final" <?php echo $doroto_flags['final'] ? '' : 'hidden'; ?>>
			<h4><?php esc_html_e('Final match', 'doubles-rotation-tournament'); ?></h4>
			<p class="doroto-match__sides" data-wp-bind--hidden="!context.data.view.final.chosen">
				<span data-wp-text="context.data.view.final.sides.0"></span>
				<span aria-hidden="true">&times;</span>
				<span data-wp-text="context.data.view.final.sides.1"></span>
				<strong data-wp-bind--hidden="!context.data.view.final.has_result">
					<span data-wp-text="context.data.view.final.result_1"></span>:<span data-wp-text="context.data.view.final.result_2"></span>
				</strong>
			</p>
			<?php if ($doroto_flags['admin']) : ?>
				<fieldset class="doroto-final__choose" data-wp-bind--hidden="!context.data.view.final.can_choose">
					<legend><?php esc_html_e('Save the composition of the final group', 'doubles-rotation-tournament'); ?></legend>
					<?php foreach (['l1' => 'L1', 'p1' => 'R1', 'l2' => 'L2', 'p2' => 'R2'] as $doroto_key => $doroto_label) : ?>
						<label class="doroto-field">
							<span><?php echo esc_html($doroto_label); ?></span>
							<select data-key="<?php echo esc_attr($doroto_key); ?>" data-wp-on--change="actions.setFinalPick">
								<option value="0">&mdash;</option>
								<template data-wp-each--player="context.data.view.standings" data-wp-each-key="context.player.id">
									<option data-wp-bind--value="context.player.id" data-wp-text="context.player.name"></option>
								</template>
							</select>
						</label>
					<?php endforeach; ?>
					<button type="button" class="doroto-button" data-wp-on--click="actions.saveFinalFour"><?php esc_html_e('Save', 'doubles-rotation-tournament'); ?></button>
				</fieldset>
			<?php endif; ?>
			<div class="doroto-score" data-wp-bind--hidden="!context.data.view.final.can_enter">
				<input type="number" min="0" max="999" inputmode="numeric" class="doroto-score__input" data-key="result_1" aria-label="<?php esc_attr_e('Score of the first team', 'doubles-rotation-tournament'); ?>" data-wp-on--input="actions.setFinalPick">
				<span aria-hidden="true">:</span>
				<input type="number" min="0" max="999" inputmode="numeric" class="doroto-score__input" data-key="result_2" aria-label="<?php esc_attr_e('Score of the second team', 'doubles-rotation-tournament'); ?>" data-wp-on--input="actions.setFinalPick">
				<button type="button" class="doroto-button" data-wp-on--click="actions.saveFinalResult"><?php esc_html_e('Save', 'doubles-rotation-tournament'); ?></button>
			</div>
		</div>

		<div class="doroto-winners" data-help="winners" data-wp-bind--hidden="!context.data.view.flags.has_winners" <?php echo $doroto_flags['has_winners'] ? '' : 'hidden'; ?>>
			<h4><?php esc_html_e('Winners', 'doubles-rotation-tournament'); ?></h4>
			<ul>
				<template data-wp-each--player="context.data.view.winners" data-wp-each-key="context.player.id">
					<li><span aria-hidden="true">🏆</span> <span data-wp-text="context.player.name"></span> (<span data-wp-text="context.player.won"></span>:<span data-wp-text="context.player.lost"></span>)</li>
				</template>
			</ul>
		</div>
	</section>

	<?php endif; ?>

	<?php if (in_array('results', $doroto_sections, true)) : ?>
	<section class="doroto-panel" role="tabpanel" data-tab="results"
		id="<?php echo esc_attr($doroto_uid . '-panel-results'); ?>"
		aria-labelledby="<?php echo esc_attr($doroto_uid . '-tab-results'); ?>"
		data-wp-class--is-active="state.isPanelActive">
		<h3 class="doroto-panel__title"><?php esc_html_e('Results', 'doubles-rotation-tournament'); ?></h3>
		<p class="doroto-empty" data-wp-bind--hidden="context.data.view.flags.has_played" <?php echo $doroto_flags['has_played'] ? 'hidden' : ''; ?>><?php esc_html_e('No match has been played yet.', 'doubles-rotation-tournament'); ?></p>
		<div class="doroto-field doroto-field--inline" data-help="filter" data-wp-bind--hidden="!context.data.view.flags.has_played" <?php echo $doroto_flags['has_played'] ? '' : 'hidden'; ?>>
			<label for="<?php echo esc_attr($doroto_uid . '-filter'); ?>"><?php esc_html_e('Player', 'doubles-rotation-tournament'); ?></label>
			<select id="<?php echo esc_attr($doroto_uid . '-filter'); ?>" data-wp-on--change="actions.setFilter">
				<option value="0"><?php esc_html_e('All players', 'doubles-rotation-tournament'); ?></option>
				<template data-wp-each--player="context.data.view.standings" data-wp-each-key="context.player.id">
					<option data-wp-bind--value="context.player.id" data-wp-text="context.player.name"></option>
				</template>
			</select>
		</div>
		<ul class="doroto-list" data-help="results">
			<template data-wp-each--match="context.data.view.played" data-wp-each-key="context.match.number">
				<li class="doroto-result" data-wp-bind--hidden="state.isFilteredOut" data-wp-class--is-skipped="context.match.skipped">
					<span class="doroto-result__number" data-wp-text="context.match.number"></span>
					<span class="doroto-result__side" data-wp-text="context.match.sides.0"></span>
					<span class="doroto-result__score" data-wp-text="context.match.score"></span>
					<span class="doroto-result__side" data-wp-text="context.match.sides.1"></span>
					<button type="button" class="doroto-icon-button" data-wp-on--click="actions.openEdit"
						data-wp-bind--hidden="!context.data.view.results_editable"
						aria-label="<?php esc_attr_e('Edit result', 'doubles-rotation-tournament'); ?>">✎</button>
				</li>
			</template>
		</ul>
	</section>

	<?php endif; ?>

	<?php if (in_array('players', $doroto_sections, true)) : ?>
	<section class="doroto-panel" role="tabpanel" data-tab="players"
		id="<?php echo esc_attr($doroto_uid . '-panel-players'); ?>"
		aria-labelledby="<?php echo esc_attr($doroto_uid . '-tab-players'); ?>"
		data-wp-class--is-active="state.isPanelActive">
		<h3 class="doroto-panel__title"><?php esc_html_e('Players', 'doubles-rotation-tournament'); ?></h3>
		<p class="doroto-empty" data-wp-bind--hidden="context.data.view.flags.has_players" <?php echo $doroto_flags['has_players'] ? 'hidden' : ''; ?>><?php esc_html_e('No one has registered for the tournament yet.', 'doubles-rotation-tournament'); ?></p>
		<div class="doroto-table-wrap" data-help="standings" data-wp-bind--hidden="!context.data.view.flags.has_players" <?php echo $doroto_flags['has_players'] ? '' : 'hidden'; ?>>
			<table class="doroto-standings">
				<thead>
					<tr>
						<th scope="col">#</th>
						<th scope="col"><?php esc_html_e('Player', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-games" title="<?php esc_attr_e('Matches', 'doubles-rotation-tournament'); ?>"><?php esc_html_e('M', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-won"><?php esc_html_e('Won', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-lost"><?php esc_html_e('Lost', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-ratio"><?php esc_html_e('Ratio', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-trend"><?php esc_html_e('Trend', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-paid" data-wp-bind--hidden="!context.data.view.payment_display" <?php echo $doroto_view['payment_display'] ? '' : 'hidden'; ?>><?php esc_html_e('Paid', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="col-rest" title="<?php esc_attr_e('Matches left to the end of the round', 'doubles-rotation-tournament'); ?>" data-wp-bind--hidden="!context.data.view.show_rest" <?php echo $doroto_view['show_rest'] ? '' : 'hidden'; ?>><?php esc_html_e('Residue', 'doubles-rotation-tournament'); ?></th>
						<?php if ($doroto_flags['admin'] || $doroto_flags['can_leave']) : ?>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'doubles-rotation-tournament'); ?></span></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<template data-wp-each--player="context.data.view.standings" data-wp-each-key="context.player.id">
						<tr data-wp-class--is-me="context.player.is_me" data-wp-class--is-special="context.player.special" data-wp-class--is-winner="context.player.winner" data-wp-class--is-suspended="!context.player.active">
							<td data-wp-text="context.player.rank"></td>
							<th scope="row">
								<span data-wp-text="context.player.name"></span>
								<span class="doroto-tag doroto-tag--special" data-wp-bind--hidden="!context.player.special"><?php esc_html_e('special group', 'doubles-rotation-tournament'); ?></span>
								<span class="doroto-tag doroto-tag--muted doroto-tag--suspended" data-wp-bind--hidden="context.player.active"><?php esc_html_e('suspended', 'doubles-rotation-tournament'); ?></span>
								<span class="doroto-tag doroto-tag--winner" data-wp-bind--hidden="!context.player.winner"><?php esc_html_e('winner', 'doubles-rotation-tournament'); ?></span>
							</th>
							<td><span data-wp-text="context.player.games"></span><span data-wp-bind--hidden="!context.player.below_minimum" title="<?php esc_attr_e('Fewer matches than the minimum for the winner', 'doubles-rotation-tournament'); ?>"> !</span></td>
							<td data-wp-text="context.player.won"></td>
							<td data-wp-text="context.player.lost"></td>
							<td data-wp-text="context.player.ratio"></td>
							<td data-wp-text="context.player.trend_text"></td>
							<td data-wp-bind--hidden="!context.data.view.payment_display" data-wp-text="context.player.paid_text"></td>
							<td data-wp-bind--hidden="!context.data.view.show_rest" data-wp-text="context.player.rest"></td>
							<?php if ($doroto_flags['admin'] || $doroto_flags['can_leave']) : ?>
								<td>
									<details class="doroto-menu"<?php echo $doroto_flags['admin'] ? '' : ' data-wp-bind--hidden="!context.player.is_me"'; ?>>
										<summary aria-label="<?php esc_attr_e('Actions', 'doubles-rotation-tournament'); ?>">⋯</summary>
										<div class="doroto-menu__items">
											<button type="button" data-wp-on--click="actions.setActive" data-active="0" data-wp-bind--hidden="!context.player.active"><?php esc_html_e('Suspend', 'doubles-rotation-tournament'); ?></button>
											<button type="button" data-wp-on--click="actions.setActive" data-active="1" data-wp-bind--hidden="context.player.active"><?php esc_html_e('Restore', 'doubles-rotation-tournament'); ?></button>
											<?php if ($doroto_flags['admin']) : ?>
												<button type="button" data-wp-on--click="actions.setPaid" data-paid="1" data-wp-bind--hidden="state.hidePayButton"><?php esc_html_e('Confirm payment', 'doubles-rotation-tournament'); ?></button>
												<button type="button" data-wp-on--click="actions.setPaid" data-paid="0" data-wp-bind--hidden="state.hideUnpayButton"><?php esc_html_e('Remove payment', 'doubles-rotation-tournament'); ?></button>
												<button type="button" data-wp-on--click="actions.setSpecial" data-add="1" data-wp-bind--hidden="context.player.special"><?php esc_html_e('Add to the special group', 'doubles-rotation-tournament'); ?></button>
												<button type="button" data-wp-on--click="actions.setSpecial" data-add="0" data-wp-bind--hidden="!context.player.special"><?php esc_html_e('Remove from the special group', 'doubles-rotation-tournament'); ?></button>
											<?php endif; ?>
											<button type="button" class="is-destructive" data-wp-on--click="actions.removePlayer" data-wp-bind--hidden="state.hideRemoveButton"><?php esc_html_e('Remove from the tournament', 'doubles-rotation-tournament'); ?></button>
										</div>
									</details>
								</td>
							<?php endif; ?>
						</tr>
					</template>
				</tbody>
			</table>
		</div>

		<?php if ($doroto_flags['admin']) : ?>
			<details class="doroto-box" data-help="add-player" data-wp-on--toggle="actions.loadCandidates">
				<summary><?php esc_html_e('Add a player', 'doubles-rotation-tournament'); ?></summary>
				<div class="doroto-field">
					<label for="<?php echo esc_attr($doroto_uid . '-add'); ?>"><?php esc_html_e('Player from the database', 'doubles-rotation-tournament'); ?></label>
					<div class="doroto-row">
						<select id="<?php echo esc_attr($doroto_uid . '-add'); ?>" data-key="addPlayer" data-wp-on--change="actions.setUi">
							<option value="0">&mdash;</option>
							<template data-wp-each--user="state.addableUsers" data-wp-each-key="context.user.id">
								<option data-wp-bind--value="context.user.id" data-wp-text="context.user.name"></option>
							</template>
						</select>
						<button type="button" class="doroto-button" data-wp-on--click="actions.addPlayer"><?php esc_html_e('Add', 'doubles-rotation-tournament'); ?></button>
					</div>
				</div>
				<fieldset class="doroto-fieldset">
					<legend><?php esc_html_e('New player account', 'doubles-rotation-tournament'); ?></legend>
					<?php foreach (['first_name' => __('First name', 'doubles-rotation-tournament'), 'last_name' => __('Last name', 'doubles-rotation-tournament'), 'email' => __('E-mail', 'doubles-rotation-tournament')] as $doroto_key => $doroto_label) : ?>
						<div class="doroto-field">
							<label for="<?php echo esc_attr($doroto_uid . '-new-' . $doroto_key); ?>"><?php echo esc_html($doroto_label); ?></label>
							<input id="<?php echo esc_attr($doroto_uid . '-new-' . $doroto_key); ?>" type="<?php echo 'email' === $doroto_key ? 'email' : 'text'; ?>"
								autocomplete="off" data-key="<?php echo esc_attr($doroto_key); ?>" data-wp-on--input="actions.setNewPlayer"
								data-wp-bind--value="context.ui.newPlayer.<?php echo esc_attr($doroto_key); ?>">
						</div>
					<?php endforeach; ?>
					<p class="doroto-help"><?php esc_html_e('The player gets an e-mail with a link to set the password.', 'doubles-rotation-tournament'); ?></p>
					<button type="button" class="doroto-button" data-wp-on--click="actions.createPlayer"><?php esc_html_e('Create and add', 'doubles-rotation-tournament'); ?></button>
				</fieldset>
			</details>
		<?php endif; ?>
	</section>

	<?php endif; ?>

	<?php if (in_array('stats', $doroto_sections, true)) : ?>
	<section class="doroto-panel" role="tabpanel" data-tab="stats"
		id="<?php echo esc_attr($doroto_uid . '-panel-stats'); ?>"
		aria-labelledby="<?php echo esc_attr($doroto_uid . '-tab-stats'); ?>"
		data-wp-class--is-active="state.isPanelActive">
		<h3 class="doroto-panel__title"><?php esc_html_e('Statistics', 'doubles-rotation-tournament'); ?></h3>
		<div class="doroto-field doroto-field--inline" data-help="stats-player">
			<label for="<?php echo esc_attr($doroto_uid . '-stats'); ?>"><?php esc_html_e('Player', 'doubles-rotation-tournament'); ?></label>
			<select id="<?php echo esc_attr($doroto_uid . '-stats'); ?>" data-wp-on--change="actions.loadStats">
				<option value="0">&mdash;</option>
				<template data-wp-each--player="context.data.view.standings" data-wp-each-key="context.player.id">
					<option data-wp-bind--value="context.player.id" data-wp-text="context.player.name"></option>
				</template>
			</select>
		</div>
		<p class="doroto-help"><?php esc_html_e('How many times the player played with each player as the left or right teammate and against them.', 'doubles-rotation-tournament'); ?></p>
		<div class="doroto-table-wrap" data-help="stats-table" data-wp-bind--hidden="!context.ui.stats" hidden>
			<table class="doroto-standings">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Player', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="stats-left" data-wp-bind--hidden="!context.data.view.doubles"><?php esc_html_e('Teammate left', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="stats-right" data-wp-bind--hidden="!context.data.view.doubles"><?php esc_html_e('Teammate right', 'doubles-rotation-tournament'); ?></th>
						<th scope="col" data-help="stats-opponent"><?php esc_html_e('Opponent', 'doubles-rotation-tournament'); ?></th>
					</tr>
				</thead>
				<tbody>
					<template data-wp-each--row="context.ui.stats.rows" data-wp-each-key="context.row.id">
						<tr>
							<th scope="row" data-wp-text="context.row.name"></th>
							<td data-wp-bind--hidden="!context.data.view.doubles" data-wp-text="context.row.left"></td>
							<td data-wp-bind--hidden="!context.data.view.doubles" data-wp-text="context.row.right"></td>
							<td data-wp-text="context.row.opponent"></td>
						</tr>
					</template>
				</tbody>
			</table>
		</div>
	</section>

	<?php endif; ?>

	<?php if (in_array('settings', $doroto_sections, true)) : ?>
		<section class="doroto-panel" role="tabpanel" data-tab="settings"
			id="<?php echo esc_attr($doroto_uid . '-panel-settings'); ?>"
			aria-labelledby="<?php echo esc_attr($doroto_uid . '-tab-settings'); ?>"
			data-wp-class--is-active="state.isPanelActive">
			<h3 class="doroto-panel__title"><?php esc_html_e('Settings', 'doubles-rotation-tournament'); ?></h3>

			<div class="doroto-box doroto-actions" data-help="settings-state">
				<button type="button" class="doroto-button" data-wp-on--click="actions.toggleRegistration" data-wp-bind--hidden="context.data.view.flags.closed" <?php echo $doroto_flags['closed'] ? 'hidden' : ''; ?>>
					<span data-wp-bind--hidden="!context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? '' : 'hidden'; ?>><?php esc_html_e('Close the registration and start', 'doubles-rotation-tournament'); ?></span>
					<span data-wp-bind--hidden="context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? 'hidden' : ''; ?>><?php esc_html_e('Open the registration again', 'doubles-rotation-tournament'); ?></span>
				</button>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.toggleTournament" data-wp-bind--hidden="context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? 'hidden' : ''; ?>>
					<span data-wp-bind--hidden="context.data.view.flags.closed" <?php echo $doroto_flags['closed'] ? 'hidden' : ''; ?>><?php esc_html_e('End the tournament', 'doubles-rotation-tournament'); ?></span>
					<span data-wp-bind--hidden="!context.data.view.flags.closed" <?php echo $doroto_flags['closed'] ? '' : 'hidden'; ?>><?php esc_html_e('Reopen the tournament', 'doubles-rotation-tournament'); ?></span>
				</button>
			</div>

			<form class="doroto-settings" data-wp-on--submit="actions.saveSettings">
				<?php
				foreach (doroto_block_settings_schema($doroto_view['types']) as $doroto_group) {
					echo '<fieldset class="doroto-fieldset"><legend>' . esc_html($doroto_group['title']) . '</legend>';
					foreach ($doroto_group['fields'] as $doroto_key => $doroto_field) {
						doroto_block_settings_field($doroto_key, $doroto_field, $doroto_settings[$doroto_key] ?? '', $doroto_uid);
					}
					if (isset($doroto_group['fields']['latitude'])) {
						// Leaflet loads when the settings tab opens (view.js); the number fields work without it.
						echo '<div class="doroto-map" data-help="field-map" data-wp-watch="callbacks.watchMap" hidden data-wp-bind--hidden="!context.ui.ready"></div>';
						echo '<p class="doroto-help">' . esc_html__('Click on the map to set the place of the tournament.', 'doubles-rotation-tournament') . '</p>';
					}
					echo '</fieldset>';
				}
				?>
				<?php if ($doroto_view['user']['level'] === 2) : ?>
					<label class="doroto-check">
						<input type="checkbox" data-wp-bind--checked="context.ui.newPost" data-wp-on--change="actions.toggleNewPost">
						<?php esc_html_e('When you check the box, a new post dedicated only to this tournament will be created.', 'doubles-rotation-tournament'); ?>
					</label>
				<?php endif; ?>
				<button type="submit" class="doroto-button" data-help="settings-save"><?php esc_html_e('Save', 'doubles-rotation-tournament'); ?></button>
			</form>

			<details class="doroto-box" data-help="settings-organizers" data-wp-on--toggle="actions.loadCandidates">
				<summary><?php esc_html_e('Organizers', 'doubles-rotation-tournament'); ?></summary>
				<ul class="doroto-list">
					<template data-wp-each--user="context.ui.organizers" data-wp-each-key="context.user.id">
						<li class="doroto-row">
							<span data-wp-text="context.user.name"></span>
							<span class="doroto-tag" data-wp-bind--hidden="!context.user.founder"><?php esc_html_e('founder', 'doubles-rotation-tournament'); ?></span>
							<button type="button" class="doroto-icon-button is-destructive" data-wp-on--click="actions.removeAdmin" data-wp-bind--hidden="state.hideRemoveAdmin" aria-label="<?php esc_attr_e('Remove', 'doubles-rotation-tournament'); ?>">&times;</button>
						</li>
					</template>
				</ul>
				<div class="doroto-row">
					<label class="screen-reader-text" for="<?php echo esc_attr($doroto_uid . '-admin'); ?>"><?php esc_html_e('New organizer', 'doubles-rotation-tournament'); ?></label>
					<select id="<?php echo esc_attr($doroto_uid . '-admin'); ?>" data-key="addAdmin" data-wp-on--change="actions.setUi">
						<option value="0">&mdash;</option>
						<template data-wp-each--user="state.adminCandidates" data-wp-each-key="context.user.id">
							<option data-wp-bind--value="context.user.id" data-wp-text="context.user.name"></option>
						</template>
					</select>
					<button type="button" class="doroto-button" data-wp-on--click="actions.addAdmin"><?php esc_html_e('Add organizer', 'doubles-rotation-tournament'); ?></button>
				</div>
			</details>

			<div class="doroto-box doroto-danger" data-help="settings-danger">
				<h4><?php esc_html_e('Test or Delete the tournament', 'doubles-rotation-tournament'); ?></h4>
				<p class="doroto-help"><?php esc_html_e('Checking the box will delete all match results and put the tournament into open registration. Thanks to this option, you can test the course of the tournament with the option of returning to the default state. (Irreversible change!)', 'doubles-rotation-tournament'); ?></p>
				<div class="doroto-actions">
					<button type="button" class="doroto-button doroto-button--secondary is-destructive" data-wp-on--click="actions.emptyTournament"><?php esc_html_e('Delete all results', 'doubles-rotation-tournament'); ?></button>
					<button type="button" class="doroto-button doroto-button--secondary is-destructive" data-wp-on--click="actions.deleteTournament"><?php esc_html_e('Delete the tournament', 'doubles-rotation-tournament'); ?></button>
				</div>
			</div>

			<?php if ($doroto_view['user']['can_create']) : ?>
				<div class="doroto-box">
					<h4><?php esc_html_e('Create a new tournament', 'doubles-rotation-tournament'); ?></h4>
					<div class="doroto-row">
						<label class="screen-reader-text" for="<?php echo esc_attr($doroto_uid . '-type'); ?>"><?php esc_html_e('Tournament type', 'doubles-rotation-tournament'); ?></label>
						<select id="<?php echo esc_attr($doroto_uid . '-type'); ?>" data-key="newType" data-wp-on--change="actions.setUi">
							<?php foreach ($doroto_view['types'] as $doroto_type => $doroto_type_name) : ?>
								<option value="<?php echo esc_attr((string) $doroto_type); ?>"><?php echo esc_html($doroto_type_name); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="doroto-button" data-wp-on--click="actions.addTournament"><?php esc_html_e('Create', 'doubles-rotation-tournament'); ?></button>
					</div>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<dialog class="doroto-dialog doroto-dialog--edit" aria-labelledby="<?php echo esc_attr($doroto_uid . '-edit-title'); ?>">
		<form method="dialog" data-wp-on--submit="actions.saveEdit">
			<h4 id="<?php echo esc_attr($doroto_uid . '-edit-title'); ?>"><?php esc_html_e('Edit result', 'doubles-rotation-tournament'); ?> <span data-wp-text="context.ui.edit.number"></span></h4>
			<p class="doroto-help"><span data-wp-text="context.ui.edit.sides.0"></span> &times; <span data-wp-text="context.ui.edit.sides.1"></span></p>
			<div class="doroto-score">
				<input type="number" min="0" max="999" class="doroto-score__input" data-key="result_1" aria-label="<?php esc_attr_e('Score of the first team', 'doubles-rotation-tournament'); ?>" data-wp-bind--value="context.ui.edit.result_1" data-wp-on--input="actions.setEdit">
				<span aria-hidden="true">:</span>
				<input type="number" min="0" max="999" class="doroto-score__input" data-key="result_2" aria-label="<?php esc_attr_e('Score of the second team', 'doubles-rotation-tournament'); ?>" data-wp-bind--value="context.ui.edit.result_2" data-wp-on--input="actions.setEdit">
			</div>
			<p class="doroto-help"><?php esc_html_e('0:0 marks the match as skipped.', 'doubles-rotation-tournament'); ?></p>
			<div class="doroto-actions">
				<button type="submit" class="doroto-button"><?php esc_html_e('Save', 'doubles-rotation-tournament'); ?></button>
				<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.closeDialog"><?php esc_html_e('Cancel', 'doubles-rotation-tournament'); ?></button>
			</div>
		</form>
	</dialog>

	<dialog class="doroto-dialog doroto-dialog--share" aria-labelledby="<?php echo esc_attr($doroto_uid . '-share-title'); ?>">
		<h4 id="<?php echo esc_attr($doroto_uid . '-share-title'); ?>"><?php esc_html_e('Share the tournament', 'doubles-rotation-tournament'); ?></h4>
		<div class="doroto-qr" data-wp-bind--hidden="!context.ui.share.qr" hidden></div>
		<p class="doroto-help"><?php esc_html_e('Scan the code with the phone camera or with the Rotation Tournaments app. It opens the tournament in the app, or on this website when the app is not installed.', 'doubles-rotation-tournament'); ?></p>
		<div class="doroto-actions">
			<button type="button" class="doroto-button" data-wp-on--click="actions.copyLink" data-link="<?php echo esc_url($doroto_view['links']['share']); ?>"><?php esc_html_e('Copy the tournament link', 'doubles-rotation-tournament'); ?></button>
			<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.copyLink" data-link="<?php echo esc_url($doroto_view['links']['join']); ?>"
				data-wp-bind--hidden="!context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? '' : 'hidden'; ?>><?php esc_html_e('Copy the invitation link', 'doubles-rotation-tournament'); ?></button>
			<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.closeDialog"><?php esc_html_e('Close', 'doubles-rotation-tournament'); ?></button>
		</div>
		<p class="doroto-help" data-wp-bind--hidden="!context.data.view.flags.registration" <?php echo $doroto_flags['registration'] ? '' : 'hidden'; ?>><?php esc_html_e('The invitation link registers the player who opens it for the tournament.', 'doubles-rotation-tournament'); ?></p>
	</dialog>

	<dialog class="doroto-dialog doroto-dialog--help" aria-labelledby="<?php echo esc_attr($doroto_uid . '-help-title'); ?>">
		<h4 id="<?php echo esc_attr($doroto_uid . '-help-title'); ?>"><?php esc_html_e('Do you need advice?', 'doubles-rotation-tournament'); ?></h4>
		<ul class="doroto-help-menu">
			<?php foreach (doroto_help_menu() as $doroto_key => $doroto_label) : ?>
				<li><button type="button" class="doroto-link-button" data-tour="<?php echo esc_attr((string) $doroto_key); ?>" data-wp-on--click="actions.startTour"><?php echo esc_html($doroto_label); ?></button></li>
			<?php endforeach; ?>
			<?php if ($doroto_youtube !== '') : ?>
				<li><a href="<?php echo esc_url($doroto_youtube); ?>" target="_blank" rel="noopener"><?php esc_html_e('Video help on YouTube', 'doubles-rotation-tournament'); ?></a></li>
			<?php endif; ?>
		</ul>
		<p class="doroto-help"><?php esc_html_e('The examples open the example tournaments of this website. Logged-in users become their organizers, so they can try every setting there.', 'doubles-rotation-tournament'); ?></p>
		<div class="doroto-actions">
			<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.closeDialog"><?php esc_html_e('Close', 'doubles-rotation-tournament'); ?></button>
		</div>
	</dialog>

	<dialog class="doroto-dialog doroto-dialog--confirm" aria-labelledby="<?php echo esc_attr($doroto_uid . '-confirm'); ?>">
		<p id="<?php echo esc_attr($doroto_uid . '-confirm'); ?>" data-wp-text="context.ui.confirm.text"></p>
		<div class="doroto-actions">
			<button type="button" class="doroto-button is-destructive" data-wp-on--click="actions.confirmYes"><?php esc_html_e('Yes', 'doubles-rotation-tournament'); ?></button>
			<button type="button" class="doroto-button doroto-button--secondary" data-wp-on--click="actions.closeDialog"><?php esc_html_e('No', 'doubles-rotation-tournament'); ?></button>
		</div>
	</dialog>
</div>
