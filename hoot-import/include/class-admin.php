<?php
/**
 * HootImport Admin
 */


namespace HootImport\Inc;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( '\HootImport\Inc\Admin' ) ) :

	class Admin {

		/**
		 * Class Instance
		 */
		private static $instance;

		/**
		 * Single Demo - Pack Manifest
		 * @since  1.0
		 * @access public
		 */
		public $demopack = array();

		/**
		 * Single Demo - slug identifier
		 * @since  1.0
		 * @access public
		 */
		public $demoslug = '';

		/**
		 * Setup Admin
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function __construct() {

			// Add action links on Plugin Page
			add_action( 'plugin_action_links_' . hootimport()->plugin_basename, array( $this, 'plugin_action_links' ), 10, 4 );

			// Add admin page
			add_action( 'admin_menu', array( $this, 'add_page' ), 5 );

			// Add menu class
			add_action( 'admin_head', array( $this, 'add_menu_classes' ) );

			// Load admin page assets
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'localize_script' ) );

			// Footer rating text.
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 1 );
			add_action( 'wp_ajax_hootimport_rated', array( $this, 'admin_footer_textrated' ) );

			// Disable the WooCommerce Setup Wizard on Hoot Import page only
			add_action( 'current_screen', array( $this, 'woocommerce_disable_setup_wizard' ) );

			// Flush rewrite rules from a recent WooCommerce XML import
			if ( get_option( 'hootimport_wc_flush' ) ) {
				add_action( 'admin_menu', array( $this, 'woocommerce_flush' ), 5 );
			}

		}

		/**
		 * Add action links
		 * @param string[] $actions     An array of plugin action links. By default this can include 'activate',
		 *                              'deactivate', and 'delete'. With Multisite active this can also include
		 *                              'network_active' and 'network_only' items.
		 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param array    $plugin_data An array of plugin data. See `get_plugin_data()`.
		 * @param string   $context     The plugin context. By default this can include 'all', 'active', 'inactive',
		 *                              'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
		 * @since  1.0
		 * @access public
		 */
		public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
			$actions['manage'] = '<a href="' . esc_url( admin_url( 'themes.php?page=' . hootimport()->slug ) ) . '">' . esc_html__( 'Import Demo', 'hoot-import' ) . '</a>';
			return $actions;
		}

		/**
		 * Add Admin Page
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function add_page(){
			$menu_title = hootimport()->get_theme_config('menu_title') ?? esc_html__( 'Import Theme Demo', 'hoot-import' );
			add_submenu_page(
				'themes.php',
				esc_html__( 'Import Demo', 'hoot-import' ),
				$menu_title,
				'import', // capability
				hootimport()->slug,
				array( $this, 'render_admin' ),
				1,
			);
		}

		/**
		 * Add class to menu
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function add_menu_classes() {
			global $submenu;
			if ( isset( $submenu['themes.php'] ) ) {
				$submenu_class = 'hide-if-no-js';
				foreach ( $submenu['themes.php'] as $order => $menu_item ) {
					if ( $menu_item[2] === hootimport()->slug ) {
						$submenu['themes.php'][ $order ][4] = empty( $menu_item[4] ) ? $submenu_class : $menu_item[4] . ' ' . $submenu_class;
						break;
					}
				}
			}
		}

		/**
		 * Load admin page assets
		 * @since  1.0
		 * @access public
		 * @param string $hook
		 * @return void
		 */
		public function enqueue_scripts( $hook ) {
			$slug = hootimport()->slug;
			if ( $hook == "appearance_page_{$slug}" ) {
				// Scripts
				wp_enqueue_script( 'jquery-confirm', hootimport()->uri . 'assets/jquery-confirm.min.js', array( 'jquery' ), '3.3.4', true );
				wp_enqueue_script( $slug, hootimport()->uri . 'assets/hootimport.js', array( 'jquery', 'jquery-confirm' ), hootimport()->version, true );
				// Styles
				wp_enqueue_style( 'jquery-confirm', hootimport()->uri . 'assets/jquery-confirm.min.css', array(), '3.3.4' );
				wp_enqueue_style( $slug, hootimport()->uri . 'assets/hootimport.css', array( 'jquery-confirm' ), hootimport()->version );
			}
		}

		/**
		 * Pass script data
		 * @since  1.0
		 * @access public
		 * @param string $hook
		 * @return void
		 */
		public function localize_script( $hook ) {
			$slug = hootimport()->slug;
			if ( $hook == "appearance_page_{$slug}" ) {
				wp_localize_script(
					$slug,
					'hootimportData',
					array(
						'ajaxurl'   => admin_url( 'admin-ajax.php' ),
						'nonce'     => wp_create_nonce( 'hootimportnonce' ),
						'import_action' => 'hootimport_process',
						'strings' => array(
							'processing_plugin' => esc_html__( 'Processing...', 'hoot-import' ),
							'active_process_alert' => esc_html__( 'Please wait. Another process in being performed.', 'hoot-import' ),
							'confirm_msg' => '<h2>' . esc_html__( 'Please Note:', 'hoot-import' ) . '</h2>'
											. esc_html__( 'Before you import the demo content, please note the following points:', 'hoot-import' )
											. '<ol>'
												. '<li class="hootimp-highlightbg"><strong>' . esc_html__( 'The import process will automatically fetch the required files and images from wpHoot servers.', 'hoot-import' ) . '</strong></li>'
												. '<li class="hootimp-highlight"><strong>' . esc_html__( 'It is highly recommended to import demo on a fresh WordPress installation to replicate it exactly like the theme demo.', 'hoot-import' ) . '</strong></li>'
												. '<li><strong>' . esc_html__( 'None of the existing posts, pages, attachments, menus and other data on your site will be deleted during the import.', 'hoot-import' ) . '</strong></li>'
												. '<li>' . esc_html__( 'Please click the Import button and wait. This process can take a few minutes depending upon your server.', 'hoot-import' ) . '</li>'
											. '</ol>',
							'confirm_primarybtn' => esc_html__( 'Start Import', 'hoot-import' ),
							'confirm_cancelbtn' => esc_html__( 'Cancel', 'hoot-import' ),
							'loading_step' => esc_html__( 'Step', 'hoot-import' ),
							'loading_plugin' => esc_html__( 'Installing', 'hoot-import' ),
							'loading_prepare' => esc_html__( 'Fetching required files', 'hoot-import' ),
							'loading_content' => esc_html__( 'Importing', 'hoot-import' ),
							'loading_xml' => esc_html__( 'Please Wait. This step may take a few minutes.', 'hoot-import' ),
							'stillloading_xml' => esc_html__( 'Still working... Please wait...', 'hoot-import' ),
							'loading_final' => esc_html__( 'Finalizing Settings...', 'hoot-import' ),
						),
					)
				);
			}
		}

		/**
		 * Change the admin footer text for Hoot Import page
		 * @param  string $footer_text
		 * @return string
		 */
		public function admin_footer_text( $footer_text ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return $footer_text;
			}
			$screen = get_current_screen();
			if ( 'appearance_page_hoot-import' === $screen->id && ! get_option( 'hootimport_admin_footer' ) ) {
				$footer_text =
					/* Translators: The %s are placeholders for HTML, so the order can't be changed. */
					sprintf( esc_html__( 'If you like Hoot Import plugin, please consider rating us a %1$s %3$s on WordPress.org%2$s to help us spread the word.', 'hoot-import' ), '<a class="hootimp-rateus" href="https://wordpress.org/support/plugin/hoot-import/reviews/?rate=5#new-post" rel="nofollow" target="_blank" data-rated="' . esc_attr__( 'Thanks :)', 'hoot-import' ) . '">', '</a>', '&#9733;&#9733;&#9733;&#9733;&#9733;' );
			}
			return $footer_text;
		}

		/**
		 * Admin Footer - Rated
		 */
		public function admin_footer_textrated() {
			check_ajax_referer( 'hootimportnonce', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( -1 );
			}
			update_option( 'hootimport_admin_footer', 1 );
			wp_die();
		}

		/**
		 * Disable the WooCommerce Setup Wizard on Hoot Import page only
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function woocommerce_disable_setup_wizard( $screen ) {
			if ( is_object( $screen ) && !empty( $screen->id ) && 'appearance_page_hoot-import' === $screen->id ) {
				add_filter( 'woocommerce_enable_setup_wizard', '__return_false', 1 );
			}
		}

		/**
		 * Flush rewrite rules from a recent WooCommerce XML import
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function woocommerce_flush(){
			flush_rewrite_rules();
			delete_option( 'hootimport_wc_flush' );
		}

		/**
		 * Get manifest and set $demopack
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function set_demopack() {
			$manifest = include( hootimport()->dir . 'include/demopacks.php' );
			if ( empty( $manifest ) || !is_array( $manifest ) || empty( $manifest['cdn_url'] ) ) {
				$this->demopack = array( 'error' => 'invalid_manifest' );
			} else {
				$demoslug = isset( $manifest[ $this->demoslug ] ) && is_array( $manifest[ $this->demoslug ] ) ? $this->demoslug : str_ireplace( '-premium', '', $this->demoslug );
				if ( !empty( $manifest[ $demoslug ] ) && is_array( $manifest[ $demoslug ] ) ) {
					$this->demopack = array(
						'pack' => trailingslashit( $manifest['cdn_url'] ) . $this->demoslug . '.zip',
						'img' => trailingslashit( $manifest['cdn_base'] ) . 'images/hootimport/' . ( !empty( $manifest[ $demoslug ]['img'] ) ? $manifest[ $demoslug ]['img'] : $demoslug . '.jpg' ),
						'plugins' => !empty( $manifest[ $demoslug ]['plugins'] ) ? $manifest[ $demoslug ]['plugins'] : array(),
					);
				} else {
					$this->demopack = array( 'error' => 'incompatible_theme' );
				}
			}
		}

		/**
		 * Render Page
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function render_admin(){
			// Set demoslug and demopack
			$demoslug = hootimport()->get_theme_config('id');
			$this->demoslug = !empty( $demoslug ) && is_string( $demoslug ) ? strtolower( $demoslug ) : '';
			if ( !empty( $this->demoslug ) ) {
				$this->set_demopack();
			}

			// Set compatibility
			$is_compatible = !empty( $this->demopack ) && is_array( $this->demopack ) && !empty( $this->demopack['pack'] );

			// Regular Maintenance tasks
			if ( $is_compatible ) {
				$force_cleanup = isset( $_GET['refreshdemo'] ) && $_GET['refreshdemo'] === 'true' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_GET['_wpnonce'] ) ), 'hootimport_refresh_demo_data_nonce' ) ? true : false;
				hootimport_cleanup( hootimport()->demopack_dir, $force_cleanup );
			}
			?>
			<div class="hootimp-wrap">

				<div class="hootimp-header">
					<h3><?php esc_html_e( 'Hoot Import', 'hoot-import' ); ?></h3>
					<h4><?php
						/* Translators: %s is the plugin version. */
						printf( esc_html__( 'Plugin Version: %1$s', 'hoot-import' ), esc_html( hootimport()->version ) );
					?></h4>
				</div><!-- .hootimp-header -->

				<div class="hootimp-body">
					<h1 class="hidden"><?php esc_html_e( 'Hoot Import', 'hoot-import' ) ?></h1>

					<?php if ( !$is_compatible ) : ?>
						<div class="hootimp-content"><?php
							$icnotice = hootimport()->get_theme_config('incompatible');
							if ( !empty( $icnotice ) && is_string( $icnotice ) ) {
								echo '<div class="notice notice-warning">' . wp_kses_post( wpautop( $icnotice ) ) . '</div>';
							} elseif ( empty( $this->demopack )
								|| !is_array( $this->demopack )
								|| ( isset( $this->demopack['error'] ) && $this->demopack['error'] === 'incompatible_theme' )
							) {
								$activetmpl = wp_get_theme();
								$activetmpl_author = ($activetmpl->parent()) ? $activetmpl->parent()->get('Author') : $activetmpl->get('Author');
								echo '<div class="notice notice-info">';
									if ( stripos( $activetmpl_author, 'wphoot' ) !== false ) {
										echo '<p>' . esc_html__( 'The current theme version is not supported by Hoot Import plugin.', 'hoot-import' ) . '</p>';
										echo '<p>' . esc_html__( 'Please update the theme and Hoot Import plugin to their latest versions.', 'hoot-import' ) . '</p>';
									} else {
										echo '<p>' . esc_html__( 'The current theme is not supported by Hoot Import plugin.', 'hoot-import' ) . '</p>';
										/* Translators: The %s are placeholders for HTML, so the order can't be changed. */
										echo '<p>' . sprintf( esc_html__( 'Please make sure you are using an official %1$swpHoot Theme%2$s', 'hoot-import' ), '<a href="https://wordpress.org/themes/author/wphoot/" rel="nofollow">', '</a>' ) . '</p>';
									}
								echo '</div>';
							} else {
								echo '<div class="notice notice-error">';
									if ( isset( $this->demopack['error'] ) && $this->demopack['error'] === 'invalid_manifest' ) {
										echo '<p>' . esc_html__( 'The theme demos manifest is not formatted properly.', 'hoot-import' ) . '</p>';
									} else {
										echo '<p>' . esc_html__( 'An unknown error occurred.', 'hoot-import' ) . '</p>';
									}
								echo '</div>';
							}
						?></div>
					<?php else: ?>
						<div class="hootimp-content hootimp-content-install">
							<div class="hootimp-screenshots">
								<div class="hootimp-screenshot">
									<?php
									if ( !empty( $this->demopack['img'] ) ) {
										echo '<img src="' . esc_url( $this->demopack['img'] ) . '" alt="' . esc_attr__( 'Import Demo', 'hoot-import' ) . '" />';
									} ?>
								</div>
							</div>
							<div class="hootimp-theme-info">
								<h2><?php
									if ( hootimport()->get_theme_config('theme_name') ) {
										echo esc_html( hootimport()->get_theme_config('theme_name') );
										if ( hootimport()->get_theme_config('theme_version') ) {
											echo ' <span>' . esc_html( hootimport()->get_theme_config('theme_version') ) . '</span>';
										}
									} else {
										$theme = wp_get_theme();
										echo esc_html( $theme->get('Name') );
										echo ' <span>' . esc_html( $theme->get('Version') ) . '</span>';
									}
								?></h2>
								<p><?php esc_html_e( 'Importing demo data makes your website look similar to the theme demo. Users often find it easier to start with the demo content and then edit it to fit their needs rather than starting from scratch.', 'hoot-import' ); ?></p>
								<form id="hootimp-form" class="hootimp-form<?php echo ( class_exists( 'WooCommerce' ) ? '' : ' hootimp--nowc' ); ?>">
									<div class="hootimp-noloader">

										<?php $plugin_ops = $this->get_plugins_info();

										// Set plugin status
										$activeplugins = array();
										foreach ( $plugin_ops as $id => $option ) {
											if ( is_array( $option ) && !empty( $option['data'] ) && is_array( $option['data'] ) ) {
												if ( !empty( $option['data']['class'] ) && class_exists( $option['data']['class'] ) ) {
													$activeplugins[ $id ] = $plugin_ops[ $id ];
													$activeplugins[ $id ]['status'] = 'active';
													unset( $plugin_ops[ $id ] );
												} elseif ( !empty( $option['data']['const'] ) && defined( $option['data']['const'] ) ) {
													$activeplugins[ $id ] = $plugin_ops[ $id ];
													$activeplugins[ $id ]['status'] = 'active';
													unset( $plugin_ops[ $id ] );
												} elseif ( !empty( $option['data']['file'] ) && file_exists( WP_PLUGIN_DIR . "/{$option['data']['file']}" ) ) {
													$plugin_ops[ $id ]['status'] = 'installed';
												} else {
													$plugin_ops[ $id ]['status'] = 'unavailable';
												}
											} else { // this shouldn't have happened
												$plugin_ops[ $id ]['status'] = 'unavailable';
											}
										}

										// Divide remaning non active into rcmd categories
										$required = array_filter( $plugin_ops, function( $item ) {
											return !empty( $item['rcmd'] ) && $item['rcmd'] === 'reqd';
										} );
										$recommended = array_filter( $plugin_ops, function( $item ) {
											return !empty( $item['rcmd'] ) && $item['rcmd'] !== 'reqd';
										} );
										$optional = array_filter( $plugin_ops, function( $item ) {
											return empty( $item['rcmd'] );
										} );
										$show_subhead = !empty( $activeplugins ) || ( count(array_filter(array($required, $recommended, $optional))) >= 2 );

										if ( ( !empty( $plugin_ops ) && is_array( $plugin_ops ) ) || ( !empty( $activeplugins ) && is_array( $activeplugins) ) ) : ?>
											<div class="hootimp-op-group">
												<h4><?php esc_html_e( 'Plugins:', 'hoot-import' ); ?></h4>
												<div class="hootimp-h4desc"><?php esc_html_e( 'These plugins have been used on the demo site and are required to replicate the demo content.', 'hoot-import' ); ?></div>
												<?php if ( !empty( $activeplugins ) ) : ?>
													<?php if ( $show_subhead ) : ?><h5><span><?php esc_html_e( 'Active Plugins:', 'hoot-import' ); ?></span></h5><?php endif; ?>
													<?php foreach ( $activeplugins as $id => $plugin ) {
														$this->render_option( 'plugin', $id, $plugin );
													} ?>
												<?php endif; ?>
												<?php if ( !empty( $required ) ) : ?>
													<?php if ( $show_subhead ) : ?><h5><span><?php esc_html_e( 'Required Plugins:', 'hoot-import' ); ?></span></h5><?php endif; ?>
													<?php foreach ( $required as $id => $plugin ) {
														$this->render_option( 'plugin', $id, $plugin );
													} ?>
												<?php endif; ?>
												<?php if ( !empty( $recommended ) ) : ?>
													<?php if ( $show_subhead ) : ?><h5><span><?php esc_html_e( 'Highly Recommended', 'hoot-import' ); ?></span></h5><?php endif; ?>
													<?php foreach ( $recommended as $id => $plugin ) {
														$this->render_option( 'plugin', $id, $plugin );
													} ?>
												<?php endif; ?>
												<?php if ( !empty( $optional ) ) : ?>
													<?php if ( $show_subhead ) : ?><h5><span><?php esc_html_e( 'Optional', 'hoot-import' ); ?></span></h5><?php endif; ?>
													<?php foreach ( $optional as $id => $plugin ) {
														$this->render_option( 'plugin', $id, $plugin );
													} ?>
												<?php endif; ?>
											</div>
										<?php endif; ?>

										<div class="hootimp-op-group">
											<h4><?php esc_html_e( 'Import Content:', 'hoot-import' ); ?></h4>
											<?php
												$this->render_option( 'content', 'xml', array(
													'name' => esc_html__( 'Content XML', 'hoot-import' ),
													'desc' => esc_html__( 'posts, pages, categories, menus, images etc.', 'hoot-import' ),
												) );
												$this->render_option( 'content', 'wcxml', array(
													'name' => esc_html__( 'WooCommerce XML', 'hoot-import' ),
													'desc' => esc_html__( 'products, categories, shop pages etc.', 'hoot-import' ),
													'checked' => class_exists( 'WooCommerce' ),
												) );
												$this->render_option( 'content', 'dat', array(
													'name' => esc_html__( 'Customizer DAT', 'hoot-import' ),
													'desc' => esc_html__( 'Customizer Settings', 'hoot-import' ),
												) );
												$this->render_option( 'content', 'wie', array(
													'name' => esc_html__( 'Widgets WIE', 'hoot-import' ),
												) );
											?>

											<div class="hootimp-action">
												<?php if ( !empty( $this->demoslug ) ) : ?>
													<input type="hidden" name="demo" value="<?php echo esc_attr( $this->demoslug ) ?>" />
												<?php endif; ?>
												<?php if ( !empty( $this->demopack['pack'] ) ) : ?>
													<input type="hidden" name="pack" value="<?php echo esc_attr( $this->demopack['pack'] ) ?>" />
												<?php endif; ?>
												<a href="#" id="hootimp-submit" class="button button-primary button-hero hootimp-submit"><?php esc_html_e( 'Import Demo', 'hoot-import' ) ?></a>
											</div>
										</div>

									</div>
									<div class="hootimp-loader">
										<div class="hootimp-loaderbar"><div></div></div>
										<div id="hootimp-loadermsg"><?php esc_html_e( 'Importing Demo...', 'hoot-import' ); ?></div>
									</div>
									<div class="hootimp-complete">
										<div>
											<div class="hootimp-complete-icon"></div>
											<div><strong><?php esc_html_e( 'All Done.', 'hoot-import' ); ?></strong></div>
										</div>
										<ol>
											<li><?php
											/* Translators: %s are placeholders for HTML, so the order can't be changed. */
											printf( esc_html__( 'To edit Settings - %1$sVisit Customizer%2$s', 'hoot-import' ), '<a href="' . esc_url( admin_url( 'customize.php' ) ) . '" rel="nofollow">', '</a>' );
											?></li>
											<li><?php
											/* Translators: %s are placeholders for HTML, so the order can't be changed. */
											printf( esc_html__( 'To edit Widgets - %1$sVisit Widgets screen%2$s', 'hoot-import' ), '<a href="' . esc_url( admin_url( 'widgets.php' ) ) . '" rel="nofollow">', '</a>' );
											?></li>
											<li><?php
											/* Translators: %s are placeholders for HTML, so the order can't be changed. */
											printf( esc_html__( 'To see the installed demo content - %1$sView Site%2$s', 'hoot-import' ), '<a href="' . esc_url( get_home_url() ) . '" rel="nofollow">', '</a>' );
											?></li>
										</ol>
										<p><a href="#" class="hootimp-show-log"><?php esc_html_e( 'View Log', 'hoot-import' ); ?></a></p>
									</div>
									<div class="hootimp-loaderror notice notice-error">
										<p><?php esc_html_e( 'Import process finished with errors. Please try again later.', 'hoot-import' ); ?></p>
										<div id="hootimp-loaderror-details"></div>
										<?php
										?>
										<p><a href="#" class="hootimp-show-log"><?php esc_html_e( 'View Log', 'hoot-import' ); ?></a></p>
									</div>
									<div class="hootimp-load-details"></div>
								</form>
							</div>
						</div>
						<div class="hootimp-footer">
							<p><span class="dashicons dashicons-update"></span> <a href="<?php
								$current_url = !empty( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
								if ( strpos( $current_url, '_wpnonce' ) === false ) {
									$current_url = add_query_arg( 'refreshdemo', 'true', $current_url );
									$current_url = wp_nonce_url( $current_url, 'hootimport_refresh_demo_data_nonce' );
								}
								echo esc_url( $current_url );
							?>"><?php esc_html_e( 'Refetch Demo Data Files', 'hoot-import' ); ?></a></p>
						</div><!-- .hootimp-footer -->
					<?php endif; ?>
				</div><!-- .hootimp-body -->

			</div><!-- .hootimp-wrap -->

			<?php
		}

		/**
		 * Render Option
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function render_option( $type, $id, $option ){
			if ( is_array( $option ) ) :
				$isplugin = $type === 'plugin' ? true : false;

				$opname = !empty( $option['name'] ) ? $option['name'] : $id;
				$opdesc = !empty( $option['desc'] ) ? $option['desc'] : '';
				$checked = isset( $option['checked'] ) ? !empty( $option['checked'] ) : true;
				$opboxclasses = '';
				// Plugin Stuff
				$opreqd = false;
				$opdata = array();
				$pluginstatus = '';

				if ( ! $isplugin ) {
					$opboxclasses = 'hootimp-opbox hootimp-opbox--content hootimp-opbox--' . $id;
				} else {
					$pluginstatus = !empty( $option['status'] ) && is_string( $option['status'] ) ? $option['status'] : 'unavailable';
					$opreqd = !empty( $option['rcmd'] ) && $option['rcmd'] === 'reqd';
					$opdata = !empty( $option['data'] ) && is_array( $option['data'] ) ? $option['data'] : array();
					if ( $pluginstatus === 'active' || $opreqd ) {
						$checked = true;
					}
					$opboxclasses = 'hootimp-opbox hootimp-opbox--plugin hootimp-opbox--plugin_' . $pluginstatus;
					if ( $opreqd )
						$opboxclasses .= ' hootimp-opbox--plugin_reqd';
					if ( ! $checked )
						$opboxclasses .= ' hootimp-opbox--plugin_noaction';
				}
				?>
				<div class="<?php echo hootimport_sanitize_html_classes( $opboxclasses ) ?>">

					<div class="hootimp-optoggle">
						<?php if ( $isplugin && $pluginstatus === 'active' ) : // active plugins
							?><span class="dashicons dashicons-yes"></span>
						<?php else : // inactive/not-installed plugin OR Not a plugin ?>
							<input type="checkbox"<?php
								echo ' name="' . esc_attr( $type ) . '[]"';
								echo ' value="' . esc_attr( $id ) . '"';
								echo ' data-name="' . esc_attr( $opname ) . '"';
								if ( $checked ) echo ' checked="checked"';
								if ( $isplugin ) {
									foreach ( $opdata as $datakey => $dataval ) {
										echo ' data-' . sanitize_key( $datakey ) . '="' . esc_attr( $dataval ) . '"';
									}
								}
							?> />
							<span class="hootimp-toggle"></span>
						<?php endif; ?>
					</div>

					<div class="hootimp-oplabel">
						<strong><?php echo esc_html( $opname ) ?></strong>
						<?php if ( $opdesc ) {
							echo '<em>(' . esc_html( $opdesc ) . ')</em>';
						} elseif ( $isplugin ) {
							echo '<em><a href="' . esc_url( 'https://wordpress.org/plugins/' . $id . '/' ) . '" target="_blank">' . esc_html( 'View details', 'hoot-import' ) . '</a></em>';
						} ?>
					</div>

					<?php if ( $isplugin ) : ?>
						<div class="hootimp-opnote">
							<div class="hootimp-opnote--active"><span class="dashicons dashicons-yes"></span><?php
								esc_html_e( 'Active', 'hoot-import' );
							?></div>
							<div class="hootimp-opnote--installed"><span class="dashicons dashicons-marker"></span><?php
								esc_html_e( 'Activate', 'hoot-import' );
								if ( $opreqd ) echo ' <strong>' . esc_html__( '(Required)', 'hoot-import' ) . '</strong>';
							?></div>
							<div class="hootimp-opnote--unavailable"><span class="dashicons dashicons-plus"></span><?php
								esc_html_e( 'Install', 'hoot-import' );
								if ( $opreqd ) echo ' <strong>' . esc_html__( '(Required)', 'hoot-import' ) . '</strong>';
							?></div>
						</div>
					<?php endif; ?>

				</div>
			<?php endif;
		}

		/**
		 * Common Plugins
		 * @since  1.0
		 * @access public
		 * @return array
		 */
		public function get_plugins_info(){
			$common = array(
				'hootkit' => array(
					'name' => esc_html__( 'HootKit', 'hoot-import' ),
					'rcmd' => true,
					'data' => array( 'class' => 'HootKit', 'file' => 'hootkit/hootkit.php' ), // class || const || func
				),
				'contact-form-7' => array(
					'name' => esc_html__( 'Contact Form 7', 'hoot-import' ),
					'data' => array( 'const' => 'WPCF7_VERSION', 'file' => 'contact-form-7/wp-contact-form-7.php' ),
				),
				'breadcrumb-navxt' => array(
					'name' => esc_html__( 'Breadcrumb NavXT', 'hoot-import' ),
					'data' => array( 'class' => 'breadcrumb_navxt', 'file' => 'breadcrumb-navxt/breadcrumb-navxt.php' ),
				),
				'woocommerce' => array(
					'name' => esc_html__( 'Woocommerce - eCommerce Shop', 'hoot-import' ),
					'checked' => false,
					'data' => array( 'class' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php' ),
				),
				'newsletter' => array(
					'name' => esc_html__( 'Newsletter', 'hoot-import' ),
					'checked' => false,
					'data' => array( 'const' => 'NEWSLETTER_VERSION', 'file' => 'newsletter/plugin.php' ),
				),
				'mappress-google-maps-for-wordpress' => array(
					'name' => esc_html__( 'MapPress - Google Maps', 'hoot-import' ),
					'checked' => false,
					'data' => array( 'class' => 'Mappress', 'file' => 'mappress-google-maps-for-wordpress/mappress.php' ),
				),
			);
			$plugins = array();
			$demoplugins = !empty( $this->demopack['plugins'] ) && is_array( $this->demopack['plugins'] ) ? $this->demopack['plugins'] : array();
			foreach ( $demoplugins as $check ) {
				if ( is_string( $check ) ) {
					if ( !empty( $common[ $check ] ) ) {
						$plugins[ $check ] = $common[ $check ];
					}
				} elseif( is_array( $check ) && !empty( $check['slug'] ) ) {
					$slug = $check['slug'];
					$data = !empty( $check['data'] ) && is_array( $check['data'] ) ? $check['data'] : array();
					$plugins[ $slug ] = !empty( $common[ $slug ] ) ? hootimport_recursive_parse_args( $data, $common[ $slug ] ) : $data;
				}
			}
			return $plugins;
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

	Admin::get_instance();

endif;