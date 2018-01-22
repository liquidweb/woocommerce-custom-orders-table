# WooCommerce Order Tables

[![Build Status](https://travis-ci.org/liquidweb/woocommerce-order-tables.svg?branch=fix%2Ftravis-config)](https://travis-ci.org/liquidweb/woocommerce-order-tables)

Managed WooCommerce plugin for Liquid Web.

## Background & Purpose
WooCommerce even with CRUD classes in core, still uses a custom post type for orders. By moving orders to use a custom table in the site database, will improve store orders performance.

### Here's how
WooCommerce saves more than 40 custom fields per order— including those from plugins— inside the wp_postmeta table. If your store gets ~40 per day, that's 1600 rows (40 * 40) added to the postmeta table in a day.

In one month that number can be 48,000 new rows (1600 * 30) added to your postmeta table. The more rows in a table the longer it will take for a query to execute. WooCommerce Order Tables creates a new table for WooCommerce orders which would cut that number tremendously by making each custom field into a column so that 1 order = 1 row.

## How to use
To be able migrate orders that are stored in the post type `shop_order` on your store, you will need to run a couple of WP-CLI commands. This plugin includes two WP-CLI commands, one is for getting how many orders have not been migrated yet into the order table `wp wc-order-table count`. The other WP-CLI command is to migrate the orders to the `woocommerce_order` database table, that command is `wp wc-order-table migrate --batch=500 --page=1`. So the default batch number of the orders to process is 1000, so you can adjust the batch number as needed.
