<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Register the blocks built from src/blocks into build/blocks (npm run build).
 * Every block folder with a block.json is registered; its scripts and styles load
 * only on pages that contain the block.
 * @since 2.0.0
 */
function doroto_register_blocks()
{
	$dir = plugin_dir_path(dirname(__FILE__)) . 'build/blocks';
	foreach ((array) glob($dir . '/*/block.json') as $metadata) {
		$block = register_block_type(dirname($metadata));
		if ($block && !empty($block->editor_script_handles)) {
			foreach ($block->editor_script_handles as $handle) {
				wp_set_script_translations($handle, 'doubles-rotation-tournament');
			}
		}
	}
}
add_action('init', 'doroto_register_blocks');
