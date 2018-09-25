<?php
/**
 * Dummy test class for WP_CLI.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static $__commands = array();
		public static $__logger   = array();
		public static $__counts   = array();

		public static function reset() {
			self::$__commands = array();
			self::$__logger   = array();
			self::$__counts   = array(
				'debug'   => 0,
				'info'    => 0,
				'success' => 0,
				'warning' => 0,
				'error'   => 0,
			);
		}

		public static function add_command( $name, $callable, $args = array() ) {
			self::$__commands[] = func_get_args();
		}

		public static function debug( $message ) {
			return self::logMessage( 'debug', $message );
		}

		public static function log( $message ) {
			return self::logMessage( 'info', $message );
		}

		public static function success( $message ) {
			return self::logMessage( 'success', $message );
		}

		public static function warning( $message ) {
			return self::logMessage( 'warning', $message );
		}

		public static function error( $message ) {
			return self::logMessage( 'error', $message );
		}

		protected static function logMessage( $level, $message ) {
			self::$__logger[] = array(
				'level'   => $level,
				'message' => $message,
			);

			self::$__counts[ $level ]++;
		}
	}
}
