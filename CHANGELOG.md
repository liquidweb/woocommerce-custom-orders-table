# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

**Note:** Based on [our compatibility policy](CONTRIBUTING.md#compatibility-policy), this plugin now requires WooCommerce 3.7 or higher. As such, PHP 5.6 or newer is also required.

### Added

* Enable the migration WP-CLI commands to accept `--batch-size=0`, which disables batching ([#152], props @AlchemyUnited, @mfs-mindsize).

### Fixed

* Change the plugin's display name from "WooCommerce - Custom Orders Table" to "WooCommerce Custom Orders Table" ([#138], props @jb510).
* Add a `method_exists()` check to the wildcard `set_{$column}` method ([#139], props @blohbaugh).
* Rewrite the way that WooCommerce core is installed in test environments ([#154]).
* Use offsets to avoid infinite loops during migration ([#157], props @mfs-mindsize).
* Only register the hooks for `WC_Customer_Data_Store_Custom_Table` once ([#164]).

## [Version 1.0.0 (Release Candidate 3)] - 2019-07-24

* Ensure the orders query is adjusted as late as possible ([#126]).
* Update the plugin license to GPLv3+ to match WooCommerce core ([#123]).
* Define the compatibility policy for the plugin with regards to WordPress, WooCommerce, and PHP versions ([#120], [#127]).
* Introduce PHPStan for static code analysis ([#116], [#117], props @szepeviktor).
* Refresh and update Composer configuration and dependencies ([#121], [#124]).

## [Version 1.0.0 (Release Candidate 2)] - 2018-12-14

* Reduced overhead of PHP autoloader ([#86], props @schlessera).
* Converted the `customer_user_agent` column from `varchar(200)` to `text` ([#91]).
* Fixed an issue where `empty()` was being called on a non-variable, which causes a fatal error in PHP < 5.5 ([#94]).
* Prevented empty strings from being saved to the `order_key` column, which causes issues with the column's uniqueness constraint ([#101], props @crstauf).
* Fixed an issue where *existing* invalid emails in the system were causing migration errors as they were re-saved ([#104]).
* Updated Travis CI testing matrix to include WordPress 5.0 ([#103]).
* Repaired the generation of code coverage reports for Coveralls ([#87], [#88]).

## [Version 1.0.0 (Release Candidate)] - 2018-09-25

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


[Unreleased]: https://github.com/liquidweb/woocommerce-custom-orders-table/compare/master...develop
[Version 1.0.0 (Release Candidate 3)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-rc3
[Version 1.0.0 (Release Candidate 2)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-rc2
[Version 1.0.0 (Release Candidate)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-rc1
[Version 1.0.0 (Beta 3)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-beta.3
[Version 1.0.0 (Beta 2)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-beta.2
[Version 1.0.0 (Beta 1)]: https://github.com/liquidweb/woocommerce-custom-orders-table/releases/tag/v1.0.0-beta.1
[#2]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/2
[#5]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/5
[#8]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/8
[#9]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/9
[#12]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/12
[#15]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/15
[#26]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/26
[#28]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/28
[#35]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/35
[#36]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/36
[#38]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/38
[#43]: https://github.com/liquidweb/woocommerce-custom-orders-table/issues/43
[#46]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/46
[#50]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/50
[#51]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/51
[#52]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/52
[#53]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/53
[#60]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/60
[#61]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/61
[#64]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/64
[#65]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/65
[#72]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/72
[#78]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/78
[#79]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/79
[#80]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/80
[#81]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/81
[#82]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/82
[#84]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/84
[#86]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/86
[#87]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/87
[#88]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/88
[#91]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/91
[#94]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/94
[#101]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/101
[#103]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/103
[#104]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/104
[#116]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/116
[#117]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/117
[#120]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/120
[#121]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/121
[#123]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/123
[#124]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/124
[#126]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/126
[#127]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/127
[#138]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/138
[#139]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/139
[#152]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/152
[#154]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/154
[#157]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/157
[#164]: https://github.com/liquidweb/woocommerce-custom-orders-table/pull/164
