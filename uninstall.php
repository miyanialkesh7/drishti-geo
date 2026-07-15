<?php
/**
 * Uninstall AI Reach (GEO Tracker)
 * Runs when the plugin is deleted from the WP admin. Cleans up all options.
 *
 * @package AI_Reach_GEO_Tracker
 */

declare(strict_types=1);

// Only execute on uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
$ai_reach_options = array(
	'ai_reach_brand_name',
	'ai_reach_keywords',
	'ai_reach_api_provider',
	'ai_reach_openrouter_key',
	'ai_reach_openai_key',
	'ai_reach_gemini_key',
	'ai_reach_perplexity_key',
	'ai_reach_anthropic_key',
	'ai_reach_openrouter_model',
	'ai_reach_openai_model',
	'ai_reach_gemini_model',
	'ai_reach_perplexity_model',
	'ai_reach_anthropic_model',
	'ai_reach_daily_scan',
	'ai_reach_scan_results',
	'ai_reach_checklist_results',
	'ai_reach_last_scan_time',
);

foreach ( $ai_reach_options as $ai_reach_option ) {
	delete_option( $ai_reach_option );
}

// Clear scheduled cron event.
$ai_reach_cron_timestamp = wp_next_scheduled( 'ai_reach_daily_scan_cron' );
if ( $ai_reach_cron_timestamp ) {
	wp_unschedule_event( $ai_reach_cron_timestamp, 'ai_reach_daily_scan_cron' );
}
