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
		public static $__counts   = array(
			'debug'   => 0,
			'info'    => 0,
			'success' => 0,
			'warning' => 0,
			'error'   => 0,
		);

		public static function add_command( $name, $callable, $args = array() ) {
			self::$__commands[] = func_get_args();
		}

		public static function log( $message ) {
			self::$__logger[] = array(
				'level'   => 'info',
				'message' => $message,
			);
		}

		public static function success( $message ) {
			self::$__logger[] = array(
				'level'   => 'success',
				'message' => $message,
			);
		}

		public static function warning( $message ) {
			self::$__logger[] = array(
				'level'   => 'warning',
				'message' => $message,
			);
		}

		public static function error( $message ) {
			self::$__logger[] = array(
				'level'   => 'error',
				'message' => $message,
			);
		}
	}
}
