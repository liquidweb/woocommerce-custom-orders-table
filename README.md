# WooCommerce Custom Orders Table

[![Build Status](https://travis-ci.org/liquidweb/woocommerce-custom-orders-table.svg?branch=develop)](https://travis-ci.org/liquidweb/woocommerce-custom-orders-table)
[![Coverage Status](https://coveralls.io/repos/github/liquidweb/woocommerce-custom-orders-table/badge.svg?branch=develop)](https://coveralls.io/github/liquidweb/woocommerce-custom-orders-table?branch=develop)

This plugin improves WooCommerce performance by introducing a custom table to hold all of the most common order information in a single, properly-indexed location.

## Background

[WooCommerce 3.0 introduced the notion of CRUD (Create, Read, Update, and Delete) interfaces](https://woocommerce.wordpress.com/2016/10/27/the-new-crud-classes-in-woocommerce-2-7/) in a move to unify the way WooCommerce data is stored and retrieved. However, orders are still stored as custom post types within WordPress, with each piece of order information (billing address, shipping address, taxes, totals, and more) being stored in post meta.

In fact, WooCommerce will typically create over **40 separate post meta entries** for every single order. If your store receives even 10 orders a day, that means 400 new rows in the table every single day!

The larger the post meta table grows, the longer queries will take to execute, potentially slowing down queries (and thus page load times) for you and your visitors.

WooCommerce Custom Orders Table uses the WooCommerce CRUD design to save order data into a single, flat table that's optimized for WooCommerce queries; one order means only one new row, with minimal performance impact.

## Requirements

WooCommerce Custom Orders Table requires [WooCommerce 3.5.1 or newer](https://wordpress.org/plugins/woocommerce/).

If you're looking to migrate existing order data, [you'll need to have the ability to run WP-CLI commands in your WooCommerce environment](http://wp-cli.org/).

## Migrating order data

After installing and activating the plugin, you'll need to migrate orders from post meta into the newly-created orders table.

The easiest way to accomplish this is via [WP-CLI](http://wp-cli.org/), and the plugin ships with three commands to help:

### Counting the orders to be migrated

If you'd like to see the number of orders that have yet to be moved into the orders table, you can quickly retrieve this value with the `count` command:

```
$ wp wc orders-table count
```

### Migrate order data from post meta to the orders table

The `migrate` command will flatten the most common post meta values for WooCommerce orders into a flat database table, optimized for performance.

```
$ wp wc orders-table migrate
```

Orders are queried in batches (determined via the `--batch-size` option) in order to reduce the memory footprint of the command (e.g. "only retrieve `$size` orders at a time"). Some environments may require a lower value than the default of 100.

**Please note** that `migrate` will delete the original order post meta rows after a successful migration. If you want to preserve these, include the `--save-post-meta` flag!

#### Options

<dl>
	<dt>--batch-size=&lt;size&gt;</dt>
	<dd>The number of orders to process in each batch. Default is 100 orders per batch.</dd>
	<dd>Passing `--batch-size=0` will disable batching.</dd>
	<dt>--save-post-meta</dt>
	<dd>Preserve the original post meta after a successful migration. Default behavior is to clean up post meta.</dd>
</dl>


### Copying data from the orders table into post meta

If you require the post meta fields to be present (or are removing the custom orders table plugin), you may rollback the migration at any time with the `backfill` command.

```
$ wp wc orders-table backfill
```

This command does the opposite of `migrate`, looping through the orders table and saving each column into the corresponding post meta key. Be aware that this may dramatically increase the size of your post meta table!

#### Options

<dl>
	<dt>--batch-size=&lt;size&gt;</dt>
	<dd>The number of orders to process in each batch. Default is 100 orders per batch.</dd>
	<dd>Passing `--batch-size=0` will disable batching.</dd>
</dl>

## Contributing

If you're interested in contributing to the development of the plugin or need to report an issue, please [see the contributing guidelines for the project](https://github.com/liquidweb/woocommerce-custom-orders-table/blob/develop/CONTRIBUTING.md).
