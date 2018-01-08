# Contributing to WooCommerce Custom Order Tables

Thank you for your interest in WooCommerce Custom Order Tables!


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

This project adheres to the [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/), and [an `.editorconfig` file](http://editorconfig.org/) is included in the repository to help most <abbr title="Integrated Development Environment">IDE</abbr>s adjust accordingly.


### Branching strategy

This project uses [Gitflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) as a branching strategy:

* `develop` represents the current development version, whereas `master` represents the latest stable release.
* All work should be done in separate feature branches, which should be branched from `develop`.


#### Tagging a new release

When a new release is being prepared, a new `release/vX.X.X` branch will be created from `develop`, version numbers bumped and any last-minute release adjustments made, then the release branch will be merged (via non-fast-forward merge) into `master`.

Once master has been updated, the release should be tagged, then `master` should be merged into `develop`.


### Unit testing

WooCommerce Custom Order Tables uses [the WordPress core testing suite](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/) to provide automated tests for its functionality.

When submitting pull requests, please include relevant tests for your new features and bugfixes. This helps prevent regressions in future iterations of the plugin, and helps instill confidence in store owners using this to enhance their WooCommerce stores.

#### Test coverage

To generate a code coverage report (test coverage percentage as well as areas of untested or under-tested code that could pose risk), you run the following:

```sh
$ composer test-coverage
```

The report will be saved to `tests/coverage/`. Please note that XDebug must be enabled in order to generate code coverage reports!
