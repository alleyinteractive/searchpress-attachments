<?php
/**
 * Searchpress_Attachments Tests: Bootstrap File
 *
 * @package Searchpress_Attachments
 * @subpackage Tests
 */

// Load Core's test suite.
$searchpress_attachments_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $searchpress_attachments_tests_dir ) {
	$searchpress_attachments_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $searchpress_attachments_tests_dir . '/includes/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

/**
 * Setup our environment.
 */
function searchpress_attachments_manually_load_environment() {
	/*
	 * Tests won't start until the uploads directory is scanned, so use the
	 * lightweight directory from the test install.
	 *
	 * @see https://core.trac.wordpress.org/changeset/29120.
	 */
	add_filter(
		'pre_option_upload_path',
		function () {
			return ABSPATH . 'wp-content/uploads';
		}
	);

	// Load this plugin.
	require_once dirname( __DIR__ ) . '/index.php';
}
tests_add_filter( 'muplugins_loaded', 'searchpress_attachments_manually_load_environment' );

// Include core's bootstrap.
require $searchpress_attachments_tests_dir . '/includes/bootstrap.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable