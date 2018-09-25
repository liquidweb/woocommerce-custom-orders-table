# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

* Major refactoring of the WP-CLI migration commands ([#61], [#79], [#81]).
* Database table optimizations ([#65], props @raunak-gupta).
* Prevent duplicate IDs when saving orders and refunds ([#64]).
* Ensure that order refunds are also stored in the custom orders table ([#52]).
* Ensure the custom orders table is registered within WooCommerce ([#50]).
* Resolve issue in CLI importer where a `false` value from `wc_get_order()` could cause a fatal error ([#43] & [#46], props @zacscott).
* Fix bug where orders with the same post date could be handled in the wrong order during migration ([#84]).
* Prevent customer notes from being deleted during migration ([#82]).
* Bump the "WC tested up to" version to 3.5.0 ([#80]).
* Major refactoring within the plugin test suite ([#51], [#53], [#60], [#72], [#78]).
* Prevent Travis CI from using PHPUnit 7.0 [until the WordPress core test suite can support it, too](https://core.trac.wordpress.org/ticket/43218).

[This release also restores the repo development history](https://github.com/liquidweb/woocommerce-custom-orders-table/pull/63) prior to [Version 1.0.0 (Beta 1)], ensuring that the team @Mindsize is credited appropriately for their work.

## [Version 1.0.0 (Beta 3)] - 2018-01-23

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
[Version 1.0.0 (Beta 3)]: https://github.com/liquidweb/woocommerce-order-tables/releases/tag/v1.0.0-beta.3
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
[#43]: https://github.com/liquidweb/woocommerce-order-tables/issues/43
[#46]: https://github.com/liquidweb/woocommerce-order-tables/pull/46
[#50]: https://github.com/liquidweb/woocommerce-order-tables/pull/50
[#51]: https://github.com/liquidweb/woocommerce-order-tables/pull/51
[#52]: https://github.com/liquidweb/woocommerce-order-tables/pull/52
[#53]: https://github.com/liquidweb/woocommerce-order-tables/pull/53
[#60]: https://github.com/liquidweb/woocommerce-order-tables/pull/60
[#61]: https://github.com/liquidweb/woocommerce-order-tables/pull/61
[#64]: https://github.com/liquidweb/woocommerce-order-tables/pull/64
[#65]: https://github.com/liquidweb/woocommerce-order-tables/pull/65
[#72]: https://github.com/liquidweb/woocommerce-order-tables/pull/72
[#78]: https://github.com/liquidweb/woocommerce-order-tables/pull/78
[#79]: https://github.com/liquidweb/woocommerce-order-tables/pull/79
[#80]: https://github.com/liquidweb/woocommerce-order-tables/pull/80
[#81]: https://github.com/liquidweb/woocommerce-order-tables/pull/81
[#82]: https://github.com/liquidweb/woocommerce-order-tables/pull/82
[#84]: https://github.com/liquidweb/woocommerce-order-tables/pull/84
