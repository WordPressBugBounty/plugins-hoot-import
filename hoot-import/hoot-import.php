<?php
/**
 * Plugin Name:       Hoot Import
 * Description:       Hoot Import lets you import demo content for WordPress themes by wpHoot.
 * Version:           1.7.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            wphoot
 * Author URI:        https://wphoot.com/
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain:       hoot-import
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * The core plugin class.
 * @since   1.0
 * @package HootImport
 */
if ( ! class_exists( 'HootImport' ) ) :

	class HootImport {

		/**
		 * Plugin Info
		 * @since  1.0
		 * @access public
		 */
		public $version;
		public $name;
		public $slug;
		public $file;
		public $dir;
		public $uri;
		public $plugin_basename;

		/**
		 * Theme Config
		 * @since  1.0
		 * @access public
		 */
		public $demopack_dir;
		public $demopack_url;
		public $theme_config = array();

		/**
		 * Constructor method.
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function __construct() {

			// Plugin Info
			$this->version         = '1.7.1';
			$this->name            = 'Hoot Import';
			$this->slug            = 'hoot-import';
			$this->file            = __FILE__;
			$this->dir             = trailingslashit( plugin_dir_path( __FILE__ ) );
			$this->uri             = trailingslashit( plugin_dir_url( __FILE__ ) );
			$this->plugin_basename = plugin_basename( __FILE__ );

			// Setup Demopack Directory
			$import_dir = '/hoot-import-demofiles/';
			$upload_dir = wp_upload_dir( null, false );
			$this->demopack_dir = $upload_dir['basedir'] . $import_dir;
			$this->demopack_url = $upload_dir['baseurl'] . $import_dir;

			// Load Text Domain
			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

			// DeActivation Hook
			register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

			// Load Plugin Files and Helpers
			$this->loader();

		}

		/**
		 * Load Plugin Files and Helpers
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function loader() {

			if ( is_admin() ) {

				// Setup theme config (filter added at after_setup_theme hook)
				add_action( 'init', array( $this, 'setup_theme_config' ), 5 );

				// Load Files
				require_once( $this->dir . 'include/functions.php' );
				require_once( $this->dir . 'include/class-importer.php' );
				require_once( $this->dir . 'include/class-admin.php' );

			}
		}

		/**
		 * Load Plugin Text Domain
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain(
				$this->slug,
				false,
				dirname( $this->plugin_basename ) . '/languages/'
			);
		}

		/**
		 * DeActivation Hook
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function deactivation() {
			delete_option( 'hootimport_admin_footer' );
			hootimport_cleanup( $this->demopack_dir, true );
		}

		/**
		 * Setup theme config
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function setup_theme_config() {
			// Theme Config
			$this->theme_config = apply_filters( 'hootimport_theme_config', array() );
			if ( ! is_array( $this->theme_config ) ) {
				$this->theme_config = array();
			}
		}

		/**
		 * Get theme config
		 * @since  1.0
		 * @access public
		 * @return mixed
		 */
		public function get_theme_config( $key ) {
			return ( is_array( $this->theme_config ) && isset( $this->theme_config[ $key ] ) ) ? $this->theme_config[ $key ] : null;
		}

		/**
		 * Returns the instance
		 * @since  1.0
		 * @access public
		 * @return object
		 */
		public static function get_instance() {
			static $instance = null;
			if ( is_null( $instance ) ) {
				$instance = new self;
			}
			return $instance;
		}

	}

	/**
	 * Gets the instance of the `HootImport` class. This function is useful for quickly grabbing data
	 * used throughout the plugin.
	 * @since  1.0
	 * @access public
	 * @return object
	 */
	function hootimport() {
		return HootImport::get_instance();
	}

	// Lets roll!
	hootimport();

endif;