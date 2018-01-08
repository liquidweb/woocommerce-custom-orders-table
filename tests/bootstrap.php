<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Woocommerce_Order_Tables
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/wc-custom-order-table.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/../vendor/autoload_52.php';
require __DIR__ . '/testcase.php';

/*
 * Automatically activate WooCommerce in the test environment.
 *
 * If WooCommerce cannot be activated, an error message will be thrown and the test execution
 * halted, with a non-zero exit code.
 */
$activated = activate_plugin( 'woocommerce/woocommerce.php' );

// If the issue is that the plugin isn't installed, attempt to install it.
if ( is_wp_error( $activated ) && 'plugin_not_found' === $activated->get_error_code() ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	echo PHP_EOL . "WooCommerce is not currently installed in the test environment, attempting to install...";

	// Retrieve information about WooCommerce.
	$plugin_data = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/woocommerce.json' );

	if ( ! is_wp_error( $plugin_data ) ) {
		$plugin_data = json_decode( wp_remote_retrieve_body( $plugin_data ) );
		$plugin_url  = $plugin_data->download_link;
	} else {
		$plugin_url = false;
	}

	// Download the plugin from the WordPress.org repository.
	$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$installed = $upgrader->install( $plugin_url );

	if ( true === $installed ) {
		echo "\033[0;32mOK\033[0;m" . PHP_EOL . PHP_EOL;
	} else {
		echo "\033[0;31mFAIL\033[0;m" . PHP_EOL;

		if ( is_wp_error( $installed ) ) {
			printf( 'Unable to install WooCommerce: %s.', $installed->get_error_message() );
		}

		printf(
			'Please download and install WooCommerce into %s' . PHP_EOL,
			trailingslashit(dirname( dirname( $_tests_dir ) ) )
		);

		exit( 1 );
	}

	// Try once again to activate.
	$activated = activate_plugin( 'woocommerce/woocommerce.php' );
}

// Nothing more we can do, unfortunately.
if ( is_wp_error( $activated ) ) {
	echo PHP_EOL . 'WooCommerce could not automatically be activated in the test environment:';
	echo PHP_EOL . $activated->get_error_message();
	echo PHP_EOL . PHP_EOL . "\033[0;31mUnable to proceed with tests, aborting.\033[0m";
	echo PHP_EOL;
	exit( 1 );
}
