<?php
if (! defined('ABSPATH')) exit;

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}
require_once plugin_dir_path(__FILE__) . 'includes/doroto-repeated-functions.php';
global $wpdb;

$options_starting_with_doroto = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'doroto_%'");

$delete_settings = intval(doroto_read_settings('delete_settings', 1));
$delete_pages = intval(doroto_read_settings('delete_pages', 1));
$delete_database = intval(doroto_read_settings('delete_database', 0));

if ($delete_pages) {
	$main_page_id = intval(get_option('doroto_main_page_id'));
	$help_page_id = intval(get_option('doroto_help_page_id'));
	$example_page_id = intval(get_option('doroto_example_page_id'));
	$privacy_policy_id = intval(get_option('doroto_privacy_policy_page_id'));
	$terms_of_service_id = intval(get_option('doroto_terms_of_service_page_id'));

	if (!empty($main_page_id)) {
		wp_delete_post($main_page_id, false);
	}
	if (!empty($help_page_id)) {
		wp_delete_post($help_page_id, false);
	}
	if (!empty($privacy_policy_id)) {
		wp_delete_post($privacy_policy_id, false);
	}
	if (!empty($terms_of_service_id)) {
		wp_delete_post($terms_of_service_id, false);
	}

	if (!empty($example_page_id)) {
		wp_delete_post($example_page_id, false);
	}

	$email_domain = '@DoRoTo-example.com';
	$users_to_delete = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->prefix}users WHERE user_email LIKE %s",
			'%' . $wpdb->esc_like($email_domain) . '%'
		)
	);

	foreach ($users_to_delete as $user) {
		wp_delete_user($user->ID, true);
	}


	$results = $wpdb->get_results("SELECT page_id FROM {$wpdb->prefix}doroto_tournaments WHERE page_id IS NOT NULL");

	foreach ($results as $result) {

		if (is_numeric($result->page_id)) {
			wp_delete_post($result->page_id, true);
		}
	}

	$wpdb->update("{$wpdb->prefix}doroto_tournaments", array('page_id' => null), array('page_id' => 0), array('%s'), array('%d'));
	$wpdb->update("{$wpdb->prefix}doroto_tournaments", array('page_id' => null), array('page_id' => 'null'), array('%s'), array('%s'));
}

foreach ($options_starting_with_doroto as $option) {
	$variable = $option->option_name;
	if (($variable == 'doroto_settings') && $delete_settings == 0) {
		continue;
	}
	if (($variable == 'doroto_main_page_id' || $variable == 'doroto_help_page_id' || $variable == 'doroto_example_page_id'
		|| $variable == 'doroto_privacy_policy_page_id' || $variable == 'doroto_terms_of_service_page_id') && $delete_pages == 0) {
		continue;
	}
	delete_option($option->option_name);
}

if ($delete_database) {
	$table_name = $wpdb->prefix . 'doroto_tournaments';
	$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}
