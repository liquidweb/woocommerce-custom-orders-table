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

use PHPUnit\Util\Configuration;

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

// Determine which version of WooCommerce we're testing against.
$wc_version    = getenv('WC_VERSION') ?: 'latest';
$target_suffix = preg_match( '/\d+\.\d+/', $wc_version, $match ) ? $match[0] : 'master';
$target_dir    = dirname( __DIR__ ) . '/vendor/woocommerce/woocommerce-src-' . $target_suffix;

// Attempt to install the given version of WooCommerce if it doesn't already exist.
if ( ! is_dir( $target_dir ) ) {
	try {
		exec(
			sprintf(
				'%1$s/bin/install-woocommerce.sh %2$s',
				__DIR__,
				escapeshellarg( $wc_version )
			),
			$output,
			$exit
		);

		if (0 !== $exit) {
			throw new \RuntimeException( sprintf( 'Received a non-zero exit code: %1$d', $exit ) );
		}
	} catch ( \Throwable $e ) {
		printf( "\033[0;31mUnable to install WooCommerce@%s\033[0;0m" . PHP_EOL, $wc_version );
		printf( 'Please run `sh tests/bin/install-woocommerce.sh %1$s` manually.' . PHP_EOL, $wc_version );

		exit( 1 );
	}
}

// Locate the WooCommerce test bootstrap file for this release.
$_bootstrap = $target_dir . '/tests/bootstrap.php';

if ( ! file_exists( $_bootstrap ) ) {
	printf(
		"\033[0;31mUnable to find the the test bootstrap file for WooCommerce@%1$s, aborting.\033[0;m\n",
		$wc_version
	);
	exit( 1 );
}

/*
 * Since PHPUnit won't let us dynamically define test suites based on $wc_version, use reflection
 * to inject them into the parsed representation of the XML.
 */
$config_xml_path    = dirname( __DIR__ ) . '/phpunit.xml.dist';
$instances_property = new \ReflectionProperty(Configuration::class, 'instances');
$instances_property->setAccessible( true );
$xpath_property     = new \ReflectionProperty(Configuration::class, 'xpath');
$xpath_property->setAccessible( true );

// Retrieve the parsed config from phpunit.xml.dist.
$config = $instances_property->getValue();
$xargs  = $xpath_property->getValue( $config[ $config_xml_path ] );

// Update the *FIRST* <directory> value inline for the "core" test suite.
$core = $xargs->query('testsuites/testsuite[@name="core"]');

if ( ! empty( $core ) ) {
	// Replace any %WC_VERSION% placeholders in paths.
	foreach ( $core->item( 0 )->childNodes as $node ) {
		$node->textContent = str_replace( '%WC_VERSION%', $target_suffix, $node->textContent );
	}
} else {
	echo 'Unable to override the "core" testsuite within PHPUnit, using default.';
}

unset( $config_xml_path, $instances_property, $xpath_property, $config, $xargs, $core );

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
