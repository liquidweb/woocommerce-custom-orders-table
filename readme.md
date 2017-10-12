# WooCommerce Order Tables
Managed WooCommerce plugin for Liquid Web.

## Background & Purpose
WooCommerce even with CRUD classes in core, still uses a custom post type for orders. By moving orders to use a custom table in the site database, will improve store orders performance.

## Installation
This plugin uses a Composer autoloader. If you are working with code from the `master` branch in a development environment, the autoloader needs to be generated in order for the plugin to work. After cloning this repository, run `composer install` in the root directory of the plugin.

The packaged version of this plugin, which is available in [releases](https://github.com/liquidweb/WooCommerce-Order-Tables/releases), contains the autoloader. This means that end users should not need to worry about running the `composer install` command to get things working. Just grab the latest release and go!
