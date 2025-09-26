<?php
/**
 * HootImport Admin
 */

namespace HootImport\Inc;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( '\HootImport\Inc\Upgrader' ) ) :

	class Upgrader extends \WP_Upgrader {

		/**
		 * Get Package
		 * @since  1.0
		 * @access public
		 * @param string $pack
		 * @param string $dir
		 * @return bool|string
		 */
		public function install( $pack, $dir ) {
			$this->init();
			// returns array|false|WP_Error The result from self::install_package() on success, otherwise a WP_Error,
			//                              or false if unable to connect to the filesystem.
			$ret = $this->run( array(
				'package' => $pack,
				'destination' => $dir,
				'clear_destination' => true, // Default false.
				'clear_working' => true,
				'hook_extra' => array(
					'type' => 'demo',
					'action' => 'install',
				),
			) );
			if ( is_wp_error( $ret ) ) {
				return '[' . $ret->get_error_code() . '] ' . $ret->get_error_message();
			}
			if ( ! $ret ) {
				return 'unable to connect to the filesystem';
			}
			return true;
		}

	}

endif;