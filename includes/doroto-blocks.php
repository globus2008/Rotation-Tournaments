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

/**
 * Configuration of the "doroto" Interactivity API store (REST address, nonce, texts).
 * Printed once per page by WordPress core as JSON.
 * @since 2.0.0
 */
function doroto_block_config()
{
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;
	wp_interactivity_config('doroto', [
		'restUrl' => esc_url_raw(rest_url()),
		'nonce' => wp_create_nonce('wp_rest'),
		'pollSeconds' => 30,
		// Block themes style buttons through .wp-element-button (theme.json); classic themes style every button.
		'blockTheme' => function_exists('wp_is_block_theme') && wp_is_block_theme(),
		// Presentation started by the button: seconds per section (admin setting of the old presentation).
		'presentationSeconds' => max(5, intval(doroto_read_settings('show_next_seconds', 15))),
		// The map of the settings tab loads Leaflet on demand; its stylesheet is the plugin's copy.
		'leafletCss' => plugins_url('assets/css/leaflet.css', dirname(__FILE__)),
		'mapCenter' => [floatval(doroto_read_settings('latitude', 50)), floatval(doroto_read_settings('longitude', 15))],
		'i18n' => [
			'networkError' => __('The server could not be reached. Please try again.', 'doubles-rotation-tournament'),
			'confirmRemovePlayer' => __('Remove this player from the tournament?', 'doubles-rotation-tournament'),
			'confirmDelete' => __('Delete the tournament? This cannot be undone.', 'doubles-rotation-tournament'),
			'confirmEmpty' => __('Delete all match results and open the registration again? This cannot be undone.', 'doubles-rotation-tournament'),
			'confirmEnd' => __('End the tournament?', 'doubles-rotation-tournament'),
			'confirmSkip' => __('Skip the selected matches?', 'doubles-rotation-tournament'),
			'confirmRemoveAdmin' => __('Take the organizer rights from this user?', 'doubles-rotation-tournament'),
			'linkCopied' => __('The link was copied.', 'doubles-rotation-tournament'),
			'invalidScore' => __('Enter the score of both teams.', 'doubles-rotation-tournament'),
			'updated' => __('The tournament was updated.', 'doubles-rotation-tournament'),
			'deleted' => __('The tournament was deleted.', 'doubles-rotation-tournament'),
			'tourNext' => __('Next', 'doubles-rotation-tournament'),
			'tourPrev' => __('Back', 'doubles-rotation-tournament'),
			'tourDone' => __('Close', 'doubles-rotation-tournament'),
			'helpBusy' => __('The example tournaments are being prepared. Please try again in a minute.', 'doubles-rotation-tournament'),
		],
	]);
}

/**
 * A colour picked in the block editor, safe for an inline style: a hex value, rgb()/hsl()
 * or a theme preset variable. Anything else gives '' (the default of the stylesheet).
 * @since 2.0.0
 */
function doroto_block_color($value): string
{
	$value = trim((string) $value);
	if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)
		|| preg_match('/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)$/i', $value)
		|| preg_match('/^var\(--wp--preset--color--[a-z0-9-]+\)$/i', $value)) {
		return $value;
	}
	return '';
}

/**
 * Readable text colour (black or white) on a hex background, '' for other values.
 * @since 2.0.0
 */
function doroto_block_text_on(string $color): string
{
	if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})([0-9a-f]{2})?$/i', $color, $m)) {
		return '';
	}
	$hex = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];
	$channels = array_map(function ($part) {
		$c = hexdec($part) / 255;
		return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
	}, str_split($hex, 2));
	$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	// Contrast against white vs. black; pick the larger one.
	return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#000000';
}

/**
 * Highlight colours of the active theme as custom properties for the tournament block:
 * the theme's button colours (accent and its text) and a second palette colour for the
 * special group. Kept as preset variables, so a style variation of the theme applies too.
 * Classic themes without theme.json give nothing and the stylesheet falls back to presets.
 * @since 2.0.0
 */
