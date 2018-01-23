<?php
/**
 * Plugin Name:          WooCommerce - Custom Orders Table
 * Plugin URI:           https://github.com/liquidweb/WooCommerce-Order-Tables
 * Description:          Store WooCommerce order data in a custom table for improved performance.
 * Version:              1.0.0
 * Author:               Liquid Web
 * Author URI:           https://www.liquidweb.com
 * License:              GPL2
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * WC requires at least: 3.2.6
 * WC tested up to:      3.3.0
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/* Define constants to use throughout the plugin. */
define( 'WC_CUSTOM_ORDER_TABLE_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_CUSTOM_ORDER_TABLE_PATH', plugin_dir_path( __FILE__ ) );

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path
set_include_path( get_include_path() . PATH_SEPARATOR . WC_CUSTOM_ORDER_TABLE_PATH . '/includes/' );
// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path

/**
 * Autoloader for plugin files.
 *
 * This autoloader operates under the assumption that class filenames use the WordPress filename
 * conventions, where a class of 'Foo_Bar' would be named 'class-foo-bar.php'.
 *
 * @param string $class The class name to autoload.
 */
function wc_custom_order_table_autoload( $class ) {
	$class = 'class-' . str_replace( '_', '-', $class );

	return spl_autoload( $class );
}
spl_autoload_register( 'wc_custom_order_table_autoload' );

/**
 * Install the database tables upon plugin activation.
 */
register_activation_hook( __FILE__, array( 'WooCommerce_Custom_Orders_Table_Install', 'activate' ) );

/**
 * Retrieve an instance of the WC_Custom_Order_Table class.
 *
 * If one has not yet been instantiated, it will be created.
 *
 * @global $wc_custom_order_table
 *
 * @return WC_Custom_Order_Table The global WC_Custom_Order_Table instance.
 */
function wc_custom_order_table() {
	global $wc_custom_order_table;

	if ( ! $wc_custom_order_table instanceof WC_Custom_Order_Table ) {
		$wc_custom_order_table = new WC_Custom_Order_Table();
		$wc_custom_order_table->setup();
	}

	return $wc_custom_order_table;
}

add_action( 'woocommerce_init', 'wc_custom_order_table' );
