<?php
/**
 * Uninstall Drishti GEO
 * Runs when the plugin is deleted from the WP admin. Cleans up all options.
 *
 * @package Drishti_GEO
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
$drishti_geo_options = array(
	'drishti_geo_brand_name',
	'drishti_geo_keywords',
	'drishti_geo_api_provider',
	'drishti_geo_openrouter_key',
	'drishti_geo_openai_key',
	'drishti_geo_gemini_key',
	'drishti_geo_perplexity_key',
	'drishti_geo_anthropic_key',
	'drishti_geo_openrouter_model',
	'drishti_geo_openai_model',
	'drishti_geo_gemini_model',
	'drishti_geo_perplexity_model',
	'drishti_geo_anthropic_model',
	'drishti_geo_daily_scan',
	'drishti_geo_scan_results',
	'drishti_geo_checklist_results',
	'drishti_geo_last_scan_time',
);

foreach ( $drishti_geo_options as $drishti_geo_option ) {
	delete_option( $drishti_geo_option );
}

// Clear scheduled cron event.
$drishti_geo_cron_timestamp = wp_next_scheduled( 'drishti_geo_daily_scan_cron' );
if ( $drishti_geo_cron_timestamp ) {
	wp_unschedule_event( $drishti_geo_cron_timestamp, 'drishti_geo_daily_scan_cron' );
}
