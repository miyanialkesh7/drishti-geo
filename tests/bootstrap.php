<?php
/**
 * PHPUnit bootstrap.
 *
 * These are lightweight unit tests that do not require a WordPress
 * install or database — they run against plain PHP/file-level checks.
 */

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
