<?php
/**
 * Widget (WIE) Importer
 * Code adapted from the "Widget Importer & Exporter" plugin version 1.6.1
 * see import.php > wie_import_data()
 * see widgets.php > wie_available_widgets()
 */

defined( 'ABSPATH' ) || exit;

/**
 * HootImport_WIE_Importer Class
 */
class HootImport_WIE_Importer {

	/**
	 * Move widgets to inactive sidebar
	 *
	 * @return void
	 */
	public static function setup() {

		// Get all active widgets in sidebars.
		$sidebars_widgets = get_option( 'sidebars_widgets' );
		$inactive_widgets = $sidebars_widgets['wp_inactive_widgets'] ?? array();

		// Move widgets to inactive widgets area.
		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			// Skip inactive widgets area.
			if ( $sidebar_id == 'wp_inactive_widgets' ) {
				continue;
			}
			// Add widget to inactive widgets area.
			foreach ( $widgets as $widget_id ) {
				$inactive_widgets[] = $widget_id;
			}
			// Clear the sidebar.
			$sidebars_widgets[$sidebar_id] = array();
		}

		// Update the sidebars and widgets.
		$sidebars_widgets['wp_inactive_widgets'] = $inactive_widgets;
		update_option( 'sidebars_widgets', $sidebars_widgets );

	}

	/**
	 * Imports Widgets
	 * @param string $fileurl
	 * @return array|WP_Error
	 */
	public static function import( $fileurl ) {
		global $wp_registered_sidebars;
		$fileresponse = wp_remote_get( $fileurl );
		if ( is_wp_error( $fileresponse ) || 200 !== wp_remote_retrieve_response_code( $fileresponse ) ) {
			return new WP_Error( 'hootimport_widgets_file_error', esc_html__( 'The widget import file is not readable.', 'hoot-import' ) );
		}
		$data = wp_remote_retrieve_body( $fileresponse );
		$data = json_decode( $data );

		$data = apply_filters( 'hootimport_widgets_data', $data );

		// Data checks.
		if ( empty( $data ) || ! is_object( $data ) ) {
			return new WP_Error( 'hootimport_widgets_data_error', esc_html__( 'The widget import file is not in a correct format. Please make sure to use the correct import file.', 'hoot-import' ) );
		}

		// Get all available widgets site supports.
		$available_widgets = self::available_widgets();

		// Get all existing widget instances.
		$widget_instances = array();
		foreach ( $available_widgets as $widget_data ) {
			$widget_instances[ $widget_data['id_base'] ] = get_option( 'widget_' . $widget_data['id_base'] );
		}

		// Begin results.
		$results = array();

		// Loop import data's sidebars.
		foreach ( $data as $sidebar_id => $widgets ) {

			// Skip inactive widgets (should not be in export file).
			if ( 'wp_inactive_widgets' == $sidebar_id ) {
				continue;
			}

			// Check if sidebar is available on this site.
			// Otherwise add widgets to inactive, and say so.
			if ( isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
				$sidebar_available    = true;
				$use_sidebar_id       = $sidebar_id;
				$sidebar_message_type = 'success';
				$sidebar_message      = '';
			} else {
				$sidebar_available    = false;
				$use_sidebar_id       = 'wp_inactive_widgets'; // Add to inactive if sidebar does not exist in theme.
				$sidebar_message_type = 'error';
				$sidebar_message      = esc_html__( 'Widget area does not exist in theme (using Inactive)', 'hoot-import' );
			}

			// Result for sidebar.
			// Sidebar name if theme supports it; otherwise ID.
			$results[ $sidebar_id ]['name']         = ! empty( $wp_registered_sidebars[ $sidebar_id ]['name'] ) ? $wp_registered_sidebars[ $sidebar_id ]['name'] : $sidebar_id;
			$results[ $sidebar_id ]['message_type'] = $sidebar_message_type;
			$results[ $sidebar_id ]['message']      = $sidebar_message;
			$results[ $sidebar_id ]['widgets']      = array();

			// Loop widgets.
			foreach ( $widgets as $widget_instance_id => $widget ) {
				$fail = false;

				// Get id_base (remove -# from end) and instance ID number.
				$id_base            = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				$instance_id_number = str_replace( $id_base . '-', '', $widget_instance_id );

				// Does site support this widget?
				if ( ! $fail && ! isset( $available_widgets[ $id_base ] ) ) {
					$fail                = true;
					$widget_message_type = 'error';
					$widget_message      = esc_html__( 'Site does not support widget', 'hoot-import' ); // Explain why widget not imported.
				}

				// Convert multidimensional objects to multidimensional arrays
				// Some plugins like Jetpack Widget Visibility store settings as multidimensional arrays
				// Without this, they are imported as objects and cause fatal error on Widgets page
				// If this creates problems for plugins that do actually intend settings in objects then may need to consider other approach: https://wordpress.org/support/topic/problem-with-array-of-arrays
				// It is probably much more likely that arrays are used than objects, however.
				$widget = json_decode( wp_json_encode( $widget ), true );

				// Filter to modify settings array
				// Do before identical check because changes may make it identical to end result (such as URL replacements).
				$widget = apply_filters( 'hootimport_widget_settings_array', $widget, $id_base, $instance_id_number, $available_widgets );

				// Does widget with identical settings already exist in same sidebar?
				if ( ! $fail && isset( $widget_instances[ $id_base ] ) ) {
				if ( $use_sidebar_id === 'wp_inactive_widgets' ) :
					// Get existing widgets in this sidebar.
					$sidebars_widgets = get_option( 'sidebars_widgets' );
					$sidebar_widgets  = isset( $sidebars_widgets[ $use_sidebar_id ] ) ? $sidebars_widgets[ $use_sidebar_id ] : array(); // Check Inactive if that's where will go.
					// Loop widgets with ID base.
					$single_widget_instances = ! empty( $widget_instances[ $id_base ] ) ? $widget_instances[ $id_base ] : array();
					foreach ( $single_widget_instances as $check_id => $check_widget ) {
						// Is widget in same sidebar and has identical settings?
						if ( in_array( "$id_base-$check_id", $sidebar_widgets ) && (array) $widget == $check_widget ) {
							$fail                = true;
							$widget_message_type = 'warning';

							// Explain why widget not imported.
							$widget_message      = esc_html__( 'Widget already exists', 'hoot-import' );

							break;
						}
					}
				endif;
				// Check in inactive sidebar due to self::setup() moving all widgets there.
				if ( $use_sidebar_id !== 'wp_inactive_widgets' ) :
					// Get existing widgets in this sidebar.
					$sidebars_widgets = get_option( 'sidebars_widgets' );
					$inactive_widgets  = isset( $sidebars_widgets[ 'wp_inactive_widgets' ] ) ? $sidebars_widgets[ 'wp_inactive_widgets' ] : array();
					// Loop widgets with ID base.
					$single_widget_instances = ! empty( $widget_instances[ $id_base ] ) ? $widget_instances[ $id_base ] : array();
					foreach ( $single_widget_instances as $check_id => $check_widget ) {
						// Is widget in same sidebar and has identical settings?
						if ( in_array( "$id_base-$check_id", $inactive_widgets ) && (array) $widget == $check_widget ) {
							// Assign widget instance to sidebar.
							// Which sidebars have which widgets, get fresh every time.
							$sidebars_widgets_ltst = get_option( 'sidebars_widgets' );
							// Avoid rarely fatal error when the option is an empty string
							// https://github.com/churchthemes/widget-importer-exporter/pull/11.
							if (! $sidebars_widgets_ltst) {
								$sidebars_widgets_ltst = array();
							}
							// Use ID number from new widget instance.
							$new_instance_id = "$id_base-$check_id";
							// Add new instance to sidebar.
							$sidebars_widgets_ltst[ $use_sidebar_id ][] = $new_instance_id;
							// Remove instance from inactive and reindex the array to ensure keys are sequential
							$inactivelist = $sidebars_widgets_ltst[ 'wp_inactive_widgets' ];
							$inactive_widgetkey = array_search("$id_base-$check_id", $inactivelist);
							if ($inactive_widgetkey !== false) {
								unset( $inactivelist[ $inactive_widgetkey ] );
								$sidebars_widgets_ltst[ 'wp_inactive_widgets' ] = array_values($inactivelist);
							}
							// Save the amended data.
							update_option( 'sidebars_widgets', $sidebars_widgets_ltst );

							$fail                = true;
							$widget_message_type = 'warning';
							// Explain why widget not imported.
							$widget_message      = esc_html__( 'Widget already exists...', 'hoot-import' );
							break;
						}
					}
				endif;

				}

				// No failure.
				if ( ! $fail ) {
					// Add widget instance.
					$single_widget_instances   = get_option( 'widget_' . $id_base ); // All instances for that widget ID base, get fresh every time.
					$single_widget_instances   = ! empty( $single_widget_instances ) ? $single_widget_instances : array(
						'_multiwidget' => 1,   // Start fresh if have to.
					);
					$single_widget_instances[] = $widget; // Add it.

					// Get the key it was given.
					end( $single_widget_instances );
					$new_instance_id_number = key( $single_widget_instances );

					// If key is 0, make it 1
					// When 0, an issue can occur where adding a widget causes data from other widget to load,
					// and the widget doesn't stick (reload wipes it).
					if ( '0' === strval( $new_instance_id_number ) ) {
						$new_instance_id_number = 1;
						$single_widget_instances[ $new_instance_id_number ] = $single_widget_instances[0];
						unset( $single_widget_instances[0] );
					}

					// Move _multiwidget to end of array for uniformity.
					if ( isset( $single_widget_instances['_multiwidget'] ) ) {
						$multiwidget = $single_widget_instances['_multiwidget'];
						unset( $single_widget_instances['_multiwidget'] );
						$single_widget_instances['_multiwidget'] = $multiwidget;
					}

					// Update option with new widget.
					update_option( 'widget_' . $id_base, $single_widget_instances );

					// Assign widget instance to sidebar.
					// Which sidebars have which widgets, get fresh every time.
					$sidebars_widgets = get_option( 'sidebars_widgets' );

					// Avoid rarely fatal error when the option is an empty string
					// https://github.com/churchthemes/widget-importer-exporter/pull/11.
					if (! $sidebars_widgets) {
						$sidebars_widgets = array();
					}

					// Use ID number from new widget instance.
					$new_instance_id = $id_base . '-' . $new_instance_id_number;

					// Add new instance to sidebar.
					$sidebars_widgets[ $use_sidebar_id ][] = $new_instance_id;

					// Save the amended data.
					update_option( 'sidebars_widgets', $sidebars_widgets );

					// After widget import action.
					$after_widget_import = array(
						'sidebar'           => $use_sidebar_id,
						'sidebar_old'       => $sidebar_id,
						'widget'            => $widget,
						'widget_type'       => $id_base,
						'widget_id'         => $new_instance_id,
						'widget_id_old'     => $widget_instance_id,
						'widget_id_num'     => $new_instance_id_number,
						'widget_id_num_old' => $instance_id_number,
					);
					do_action( 'hootimport_after_single_widget_import', $after_widget_import );

					// Success message.
					if ( $sidebar_available ) {
						$widget_message_type = 'success';
						$widget_message      = esc_html__( 'Widget Imported', 'hoot-import' );
					} else {
						$widget_message_type = 'warning';
						$widget_message      = esc_html__( 'Widget Imported to Inactive', 'hoot-import' );
					}
				}

				// Result for widget instance.
				$results[ $sidebar_id ]['widgets'][ $widget_instance_id ]['name']         = isset( $available_widgets[ $id_base ]['name'] ) ? $available_widgets[ $id_base ]['name'] : $id_base; // Widget name or ID if name not available (not supported by site).
				$results[ $sidebar_id ]['widgets'][ $widget_instance_id ]['title']        = ! empty( $widget['title'] ) ? $widget['title'] : esc_html__( 'No Title', 'hoot-import' ); // Show "No Title" if widget instance is untitled.
				$results[ $sidebar_id ]['widgets'][ $widget_instance_id ]['message_type'] = $widget_message_type;
				$results[ $sidebar_id ]['widgets'][ $widget_instance_id ]['message']      = $widget_message;
			}
		}

		// Return results.
		return $results;
	}

	/**
	 * Available widgets.
	 * Gather site's widgets into array with ID base, name, etc.
	 */
	private static function available_widgets() {
		global $wp_registered_widget_controls;

		$widget_controls   = $wp_registered_widget_controls;

		$available_widgets = array();

		foreach ( $widget_controls as $widget ) {
			if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[ $widget['id_base'] ] ) ) {
				$available_widgets[ $widget['id_base'] ]['id_base'] = $widget['id_base'];
				$available_widgets[ $widget['id_base'] ]['name']    = $widget['name'];
			}
		}

		return apply_filters( 'hootimporter_available_widgets', $available_widgets );
	}
}
