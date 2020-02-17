=== Migrate To Liquid Web ===
Contributors: stevegrunwell, liquidweb
Tags: liquidweb, woocommerce
Requires at least: 5.3.2
Tested up to: 5.3
Stable tag: 3.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin improves WooCommerce performance by introducing a custom table to hold all of the most common order information in a single, properly-indexed location.

== Description ==

WooCommerce 3.0 introduced the [notion of CRUD (Create, Read, Update, and Delete) interfaces](https://woocommerce.wordpress.com/2016/10/27/the-new-crud-classes-in-woocommerce-2-7/ "notion of CRUD (Create, Read, Update, and Delete) interfaces") in a move to unify the way WooCommerce data is stored and retrieved. However, orders are still stored as custom post types within WordPress, with each piece of order information (billing address, shipping address, taxes, totals, and more) being stored in post meta. In fact, WooCommerce will typically create over 40 separate post meta entries for every single order. If your store receives even 10 orders a day, that means 400 new rows in the table every single day! The larger the post meta table grows, the longer queries will take to execute, potentially slowing down queries (and thus page load times) for you and your visitors. WooCommerce Custom Orders Table uses the WooCommerce CRUD design to save order data into a single, flat table that's optimized for WooCommerce queries; one order means only one new row, with minimal performance impact.


== Installation ==

= There are two ways to install the Liquid Web plugin: =

1. Download the plugin through the ‘Plugins’ menu in your WordPress admin panel
2. Upload the `woocommerce-custom-orders-table` folder to the `/wp-content/plugins/` directory through sFTP

After installing you need to activate the plugin. Don't forget to migrate existing orders if WooCommerce has orders already.

== Frequently Asked Questions ==

= Counting the orders to be migrated =

If you'd like to see the number of orders that have yet to be moved into the orders table, you can quickly retrieve this value with the `count` command:

```console
$ wp wc orders-table count
```

= Migrate order data from post meta to the orders table =

The `migrate` command will flatten the most common post meta values for WooCommerce orders into a flat database table, optimized for performance:

```console
$ wp wc orders-table migrate
```
Orders are queried in batches (determined via the --batch-size option) in order to reduce the memory footprint of the command (e.g. "only retrieve $size orders at a time"). Some environments may require a lower value than the default of 100.

**Please note** that migrate will delete the original order post meta rows after a successful migration. If you want to preserve these, include the --save-post-meta flag!

####Options

`--batch-size=<size>`

The number of orders to process in each batch. Default is 100 orders per batch.
Passing `--batch-size=0` will disable batching.

`--save-post-meta`

Preserve the original post meta after a successful migration. Default behavior is to clean up post meta.

= Copying data from the orders table into post meta =

If you require the post meta fields to be present (or are removing the custom orders table plugin), you may rollback the migration at any time with the `backfill` command:

```console
$ wp wc orders-table backfill
```

This command does the opposite of `migrate`, looping through the orders table and saving each column into the corresponding post meta key. Be aware that this may dramatically increase the size of your post meta table!

####Options

`--batch-size=<size>`

The number of orders to process in each batch. Default is 100 orders per batch.
Passing `--batch-size=0` will disable batching.

== Changelog ==

= 1.0 =
* Initial release
