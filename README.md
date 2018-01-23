# WooCommerce Order Tables

[![Build Status](https://travis-ci.org/liquidweb/woocommerce-order-tables.svg?branch=fix%2Ftravis-config)](https://travis-ci.org/liquidweb/woocommerce-order-tables)

Managed WooCommerce plugin for Liquid Web.

## Background & Purpose
WooCommerce even with CRUD classes in core, still uses a custom post type for orders. By moving orders to use a custom table in the site database, will improve store orders performance.

### Here's how
WooCommerce saves more than 40 custom fields per order— including those from plugins— inside the wp_postmeta table. If your store gets ~40 per day, that's 1600 rows (40 * 40) added to the postmeta table in a day.

In one month that number can be 48,000 new rows (1600 * 30) added to your postmeta table. The more rows in a table the longer it will take for a query to execute. WooCommerce Order Tables creates a new table for WooCommerce orders which would cut that number tremendously by making each custom field into a column so that 1 order = 1 row.

## Installation
This plugin uses a Composer autoloader. If you are working with code from the `master` branch in a development environment, the autoloader needs to be generated in order for the plugin to work. After cloning this repository, run `composer install` in the root directory of the plugin.

The packaged version of this plugin, which is available in [releases](https://github.com/liquidweb/WooCommerce-Order-Tables/releases), contains the autoloader. This means that end users should not need to worry about running the `composer install` command to get things working. Just grab the latest release and go, but be sure to backup your database first!

## Migrating order data

After installing and activating the plugin, you'll need to migrate orders from post meta into the newly-created orders table.

The easiest way to accomplish this is via [WP-CLI](http://wp-cli.org/), and the plugin ships with three commands to help:

### Counting the orders to be migrated

If you'd like to see the number of orders that have yet to be moved into the order table, you can quickly retrieve this value with the `count` command:

```
$ wp wc-order-table count
```

### Migrate order data from post meta to the orders table

The `migrate` command will flatten the most common post meta values for WooCommerce orders into a flat database table, optimized for performance.

```
$ wp wc-order-table migrate
```

Orders are queried in batches (determined via the `--batch-size` option) in order to reduce the memory footprint of the command (e.g. "only retrieve {$size} orders at a time). Some environments may require a lower value than the default of 1000.

#### Options

<dl>
	<dt>batch-size</dt>
	<dd>The number of orders to process in each batch. Default is 1000 orders.</dd>
</dl>


### Copying data from the orders table into post meta

If you require the post meta fields to be present (or are removing the custom order table plugin), you may rollback the migration at any time with the `backfill` command.

```
$ wp wc-order-table backfill
```

This command does the opposite of `migrate`, looping through the orders table and saving each column into the corresponding post meta key. Be aware that this may dramatically increase the size of your post meta table!

#### Options

<dl>
	<dt>batch-size</dt>
	<dd>The number of orders to process in each batch. Default is 1000 orders.</dd>
	<dt>batch</dt>
	<dd>The batch number to start from when migrating data. Default is 1.</dd>
</dl>
