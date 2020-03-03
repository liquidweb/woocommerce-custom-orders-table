Contributors: liquidweb, mindsize, stevegrunwell
Tags: woocommerce, performance
Requires at least: 4.9
Tested up to: 5.0.1
Requires PHP: 5.2.4
Stable tag: 1.0.0
License: GNU General Public License v2.0
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Store WooCommerce order data in a custom table for improved performance.

== Description ==

This plugin improves WooCommerce performance by introducing a custom table to hold all of the most common order information in a single, properly-indexed location.

### Background

[WooCommerce 3.0 introduced the notion of CRUD (Create, Read, Update, and Delete) interfaces](https://woocommerce.wordpress.com/2016/10/27/the-new-crud-classes-in-woocommerce-2-7/) in a move to unify the way WooCommerce data is stored and retrieved. However, orders are still stored as custom post types within WordPress, with each piece of order information (billing address, shipping address, taxes, totals, and more) being stored in post meta. In fact, WooCommerce will typically create over **40 separate post meta entries** for every single order. If your store receives even 10 orders a day, that means 400 new rows in the table every single day! The larger the post meta table grows, the longer queries will take to execute, potentially slowing down queries (and thus page load times) for you and your visitors. WooCommerce Custom Orders Table uses the WooCommerce CRUD design to save order data into a single, flat table that's optimized for WooCommerce queries; one order means only one new row, with minimal performance impact.

### Requirements

WooCommerce Custom Orders Table requires [WooCommerce 3.2.6 or newer](https://wordpress.org/plugins/woocommerce/).

If you're looking to migrate existing order data, [you'll need to have the ability to run WP-CLI commands in your WooCommerce environment](http://wp-cli.org/).

### Contributing

If you're interested in contributing to the development of the plugin or need to report an issue, please [see the contributing guidelines for the project on GitHub](https://github.com/liquidweb/woocommerce-custom-orders-table/blob/develop/CONTRIBUTING.md).

== Installation ==

You may install the plugin as you would install any other plugin: either through the "WP Admin &rsaquo; Plugins &rsaquo; Add New" screen or uploading the zip archive into WordPress.

After installing and activating the plugin, you'll need to migrate orders from post meta into the newly-created orders table via [WP-CLI](http://wp-cli.org/):

	$ wp wc orders-table migrate

For additional information on available WP-CLI commands (including a built-in way to restore order post meta), [please see the plugin's GitHub README](https://github.com/liquidweb/woocommerce-custom-orders-table#migrating-order-data).

== Frequently Asked Questions ==

= Will this harm existing order post meta? =

Once activated, WooCommerce Custom Orders Table will register the custom table (`wp_woocommerce_orders`, by default) as the source for all order data. If a request is made to load data for an order that _doesn't_ exist in the custom table, the plugin will automatically migrate the corresponding post meta into a new table row.

For the best performance, it's recommended that you [explicitly migrate order data via WP-CLI](https://github.com/liquidweb/woocommerce-custom-orders-table#migrating-order-data) rather than count on each order being migrated the first time it's loaded.

= Can I restore my order post meta if I don't like the custom table? =

If you decide that WooCommerce Custom Orders Table isn't right for you, [the plugin includes a handy `backfill` WP-CLI command](https://github.com/liquidweb/woocommerce-custom-orders-table#copying-data-from-the-orders-table-into-post-meta), which puts all of the data back into post meta.

Be forewarned, running `backfill` on a store with many orders will cause the post meta table to balloon quickly, so this is not recommended during peak traffic times.

= Will using a custom table for order data break anything else within WooCommerce? =

During development, we've been _extremely_ careful not to break existing WooCommerce functionality; our mantra on the project has been "if it works in stock WooCommerce, it had better work with our plugin active."

To this end, [the plugin test suite](https://travis-ci.org/liquidweb/woocommerce-custom-orders-table) actually bootstraps WooCommerce's core test suite, ensuring that every test within WooCommerce passes when the Custom Orders Table plugin is active. [You can read more about confidently testing WooCommerce plugins in this blog post](https://stevegrunwell.com/blog/writing-woocommerce-extensions/).

== Changelog ==

For a complete list of all changes, please [see the plugin changelog on GitHub](https://github.com/liquidweb/woocommerce-custom-orders-table/blob/develop/CHANGELOG.md).

= 1.0.0 =
* Initial public release of the plugin.
