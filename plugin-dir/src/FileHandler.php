<?php

namespace iTRON\PollyTTS;
/**
 *
 *
 * @since      0.1
 *
 */

abstract class FileHandler {

	abstract public function save( $wp_filesystem, $file_temp_full_name, $dir_final_full_name, $file_final_full_name, $post_id, $file_name);
	abstract public function delete( $wp_filesystem, $file, $post_id);
	abstract public function get_type();

	protected function get_prefix( $post_id) {
		if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
			$prefix = get_the_date( 'Y', $post_id ) . '/' . get_the_date( 'm', $post_id ) . '/';
		} else {
			$prefix = '';
		}

		/**
		 * Filters the file prefix used to generate the file path
		 *
		 * @param string $prefix The file prefix
		 */
		return apply_filters( 'itron_polly_tts_file_prefix', $prefix );
	}
}
