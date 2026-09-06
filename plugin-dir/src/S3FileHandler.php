<?php

namespace iTRON\PollyTTS;
/**
 *
 *
 * @since      0.1
 *
 */

class S3FileHandler extends FileHandler {
	private $s3_client;
	private $audio_storage;

	/**
	 * @var Common
	 */
	private $common;

	/**
	 * S3FileHandler constructor.
	 *
	 * @param Common       $common        Shared plugin operations.
	 * @param AudioStorage $audio_storage Immutable locator storage.
	 */
	public function __construct( Common $common, ?AudioStorage $audio_storage = null ) {
		$this->common        = $common;
		$this->audio_storage = $audio_storage ?? new AudioStorage();
	}

	/**
	   * Return type of storage which is supported by class (S3).
	   *
	   * @since      0.1
	   */
	public function get_type() {
		return 's3';
	}

	public function set_s3_client( $new_s3_client) {
		$this->s3_client = $new_s3_client;
	}

	/**
	 * Function responsible for saving file on local storage file system.
	 *
	 * @param           $wp_filesystem         Not used here.
	 * @param           $descriptor            Validated immutable locator.
	 * @param           $post_id               ID of the post.
	 * @since      0.1
	 */
	public function delete( $wp_filesystem, array $descriptor, $post_id) {
		$descriptor = $this->audio_storage->validate_descriptor( (int) $post_id, $descriptor );
		if ( 's3' !== $descriptor['type'] ) {
			throw new \InvalidArgumentException( 'An S3 handler requires an S3 storage descriptor.' );
		}

		$this->delete_s3_object( $descriptor['bucket'], $descriptor['key'] );

		return true;
	}


	/**
	 * Function responsible for saving file on local storage file system.
	 *
	 * @param           $wp_filesystem         Not used here.
	 * @param           $file_temp_full_name   Temporary name of file on local filesystem.
	 * @param           $dir_final_full_name   Final destination where file should be saved.
	 * @param           $file_final_full_name  Final name of file.
	 * @param           $post_id               ID of the post.
	 * @param           $file_name             Name of the file.
	 * @since      0.1
	 */
	public function save( $wp_filesystem, $file_temp_full_name, $dir_final_full_name, $file_final_full_name, $post_id, $file_name) {
		$post_id = (int) $post_id;
		if ( $this->audio_storage->expected_filename( $post_id ) !== $file_name ) {
			throw new \InvalidArgumentException( 'Unexpected generated audio filename.' );
		}

		$this->audio_storage->validate_temporary_path( $file_temp_full_name, $post_id );

		$key                  = $this->get_prefix( $post_id ) . $file_name;
		$s3_bucket_name       = (string) GeneralConfiguration::get_bucket_name();
		$selected_region      = (string) $this->s3_client->getRegion();
		$audio_location_link  = $this->get_s3_object_link( $post_id, $file_name, $s3_bucket_name, $selected_region, $key );
		$descriptor           = $this->audio_storage->create_s3_descriptor( $post_id, $s3_bucket_name, $selected_region, $key, $audio_location_link );

		// We are storing audio file on Amazon S3.
		$this->s3_client->putObject(
			array(
				'Bucket'      => $s3_bucket_name,
				'ContentType' => 'audio/mpeg',
				'Key'         => $key,
				'SourceFile'  => $file_temp_full_name,
			)
		);

		if ( ! $this->audio_storage->record_saved_asset( $post_id, $descriptor ) ) {
			throw new \RuntimeException( 'Could not record the S3 audio locator.' );
		}

		$wp_filesystem->delete( $file_temp_full_name );

		return $audio_location_link;
	}

	public function get_s3_object_link( $post_id, $file_name, $bucket = null, $region = null, $key = null ) {

		$s3_bucket_name         = null === $bucket ? GeneralConfiguration::get_bucket_name() : $bucket;
		$cloudfront_domain_name = apply_filters( 'itron_polly_tts_cloudfront_domain', get_option( 'itron_polly_tts_cloudfront' ) );
		$key                    = null === $key ? $this->get_prefix( $post_id ) . $file_name : $key;
		$encoded_key            = implode( '/', array_map( 'rawurlencode', explode( '/', $key ) ) );

		if ( empty( $cloudfront_domain_name ) ) {
			$selected_region = null === $region ? GeneralConfiguration::get_aws_region() : $region;

			// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Generated audio is intentionally served from the user-configured Amazon S3 service documented in readme.txt.
			$audio_location_link = 'https://s3.' . $selected_region . '.amazonaws.com/' . $s3_bucket_name . '/' . $encoded_key;
		} else {
			$audio_location_link = 'https://' . untrailingslashit( $cloudfront_domain_name ) . '/' . $encoded_key;
		}

		return $audio_location_link;

	}

	/**
	 * @throws S3BucketNotAccessibleException
	 */
	public function is_bucket_accessible(): bool {
		$s3BucketName = GeneralConfiguration::get_bucket_name();

		//Check if bucket is provided and can be access.
		if ( empty( $s3BucketName ) ) {
			return false;
		}

		try {
			$this->s3_client->headBucket( array( 'Bucket' => $s3BucketName ) );
		} catch ( \Aws\S3\Exception\S3Exception $e ) {
			throw new S3BucketNotAccessibleException( 'S3 Bucket not Accessible' );
		}

		return true;
	}


		/**
		 * Delets object from S3.
		 *
		 * @param string $post_id ID of the post for which audio should be deleted.
		 * @since      0.1
		 */
	private function delete_s3_object( $bucket, $key ) {

		$this->s3_client->deleteObject(
			array(
				'Bucket' => $bucket,
				'Key'    => $key,
			)
		);

	}
}
