<?php
/**
 * Server render of the "Rotation tournaments list" block.
 * The first page is printed here (readable without JavaScript); filters, search and
 * paging reload it through GET doroto/v1/view-list in view.js.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */
if (!defined('ABSPATH')) {
	exit;
}

// Page that shows the tournaments: chosen in the block, this page when it holds the
// tournament block, otherwise the main tournament page of the plugin.
$doroto_target = intval($attributes['targetPage'] ?? 0);
if ($doroto_target > 0 && get_post_status($doroto_target) === 'publish') {
	$doroto_page_url = get_permalink($doroto_target);
} elseif (is_singular() && has_block('doroto/tournament', get_queried_object())) {
	$doroto_page_url = get_permalink(get_queried_object());
} else {
	$doroto_page_url = doroto_tournament_page_url(0);
}
$doroto_per_page = doroto_view_list_page_size(intval($attributes['perPage'] ?? 0));
$doroto_list = doroto_view_list(0, '', 0, $doroto_per_page, $doroto_page_url);
$doroto_can_create = !empty($attributes['showCreate']) && doroto_service_may_create_tournament();
$doroto_uid = wp_unique_id('doroto-list-');

doroto_block_config();
$doroto_context = [
	'list' => $doroto_list,
	'filter' => 0,
	'search' => '',
	'pageUrl' => $doroto_page_url,
	'perPage' => $doroto_per_page,
	'busy' => false,
	'message' => '',
	'newType' => doroto_default_tournament_type(),
];
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'doroto-list-block']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="doroto/list"
	<?php echo wp_interactivity_data_wp_context($doroto_context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-class--is-busy="context.busy">

	<form class="doroto-list-block__filters" role="search" data-wp-on--submit="actions.search">
		<div class="doroto-list-block__field">
			<label for="<?php echo esc_attr($doroto_uid . '-filter'); ?>"><?php esc_html_e('Show', 'doubles-rotation-tournament'); ?></label>
			<select id="<?php echo esc_attr($doroto_uid . '-filter'); ?>" data-wp-on--change="actions.setFilter">
				<?php foreach (doroto_view_list_filters() as $doroto_value => $doroto_label) : ?>
					<option value="<?php echo esc_attr((string) $doroto_value); ?>"><?php echo esc_html($doroto_label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="doroto-list-block__field">
			<label for="<?php echo esc_attr($doroto_uid . '-search'); ?>"><?php esc_html_e('Search', 'doubles-rotation-tournament'); ?></label>
			<input type="search" id="<?php echo esc_attr($doroto_uid . '-search'); ?>" data-wp-on--input="actions.setSearch">
		</div>
		<button type="submit" class="doroto-list-block__button"><?php esc_html_e('Filter Tournaments', 'doubles-rotation-tournament'); ?></button>
	</form>

	<p class="doroto-list-block__empty" data-wp-bind--hidden="context.list.total" <?php echo $doroto_list['total'] ? 'hidden' : ''; ?>><?php esc_html_e('No tournament matches the filter.', 'doubles-rotation-tournament'); ?></p>

	<ul class="doroto-list-block__items" aria-live="polite">
		<template data-wp-each--item="context.list.rows" data-wp-each-key="context.item.id">
			<li class="doroto-list-block__item">
				<a class="doroto-list-block__name" data-wp-bind--href="context.item.url" data-wp-text="context.item.name"></a>
				<span class="doroto-list-block__state" data-wp-class--is-closed="state.isClosed" data-wp-text="context.item.state_text"></span>
				<span class="doroto-list-block__meta">
					<span data-wp-text="context.item.type_name"></span>
					&middot; <?php esc_html_e('Players', 'doubles-rotation-tournament'); ?>: <span data-wp-text="context.item.players"></span>
					&middot; <span data-wp-text="context.item.date"></span>
				</span>
				<span class="doroto-list-block__tags">
					<span class="doroto-list-block__tag" data-wp-bind--hidden="!context.item.is_player"><?php esc_html_e('I play', 'doubles-rotation-tournament'); ?></span>
					<span class="doroto-list-block__tag" data-wp-bind--hidden="!context.item.is_admin"><?php esc_html_e('I organize', 'doubles-rotation-tournament'); ?></span>
				</span>
			</li>
		</template>
	</ul>

	<nav class="doroto-list-block__paging" aria-label="<?php esc_attr_e('Pages', 'doubles-rotation-tournament'); ?>" data-wp-bind--hidden="!context.list.total" <?php echo $doroto_list['total'] ? '' : 'hidden'; ?>>
		<button type="button" class="doroto-list-block__button" data-wp-on--click="actions.prev" data-wp-bind--disabled="!context.list.has_prev" <?php echo $doroto_list['has_prev'] ? '' : 'disabled'; ?>>&larr; <?php esc_html_e('Previous', 'doubles-rotation-tournament'); ?></button>
		<span data-wp-text="context.list.page_text"><?php echo esc_html($doroto_list['page_text']); ?></span>
		<button type="button" class="doroto-list-block__button" data-wp-on--click="actions.next" data-wp-bind--disabled="!context.list.has_next" <?php echo $doroto_list['has_next'] ? '' : 'disabled'; ?>><?php esc_html_e('Next', 'doubles-rotation-tournament'); ?> &rarr;</button>
	</nav>

	<?php if ($doroto_can_create) : ?>
		<div class="doroto-list-block__create">
			<label for="<?php echo esc_attr($doroto_uid . '-type'); ?>"><?php esc_html_e('Create a new tournament', 'doubles-rotation-tournament'); ?></label>
			<select id="<?php echo esc_attr($doroto_uid . '-type'); ?>" data-wp-on--change="actions.setType">
				<?php foreach (doroto_visible_tournament_types() as $doroto_type => $doroto_type_name) : ?>
					<option value="<?php echo esc_attr((string) $doroto_type); ?>"<?php selected($doroto_type, doroto_default_tournament_type()); ?>><?php echo esc_html($doroto_type_name); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="doroto-list-block__button" data-wp-on--click="actions.create"><?php esc_html_e('Create', 'doubles-rotation-tournament'); ?></button>
		</div>
	<?php endif; ?>

	<p class="doroto-list-block__message" role="status" data-wp-text="context.message" data-wp-bind--hidden="!context.message" hidden></p>
</div>
