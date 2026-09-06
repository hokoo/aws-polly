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
	private $audio_storage;

	/**
	 * LocalFileHandler constructor.
	 *
	 * @param Common       $common        Shared plugin operations.
	 * @param AudioStorage $audio_storage Immutable locator storage.
	 */
	public function __construct( Common $common, ?AudioStorage $audio_storage = null ) {
		$this->common        = $common;
		$this->audio_storage = $audio_storage ?? new AudioStorage();
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
	 * Return the validated local path and URL used by save().
	 *
	 * @param int    $post_id   ID of the post.
	 * @param string $file_name Expected plugin audio filename.
	 * @return array{path: string, url: string}
	 */
	public function get_destination( int $post_id, string $file_name ): array {
		return $this->audio_storage->get_local_destination( $post_id, $file_name, $this->get_prefix( $post_id ) );
	}

	/**
	 * Function responsible for saving file on local storage file system.
	 *
	 * @param           $wp_filesystem         Reference to WP filesystem.
	 * @param           $descriptor            Validated immutable locator.
	 * @param           $post_id               ID of the post.
	 * @since      0.1
	 */
	public function delete( $wp_filesystem, array $descriptor, $post_id) {
		$descriptor = $this->audio_storage->validate_descriptor( (int) $post_id, $descriptor );
		if ( 'local' !== $descriptor['type'] ) {
			throw new \InvalidArgumentException( 'A local handler requires a local storage descriptor.' );
		}

		$path = $this->audio_storage->validate_local_path( $descriptor['path'], (int) $post_id );
		if ( ! $wp_filesystem->exists( $path ) ) {
			return true;
		}

		return (bool) $wp_filesystem->delete( $path );
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
		$post_id     = (int) $post_id;
		$destination = $this->get_destination( $post_id, $file_name );

		if ( ! $this->paths_match( $destination['path'], $file_final_full_name ) || ! $this->paths_match( dirname( $destination['path'] ), $dir_final_full_name ) ) {
			throw new \InvalidArgumentException( 'Local destination does not match the validated uploads prefix.' );
		}

		$this->audio_storage->validate_temporary_path( $file_temp_full_name, $post_id );

		// Creating directories based on full path of file.
		if ( ! $wp_filesystem->is_dir( $dir_final_full_name ) ) {
			if ( ! wp_mkdir_p( $dir_final_full_name ) ) {
				throw new \RuntimeException( 'Could not create the local audio directory.' );
			}
		}
		$this->audio_storage->validate_local_path( $file_final_full_name, $post_id );
		if ( $wp_filesystem->exists( $file_final_full_name ) ) {
			throw new \RuntimeException( 'Local audio destination is still occupied after cleanup.' );
		}

		// We are storing audio file on the WP server.
		if ( ! $wp_filesystem->move( $file_temp_full_name, $file_final_full_name, false ) ) {
			throw new \RuntimeException( 'Could not move generated audio to its local destination.' );
		}
		if ( $wp_filesystem->exists( $file_temp_full_name ) && ! $wp_filesystem->delete( $file_temp_full_name ) ) {
			throw new \RuntimeException( 'Could not remove the temporary audio file.' );
		}
		if ( ! $wp_filesystem->exists( $file_final_full_name ) ) {
			throw new \RuntimeException( 'Generated audio is missing from its local destination.' );
		}

		$file_final_full_name = $this->audio_storage->validate_local_path( $file_final_full_name, $post_id, true );
		$attachment           = null;
		$descriptor           = $this->audio_storage->create_local_descriptor( $post_id, $file_final_full_name, $destination['url'] );

		// Adding audio info to media library (If Media Library was selected)
		$common = $this->common;
		if ($common->is_medialibrary_enabled()) {
			try {
				$attachment_id = $this->add_media_library( $file_final_full_name, $destination['url'], $post_id );
			} catch ( \Throwable $e ) {
				$this->audio_storage->record_save_failure( $post_id, $descriptor, 'attachment_create_failed' );
				throw $e;
			}
			$attachment    = array(
				'id'             => $attachment_id,
				'parent_post_id' => $post_id,
				'path'           => $file_final_full_name,
				'mime_type'      => 'audio/mpeg',
			);
		}

		if ( null !== $attachment ) {
			$descriptor = $this->audio_storage->create_local_descriptor( $post_id, $file_final_full_name, $destination['url'], $attachment );
			$provenance = array(
				'post_id'  => $post_id,
				'asset_id' => $descriptor['asset_id'],
				'path'     => $file_final_full_name,
			);
			$updated = update_post_meta( $attachment['id'], AudioStorage::ATTACHMENT_PROVENANCE_META_KEY, $provenance );
			if ( ! $updated && $provenance !== get_post_meta( $attachment['id'], AudioStorage::ATTACHMENT_PROVENANCE_META_KEY, true ) ) {
				$this->audio_storage->record_save_failure( $post_id, $descriptor, 'attachment_provenance_failed' );
				throw new \RuntimeException( 'Could not record media attachment provenance.' );
			}
		}

		if ( ! $this->audio_storage->record_saved_asset( $post_id, $descriptor ) ) {
			throw new \RuntimeException( 'Could not record the local audio locator.' );
		}

		return $destination['url'];

	}

	/**
	 * Adding information about audio to media library
	 *
	 * @param           $post_id       Id of the post.
	 * @param           $filename      Path to file.
	 * @since      0.1
	 */
	private function add_media_library( $filename, $delivery_url, $post_id ): int {

		// The ID of the post this attachment is for.
		$parent_post_id = $post_id;

		// Check the type of file. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $filename ), null );

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $delivery_url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
		if ( is_wp_error( $attach_id ) || (int) $attach_id < 1 ) {
			throw new \RuntimeException( 'Could not create the media attachment.' );
		}

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		update_post_meta( $post_id, 'itron_polly_tts_media_library_attachment_id', $attach_id );

		return (int) $attach_id;
	}

	private function paths_match( string $expected, string $actual ): bool {
		$expected = rtrim( preg_replace( '#/+#', '/', str_replace( '\\', '/', $expected ) ), '/' );
		$actual   = rtrim( preg_replace( '#/+#', '/', str_replace( '\\', '/', $actual ) ), '/' );

		return $expected === $actual;
	}

}
