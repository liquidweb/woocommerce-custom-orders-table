<?php
/**
 * Bootstrap the PHPUnit test suite(s).
 *
 * Since WooCommerce Custom Orders Table is meant to integrate seamlessly with WooCommerce itself,
 * the bootstrap relies heavily on the WooCommerce core test suite.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
$_bootstrap = dirname( __DIR__ ) . '/vendor/woocommerce/woocommerce/tests/bootstrap.php';

// Verify that Composer dependencies have been installed.
if ( ! file_exists( $_bootstrap ) ) {
	echo "\033[0;31mUnable to find the WooCommerce test bootstrap file. Have you run `composer install`?\033[0;m" . PHP_EOL;
	exit( 1 );
}

// Finally, Start up the WP testing environment.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $_bootstrap;
require_once __DIR__ . '/testcase.php';
require_once dirname( __DIR__ ) . '/woocommerce-custom-orders-table.php';

echo esc_html( sprintf(
	/* Translators: %1$s is the WooCommerce release being loaded. */
	__( 'Using WooCommerce %1$s.', 'woocommerce-custom-orders-table' ),
	WC_VERSION
) ) . PHP_EOL;

// Activate the plugin *after* WooCommerce has been bootstrapped.
WooCommerce_Custom_Orders_Table_Install::activate();
