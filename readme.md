# wp-custom-order-tables
Managed WooCommerce plugin for Liquid Web.

## Background & Purpose
WooCommerce even with CRUD classes in core, still uses a custom post type for orders. By moving orders to use a custom table in the site database, will improve store orders performance.

## Installation
This plugin uses a Composer autoloader. In development environments, the autoloader needs to be generated. After cloning this repository, run `composer install` in the root directory of the plugin.

As soon as the plugin is being distributed, the package needs to contain the already generated autoloader, so end users don't need to worry about this.
