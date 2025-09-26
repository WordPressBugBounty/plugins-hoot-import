<?php
/**
 * Import Customize Option
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Customizer Demo Importer Option class.
 * @see WP_Customize_Setting
 */
final class HootImport_Customize_Option extends WP_Customize_Setting {
	/**
	 * Import an option value for this setting.
	 * @param mixed $value The value to update.
	 * @return void
	 */
	public function import( $value ) {
		$this->update( $value );
	}
}
