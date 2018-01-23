# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

* Plugin test suite now extends the WooCommerce core test suite, ensuring WooCommerce behaves as expected when the plugin is active ([#26]).
* The custom data store now extends the `WC_Order_Data_Store_CPT` class, eliminating a lot of code duplication in the process ([#28]).
	- Includes areas that were previously missing, including reporting.
	- Plugin should now have 100% compatibility with default WooCommerce functionality.
* Removed the dependency on a Composer-generated autoloader ([#36]).
* Revert database columns to use `VARCHAR` types for compatibility with WordPress post meta tables.
* Add table indexes on the `order_key`, `customer_id`, and `order_total` columns in the orders table ([#15]).
* Refactor the WP-CLI command, including some changes to accepted arguments ([#35])
* Normalize the plugin name around "WooCommerce Custom Orders Table" ([#38])
* Added changelog and contributing documents ([#12]).
* Massive improvements to test coverage and general WooCommerce compatibility.

## [Version 1.0.0 (Beta 2)] - 2017-12-22

* Clean up codebase to adhere to the [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/), and introduce an `.editorconfig` to make this kind of change less likely in the future ([#8])
* Introduced automated unit tests via the WordPress core test suite ([#9])
* Fixed bug where custom database table was not being created upon plugin activation ([#5], [#9])
* General documentation updates ([#2])

## [Version 1.0.0 (Beta 1)] - 2017-10-02

* Initial public release of the plugin in a beta state.


[Unreleased]: https://github.com/liquidweb/woocommerce-order-tables/compare/master...develop
[Version 1.0.0 (Beta 2)]: https://github.com/liquidweb/woocommerce-order-tables/releases/tag/v1.0.0-beta.2
[Version 1.0.0 (Beta 1)]: https://github.com/liquidweb/woocommerce-order-tables/releases/tag/v1.0.0-beta.1
[#2]: https://github.com/liquidweb/woocommerce-order-tables/pull/2
[#5]: https://github.com/liquidweb/woocommerce-order-tables/pull/5
[#8]: https://github.com/liquidweb/woocommerce-order-tables/pull/8
[#9]: https://github.com/liquidweb/woocommerce-order-tables/pull/9
[#12]: https://github.com/liquidweb/woocommerce-order-tables/pull/12
[#15]: https://github.com/liquidweb/woocommerce-order-tables/pull/15
[#26]: https://github.com/liquidweb/woocommerce-order-tables/pull/26
[#28]: https://github.com/liquidweb/woocommerce-order-tables/pull/28
[#35]: https://github.com/liquidweb/woocommerce-order-tables/pull/35
[#36]: https://github.com/liquidweb/woocommerce-order-tables/pull/36
[#38]: https://github.com/liquidweb/woocommerce-order-tables/pull/38
