<?php
/**
 * Server render of the "Rotation tournament invitation" block.
 * The sign-up button is a plain link (works without JavaScript, guests log in first);
 * view.js only copies the invitation link.
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
$doroto_tournament = $doroto_tid > 0 ? doroto_prepare_tournament($doroto_tid) : null;
if (!$doroto_tournament || !doroto_view_visible($doroto_tournament)) {
	printf(
		'<div %s><p>%s</p></div>',
		get_block_wrapper_attributes(['class' => 'doroto-invite']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__('The tournament was not found.', 'doubles-rotation-tournament')
	);
	return;
}

doroto_block_config();
$doroto_players = array_map('intval', (array) (maybe_unserialize($doroto_tournament->players) ?: []));
$doroto_user = get_current_user_id();
$doroto_open = intval($doroto_tournament->open_registration) === 1;
$doroto_joined = $doroto_user > 0 && in_array($doroto_user, $doroto_players, true);
$doroto_max = intval($doroto_tournament->max_players);
$doroto_full = $doroto_max > 0 && count($doroto_players) >= $doroto_max;
$doroto_join_url = doroto_join_url($doroto_tid);
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'doroto-invite']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="doroto/invite"
	<?php echo wp_interactivity_data_wp_context(['link' => $doroto_join_url, 'message' => '']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h3 class="doroto-invite__name">
		<a href="<?php echo esc_url(doroto_tournament_page_url($doroto_tid)); ?>"><?php echo esc_html($doroto_tournament->name); ?></a>
	</h3>
	<?php if (!empty($attributes['showInvitation']) && trim((string) $doroto_tournament->invitation) !== '') : ?>
		<div class="doroto-invite__text"><?php echo wp_kses((string) $doroto_tournament->invitation, doroto_allowed_html()); ?></div>
	<?php endif; ?>
	<?php if (!empty($attributes['showPlayerCount'])) : ?>
		<p class="doroto-invite__count">
			<?php esc_html_e('Players', 'doubles-rotation-tournament'); ?>: <?php echo esc_html((string) count($doroto_players)); ?>
			<?php if ($doroto_max > 0) : ?>/ <?php echo esc_html((string) $doroto_max); ?><?php endif; ?>
		</p>
	<?php endif; ?>
	<p class="doroto-invite__actions">
		<?php if (!$doroto_open) : ?>
			<span><?php esc_html_e('The registration for the tournament has already been closed.', 'doubles-rotation-tournament'); ?></span>
		<?php elseif ($doroto_joined) : ?>
			<span><?php esc_html_e('You are registered.', 'doubles-rotation-tournament'); ?></span>
		<?php elseif ($doroto_full) : ?>
			<span><?php esc_html_e('The tournament is full.', 'doubles-rotation-tournament'); ?></span>
		<?php else : ?>
			<a class="wp-element-button" href="<?php echo esc_url($doroto_join_url); ?>"><?php esc_html_e('Join the tournament', 'doubles-rotation-tournament'); ?></a>
		<?php endif; ?>
		<button type="button" class="doroto-invite__copy" data-wp-on--click="actions.copy"><?php esc_html_e('Copy the invitation link', 'doubles-rotation-tournament'); ?></button>
	</p>
	<p class="doroto-invite__message" role="status" data-wp-text="context.message" data-wp-bind--hidden="!context.message" hidden></p>
</div>
