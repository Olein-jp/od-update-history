<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package OD_Update_History
 */

$od_update_history_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $od_update_history_tests_dir ) {
	$od_update_history_tests_dir = '/wordpress-phpunit';
}

$od_update_history_plugin_dir    = dirname( __DIR__ );
$od_update_history_autoload_file = $od_update_history_plugin_dir . '/vendor/autoload.php';
$od_update_history_polyfills_dir = $od_update_history_plugin_dir . '/vendor/yoast/phpunit-polyfills';

if ( file_exists( $od_update_history_autoload_file ) ) {
	require_once $od_update_history_autoload_file;
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && is_dir( $od_update_history_polyfills_dir ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $od_update_history_polyfills_dir );
}

require_once $od_update_history_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require_once dirname( __DIR__ ) . '/od-update-history.php';
	}
);

require $od_update_history_tests_dir . '/includes/bootstrap.php';