function doroto_block_theme_colors(): string
{
	static $style = null;
	if ($style !== null) {
		return $style;
	}
	$style = '';
	if (!function_exists('wp_get_global_styles') || !wp_theme_has_theme_json()) {
		return $style;
	}
	$button = (array) wp_get_global_styles(['elements', 'button', 'color']);
	$vars = [
		'--doroto-theme-accent' => $button['background'] ?? '',
		'--doroto-theme-on-accent' => $button['text'] ?? '',
	];
	// Second highlight colour of the palette (names used by the default themes).
	$slugs = [];
	foreach ((array) wp_get_global_settings(['color', 'palette', 'theme']) as $color) {
		$slugs[] = (string) ($color['slug'] ?? '');
	}
	foreach (['secondary', 'accent-2', 'accent', 'tertiary'] as $slug) {
		if (in_array($slug, $slugs, true) && strpos((string) $vars['--doroto-theme-accent'], '--' . $slug . ')') === false) {
			$vars['--doroto-theme-special'] = 'var(--wp--preset--color--' . $slug . ')';
			break;
		}
	}
	foreach ($vars as $var => $value) {
		$value = doroto_block_color($value);
		if ($value !== '') {
			$style .= $var . ':' . $value . ';';
		}
	}
	return $style;
}

/**
 * Inline style of a block wrapper: highlight colours of the theme, then the colours chosen
 * in the Styles panel of the block (accentColor, tabsBackground, tabsTextColor, specialColor),
 * which override them. Text on a chosen accent / bar colour is black or white unless chosen too.
 * @since 2.0.0
 */
function doroto_block_color_style(array $attributes): string
{
	$style = doroto_block_theme_colors();
	$colors = [];
	foreach (['accentColor', 'tabsBackground', 'tabsTextColor', 'specialColor'] as $attr) {
		$colors[$attr] = doroto_block_color($attributes[$attr] ?? '');
	}
	if ($colors['tabsTextColor'] === '') {
		$colors['tabsTextColor'] = doroto_block_text_on($colors['tabsBackground']);
	}
	foreach ([
		'--doroto-accent-custom' => $colors['accentColor'],
		'--doroto-on-accent-custom' => doroto_block_text_on($colors['accentColor']),
		'--doroto-tabs-bg-custom' => $colors['tabsBackground'],
		'--doroto-tabs-fg-custom' => $colors['tabsTextColor'],
		'--doroto-special-custom' => $colors['specialColor'],
	] as $var => $color) {
		if ($color !== '') {
			$style .= $var . ':' . $color . ';';
		}
	}
	return $style;
}

/**
 * Fields of the settings tab, grouped as in the old settings form (its texts are translated).
 * type: text | number | select | textarea; options: value => label.
 * @since 2.0.0
 */
