<?php
/**
 * Uninstall Nectar GEO
 * Runs when the plugin is deleted from the WP admin. Cleans up all options.
 *
 * @package Nectar_GEO
 */

declare(strict_types=1);

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only execute on uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
$nectar_geo_options = array(
	'nectar_geo_brand_name',
	'nectar_geo_keywords',
	'nectar_geo_api_provider',
	'nectar_geo_openrouter_key',
	'nectar_geo_openai_key',
	'nectar_geo_gemini_key',
	'nectar_geo_perplexity_key',
	'nectar_geo_anthropic_key',
	'nectar_geo_openrouter_model',
	'nectar_geo_openai_model',
	'nectar_geo_gemini_model',
	'nectar_geo_perplexity_model',
	'nectar_geo_anthropic_model',
	'nectar_geo_daily_scan',
	'nectar_geo_scan_results',
	'nectar_geo_checklist_results',
	'nectar_geo_last_scan_time',
);

foreach ( $nectar_geo_options as $nectar_geo_option ) {
	delete_option( $nectar_geo_option );
}

// Clear scheduled cron event.
$nectar_geo_cron_timestamp = wp_next_scheduled( 'nectar_geo_daily_scan_cron' );
if ( $nectar_geo_cron_timestamp ) {
	wp_unschedule_event( $nectar_geo_cron_timestamp, 'nectar_geo_daily_scan_cron' );
}
