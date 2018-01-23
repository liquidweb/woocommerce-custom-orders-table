<?php
/**
 * Bootstrap the PHPUnit test suite(s).
 *
 * Since WooCommerce Custom Orders Table is meant to integrate seamlessly with WooCommerce itself,
 * the bootstrap relies heavily on the WooCommerce core test suite.
 *
 * @package WooCommerce_Custom_Orders_Table
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
$_bootstrap = dirname( __DIR__ ) . '/vendor/woocommerce/woocommerce/tests/bootstrap.php';

// Verify that Composer dependencies have been installed.
if ( ! file_exists( $_bootstrap ) ) {
	echo "\033[0;31mUnable to find the WooCommerce test bootstrap file. Have you run `composer install`?\033[0;m" . PHP_EOL;
	exit( 1 );

} elseif ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "\033[0;31mCould not find $_tests_dir/includes/functions.php, have you run `tests/bin/install-wp-tests.sh`?\033[0;m" . PHP_EOL;
	exit( 1 );
}

// Gives access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Manually load the plugin on muplugins_loaded.
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/woocommerce-custom-orders-table.php';

	echo esc_html( sprintf(
		/* Translators: %1$s is the WooCommerce release being loaded. */
		__( 'Using WooCommerce %1$s.', 'woocommerce-custom-orders-table' ),
		WC_VERSION
	) ) . PHP_EOL;

	WC_Custom_Order_Table_Install::activate();

	add_filter( 'woocommerce_email_actions', '__return_empty_array' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin', 11 );

// Finally, Start up the WP testing environment.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $_bootstrap;
require_once __DIR__ . '/testcase.php';
