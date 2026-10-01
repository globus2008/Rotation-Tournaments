<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<p <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
	<?php esc_html_e('Log link to the tournament – hello from a dynamic block!', 'doubles-rotation-tournament'); ?>
</p>