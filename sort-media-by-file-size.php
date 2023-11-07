<?php
/**
 * Plugin Name: Sort Media by File Size
 * Plugin URI:  https://github.com/robertdevore/sort-media-by-file-size/
 * Description: Displays file size in a sortable column in the Media Library.
 * Author:      Robert DeVore
 * Version:     1.0.0
 * Author URI:  https://robertdevore.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sort-media-by-file-size
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    wp_die();
}

// Define plugin version.
define( 'SORT_MEDIA_BY_FILE_SIZE_VERSION', '1.0.0' );
 
// Only run in dashboard.
if ( is_admin() ) {
	/**
	 * Establish new column on media library page
     * 
     * @param array $cols 
     * 
     * @return array
	 */
	function sort_media_by_file_size_library_column_header( $cols ) {
		$cols['file_size'] = esc_html__( 'File Size', 'sort-media-by-file-size' );
		return $cols;
	}
	add_filter( 'manage_media_columns', 'sort_media_by_file_size_library_column_header' );

	/**
	 * Output the actual cell in the media file sizes row on the media library
	 */
	function sort_media_by_file_size_library_column_row( $column_name, $id ) {
				
		if ( $column_name == 'file_size' ) {

			$space         = sort_media_by_file_size_get_size( $id );
			$originalspace = $space['original'];
			
			$class = '';
			
			$original_kbs = round( $originalspace / 1024, 0 );
			$original_mbs = '';

			$displayoriginalspace = $original_kbs . ' KB';

			if ( $original_kbs > 1023 ) {
				$original_mbs         = round( $original_kbs / 1024, 1 );
				$displayoriginalspace = $original_mbs . ' MB';
			}

            echo $displayoriginalspace;
		}
	}
	add_action( 'manage_media_custom_column', 'sort_media_by_file_size_library_column_row', 10, 2 );

	/**
	 * Calculate (if necessary), cache (if necessary), and return the size of this media item
     * 
     * @param int $id 
     * 
     * @return array
	 */
	function sort_media_by_file_size_get_size( $id ) {

		$return_array = array(
            'coriginal' => '',
            'total'     => '',
		);

		$upload_dir      = wp_upload_dir();
		$upload_base_dir = $upload_dir['basedir'];

        $space    = 0;
		$metadata = wp_get_attachment_metadata( $id );

		if ( $metadata ) {

			// See if image or audio/video.
			if ( isset( $metadata['file_size'] ) && $metadata['file_size'] ) {
				$return_array['original'] = $metadata['file_size'];
				update_post_meta( $id, 'mediafilesize', $return_array['original'] );
			} else {
				// This is an image with possible multiple sizes.
				$original_sub_path = $metadata['file'];

				$orig_full_path = $upload_base_dir . '/' . $original_sub_path;
				$originalsize   = filesize( $orig_full_path );

				$return_array['original'] = $originalsize;

				// Extract upload path.
				$orig_parts = explode( '/', $orig_full_path );
				array_pop( $orig_parts );
				$str_path = implode( '/', $orig_parts );

				// Total space used is original size + other sizes.
				$space = $originalsize;

        if ( isset( $metadata['sizes'] ) && $metadata['sizes'] ) {
					foreach ( $metadata['sizes'] as $size ) {

						$sizepath = $str_path . '/' . $size['file'];
            if ( $sizepath > 0 ) {
                $this_size = filesize( $sizepath );
            } else {
                $this_size = 0;
            }
						$space = $space + $this_size;
					}
				}
				$return_array['total'] = $space;

				update_post_meta( $id, 'mediafilesize', $return_array['total'] );
			}

		}  else {
			// Single file - not a set of images.
			$path            = get_post_meta( $id, '_wp_attached_file', true );
			$upload_dir      = wp_upload_dir();
			$upload_base_dir = $upload_dir['basedir'];
			$space           = filesize( $upload_base_dir . '/' . $path );
			
			$return_array['original'] = $space;

			update_post_meta( $id, 'mediafilesize', $return_array['original'] );
		}

		return $return_array;
	}

	/**
	 * Cache media file size as attachment data for sorting
     * 
     * @return void
	 */
	function sort_media_by_file_size_run_metadata() {
		$args        = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => null );
		$attachments = get_posts( $args );

    // Check if attachments exist.
		if ( $attachments ) {
      // Loop through attachments.
			foreach ( $attachments as $post ) {
				setup_postdata( $post );

        // Get the file size in an array.
        $sizearray = sort_media_by_file_size_get_size( $post->ID );

        // Add the original file size to the metadata.
        update_post_meta( $post->ID, 'mediafilesize', $sizearray['original'] );
			}
		}
	}

	/**
	 * Remove stored metadata with each attachment (on deactivation)
     * 
     * @return void
	 */
	function sort_media_by_file_size_clear_metadata() {
		$args        = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => null ); 
		$attachments = get_posts( $args );

    // Check if attachments exist.
		if ( $attachments ) {
      // Loop through attachments.
			foreach ( $attachments as $post ) {
				setup_postdata( $post );
        // Remove metadata.
				delete_post_meta( $post->ID, 'mediafilesize' );
			}
		}
	}

	/**
	 * Register the media file size column as sortable (3.1+)
     * 
     * @return array
	 */
	function sort_media_by_file_size_library_register_sortable( $columns ) {
		$columns['file_size'] = 'mediafilesize';
		return $columns;
	}
	add_filter( 'manage_upload_sortable_columns', 'sort_media_by_file_size_library_register_sortable' );

	/**
	 * Define what it means to sort by 'mediafilesizes'
     * 
     * @param array $vars 
     * 
     * @return array
	 */
	function sort_media_by_file_size_column_orderby( $vars ) {

		if ( isset( $vars['orderby'] ) && 'mediafilesize' == $vars['orderby'] ) {

			$vars = array_merge( $vars, array(
				'meta_key' => 'mediafilesize',
				'orderby'  => 'meta_value_num'
			) );
		}
		return $vars;
	}
	add_filter( 'request', 'sort_media_by_file_size_column_orderby' );

}

/**
 * Run on plugin activation
 * 
 * @return void
 */
function sort_media_by_file_size_plugin_activation() {
    sort_media_by_file_size_run_metadata();
}

// Register the activation hook
register_activation_hook( __FILE__, 'sort_media_by_file_size_plugin_activation' );
