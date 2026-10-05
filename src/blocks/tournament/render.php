<?php
/**
 * Server render of the "Rotation tournament" block.
 * The view model goes to the Interactivity API state (JSON printed by WordPress core),
 * all behaviour lives in view.js.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */
if (!defined('ABSPATH')) {
	exit;
}

$doroto_tournament_id = intval($attributes['tournamentId'] ?? 0);
if ($doroto_tournament_id <= 0) {
	$doroto_tournament_id = intval(doroto_getTournamentId());
}
$doroto_view = $doroto_tournament_id > 0 ? doroto_view_model($doroto_tournament_id) : null;
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'doroto-block']); ?>
	data-wp-interactive="doroto"
	<?php echo wp_interactivity_data_wp_context(['tournamentId' => $doroto_tournament_id]); ?>>
	<?php if ($doroto_view === null) : ?>
		<p><?php esc_html_e('The tournament was not found.', 'doubles-rotation-tournament'); ?></p>
	<?php else : ?>
		<h2 class="doroto-block__title"><?php echo esc_html($doroto_view['name']); ?></h2>
	<?php endif; ?>
</div>
