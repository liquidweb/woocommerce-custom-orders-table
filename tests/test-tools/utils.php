<?php
/**
 * Dummy utility functions for WP-CLI.
 */

namespace WP_CLI\Utils;

use MockProgressBar;

if ( ! function_exists( __NAMESPACE__ . '\make_progress_bar' ) ) {
	function make_progress_bar( $message, $count, $interval = 100 ) {
		return new MockProgressBar( $message, $count, $interval );
	}
}
