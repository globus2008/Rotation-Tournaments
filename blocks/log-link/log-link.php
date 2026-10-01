<?php

/**
 * Plugin Name:       Log link to the tournament
 * Description:       Log link to the tournament
 * Requires at least: 7.0
 * Requires PHP:      7.2
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       doubles-rotation-tournament
 *
 * @package LogLink
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('doroto_log_link_block_init')) {
	function doroto_log_link_block_init()
	{
		register_block_type(
			__DIR__ . '/build',
			array(
				'render_callback' => 'doroto_log_link_render_dynamic_block',
			)
		);
	}
}
add_action('init', 'doroto_log_link_block_init');

if (!function_exists('doroto_log_link_render_dynamic_block')) {
	function doroto_log_link_render_dynamic_block($attributes)
	{
		$tournament_id = isset($attributes['tournamentId']) ? intval($attributes['tournamentId']) : 0;
		$tournament_name = isset($attributes['tournamentName']) ? sanitize_text_field($attributes['tournamentName']) : 'Neznámý turnaj';
		$hide_invitation = isset($attributes['hideInvitation']) ? boolval($attributes['hideInvitation']) : false;
		$hide_tournament_name = isset($attributes['hideTournamentName']) ? boolval($attributes['hideTournamentName']) : false;
		$hide_player_count = isset($attributes['hidePlayerCount']) ? boolval($attributes['hidePlayerCount']) : false;

		$background_color = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '';
		$text_color = isset($attributes['textColor']) ? $attributes['textColor'] : '';

		if (empty($tournament_id)) {
			return '<p>' . esc_html__('Invalid tournament ID', 'doubles-rotation-tournament') . '</p>';
		}

		$tournament_data = doroto_log_link_get_tournament_data_by_id($tournament_id);

		if (!$tournament_data) {
			return '<p>' . esc_html__('The tournament was not found.', 'doubles-rotation-tournament') . '</p>';
		}

		$invitation = $tournament_data['invitation'] ?? '';
		$players_count = intval($tournament_data['players_count'] ?? 0);
		$max_players = intval($tournament_data['max_players'] ?? 0);

		$wrapper_attributes = get_block_wrapper_attributes();

		if ($background_color || $text_color) {
			$styles = '';
			if ($background_color) {
				$styles .= 'background-color: ' . esc_attr($background_color) . '; ';
			}
			if ($text_color) {
				$styles .= 'color: ' . esc_attr($text_color) . ';';
			}

			$wrapper_attributes .= ' style="' . esc_attr($styles) . '"';
		}

		$output = '<div ' . $wrapper_attributes . '>';

		if (!$hide_invitation && $invitation) {
			$output .= '<div class="tournament-invitation">' . wp_kses_post($invitation) . '</div>';
		}

		if (!$hide_tournament_name) {
			$output .= '<p><strong>' . esc_html__('Tournament name:', 'doubles-rotation-tournament') . '</strong> ' . esc_html($tournament_name) . '</p>';
		}

		if (!$hide_player_count) {
			$output .= '<p><strong>' . esc_html__('Players count:', 'doubles-rotation-tournament') . '</strong> ' . esc_html($players_count);
			if ($max_players > 0) {
				$output .= ' / ' . esc_html($max_players);
			}
			$output .= '</p>';
		}

		$login_url = esc_url(doroto_log_link_get_login_link($tournament_id));
		$output .= '<p><strong>' . esc_html__('Log in link:', 'doubles-rotation-tournament') . '</strong> <a href="' . $login_url . '" style="color: inherit;">' . esc_html__('Log in/Log out from the tournament', 'doubles-rotation-tournament') . '</a></p>';

		$output .= '</div>';

		return $output;
	}
}

if (!function_exists('doroto_log_link_get_tournament_data_by_id')) {
	function doroto_log_link_get_tournament_data_by_id($tournament_id)
	{
		if (empty($tournament_id)) {
			return null;
		}
		$tournament = doroto_prepare_tournament($tournament_id);
		if (!$tournament) {
			return null;
		}

		$allowed_html = doroto_allowed_html();
		return array(
			'name' => sanitize_text_field($tournament->name),
			'invitation' => wp_kses($tournament->invitation, $allowed_html),
			'players_count' => count(maybe_unserialize($tournament->players) ?: []),
			'max_players' => intval($tournament->max_players),
		);
	}
}

if (!function_exists('doroto_log_link_get_login_link')) {
	function doroto_log_link_get_login_link($tournament_id)
	{
		if (empty($tournament_id)) {
			return '';
		}
		return doroto_join_url(intval($tournament_id));
	}
}
