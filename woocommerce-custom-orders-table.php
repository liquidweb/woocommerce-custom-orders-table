<?php
/**
 * Plugin Name:          WooCommerce Custom Orders Table
 * Plugin URI:           https://github.com/liquidweb/woocommerce-custom-orders-tables
 * Description:          Store WooCommerce order data in a custom table for improved performance.
 * Version:              1.0.0-rc3
 * Author:               Liquid Web
 * Author URI:           https://www.liquidweb.com
 * License:              GPLv3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 3.8.0
 * WC tested up to:      4.0.0
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

defined( 'ABSPATH' ) || exit;

/* Define constants to use throughout the plugin. */
define( 'WC_CUSTOM_ORDER_TABLE_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_CUSTOM_ORDER_TABLE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Autoloader for plugin files.
 *
 * This autoloader operates under the assumption that class filenames use the WordPress filename
 * conventions, where a class of 'Foo_Bar' would be named 'class-foo-bar.php'.
 *
 * @param string $class The class name to autoload.
 *
 * @return void
 */
function wc_custom_order_table_autoload( $class ) {

	/**
	 * Eventually, we'll be moving towards a proper, PSR-4 autoloading scheme.
	 *
	 * @link https://github.com/woocommerce/woocommerce-example-package
	 * @link https://github.com/liquidweb/woocommerce-custom-orders-table/issues/153
	 */
	if ( strpos( $class, '\\' ) === false ) {
		$filename = strtolower( 'class-' . str_replace( '_', '-', $class ) . '.php' );
	} else {
		$class    = str_replace( 'LiquidWeb\\WooCommerceCustomOrdersTable\\', '', $class );
		$filename = str_replace( '\\', '/', $class ) . '.php';
	}

	$filepath = WC_CUSTOM_ORDER_TABLE_PATH . 'includes/' . $filename;

	// Bail if the file name we generated does not exist.
	if ( ! is_readable( $filepath ) ) {
		return;
	}

	include $filepath;
}
spl_autoload_register( 'wc_custom_order_table_autoload' );

/**
 * Install the database tables upon plugin activation.
 */
register_activation_hook( __FILE__, array( 'WooCommerce_Custom_Orders_Table_Install', 'activate' ) );

/**
 * Retrieve an instance of the WooCommerce_Custom_Orders_Table class.
 *
 * If one has not yet been instantiated, it will be created.
 *
 * @global $wc_custom_order_table
 *
 * @return WooCommerce_Custom_Orders_Table The global WooCommerce_Custom_Orders_Table instance.
 */
function wc_custom_order_table() {
	global $wc_custom_order_table;

	if ( ! $wc_custom_order_table instanceof WooCommerce_Custom_Orders_Table ) {
		$wc_custom_order_table = new WooCommerce_Custom_Orders_Table();
		$wc_custom_order_table->setup();
	}

	return $wc_custom_order_table;
}

add_action( 'woocommerce_init', 'wc_custom_order_table' );
