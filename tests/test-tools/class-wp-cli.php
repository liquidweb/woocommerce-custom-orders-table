<?php
/**
 * Dummy test class for WP_CLI.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static $__commands = array();
		public static $__logger   = array();

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
	}
}
