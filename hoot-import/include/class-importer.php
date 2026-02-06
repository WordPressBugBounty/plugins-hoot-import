<?php
/**
 * HootImport Admin
 */

namespace HootImport\Inc;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( '\HootImport\Inc\Importer' ) ) :

	class Importer {

		/**
		 * Class Instance
		 */
		private static $instance;

		/**
		 * Set demoslug identifier
		 * @since  1.0
		 * @access public
		 */
		public $demoslug = '';

		/**
		 * Set package
		 * @since  1.0
		 * @access public
		 */
		public $pack = '';

		/**
		 * Mapped Main Menu ID
		 * @since  1.0
		 * @access public
		 */
		public $main_menu = 0;

		/**
		 * Meta Flag
		 * @since  1.8
		 * @access private
		 */
		private $metaflag = 'wphoot';

		/**
		 * Setup Importer
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function __construct() {

			// Add ajax actions
			add_action( 'wp_ajax_hootimport_process', array( $this, 'process' ) );

		}

		/**
		 * Pass script data
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function process() {
			check_ajax_referer( 'hootimportnonce', 'nonce' );
			$response = array();
			// NOTE: ajax currently only sends 'mods' for 'final' type only.
			$mods = isset( $_POST['mods'] ) ? sanitize_text_field( wp_unslash( $_POST['mods'] ) ) : false;
			if ( $mods ) $mods = json_decode( $mods, true );
			if ( !is_array( $mods ) ) $mods = array();
			$mod  = isset( $_POST['mod'] )  ? sanitize_text_field( wp_unslash( $_POST['mod']  ) ) : false;
			if ( $mod  ) $mod  = json_decode( $mod , true );
			if ( !is_array( $mod  ) ) $mod  = array();
			$this->demoslug = !empty( $mod['demoslug'] ) ? sanitize_key( wp_unslash( $mod['demoslug'] ) ) : '';
			$this->pack     = !empty( $mod['pack'] )     ? sanitize_url( wp_unslash( $mod['pack'] ) )     : '';

			// Sanitize data received from $mod
			$type     = !empty( $mod['type'] ) && in_array( $mod['type'], array( 'prepare', 'plugin', 'content', 'final' ) ) ? $mod['type'] : '';
			$slug     = !empty( $mod['value'] )    ? sanitize_key( wp_unslash( $mod['value'] ) )           : '';
			$const    = !empty( $mod['const'] )    ? sanitize_text_field( wp_unslash( $mod['const'] ) )    : '';
			$class    = !empty( $mod['class'] )    ? sanitize_text_field( wp_unslash( $mod['class'] ) )    : '';
			$function = !empty( $mod['function'] ) ? sanitize_text_field( wp_unslash( $mod['function'] ) ) : '';
			$file     = !empty( $mod['file'] )     ? plugin_basename( sanitize_text_field( wp_unslash( $mod['file'] ) ) ) : '';

			if ( empty( $mod ) || empty( $this->demoslug ) ) {
				$response['error'] = esc_html( 'Empty config data', 'hoot-import' );
			} elseif ( $type == 'prepare' ) {
				$response = $this->fetch_files();
				// Do subroutines if any
				if ( is_array( $response ) && isset( $response['success'] ) ) {
					if ( !empty( $mod['subroutines'] ) && is_array( $mod['subroutines'] ) ) {
						if ( in_array( 'wie', $mod['subroutines'] ) ) {
							$response['subroutine'] = $this->prepare_wie();
						}
					}
				}
			} elseif ( $type == 'plugin' ) {
				add_action( 'hootimport_plugin_activated', array( $this, 'plugin_activated' ), 5 );
				$response = $this->process_plugin( $slug, $const, $class, $function, $file );
			} elseif ( $type == 'content' ) {
				$response = $this->process_content( $slug );
			} elseif ( $type == 'final' ) {
				$response = $this->process_final( $mods );
			} else {
				$response['error'] = esc_html( 'Invalid type of content', 'hoot-import' );
			}
			wp_send_json( $response );
			exit();
		}

		/**
		 * Fetch Files from CDN
		 * @param bool $forcefetch Whether to always fetch files from the CDN
		 * @return array
		 */
		public function fetch_files( $forcefetch=false ) {
			// No need to check for freshness as hootimport_cleanup(dir,false) is run when our plugin begins rendering admin page.
			$demopack_dir = hootimport()->demopack_dir;
			$demoslug = $this->demoslug;
			$pack = $this->pack;
			if ( ! $forcefetch && file_exists( "{$demopack_dir}{$demoslug}-json.txt" ) )
				return array( 'success' => esc_html__( 'Files already fetched', 'hoot-import' ) );

			if ( empty( $pack ) || !is_string( $pack ) )
				return array( 'error' => esc_html__( 'Configuration error while fetching files', 'hoot-import' ) );

			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once hootimport()->dir . '/include/class-upgrader.php';
			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new Upgrader( $skin );
			$result   = $upgrader->install( $pack, $demopack_dir );

			if ( is_string( $result ) ) {
				// Error
				return array( 'error' => esc_html( $result ) );
			} else {
				// Success - Setup freshness
				if ( file_exists( "{$demopack_dir}{$demoslug}-json.txt" ) ) {
					set_transient( 'hootimport_freshpack', 'fresh', WEEK_IN_SECONDS * 4 );
				}
				return array( 'success' => esc_html__( 'Fetched files', 'hoot-import' ) );
			}
		}

		/**
		 * Get Package
		 * @since  1.0
		 * @access public
		 * @param string $type content type (xml, wcxml, dat, wie)
		 * @return array
		 */
		public function getpackage( $type ) {
			$demoslug = $this->demoslug;

			if ( empty( $type ) || empty( $demoslug ) || !is_string( $type ) || !is_string( $demoslug ) ) {
				return array( 'error' => esc_html__( 'Configuration error while getting package', 'hoot-import' ) );
			}
			$suffix = $type === 'wcxml' ? '-wc-xml' : '-'.$type;
			$ext    = 'txt';

			$response = array();
			$demopack_dir = hootimport()->demopack_dir;
			$demopack_url = hootimport()->demopack_url;
			$checkfile    = "{$demopack_dir}{$demoslug}{$suffix}.{$ext}";
			$checkfileurl = "{$demopack_url}{$demoslug}{$suffix}.{$ext}";

			if ( file_exists( $checkfile ) )
				return array( 'localfile' => $checkfile, 'fileurl' => $checkfileurl );

			// We should have already fetched the package during 'prepare', but give it one more try in case $checkfile does not exist for some reason.
			$result = $this->fetch_files( true );
			if ( is_array( $result ) && isset( $result['error'] ) ) {
				$response = array( 'error' => esc_html__( 'Error encountered while getting package:', 'hoot-import' ) . ' ' . $result['error'] );
			} elseif ( file_exists( $checkfile ) ) {
				$response = array( 'localfile' => $checkfile, 'fileurl' => $checkfileurl );
			} else {
				$response = array( 'error' => esc_html__( 'File does not exist in downloaded pack.', 'hoot-import' ) );
			}
			return $response;
		}

		/**
		 * Get Setup JSON
		 * @since  1.0
		 * @access public
		 * @return array
		 */
		public function get_setup_json() {
			$setupinfo = array();
			$result = $this->getpackage( 'json' );

			if ( !empty( $result['localfile'] ) && !empty( $result['fileurl'] ) ) {
				if ( ! file_exists( $result['localfile'] ) ) {
					return array( 'error' => esc_html__( 'Setup file does not exist.', 'hoot-import' ) );
				}
				$fileresponse = wp_remote_get( $result['fileurl'] );
				if ( is_wp_error( $fileresponse ) || 200 !== wp_remote_retrieve_response_code( $fileresponse ) ) {
					return array( 'error' => esc_html__( 'Cannot read setup file.', 'hoot-import' ) );
				}
				$file_contents = wp_remote_retrieve_body( $fileresponse );
				$setupinfo = json_decode( $file_contents, true );
				if ( json_last_error() !== JSON_ERROR_NONE || !is_array( $setupinfo ) ) {
					return array( 'error' => esc_html__( 'Invalid Setup file format.', 'hoot-import' ) );
				}
			} elseif ( !empty( $result['error'] ) && is_string( $result['error'] ) ) {
				// If setup files does not exist in downloaded pack, do nothing and return success (no need to signal an error)
				return stripos( $result['error'], 'File does not exist' ) !== false ? array( 'warning' => $result['error'] ) : $result;
			} else {
				return array( 'error' => esc_html__( 'Unknown error encountered while getting setup file', 'hoot-import' ), 'data' => array( 'json', $this->demoslug, $result ) );
			}

			return $setupinfo;
		}

		/**
		 * Process Plugin
		 * @since  1.0
		 * @access public
		 * @return array
		 */
		public function process_plugin( $slug, $const, $class, $function, $file ) {
			// Check if plugin is already installed and activated
			if (
				( $class && class_exists( $class ) ) ||
				( $function && function_exists( $function) ) ||
				( $const && defined( $const ) )
			) {
				return array( 'success' => esc_html__( 'Plugin already installed and activated', 'hoot-import' ) );
			}

			// Check if plugin is already installed but not activated
			if ( $file && file_exists( WP_PLUGIN_DIR . "/{$file}" ) ) {
				if ( ! current_user_can( 'activate_plugin', $file ) ) {
					return array( 'error' => esc_html__( 'You do not have permission to activate plugins', 'hoot-import' ) );
				}
				if ( is_plugin_inactive( $file ) ) {
					$result = activate_plugin( $file );
					// Run immediately after activate_plugin()
					do_action( 'hootimport_plugin_activated', $slug );
					if ( is_wp_error( $result ) ) {
						return array( 'error' => esc_html__( 'Error encountered while activating:', 'hoot-import' ) . ' [' . $result->get_error_code() . '] ' . $result->get_error_message() );
					}
				}
				return array( 'success' => esc_html__( 'Plugin activated', 'hoot-import' ) );
			}

			// Basic Checks
			if ( ! current_user_can( 'install_plugins' ) ) {
				return array( 'error' => esc_html__( 'You do not have permission to install plugins', 'hoot-import' ) );
			}
			if ( !$slug ) {
				return array( 'error' => esc_html__( 'Empty plugin value slug', 'hoot-import' ) );
			}

			// Install and activate plugin
			include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
			include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
			include_once( ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php' );
			include_once( ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php' );
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'short_description' => false,
						'rating' => false,
						'ratings' => false,
						'downloaded' => false,
						'last_updated' => false,
						'added' => false,
						'tags' => false,
						'compatibility' => false,
						'homepage' => false,
						'donate_link' => false,
					),
				)
			);
			if ( is_wp_error( $api ) ) {
				return array( 'error' => esc_html__( 'API error encountered while installing plugin:', 'hoot-import' ) . ' [' . $api->get_error_code() . '] ' . $api->get_error_message() );
			}
			$skin      = new \WP_Ajax_Upgrader_Skin();
			$upgrader  = new \Plugin_Upgrader( $skin );
			$insresult = $upgrader->install( $api->download_link );
			if ( $insresult ) {
				$install_status = install_plugin_install_status( $api );
				if ( current_user_can( 'activate_plugin', $install_status['file'] ) && is_plugin_inactive( $install_status['file'] ) ) {
					$result = activate_plugin( $install_status['file'] );
					// Run immediately after activate_plugin()
					do_action( 'hootimport_plugin_activated', $slug );
					if ( is_wp_error( $result ) ) {
						return array( 'error' => esc_html__( 'Plugin installed but error during activation:', 'hoot-import' ) . ' [' . $result->get_error_code() . '] ' . $result->get_error_message() );
					}
					return array( 'success' => esc_html__( 'Plugin installed and activated', 'hoot-import' ) );
				}
				return array( 'error' => esc_html__( 'Plugin installed but permissions error during activation', 'hoot-import' ) );
			} else {
				return array( 'error' => esc_html__( 'Plugin installation failed', 'hoot-import' ) );
			}
		}

		/**
		 * Process Content
		 * @since  1.0
		 * @access public
		 * @return array
		 */
		public function process_content( $slug ) {
			$response = array();
			$demoslug = $this->demoslug;
			$type = $slug;
			if ( !$type || !$demoslug ) {
				return array( 'error' => esc_html__( 'Error config data for content', 'hoot-import' ) );
			}

			$result = $this->getpackage( $type );

			if ( !empty( $result['localfile'] ) && !empty( $result['fileurl'] ) ) {

				if ( $type === 'xml' ) {
					// Add identifier meta to imported items
					if ( ! apply_filters( 'hootimport_disable_rollback', false ) ) {
						// Terms :: add_term_meta
						add_filter( 'hootimport_wp_import_term_meta',    array( $this, 'add_identifier_meta' ), 5, 3 );
						// Posts and attachments :: add_post_meta
						add_filter( 'hootimport_wp_import_post_meta',    array( $this, 'add_identifier_meta' ), 5, 3 );
						// Comments :: add_comment_meta
						add_filter( 'hootimport_wp_import_comment_meta', array( $this, 'add_identifier_meta' ), 5, 3 );
						// Menu Items
						add_action( 'hootimport_wp_import_menu_item',    array( $this, 'add_menuitem_identifier_meta' ), 5, 3 );
					}
					// Create a transient to map item ids
					add_action( 'hootimport_wp_import_items_processed', array( $this, 'set_idsmap' ), 5, 3 );
					// Reset menu and menu items before import
					add_action( 'hootimport_wp_import_terms_before', array( $this, 'reset_menu' ), 5 );
					// Update custom menu item urls
					add_action( 'hootimport_wp_import_menu_item_customurl', array( $this, 'menu_item_customurl' ), 5 );
					// Re-import home page ('home pages' may be different in different themes - post_exists will not catch this)
					$this->draft_pages( 'xml' );
					// Fire it up
					$response = $this->import_xml( $result['localfile'] );
				}
				elseif ( $type === 'wcxml' ) {
					// NOTE: WC widgets are still imported as part of wie import process
					//       Hence even if user has selected not to import WC XML, WC widgets will still be
					//       imported to maintain appearance (use existing site's WC content)
					$this->map_main_menu();
					if ( ! apply_filters( 'hootimport_disable_rollback', false ) ) {
						add_filter( 'hootimport_wp_import_term_meta',    array( $this, 'add_identifier_meta' ), 5, 3 );
						add_filter( 'hootimport_wp_import_post_meta',    array( $this, 'add_identifier_meta' ), 5, 3 );
						add_filter( 'hootimport_wp_import_comment_meta', array( $this, 'add_identifier_meta' ), 5, 3 );
						add_action( 'hootimport_wp_import_menu_item',    array( $this, 'add_menuitem_identifier_meta' ), 5, 3 );
					}
					add_action( 'hootimport_wp_import_items_processed', array( $this, 'set_idsmap_wc' ), 5, 3 );
					add_filter( 'hootimport_wp_import_terms', array( $this, 'wc_skip_main_menu' ), 5 );
					add_action( 'hootimport_wp_import_terms_before', array( $this, 'reset_menu_wc' ), 5 );
					add_filter( 'hootimport_wp_import_menu_item_menu_id', array( $this, 'wc_menu_item_menu_id' ), 5 );
					add_action( 'hootimport_wp_import_menu_item_customurl', array( $this, 'menu_item_customurl' ), 5 );
					$response = $this->import_xml( $result['localfile'], true );
				}
				elseif ( $type === 'dat' ) {
					// Fire it up
					$response = $this->import_dat( $result['fileurl'] );
				}
				elseif ( $type === 'wie' ) {
					// Update widget data before import
					add_filter( 'hootimport_widget_settings_array', array( $this, 'update_widget_settings_array' ), 5, 4 );
					// Fire it up
					$response = $this->import_wie( $result['fileurl'] );
				}

			} elseif ( !empty( $result['error'] ) && is_string( $result['error'] ) ) {
				return $result;
			} else {
				$response = array( 'error' => esc_html__( 'Unknown error encountered while getting demo file', 'hoot-import' ), 'data' => array( $type, $demoslug, $result ) );
			}

			return $response;
		}

		/**
		 * Import XML
		 * @since  1.0
		 * @access public
		 * @param string $localfile
		 * @param bool $is_wc
		 * @return array
		 */
		public function import_xml( $localfile, $is_wc = false ) {
			$response = array();
			if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
				define( 'WP_LOAD_IMPORTERS', true );
			}

			// Load Importer
			require_once ABSPATH . 'wp-admin/includes/import.php';
			if ( ! class_exists( 'WP_Importer' ) ) {
				$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
				if ( file_exists( $class_wp_importer ) ) {
					require $class_wp_importer;
				}
			}
			include_once hootimport()->dir . '/include/importers/class-wxr-importer.php';

			// Import XML file content
			$wp_import = new \HootImport_WXR_Importer();
			$wp_import->fetch_attachments = ! apply_filters( 'hootimport_disable_fetch_attachments', false );
			ob_start();
			$result = $wp_import->import( $localfile );
			if ( $result === false ) {
				$response[ 'error' ] = ob_get_clean();
			} else {
				$response[ 'success' ] = ob_get_clean();
			}

			return $response;
		}

		/**
		 * Import DAT
		 * @since  1.0
		 * @access public
		 * @param string $fileurl
		 * @return array
		 */
		public function import_dat( $fileurl ) {
			$response = array();

			// Load Importer
			include_once hootimport()->dir . '/include/importers/class-dat-importer.php';

			// Import DAT customizer settings
			$results = \HootImport_DAT_Importer::import( $fileurl );
			if ( is_wp_error( $results ) ) {
				$response['error'] = esc_html__( 'Error encountered while importing Customizer Settings:', 'hoot-import' ) . ' [' . $results->get_error_code() . '] ' . $results->get_error_message();
			} elseif ( $results === true ) {
				$response['success'] = esc_html__( 'Customizer Settings imported successfully', 'hoot-import' );
			} else {
				$response['error'] = esc_html__( 'Unknown error encountered while importing Customizer Settings', 'hoot-import' );
			}

			return $response;
		}

		/**
		 * Prepare WIE
		 * @since  1.8
		 * @access public
		 * @return string
		 */
		public function prepare_wie() {
			$setupinfo = $this->get_setup_json();
			if ( isset( $setupinfo['hootkit-activemods'] ) && is_array( $setupinfo['hootkit-activemods'] ) ) {
				// Save even if it is an empty array (reset all settings!)
				update_option( 'hootkit-activemods', $setupinfo['hootkit-activemods'] );
				return esc_html__( 'HootKit Settings updated', 'hoot-import' );
			}
			return '0';
		}

		/**
		 * Import WIE
		 * @since  1.0
		 * @access public
		 * @param string $fileurl
		 * @return array
		 */
		public function import_wie( $fileurl ) {
			$response = array();

			// Load Importer
			include_once hootimport()->dir . '/include/importers/class-wie-importer.php';

			// Import widgets
			\HootImport_WIE_Importer::setup();
			$results = \HootImport_WIE_Importer::import( $fileurl );
			if ( is_wp_error( $results ) ) {
				$response['error'] = esc_html__( 'Error encountered while importing Widgets:', 'hoot-import' ) . ' [' . $results->get_error_code() . '] ' . $results->get_error_message();
			} elseif ( is_array( $results ) ) {
				$success = '';
				foreach ( $results as $id => $sb ) {
					$success .= '<p>';
					if ( !empty( $sb['name'] ) ) $success .= "<strong>{$sb['name']}</strong><br />";
					if ( !empty( $sb['message'] ) ) $success .= "<em>{$sb['name']}</em><br />";
					$success .= '</p>';
					if ( !empty( $sb['widgets'] ) && is_array( $sb['widgets'] ) ) {
						$success .= '<ol>';
						foreach ( $sb['widgets'] as $widget ) {
							$name = !empty( $widget['name'] ) ? $widget['name'] : '';
							$title = !empty( $widget['title'] ) ? $widget['title'] : '';
							$message = !empty( $widget['message'] ) ? $widget['message'] : '';
							if ( $name || $title ) {
								$success .= "<li><strong>{$name} : {$title}</strong><br /><em>{$message}</em></li>";
							}
						}
						$success .= '</ol>';
					}
				}
				$response['success'] = $success;
			} else {
				$response['error'] = esc_html__( 'Unknown error encountered while importing Widgets', 'hoot-import' );
			}

			return $response;
		}

		/**
		 * Finalize Import
		 * @since  1.0
		 * @access public
		 * @param array $mods
		 * @return array
		 */
		public function process_final( $mods ) {
			$response = array();
			$demoslug = $this->demoslug;

			// Check which mods were a part of the import run
			$hasxml   = false;
			$haswcxml = false;
			$hasdat   = false;
			$haswie   = false;
			if ( is_array( $mods ) ) { foreach ( $mods as $mod ) {
				if ( !empty( $mod['type'] ) && $mod['type'] === 'content' ) {
					    if ( !empty( $mod['value'] ) && $mod['value'] === 'xml'   ) { $hasxml   = true; }
					elseif ( !empty( $mod['value'] ) && $mod['value'] === 'wcxml' ) { $haswcxml = true; }
					elseif ( !empty( $mod['value'] ) && $mod['value'] === 'dat'   ) { $hasdat   = true; }
					elseif ( !empty( $mod['value'] ) && $mod['value'] === 'wie'   ) { $haswie   = true; }
				}
			} }

			// Basic Checks
			if ( !$demoslug ) {
				return array( 'error' => esc_html__( 'Error config data for content', 'hoot-import' ) );
			}

			/*** Get $setupinfo ***/
			$setupinfo = $this->get_setup_json();
			if ( !is_array( $setupinfo ) )
				return array( 'error' => esc_html__( 'Unknown error with Setup JSON', 'hoot-import' ) );
			elseif ( isset( $setupinfo['error'] ) )
				return $setupinfo;

			/*** Setup pages ***/
			if ( $hasxml ) :
				foreach ( $setupinfo as $key => $orig_id ) {
					switch ( $key ) {
						case 'front':
							$orig_id = is_numeric( $orig_id ) ? intval( $orig_id ) : 0;
							if ( ! $orig_id ) { break; }
							$new_id = $this->get_idsmap( 'posts', $orig_id );
							if ( $new_id ) {
								update_option( 'show_on_front', 'page' );
								update_option( 'page_on_front', $new_id );
							} else {
								update_option( 'show_on_front', 'posts' );
							}
							break;
						case 'blog':
							$orig_id = is_numeric( $orig_id ) ? intval( $orig_id ) : 0;
							if ( ! $orig_id ) { break; }
							$new_id = $this->get_idsmap( 'posts', $orig_id );
							if ( $new_id ) {
								update_option( 'page_for_posts', $new_id );
							}
							break;
						case 'blogposts':
							$orig_id = is_numeric( $orig_id ) ? intval( $orig_id ) : 0;
							if ( ! $orig_id ) { break; }
							update_option( 'posts_per_page', $orig_id );
							break;
						default:
							break;
					}
				}
			endif;
			if ( $haswcxml ) :
				foreach ( $setupinfo as $key => $orig_id ) {
					switch ( $key ) {
						case 'shop':
						case 'cart':
						case 'checkout':
						case 'account':
							if ( class_exists( 'WooCommerce' ) ) {
								$orig_id = is_numeric( $orig_id ) ? intval( $orig_id ) : 0;
								if ( ! $orig_id ) { break; }
								$slug = $key === 'account' ? 'myaccount' : $key;
								$new_id = $this->get_idsmap_wc( 'posts', $orig_id );
								$page = get_post( $new_id );
								if ( $page && $page->post_status === 'publish' ) {
									update_option( 'woocommerce_' . $slug . '_page_id', $new_id );
									$args = array(
										'post_type'      => 'page',
										'post_status'    => 'publish',
										'post__not_in'   => array( $new_id ),
										'title'          => $key === 'account' ? 'My account' : ucfirst( $key ),
										'posts_per_page' => -1,
									);
									$other_pages = get_posts( $args );
									foreach ( $other_pages as $other_page ) {
										wp_update_post( array(
											'ID'          => $other_page->ID,
											'post_status' => 'draft',
										) );
									}
								}
							}
							break;
						default:
							break;
					}
				}
			endif;

			/*** Change 'Hello world!' status to draft ***/
			if ( $hasxml ) :
				$hello = hootimport_get_post_type_by_title( 'Hello world!' );
				if ( is_object( $hello ) && $hello->ID ) {
					wp_update_post( array(
						'ID'          => $hello->ID,
						'post_status' => 'draft'
					) );
				}
			endif;

			/*** Update menu locations ***/
			if ( $hasxml || $hasdat ) :
				$main_menu = !empty( $setupinfo['menu'] ) ? $setupinfo['menu'] : 0;
				$main_menu = intval( $main_menu );
				$main_menu = $main_menu ? $this->get_idsmap( 'terms', $main_menu ) : 0;
				$main_menu = $main_menu && term_exists( (int) $main_menu, 'nav_menu' ) ? $main_menu : 0;
				if ( ! $main_menu ) {
					// Try finding one using a slug
					$main_menu = term_exists( 'main-menu', 'nav_menu' );
					if ( $main_menu ) {
						$main_menu = is_array( $main_menu ) ? $main_menu['term_id'] : $main_menu;
					} else {
						$main_menu = term_exists( 'primary-menu', 'nav_menu' );
						if ( $main_menu ) {
							$main_menu = is_array( $main_menu ) ? $main_menu['term_id'] : $main_menu;
						}
					}
				}
				if ( $main_menu ) {
					$locations = get_nav_menu_locations();
					foreach ( $locations as $loc => $checkid ) {
						$locations[ $loc ] = $main_menu;
					}
					set_theme_mod( 'nav_menu_locations', $locations );
				}
			endif;

			/*** Customizer - Page Options ***/
			if ( $hasxml || $hasdat ) :
				if ( !empty( $setupinfo['404'] ) ) {
					$orig_id = intval( $setupinfo['404'] );
					if ( $orig_id ) {
						$new_id = intval( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						if ( $new_id ) { set_theme_mod( '404_custom_page', $new_id ); }
					}
				}
			endif;

			/*** Old themes Slider CPT ***/
			if ( $hasxml || $hasdat ) :
				if ( !empty( $setupinfo['cpt-sliders'] ) && is_array( $setupinfo['cpt-sliders'] ) ) {
					$mapped = array();
					foreach ( $setupinfo['cpt-sliders'] as $orig_id ) {
						$new_id = intval( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						if ( $new_id ) { $mapped[ $orig_id ] = $new_id; }
					}
					$custa = intval( get_theme_mod( 'wt_cpt_slider_a' ) );
					$custb = intval( get_theme_mod( 'wt_cpt_slider_b' ) );
					if ( !empty( $mapped[ $custa ] ) ) { set_theme_mod( 'wt_cpt_slider_a', $mapped[ $custa ] ); }
					if ( !empty( $mapped[ $custb ] ) ) { set_theme_mod( 'wt_cpt_slider_b', $mapped[ $custb ] ); }
				}
			endif;

			/*** Widgets ***/
			if ( $haswie ) :
				// It is difficult to update widgets here as this will entail fetching and parsing wie again.
				// This leads to repetitive code. To keep the code DRY, we use $thi->update_widget_settings_array() hooked into hootimport_widget_settings_array
			endif;

			// flush rewrite rules to ensure page URLs (especially WC pages) are properly set up
			if ( $hasxml || $haswcxml ) {
				update_option( 'hootimport_wc_flush', 1 );
				sleep(2); // prevent race conditions to ensure all data is saved before flushing rewrite rules
				flush_rewrite_rules();
			}

			/*** All done! ***/
			$response['success'] = esc_html__( 'Final setup completed', 'hoot-import' );
			$response['data'] = $setupinfo;
			return $response;
		}

		/**
		 * Perform actions upon plugin activation
		 * @since  1.0
		 * @access public
		 * @param  string $slug
		 * @return void
		 */
		public function plugin_activated( $slug ) {
			// Skip redirecting upon Mappress activation
			if ( $slug === 'mappress-google-maps-for-wordpress' ) {
				delete_transient('_mappress_activation_redirect');
			}
			// Skip redirecting upon Newsletter activation
			if ( $slug === 'newsletter' ) {
				delete_option('newsletter_show_welcome');
			}
		}

		/**
		 * Re-import home page ('home pages' may be different in different themes - post_exists will not catch this)
		 */
		public function draft_pages( $context ) {
			$process = array();
			if ( $context === 'xml' ) {
				$front_page_id = get_option( 'page_on_front' );
				if ( !empty( $front_page_id ) && $front_page_id > 0 ) {
					$process[] = $front_page_id;
				}
				$blog_page_id = get_option( 'page_for_posts' );
				if ( !empty( $blog_page_id ) && $blog_page_id > 0 ) {
					$process[] = $blog_page_id;
				}
				$page_titles = array( 'home page', 'my blog' );
				$pages_query = new \WP_Query( array( 'post_type' => 'page', 'posts_per_page' => -1 ) );
				if ( $pages_query->have_posts() ) {
					while ( $pages_query->have_posts() ) {
						$pages_query->the_post();
						$page_title = strtolower( get_the_title() );
						if ( in_array( $page_title, $page_titles, true ) ) {
							$process[] = get_the_ID();
						}
					}
				}
			}
			if ( !empty( $process ) ) {
				foreach ( $process as $post_id ) {
					wp_update_post( array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					) );
				}
			}
		}

		/**
		 * Add identifier meta for imported terms(cats,tags,nav_menu), posts, attachments and comments
		 * @since  1.0
		 * @access public
		 * @param  array $meta
		 * @return array
		 */
		public function add_identifier_meta( $meta, $id, $obj ) {
			if ( ! is_array( $meta ) ) {
				$meta = array();
			}
			$meta[] = array( 'key' => '_hootimport', 'value' => $this->metaflag );
			return $meta;
		}

		/**
		 * Add identifier meta to imported menu items
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function add_menuitem_identifier_meta( $id, $menu_id, $args ) {
			add_post_meta( $id, '_hootimport', $this->metaflag, true );
		}

		/**
		 * Create a transient to map item ids
		 * Use this transient to map items to new IDs for other imports
		 * We only keep the latest map (if user runs import multiple times)
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function set_idsmap( $processed_terms, $processed_posts, $processed_menu_items ) {
			if ( !$this->demoslug )
				return;
			set_transient( 'hootimport_idsmap_' . $this->demoslug, array(
				'terms'     => $processed_terms,
				'posts'     => $processed_posts,
				'menuitems' => $processed_menu_items
			), WEEK_IN_SECONDS * 4 );
		}
		public function set_idsmap_wc( $processed_terms, $processed_posts, $processed_menu_items ) {
			if ( !$this->demoslug )
				return;
			set_transient( 'hootimport_idsmap_wc_' . $this->demoslug, array(
				'terms'     => $processed_terms,
				'posts'     => $processed_posts,
				'menuitems' => $processed_menu_items
			), WEEK_IN_SECONDS * 4 );
		}

		/**
		 * Get item ids map from transient
		 * @since  1.0
		 * @access public
		 * @param  string $type
		 * @param  int $orig_id
		 * @param  bool $is_wc
		 * @return array
		 */
		public function get_idsmap( $type='', $orig_id=null, $is_wc=false ) {
			static $xml_map = null;
			static $wc_map = null;
			$ids_map = array();

			if ( ! $is_wc ) {
				if ( empty( $xml_map ) || !is_array( $xml_map ) ) {
					$xml_map = !empty( $this->demoslug ) ? get_transient( 'hootimport_idsmap_' . $this->demoslug ) : false;
					$xml_map = !empty( $xml_map ) && is_array( $xml_map ) ? $xml_map : array();
				}
				$ids_map = $xml_map;
			} else {
				if ( empty( $wc_map ) || !is_array( $wc_map ) ) {
					$wc_map = !empty( $this->demoslug ) ? get_transient( 'hootimport_idsmap_wc_' . $this->demoslug ) : false;
					$wc_map = !empty( $wc_map ) && is_array( $wc_map ) ? $wc_map : array();
				}
				$ids_map = $wc_map;
			}

			if ( $type ) {
				if ( $orig_id !== null ) {
					$orig_id = intval( $orig_id );
					return $orig_id && !empty( $ids_map[ $type ] ) && is_array( $ids_map[ $type ] ) && array_key_exists( $orig_id, $ids_map[ $type ] ) ? $ids_map[ $type ][ $orig_id ] : 0;
				} else {
					return !empty( $ids_map[ $type ] ) && is_array( $ids_map[ $type ] ) ? $ids_map[ $type ] : array();
				}
			} else {
				foreach ( array( 'terms', 'posts', 'menuitems' ) as $key ) {
					if ( empty( $ids_map[ $key ] ) || ! is_array( $ids_map[ $key ] ) ) {
						$ids_map[ $key ] = array();
					}
				}
				return $ids_map;
			}
		}
		public function get_idsmap_wc( $type='', $orig_id=null ) {
			return $this->get_idsmap( $type, $orig_id, true );
		}

		/**
		 * Try to set main menu id
		 * This is required to merge WC menu items into main menu
		 * @since  1.0
		 * @access public
		 * @param  array $id
		 * @return void
		 */
		public function map_main_menu() {
			$setupinfo = $this->get_setup_json();
			if ( !is_array( $setupinfo ) || isset( $setupinfo['error'] ) )
				return;
			$main_menu = !empty( $setupinfo['menu'] ) ? $setupinfo['menu'] : false;
			$main_menu = $main_menu ? $this->get_idsmap( 'terms', $main_menu ) : false;
			$this->main_menu = $main_menu && term_exists( (int) $main_menu, 'nav_menu' ) ? $main_menu : 0;
		}
		/**
		 * Reset menu before importing xml menu
		 * @since  1.0
		 * @access public
		 * @param  array $id
		 * @return void
		 */
		public function reset_menu( $terms ) {
			if ( !is_array( $terms ) )
				return;
			$processed = array(); // xml can contain duplicate copies of the same menu
			foreach ( $terms as $term ) {
				if (
					!empty( $term['term_taxonomy'] ) && $term['term_taxonomy'] === 'nav_menu'
					&& !empty( $term['slug'] )
					&& !empty( $term['term_id'] ) && !in_array( $term['term_id'], $processed )
				) {
					$menu = wp_get_nav_menu_object( $term['slug'] );
					if ( $menu && !empty( $menu->term_id ) ) {
						$processed[] = $term['term_id'];
							$menu_items = wp_get_nav_menu_items( $menu->term_id );
							if ( !empty( $menu_items ) && is_array( $menu_items ) ) {
								foreach ( $menu_items as $menu_item ) {
									$flag = get_post_meta( $menu_item->ID, '_hootkitimport', true );
									if ( $flag === $this->metaflag ) {
										wp_delete_post( $menu_item->ID, true ); // 'true' forces permanent deletion
									}
								}
							}
					}
				}
			}
		}
		public function reset_menu_wc( $terms ) {
			// Do nothing if no main menu exists (xml was never imported by user)
			if ( $this->main_menu ) {
				$menu_items = wp_get_nav_menu_items( $this->main_menu );
				if ( $menu_items ) {
					$checkfor = array( 'shop', 'my account', 'cart', 'checkout' );
					foreach ( $menu_items as $menu_item ) {
						if ( in_array( strtolower( $menu_item->title ), $checkfor ) ) {
							wp_delete_post( $menu_item->ID, true );
						}
					}
				}
			}
		}
		/**
		 * Skip adding main menu during wcxml import
		 */
		public function wc_skip_main_menu( $terms ) {
			// Return if no main menu exists (xml was never imported by user)
			if ( !$this->main_menu ) {
				return $terms;
			}
			return array_values( array_filter( $terms, function( $term ) {
				return $term['term_taxonomy'] !== 'nav_menu';
			} ) );
		}
		/**
		 * Merge wcxml menu items to main menu
		 */
		public function wc_menu_item_menu_id() {
			return intval( $this->main_menu ) ? $this->main_menu : false;
		}

		/**
		 * Update custom menu item urls
		 * @since  1.0
		 * @access public
		 * @param  string $url
		 * @return string
		 */
		public function menu_item_customurl( $url ) {
			$rawurl = $url;
			if ( $url ) {
				$slug = !empty( $this->demoslug ) ? str_replace( '-premium', '', $this->demoslug ) : false;
				if ( $slug ) {
					$home_url = home_url( '/' );
					// Dont replace "https://wphoot.com/"
					$replace = apply_filters( 'hootimport_menu_item_customurl_home_replace', array(
						"https://demo.wphoot.com/{$slug}/",
						"https://demo.wphoot.com/content/{$slug}/",
						"https://demosites.wphoot.com/{$slug}/",
						"https://demosites.wphoot.com/content/{$slug}/"
					) );
					$url = str_replace( $replace, $home_url, $url );
					$maybe_replaced = apply_filters( 'hootimport_menu_item_customurl_home_replaced', $url, $slug );
					if ( $maybe_replaced !== $url ) {
						$url = esc_url( $maybe_replaced );
					} else {
						// Update special cases for author urls and 404 url
						$check = rtrim( $url, '/' );
						$author_slugs = array('jasin', 'jasins', 'wphoot', 'admin', 'contact');
						foreach ( $author_slugs as $authslug ) {
							if ( $check === $home_url . 'author/' . $authslug ) {
								if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_author_posts_url' ) ) {
									$authorid = get_current_user_id();
									if ( $authorid ) {
										$authorurl = get_author_posts_url( $authorid );
										$url = $authorurl ? $authorurl : $url;
									}
								}
								break;
							}
						}
						$error_slugs  = array('i-dont-exist', '404', '404-page', 'custom-404-page', 'notfound', 'not-found');
						foreach ( $error_slugs as $errslug ) {
							if ( $check === $home_url . $errslug ) {
								$url = strpos( $rawurl, 'demosites.wphoot.com' ) !== false ? "https://demosites.wphoot.com/{$slug}/{$errslug}" : "https://demo.wphoot.com/{$slug}/{$errslug}";
								break;
							}
						}
					}
				}
			}
			return $url;
		}

		/**
		 * Update widget data before import
		 * NOTE: Adding this during wie import instead of $this->process_final() to keep the code DRY
		 * @since  1.0
		 * @param  array  $widget
		 * @param  string $id_base (widget type)
		 * @param  int    $instance_id_number
		 * @param  array  $available_widgets
		 * @return array
		 */
		public function update_widget_settings_array( $widget, $id_base, $instance_id_number, $available_widgets ) {
			switch ( $id_base ) :

			// Navigation Widget
			case 'nav_menu':
				$new_id = !empty( $widget['nav_menu'] ) ? $this->get_idsmap( 'terms', $widget['nav_menu'] ) : 0;
				if ( $new_id ) {
					$widget['nav_menu'] = $new_id;
				} else {
					// If title exists (it doesn't really!)
					$menu = isset( $widget['title'] ) ? $widget['title'] : '';
					$nav_menu = wp_get_nav_menu_object( $menu );
					if ( is_object( $nav_menu ) && $nav_menu->term_id ) {
						$widget['nav_menu'] = $nav_menu->term_id;
					} else {
						// Add something atleast
						$menus = wp_get_nav_menus();
						if ( !empty( $menus ) && is_array( $menus ) ) {
							foreach ( $menus as $menu ) {
								$widget['nav_menu'] = $menu->term_id;
								break;
							}
						} else {
							// No menu exists in user's site
						}
					}
				}
				break;

			// Text Widget
			// Shortcodes - CF7, Slider CPT
			case 'text':
				$text = isset( $widget['text'] ) ? $widget['text'] : '';
				/* CF7 */
				if ( strpos( $text, '[contact-form-7' ) !== false ) {
					// Get the form title
					$title = preg_match( '/title="([^"]*)"/', $text, $matches ) ? $matches[1] : '';
					// Get form if exists
					$form = $title ? hootimport_get_post_type_by_title( $title, 'wpcf7_contact_form' ) : false;
					// Get hash id or post id (cf7 works with both)
					// If not, CF7 does a good job finding the form just by title as well (when id not present)
					$hash = '';
					if ( is_object( $form ) && $form->ID ) {
						$hash = get_post_meta( $form->ID, '_hash', true );
						$hash = $hash ? substr( $hash, 0, 7 ) : $form->ID;
					}
					// Update ID
					$widget['text'] = preg_replace( '/id="[^"]*"/', 'id="' . $hash . '"', $text );
				}
				/* Slider CPT */
				elseif ( strpos( $text, '[hoot_slider' ) !== false ) {
					preg_match_all('/\[hoot_slider id="(\d+)"\]/', $text, $matches);
					$orig_ids = !empty( $matches[1] ) && is_array( $matches[1] ) ? $matches[1] : array(); // Contains all IDs found
					foreach ( $orig_ids as $orig_id ) {
						$new_id = intval( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						if ( $new_id ) {
							$text = str_replace('id="' . $orig_id . '"', 'id="' . $new_id . '"', $text);
						}
					}
					$widget['text'] = $text;
				}
				break;

			// Gallery
			case 'media_gallery':
				$widget['ids'] = !empty( $widget['ids'] ) && is_array( $widget['ids'] ) ? $widget['ids'] : array();
				foreach ( $widget['ids'] as $key => $orig_id ) {
					$new_id = $this->get_idsmap( 'posts', $orig_id );
					if ( $new_id ) {
						$widget['ids'][ $key ] = $new_id;
					} else {
						unset( $widget['ids'][ $key ] );
					}
				}
				// Reorder incase some items were removed
				$widget['ids'] = array_values( $widget['ids'] );
				break;

			// Image
			case 'media_image':
				$orig_id = !empty( $widget['attachment_id'] ) ? intval( $widget['attachment_id'] ) : 0;
				$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
				if ( $new_id && $new_id !== $orig_id ) {
					$attachment = get_post( $new_id );
					if ( $attachment ) {
						$widget['attachment_id'] = $new_id;
						$widget['url'] = wp_get_attachment_url( $new_id );
						$widget['size'] = 'full';
						$image_meta = wp_get_attachment_metadata( $new_id );
						$widget['width'] = isset( $image_meta['width'] ) ? $image_meta['width'] : '';
						$widget['height'] = isset( $image_meta['height'] ) ? $image_meta['height'] : '';
					}
				}
				break;

			// Hoot 1.0 Themes
			case 'hoot-blog-widget': 
			case 'hoot-posts-blocks-widget': 
			case 'hoot-post-list-widget': 
			case 'hoot-posts-list-widget':
			case 'hoot-post-grid-widget':
				$orig_id = !empty( $widget['category'] ) ? intval( $widget['category'] ) : 0;
				$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'terms', $orig_id ) : 0;
				if ( $new_id && $new_id !== $orig_id ) {
					$widget['category'] = $new_id;
				}
				break;
			case 'hoot-content-blocks-widget':
				if ( !empty( $widget['boxes'] ) && is_array( $widget['boxes'] ) ) {
					foreach ( $widget['boxes'] as $key => $box ) {
						$orig_id = is_array( $box ) && !empty( $box['page'] ) ? intval( $box['page'] ) : 0;
						$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						if ( $new_id && $new_id !== $orig_id ) {
							$widget['boxes'][ $key ]['page'] = $new_id;
						}
					}
				}
				break;

			// Hoot 2.0 Themes - Images
			// HootKit Widgets - Images
			// check for image in both xml and wcxml mapped ids

			case 'hoot-vcards-widget':
			case 'hootkit-profile':
				$orig_id = !empty( $widget['image'] ) ? intval( $widget['image'] ) : 0;
				$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
				$new_id = !empty( $new_id ) ? $new_id : $this->get_idsmap_wc( 'posts', $orig_id ); 
				if ( $new_id && $new_id !== $orig_id ) {
					$widget['image'] = $new_id;
				}
				break;
			case 'hootkit-cover-image':
				$orig_id = !empty( $widget['image'] ) ? intval( $widget['image'] ) : 0;
				$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
				$new_id = !empty( $new_id ) ? $new_id : $this->get_idsmap_wc( 'posts', $orig_id );
				if ( $new_id && $new_id !== $orig_id ) {
					$widget['image'] = $new_id;
				}
				if ( !empty( $widget['boxes'] ) && is_array( $widget['boxes'] ) ) {
					foreach ( $widget['boxes'] as $key => $value ) {
						$orig_id = is_array( $value ) && !empty( $value['image'] ) ? intval( $value['image'] ) : 0;
						$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						$new_id = !empty( $new_id ) ? $new_id : $this->get_idsmap_wc( 'posts', $orig_id );
						if ( $new_id && $new_id !== $orig_id ) {
							$widget['boxes'][ $key ]['image'] = $new_id;
						}
					}
				}
				break;
			case 'hootkit-content-blocks':
			case 'hootkit-content-grid':
			case 'hootkit-slider-carousel':
			case 'hootkit-slider-image':
			case 'hootkit-vcards':
				$term = '';
				if ( $id_base === 'hootkit-content-blocks'  || $id_base === 'hootkit-content-grid' ) $term = 'boxes';
				if ( $id_base === 'hootkit-slider-carousel' || $id_base === 'hootkit-slider-image' ) $term = 'slides';
				if ( $id_base === 'hoot-vcards-widget'      || $id_base === 'hootkit-vcards'       ) $term = 'vcards';
				if ( $term && !empty( $widget[ $term ] ) && is_array( $widget[ $term ] ) ) {
					foreach ( $widget[ $term ] as $key => $value ) {
						$orig_id = is_array( $value ) && !empty( $value['image'] ) ? intval( $value['image'] ) : 0;
						$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'posts', $orig_id ) : 0;
						$new_id = !empty( $new_id ) ? $new_id : $this->get_idsmap_wc( 'posts', $orig_id );
						if ( $new_id && $new_id !== $orig_id ) {
							$widget[ $term ][ $key ]['image'] = $new_id;
						}
					}
				}
				break;


			// HootKit Widgets - Categories
			// check if category is array or not. (older versions)
			case 'hootkit-posts-blocks':
			case 'hootkit-slider-postcarousel':
			case 'hootkit-posts-grid':
			case 'hootkit-posts-list':
			case 'hootkit-slider-postlistcarousel':
			case 'hootkit-slider-postimage':
			case 'hootkit-ticker-posts':
				foreach ( array( 'exccategory', 'category' ) as $term ) {
					if ( !empty( $widget[ $term ] ) && is_array( $widget[ $term ] ) ) {
						foreach ( $widget[ $term ] as $key => $orig_id ) {
							$orig_id = is_scalar( $orig_id ) ? intval( $orig_id ) : 0;
							$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'terms', $orig_id ) : 0;
							if ( $new_id ) {
								$widget[ $term ][ $key ] = $new_id;
							} else {
								unset( $widget[ $term ][ $key ] );
							}
						}
					} elseif ( !empty( $widget[ $term ] ) ) {
						$orig_id = is_scalar( $widget[ $term ] ) ? intval( $widget[ $term ] ) : 0;
						$new_id = !empty( $orig_id ) ? $this->get_idsmap( 'terms', $orig_id ) : 0;
						if ( $new_id ) {
							$widget[ $term ] = $new_id;
						} else {
							$widget[ $term ] = '';
						}
					}
				}
				break;

			// HootKit Widgets - Product Categories
			// check if category is array or not. (older versions)
			case 'hootkit-products-blocks':
			case 'hootkit-slider-productcarousel':
			case 'hootkit-products-list':
			case 'hootkit-slider-productlistcarousel':
			case 'hootkit-products-ticker':
				foreach ( array( 'exccategory', 'category' ) as $term ) {
					if ( !empty( $widget[ $term ] ) && is_array( $widget[ $term ] ) ) {
						foreach ( $widget[ $term ] as $key => $orig_id ) {
							$orig_id = is_scalar( $orig_id ) ? intval( $orig_id ) : 0;
							$new_id = !empty( $orig_id ) ? $this->get_idsmap_wc( 'terms', $orig_id ) : 0;
							if ( $new_id ) {
								$widget[ $term ][ $key ] = $new_id;
							} else {
								unset( $widget[ $term ][ $key ] );
							}
						}
					} elseif ( !empty( $widget[ $term ] ) ) {
						$orig_id = is_scalar( $widget[ $term ] ) ? intval( $widget[ $term ] ) : 0;
						$new_id = !empty( $orig_id ) ? $this->get_idsmap_wc( 'terms', $orig_id ) : 0;
						if ( $new_id ) {
							$widget[ $term ] = $new_id;
						} else {
							$widget[ $term ] = '';
						}
					}
				}

			default: break;
			endswitch;

			return $widget;
		}

		/**
		 * Returns the instance
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

	}

	Importer::get_instance();

endif;