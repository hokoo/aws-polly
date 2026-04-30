<?php

namespace iTRON\PollyTTS;
/**
 *
 *
 * @since      0.1
 *
 */

class LocalFileHandler extends FileHandler {
	/**
	 * @var Common
	 */
	private $common;

	/**
	 * LocalFileHandler constructor.
	 *
	 * @param Common $common
	 */
	public function __construct( Common $common) {
		$this->common = $common;
	}

	/**
	   * Return type of storage which is supported by class (local).
	   *
	   * @since      0.1
	   */
	public function get_type() {
		return 'local';
	}

	/**
	 * Function responsible for saving file on local storage file system.
	 *
	 * @param           $wp_filesystem         Reference to WP filesystem.
	 * @param           $file                  File name.
	 * @param           $post_id               ID of the post.
	 * @since      0.1
	 */
	public function delete( $wp_filesystem, $file, $post_id) {

		// Getting full file path.
		$upload_dir = trailingslashit( wp_upload_dir()['basedir'] );
		$prefix     = $this->get_prefix( $post_id );
		$files      = array( $file );

		foreach ( $this->common->get_all_polly_languages() as $language_code ) {
			$translation_meta = get_post_meta( $post_id, 'itron_polly_tts_translation_' . $language_code, true );
			if ( ! empty( $translation_meta ) ) {
				$files[] = 'itron_polly_tts_' . $post_id . $language_code . '.mp3';
			}
		}

		foreach ( array_unique( $files ) as $target_file ) {
			$file_full_path = $upload_dir . $prefix . $target_file;
			$wp_filesystem->delete( $file_full_path );
		}

	}

	/**
	 * Function responsible for saving file on local storage file system.
	 *
	 * @param           $wp_filesystem         Reference to WP filesystem.
	 * @param           $file_temp_full_name   Temporary name of file on local filesystem.
	 * @param           $dir_final_full_name   Final destination where file should be saved.
	 * @param           $file_final_full_name  Final name of file.
	 * @param           $post_id               ID of the post.
	 * @param           $file_name             Name of the file.
	 * @since      0.1
	 */
	public function save( $wp_filesystem, $file_temp_full_name, $dir_final_full_name, $file_final_full_name, $post_id, $file_name) {

		// Creating directories based on full path of file.
		if ( ! $wp_filesystem->is_dir( $dir_final_full_name ) ) {
			wp_mkdir_p( $dir_final_full_name );
		}

		// We are storing audio file on the WP server.
		// Moving file to it's final location and deleting temporary file.
		$wp_filesystem->move( $file_temp_full_name, $file_final_full_name, true );
		$wp_filesystem->delete( $file_temp_full_name );

		// Creating final link to the file
		$audio_location_link = trailingslashit( wp_upload_dir()['baseurl'] ) . $this->get_prefix( $post_id ) . $file_name;

		// Adding audio info to media library (If Media Library was selected)
		$common = $this->common;
		if ($common->is_medialibrary_enabled()) {
			$temp_media_library_file = $file_final_full_name . '_temp';

			//One more time creating temp file, before deleting previous attachment
			$wp_filesystem->move( $file_final_full_name, $temp_media_library_file, true );
			// Deleting old media library attachment.

			$media_library_att_id = get_post_meta( $post_id, 'itron_polly_tts_media_library_attachment_id', true );
			wp_delete_attachment( $media_library_att_id, true );

			// Getting back to proper name
			$wp_filesystem->move( $temp_media_library_file, $file_final_full_name, true );

			// Adding media library
			$this->add_media_library( $file_final_full_name, $post_id );
		}
		return $audio_location_link;

	}

	/**
	 * Adding information about audio to media library
	 *
	 * @param           $post_id       Id of the post.
	 * @param           $filename      Path to file.
	 * @since      0.1
	 */
	private function add_media_library( $filename, $post_id ) {

		// The ID of the post this attachment is for.
		$parent_post_id = $post_id;

		// Check the type of file. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $filename ), null );

		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		update_post_meta( $post_id, 'itron_polly_tts_media_library_attachment_id', $attach_id );

	}

}
