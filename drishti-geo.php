<?php
/**
 * Plugin Name: Drishti GEO - AI Visibility Tracking and Analytics
 * Plugin URI: https://wordpress.org/plugins/drishti-geo/
 * Description: Tracks website and brand visibility across major AI engines (ChatGPT, Gemini, Perplexity, Claude, Siri) using your choice of API provider (OpenRouter, OpenAI, Gemini, Perplexity, Anthropic), featuring a native robots.txt blocker alert and an automated ai.txt generator.
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Techeshta
 * Author URI: https://www.techeshta.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: drishti-geo
 * Domain Path: /languages
 *
 * @package Drishti_GEO
 */

declare(strict_types=1);

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Define Constants.
define( 'DRISHTI_GEO_VERSION', '1.0.1' );
define( 'DRISHTI_GEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DRISHTI_GEO_URL', plugin_dir_url( __FILE__ ) );
define( 'DRISHTI_GEO_BASENAME', plugin_basename( __FILE__ ) );

// Load classes.
require_once DRISHTI_GEO_PATH . 'includes/class-drishti-geo-scanner.php';
require_once DRISHTI_GEO_PATH . 'includes/class-drishti-geo-admin.php';

/**
 * Activation logic
 */
function drishti_geo_activate(): void {
	// Set default options if not already set.
	add_option( 'drishti_geo_brand_name', '' );
	add_option( 'drishti_geo_keywords', '' );

	// API provider selection: one of openrouter, openai, gemini, perplexity, or anthropic.
	add_option( 'drishti_geo_api_provider', 'openrouter' );

	// Per-provider API keys.
	add_option( 'drishti_geo_openrouter_key', '' );
	add_option( 'drishti_geo_openai_key', '' );
	add_option( 'drishti_geo_gemini_key', '' );
	add_option( 'drishti_geo_perplexity_key', '' );
	add_option( 'drishti_geo_anthropic_key', '' );

	// Per-provider selected models.
	add_option( 'drishti_geo_openrouter_model', 'default' );
	add_option( 'drishti_geo_openai_model', 'gpt-6-luna' );
	add_option( 'drishti_geo_gemini_model', 'gemini-3.5-flash-lite' );
	add_option( 'drishti_geo_perplexity_model', 'sonar' );
	add_option( 'drishti_geo_anthropic_model', 'claude-opus-5' );

	add_option( 'drishti_geo_daily_scan', '0' );
	add_option( 'drishti_geo_scan_results', array() );
	add_option( 'drishti_geo_checklist_results', array() );
	add_option( 'drishti_geo_last_scan_time', '' );

	// Always schedule cron on activation if enabled (or setup check).
	if ( ! wp_next_scheduled( 'drishti_geo_daily_scan_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'drishti_geo_daily_scan_cron' );
	}
}
register_activation_hook( __FILE__, 'drishti_geo_activate' );

/**
 * Deactivation logic
 */
function drishti_geo_deactivate(): void {
	// Clear the cron job.
	$timestamp = wp_next_scheduled( 'drishti_geo_daily_scan_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'drishti_geo_daily_scan_cron' );
	}
}
register_deactivation_hook( __FILE__, 'drishti_geo_deactivate' );

/**
 * Handle cron daily scan
 */
function drishti_geo_handle_cron_scan(): void {
	// Check if daily scan toggle is enabled.
	$enabled = get_option( 'drishti_geo_daily_scan', '0' );
	if ( '1' !== $enabled ) {
		return;
	}

	// Prevent PHP timeout during sequential API calls (5 engines × 45s max each).
	// No WP core alternative exists for extending execution time during this long-running cron task.
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
	}

	$provider = get_option( 'drishti_geo_api_provider', 'openrouter' );

	$key_option = 'drishti_geo_' . sanitize_key( $provider ) . '_key';
	$api_key    = get_option( $key_option, '' );
	$brand_name = get_option( 'drishti_geo_brand_name' );
	$keywords   = get_option( 'drishti_geo_keywords' );

	if ( empty( $api_key ) || empty( $brand_name ) || empty( $keywords ) ) {
		return; // Settings are missing, cannot scan.
	}

	$scanner = new Drishti_GEO_Scanner();
	$engines = array( 'openai', 'gemini', 'perplexity', 'claude', 'siri' );
	$results = array();

	// Run scans sequentially in the cron job.
	foreach ( $engines as $engine_id ) {
		$scan_res = $scanner->run_scan_for_engine( $engine_id );
		if ( ! is_wp_error( $scan_res ) ) {
			$results[ $engine_id ] = $scan_res;
		} else {
			$results[ $engine_id ] = array(
				'mentioned'  => false,
				'transcript' => __( 'Scan failed: ', 'drishti-geo' ) . $scan_res->get_error_message(),
			);
		}
	}

	// Save scan results.
	update_option( 'drishti_geo_scan_results', $results );
	update_option( 'drishti_geo_last_scan_time', current_time( 'mysql' ) );

	// Calculate and save the 9-point checklist.
	$checklist = $scanner->calculate_9_point_checklist( $results );
	update_option( 'drishti_geo_checklist_results', $checklist );
}
add_action( 'drishti_geo_daily_scan_cron', 'drishti_geo_handle_cron_scan' );

/**
 * Initialize components
 */
function drishti_geo_init(): void {
	if ( is_admin() ) {
		new Drishti_GEO_Admin();
	}
}
add_action( 'plugins_loaded', 'drishti_geo_init' );

/**
 * Serve the generated ai.txt content virtually at /ai.txt, the same way WordPress
 * core serves a virtual robots.txt. This avoids writing a physical file into
 * ABSPATH, which is not always the public web root.
 */
function drishti_geo_maybe_serve_aitxt(): void {
	$content = get_option( 'drishti_geo_aitxt_content', '' );
	if ( ! is_string( $content ) || '' === $content ) {
		return;
	}

	$home_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

	if ( rtrim( $home_path, '/' ) . '/ai.txt' !== $request_path ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text response generated entirely by this plugin, not HTML.
	exit;
}
add_action( 'template_redirect', 'drishti_geo_maybe_serve_aitxt', 0 );
