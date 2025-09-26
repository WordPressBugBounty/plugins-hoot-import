<?php
/**
 * Customizer (DAT) Importer
 * Code adapted from the "Customizer Export/Import" plugin version 0.9.7
 * see class CEI_Core::_import
 */

defined( 'ABSPATH' ) || exit;

/**
 * HootImport_DAT_Importer Class
 */
class HootImport_DAT_Importer {

	/**
	 * Imports Customizer Settings
	 * @param string $fileurl
	 * @return boolean|WP_Error
	 */
	public static function import( $fileurl ) {
		global $wp_customize;
		$fileresponse = wp_remote_get( $fileurl );
		if ( is_wp_error( $fileresponse ) || 200 !== wp_remote_retrieve_response_code( $fileresponse ) ) {
			return new WP_Error( 'hootimport_customizer_file_error', esc_html__( 'The customizer import file is not readable.', 'hoot-import' ) );
		}
		$data = wp_remote_retrieve_body( $fileresponse );
		$data = maybe_unserialize( $data );

		$data = apply_filters( 'hootimport_customizer_data', $data );

		// Data checks.
		if ( ! is_array( $data ) && ( ! isset( $data['template'] ) || ! isset( $data['mods'] ) ) ) {
			return new WP_Error( 'hootimport_customizer_data_error', esc_html__( 'The customizer import file is not in a correct format. Please make sure to use the correct customizer import file.', 'hoot-import' ) );
		}

		// Import Images.
		if ( apply_filters( 'hootimport_customizer_import_images', true ) ) {
			$data['mods'] = self::import_images( $data['mods'] );
		}

		// Import custom options.
		if ( isset( $data['options'] ) ) {
			// Reset options
			remove_theme_mods();
			// Load WordPress Customize Setting Class.
			if ( ! class_exists( 'WP_Customize_Setting' ) ) {
				require_once ABSPATH . WPINC . '/class-wp-customize-setting.php';
			}
			// Include Customizer Setting class.
			include_once hootimport()->dir . '/include/importers/class-customize-option.php';
			foreach ( $data['options'] as $option_key => $option_value ) {
				$option = new HootImport_Customize_Option(
					$wp_customize,
					$option_key,
					array(
						'default'    => '',
						'type'       => 'option',
						'capability' => 'edit_theme_options',
					)
				);
				$option->import( $option_value );
			}
		}

		// If wp_css is set then import it.
		if ( function_exists( 'wp_update_custom_css_post' ) && isset( $data['wp_css'] ) && '' !== $data['wp_css'] ) {
			wp_update_custom_css_post( $data['wp_css'] );
		}

		// Loop through theme mods and update them.
		if ( ! empty( $data['mods'] ) ) {
			foreach ( $data['mods'] as $key => $value ) {
				set_theme_mod( $key, $value );
			}
		}

		return true;
	}

	/**
	 * Imports images for settings saved as mods.
	 * @param  array $mods An array of customizer mods.
	 * @return array The mods array with any new import data.
	 */
	private static function import_images( $mods ) {
		foreach ( $mods as $key => $value ) {
			if ( self::is_image_url( $value ) ) {
				$data = self::media_handle_sideload( $value );
				if ( ! is_wp_error( $data ) ) {
					$mods[ $key ] = $data->url;
					// Handle header image controls.
					if ( isset( $mods[ $key . '_data' ] ) ) {
						$mods[ $key . '_data' ] = $data;
						update_post_meta( $data->attachment_id, '_wp_attachment_is_custom_header', get_stylesheet() );
					}
				}
			}
		}

		return $mods;
	}

	/**
	 * Taken from the core media_sideload_image function and
	 * modified to return an array of data instead of html.
	 * @param  string $file The image file path.
	 * @return object An object of image data.
	 */
	private static function media_handle_sideload( $file ) {
		$data = new stdClass();

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! empty( $file ) ) {

			// Set variables for storage, fix file filename for query strings.
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			$file_array         = array();
			$file_array['name'] = basename( $matches[0] );

			// Download file to temp location.
			$file_array['tmp_name'] = download_url( $file );

			// If error storing temporarily, return the error.
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				return $file_array['tmp_name'];
			}

			// Do the validation and storage stuff.
			$id = media_handle_sideload( $file_array, 0 );

			// If error storing permanently, unlink.
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $file_array['tmp_name'] );
				return $id;
			}

			// Build the object to return.
			$meta                = wp_get_attachment_metadata( $id );
			$data->attachment_id = $id;
			$data->url           = wp_get_attachment_url( $id );
			$data->thumbnail_url = wp_get_attachment_thumb_url( $id );
			$data->height        = $meta['height'];
			$data->width         = $meta['width'];
		}

		return $data;
	}

	/**
	 * Checks to see whether a url is an image url or not.
	 * @param  string $url The url to check.
	 * @return bool Whether the url is an image url or not.
	 */
	private static function is_image_url( $url ) {
		if ( is_string( $url ) && preg_match( '/\.(jpg|jpeg|png|gif)/i', $url ) ) {
			return true;
		}

		return false;
	}

}