function doroto_block_settings_schema(array $types): array
{
	$no_yes = ['0' => __('No', 'doubles-rotation-tournament'), '1' => __('Yes', 'doubles-rotation-tournament')];
	return [
		[
			'title' => __('Name and type, number of courts', 'doubles-rotation-tournament'),
			'fields' => [
				'name' => ['type' => 'text', 'label' => __('The name of the tournament can be edited here.', 'doubles-rotation-tournament')],
				'tournament_type' => ['type' => 'select', 'options' => $types, 'label' => __("You can change the tournament type here, if the tournament hasn't started yet.", 'doubles-rotation-tournament')],
				'courts_available' => ['type' => 'number', 'min' => 1, 'max' => 10, 'label' => __('Number of courts available for the tournament', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Match result options', 'doubles-rotation-tournament'),
			'fields' => [
				'average_result' => ['type' => 'number', 'min' => 1, 'max' => 100, 'label' => __('Average match result.', 'doubles-rotation-tournament'), 'help' => __('If you play tennis and your matches end with an average score of 6:4, then enter a value of 10.', 'doubles-rotation-tournament')],
				'announce_round_end' => ['type' => 'select', 'label' => __('After the end of the entire tournament round, show the offer, what to do next?', 'doubles-rotation-tournament'), 'options' => [
					'0' => __('No, do not announce the end of the round.', 'doubles-rotation-tournament'),
					'1' => __("Yes, but don't consider service rotation.", 'doubles-rotation-tournament'),
					'2' => __('Yes, and ensure service rotation.', 'doubles-rotation-tournament'),
				]],
				'games_hour' => ['type' => 'number', 'min' => 1, 'max' => 1000, 'label' => __('How many games (points) are played per hour on one court (table)?', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Player rights', 'doubles-rotation-tournament'),
			'fields' => [
				'whole_names' => ['type' => 'select', 'label' => __('This option determines whether player names will be displayed in full or if they will be obfuscated to protect personal data.', 'doubles-rotation-tournament'), 'options' => [
					'0' => __('Abbreviated names', 'doubles-rotation-tournament'),
					'1' => __('Full names', 'doubles-rotation-tournament'),
				]],
				'max_players' => ['type' => 'number', 'min' => 0, 'max' => 99, 'label' => __('Maximum number of registered players.', 'doubles-rotation-tournament'), 'help' => '0 = ' . __('No limit', 'doubles-rotation-tournament')],
				'allow_input_results' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Can players independently enter game results?', 'doubles-rotation-tournament')],
				'visibility' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Can other players see your tournament?', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Special group of players', 'doubles-rotation-tournament'),
			'fields' => [
				'two_special_group' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Skip matches where 2 players would play together in a special group?', 'doubles-rotation-tournament')],
				'two_out_group' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Skip matches where 2 non-special group players would play together?', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Winner', 'doubles-rotation-tournament'),
			'fields' => [
				'special_group_can_win' => ['type' => 'select', 'label' => __('The winner of the tournament will be:', 'doubles-rotation-tournament'), 'options' => [
					'0' => __('1 outside the special group', 'doubles-rotation-tournament'),
					'1' => __('1 of all players', 'doubles-rotation-tournament'),
					'2' => __('2 separately (1 from the special group and 1 outside the special group)', 'doubles-rotation-tournament'),
				]],
				'minimum_matches' => ['type' => 'number', 'min' => 1, 'max' => 10, 'label' => __('The minimum number of matches a player must play to be declared the winner.', 'doubles-rotation-tournament')],
				'temp_suspend_winner' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Can a player who is currently suspended be declared the winner?', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Other settings', 'doubles-rotation-tournament'),
			'fields' => [
				'min_not_playing' => ['type' => 'select', 'label' => __('When enough players are available:', 'doubles-rotation-tournament'), 'help' => __('This option reduces variability.', 'doubles-rotation-tournament'), 'options' => [
					'0' => __('Wait to draw until all matches have been played.', 'doubles-rotation-tournament'),
					'1' => __('Draw a next match immediately.', 'doubles-rotation-tournament'),
				]],
				'play_final_match' => ['type' => 'select', 'options' => $no_yes, 'label' => __('After the tournament closes, allow a final match to determine the best pair?', 'doubles-rotation-tournament')],
				'payment_display' => ['type' => 'select', 'options' => $no_yes, 'label' => __('Show control over the paid entry fee?', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Location', 'doubles-rotation-tournament'),
			'fields' => [
				'latitude' => ['type' => 'number', 'step' => 'any', 'min' => -90, 'max' => 90, 'label' => __('Latitude', 'doubles-rotation-tournament')],
				'longitude' => ['type' => 'number', 'step' => 'any', 'min' => -180, 'max' => 180, 'label' => __('Longitude', 'doubles-rotation-tournament')],
			],
		],
		[
			'title' => __('Post about the tournament', 'doubles-rotation-tournament'),
			'fields' => [
				'invitation' => ['type' => 'textarea', 'label' => __('Welcome text for the tournament. It will be inserted into a new post. HTML tags can be used.', 'doubles-rotation-tournament')],
			],
		],
	];
}

/**
 * Print one field of the settings tab. The value is bound to context.ui.settings.<key>;
 * the server render shows the stored value.
 * @since 2.0.0
 */
function doroto_block_settings_field(string $key, array $field, $value, string $id_prefix)
{
	$id = $id_prefix . '-' . $key;
	$bind = 'context.ui.settings.' . $key;
	echo '<div class="doroto-field" data-help="field-' . esc_attr($key) . '">';
	echo '<label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label>';
	$common = ' id="' . esc_attr($id) . '" data-key="' . esc_attr($key) . '" data-wp-on--change="actions.setSetting"';
	if ($field['type'] === 'select') {
		echo '<select' . $common . ' data-wp-bind--value="' . esc_attr($bind) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		foreach ($field['options'] as $option => $label) {
			echo '<option value="' . esc_attr($option) . '"' . selected((string) $option, (string) $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	} elseif ($field['type'] === 'textarea') {
		echo '<textarea' . $common . ' rows="5" data-wp-on--input="actions.setSetting" data-wp-bind--value="' . esc_attr($bind) . '">' . esc_textarea((string) $value) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		$extra = '';
		foreach (['min', 'max', 'step'] as $attr) {
			if (isset($field[$attr])) {
				$extra .= ' ' . $attr . '="' . esc_attr((string) $field[$attr]) . '"';
			}
		}
		echo '<input type="' . esc_attr($field['type']) . '"' . $common . $extra . ' data-wp-on--input="actions.setSetting" value="' . esc_attr((string) $value) . '" data-wp-bind--value="' . esc_attr($bind) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if (!empty($field['help'])) {
		echo '<p class="doroto-help">' . esc_html($field['help']) . '</p>';
	}
	echo '</div>';
}

/**
 * Content of the main page built from the 2.0 blocks.
 * @since 2.0.0
 */
function doroto_main_page_blocks(): string
{
	return "<!-- wp:doroto/tournament /-->\n\n"
		. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html__('Tournament Selection', 'doubles-rotation-tournament') . "</h2>\n<!-- /wp:heading -->\n\n"
		. "<!-- wp:doroto/tournament-list /-->\n";
}

/**
 * Does the main page already use the blocks (converted, or created by 2.0)?
 * @since 2.0.0
 */
function doroto_main_page_uses_blocks(): bool
{
	$page = get_post(intval(get_option('doroto_main_page_id')));
	return $page instanceof WP_Post && strpos($page->post_content, '<!-- wp:doroto/') !== false;
}

/**
 * Shortcodes for the classic editor that render the 2.0 blocks.
 * [doroto_tournament tournament_id="5" sections="players,results" presentation="1" seconds="20"]
 * [doroto_tournament_list per_page="10" target_page="12"]
 * @since 2.0.0
 */
function doroto_tournament_block_shortcode($atts = [])
{
	$atts = shortcode_atts([
		'tournament_id' => 0,
		'sections' => '',
		'presentation' => 0,
		'seconds' => 15,
	], (array) $atts);
	$attributes = [
		'tournamentId' => intval($atts['tournament_id']),
		'presentation' => !empty($atts['presentation']),
		'presentationSeconds' => intval($atts['seconds']),
	];
	if (trim((string) $atts['sections']) !== '') {
		$attributes['sections'] = array_map('sanitize_key', array_map('trim', explode(',', (string) $atts['sections'])));
	}
	return render_block(['blockName' => 'doroto/tournament', 'attrs' => $attributes, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
}
add_shortcode('doroto_tournament', 'doroto_tournament_block_shortcode');

function doroto_tournament_list_block_shortcode($atts = [])
{
	// per_page 0 = the plugin setting display_rows
	$atts = shortcode_atts(['per_page' => 0, 'target_page' => 0], (array) $atts);
	return render_block([
		'blockName' => 'doroto/tournament-list',
		'attrs' => ['perPage' => intval($atts['per_page']), 'targetPage' => intval($atts['target_page'])],
		'innerBlocks' => [],
		'innerHTML' => '',
		'innerContent' => [],
	]);
}
add_shortcode('doroto_tournament_list', 'doroto_tournament_list_block_shortcode');
