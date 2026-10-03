<?php
/**
 * Sanity check that the plugin's version number is kept in sync
 * across the plugin header and readme.txt.
 */

use PHPUnit\Framework\TestCase;

final class VersionConsistencyTest extends TestCase {

	public function test_plugin_version_matches_readme_stable_tag(): void {
		$plugin_file = dirname( __DIR__ ) . '/drishti-geo.php';
		$readme_file = dirname( __DIR__ ) . '/readme.txt';

		$plugin_contents = file_get_contents( $plugin_file );
		$readme_contents = file_get_contents( $readme_file );

		preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_contents, $plugin_matches );
		preg_match( '/^Stable tag:\s*(.+)$/mi', $readme_contents, $readme_matches );

		$this->assertNotEmpty( $plugin_matches, 'Could not find a Version header in the plugin file.' );
		$this->assertNotEmpty( $readme_matches, 'Could not find a Stable tag in readme.txt.' );
		$this->assertSame( trim( $plugin_matches[1] ), trim( $readme_matches[1] ), 'Plugin header Version and readme.txt Stable tag are out of sync.' );
	}

	public function test_plugin_constant_matches_header_version(): void {
		$plugin_file     = dirname( __DIR__ ) . '/drishti-geo.php';
		$plugin_contents = file_get_contents( $plugin_file );

		preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_contents, $header_matches );
		preg_match( '/define\(\s*\'DRISHTI_GEO_VERSION\',\s*\'([^\']+)\'\s*\)/', $plugin_contents, $const_matches );

		$this->assertNotEmpty( $header_matches, 'Could not find a Version header in the plugin file.' );
		$this->assertNotEmpty( $const_matches, 'Could not find the DRISHTI_GEO_VERSION constant.' );
		$this->assertSame( trim( $header_matches[1] ), trim( $const_matches[1] ), 'Plugin header Version and DRISHTI_GEO_VERSION constant are out of sync.' );
	}
}
