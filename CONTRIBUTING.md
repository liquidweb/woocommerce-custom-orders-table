# Contributing to WooCommerce Custom Orders Table

Thank you for your interest in contributing to the WooCommerce Custom Orders Table plugin!


## Reporting bugs and/or suggesting new features

We welcome input from the community on new features for the plugin, as well as reports of anything that doesn't seem to be working properly.

To make a suggestion or report a bug, please [create a new issue within the GitHub repository](https://github.com/liquidweb/woocommerce-order-tables/issues/new) with a descriptive title and as much pertinent information as possible.

When reporting a bug, please include the following information:

* Steps to reproduce (what steps would someone need to take to see the bug in action?)
* The expected behavior (what _should_ happen?)
* The observed behavior (what _is_ happening?)
* Information about your WooCommerce instance — this can easily be obtained via the WooCommerce &rsaquo; Status screen, via the "Get system report" button at the top of that page.


## Contributing code

If you're interested in contributing to the plugin by way of code and/or documentation, please read the following details about the structure of the project:


### Coding conventions

This project adheres to the [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/), and [an `.editorconfig` file](http://editorconfig.org/) is included in the repository to help most <abbr title="Integrated Development Environment">IDE</abbr>s adjust accordingly. The repository also ships with [the WooCommerce git hooks](https://github.com/woocommerce/woocommerce-git-hooks) to aid in development.

### Compatibility policy

WooCommerce Custom Orders Table is designed to take advantage of the latest and greatest versions of WordPress and WooCommerce. Keeping both WordPress and WooCommerce up-to-date ensures that stores are able to take advantage of the latest WooCommerce features and security fixes.

[![GitHub release](https://img.shields.io/github/release/woocommerce/woocommerce.svg)](https://github.com/woocommerce/woocommerce/releases/latest)

As such, the backwards compatibility policy for WooCommerce Custom Orders Table is:

* The plugin must be compatible with two latest **major** versions of WordPress core (e.g. If the current release of WordPress is 5.2.x, the plugin must be compatible with 5.2.x and 5.1.x).
* The plugin must be compatible with the three latest **minor** releases of WooCommerce.
	- WooCommerce 3.0 switched to [semantic versioning](https://woocommerce.wordpress.com/2017/03/13/important-update-regarding-the-upcoming-woocommerce-release-2-7-will-be-3-0-0/), so if WooCommerce 3.9.x is current, the plugin must also be compatible with 3.8.x and 3.7.x.
* In the case that a WordPress release requires a specific version of WooCommerce (for example, WordPress 5.0 requires WooCommerce 3.5.1 or higher), the WordPress compatibility will supersede the WooCommerce compatibility requirement.
* Compatibility will be based on the latest **patch** releases of both WordPress and WooCommerce (e.g. if WooCommerce 3.9.2 patches a bug in WooCommerce 3.9.1, our 3.9.x commitment will be 3.9.2 or newer).

As part of this commitment to compatibility, WooCommerce Custom Orders Table will continue to target PHP 5.6 as a minimum PHP version for the core plugin files until such time that all supported versions of WordPress and WooCommerce raise their minimum PHP version requirements.

The `tests/` directory, however, is accepting of modern (7.x) PHP.


### Branching strategy

This project uses [Gitflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) as a branching strategy:

* `develop` represents the current development version, whereas `master` represents the latest stable release.
* All work should be done in separate feature branches, which should be branched from `develop`.


#### Tagging a new release

When a new release is being prepared, a new `release/vX.X.X` branch will be created from `develop`, version numbers bumped and any last-minute release adjustments made, then the release branch will be merged (via non-fast-forward merge) into `master`.

Once master has been updated, the release should be tagged, then `master` should be merged into `develop`.


### Unit testing

WooCommerce Custom Orders Table extends WooCommerce's own test suite (which uses [the WordPress core testing suite](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)) to provide automated tests for its functionality.

When submitting pull requests, please include relevant tests for your new features and bug-fixes. This helps prevent regressions in future iterations of the plugin, and helps instill confidence in store owners using this to enhance their WooCommerce stores.

Please note that WooCommerce's `master` branch will occasionally include failing tests, which should *not* be treated as a blocker for merging pull requests on this repo as long as the failing tests are within the "core" test suite **and** the same tests are failing against WooCommerce's `master` branch](https://travis-ci.org/woocommerce/woocommerce).

#### Test coverage

[![Coverage Status](https://coveralls.io/repos/github/liquidweb/woocommerce-custom-orders-table/badge.svg?branch=develop)](https://coveralls.io/github/liquidweb/woocommerce-custom-orders-table?branch=develop)

To generate a code coverage report (test coverage percentage as well as areas of untested or under-tested code that could pose risk), you run the following:

```sh
$ composer test-coverage
```

The report will be saved to `tests/coverage/`. Please note that XDebug must be enabled in order to generate code coverage reports!
