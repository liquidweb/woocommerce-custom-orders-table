<?php
/**
 * Dummy test class for WP_CLI_Command.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

use PHPUnit\Framework\Assert;

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	class WP_CLI_Command {

		/**
		 * Assert that the given message was received.
		 *
		 * @param string $message The exact message to search for.
		 * @param string $level   Optional message level to filter by.
		 * @param string $error   The PHPUnit error message if the assertion fails.
		 */
		public function assertReceivedMessage( $message, $level = '', $error = '' ) {
			$logger = WP_CLI::$__logger;

			if ( ! empty( $level ) ) {
				$logger = array_filter( $logger, function ( $log ) use ( $message, $level ) {
					return $level === $log['level'] && $message === $log['message'];
				} );
			} else {
				$logger = array_filter( $logger, function ( $log ) use ( $message ) {
					return $message === $log['message'];
				} );
			}

			if ( empty( $error ) ) {
				if ( ! empty( $level ) ) {
					$error = sprintf( 'Did not see %1$s-level message "%2$s".', $level, $message );
				} else {
					$error = sprintf( 'Did not see message "%2$s".', $message );
				}
			}

			Assert::assertGreaterThan( 0, count( $logger ), $error );
		}

		/**
		 * Assert that the given message was received.
		 *
		 * @param string $message The substring within a message to search for.
		 * @param string $level   Optional message level to filter by.
		 * @param string $error   The PHPUnit error message if the assertion fails.
		 */
		public function assertReceivedMessageContaining( $message, $level = '', $error = '' ) {
			$logger = WP_CLI::$__logger;

			if ( ! empty( $level ) ) {
				$logger = array_filter( $logger, function ( $log ) use ( $message, $level ) {
					return $level === $log['level'] && false !== strpos( $log['message'], $message );
				} );
			} else {
				$logger = array_filter( $logger, function ( $log ) use ( $message ) {
					return false !== strpos( $log['message'], $message );
				} );
			}

			if ( empty( $error ) ) {
				if ( ! empty( $level ) ) {
					$error = sprintf( 'Did not see a %1$s-level message containing "%2$s".', $level, $message );
				} else {
					$error = sprintf( 'Did not see a message containing "%2$s".', $message );
				}
			}

			Assert::assertGreaterThan( 0, count( $logger ), $error );
		}
	}
}
