<?php
/**
 *
 *
 * @link       amazon.com
 * @since      2.0.3
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin
 */

class AmazonAI_S3FileHandler extends AmazonAI_FileHandler {
  	private $s3_client;
  
	/**
	 * @var AmazonAI_Common
	 */
	private $common;

	/**
	 * AmazonAI_S3FileHandler constructor.
	 *
	 * @param AmazonAI_Common $common
	 */
	public function __construct(AmazonAI_Common $common) {
		$this->common = $common;
	}

  /**
	 * Return type of storage which is supported by class (S3).
	 *
	 * @since    2.1.0
	 */
    public function get_type() {
      return "s3";
    }

    public function set_s3_client($new_s3_client) {
      $this->s3_client = $new_s3_client;
    }

    /**
  	 * Function responsible for saving file on local storage file system.
  	 *
  	 * @param           $wp_filesystem         Not used here.
  	 * @param           $file                  File name.
  	 * @param           $post_id               ID of the post.
  	 * @since           2.0.3
  	 */
    public function delete($wp_filesystem, $file, $post_id) {

      $common = $this->common;

      // Retrieve the name of the bucket where audio files are stored.
      $s3_bucket  = AmazonAI_PollyConfiguration::get_bucket_name();
      $prefix     = $this->get_prefix($post_id);

      // Delete main audio file.
      $this->delete_s3_object( $s3_bucket, $prefix . $file );

      // Delete translations if available.
      foreach ( $common->get_all_polly_languages() as $language_code ) {
        $value = get_post_meta( $post_id, 'amazon_polly_translation_' . $language_code, true );
        if ( ! empty( $value ) ) {
          $s3_key = $prefix . 'amazon_polly_' . $post_id . $language_code . '.mp3';
          $this->delete_s3_object( $s3_bucket, $s3_key );
        }
      }

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
  	 * @since           2.0.3
  	 */
    public function save($wp_filesystem, $file_temp_full_name, $dir_final_full_name, $file_final_full_name, $post_id, $file_name) {
        $media_library_att_id = get_post_meta( $post_id, 'amazon_polly_media_library_attachment_id', true );
  			if ( !empty($media_library_att_id) ) {
  				wp_delete_attachment( $media_library_att_id, true );
  			}

        $key = $this->get_prefix($post_id) . $file_name;

  			// We are storing audio file on Amazon S3.
  			$s3BucketName = AmazonAI_PollyConfiguration::get_bucket_name();
  			$audio_location = 's3';
  			$result         = $this->s3_client->putObject(
  				array(
  					'ACL'        => 'public-read',
  					'Bucket'     => $s3BucketName,
  					'Key'        => $key,
  					'SourceFile' => $file_temp_full_name,
  				)
  			);
  			$wp_filesystem->delete( $file_temp_full_name );

        return $this->get_s3_object_link($post_id, $file_name);
    }

    public function get_s3_object_link($post_id, $file_name) {

      $s3BucketName = AmazonAI_PollyConfiguration::get_bucket_name();
      $cloudfront_domain_name = apply_filters('amazon_polly_cloudfront_domain', get_option( 'amazon_polly_cloudfront' ));
      $key = $this->get_prefix($post_id) . $file_name;

      if ( empty( $cloudfront_domain_name ) ) {

        $common = $this->common;
        $selected_region = $common->get_aws_region();

        $audio_location_link = 'https://s3.' . $selected_region . '.amazonaws.com/' . $s3BucketName . '/' . $key;
      } else {
        $audio_location_link = 'https://' . $cloudfront_domain_name . '/' . $key;
      }

      return $audio_location_link;

    }

	/**
	 * @throws S3BucketNotAccException
	 */
	public function is_bucket_accessible(): bool {
		$s3BucketName = AmazonAI_PollyConfiguration::get_bucket_name();

		//Check if bucket is provided and can be access.
		if ( empty( $s3BucketName ) ) {
			return false;
		}

		try {
			$this->s3_client->headBucket( array( 'Bucket' => $s3BucketName ) );
		} catch ( Aws\S3\Exception\S3Exception $e ) {
			throw new S3BucketNotAccException( 'S3 Bucket not Accessible' );
		}

		return true;
	}


        /**
         * Delets object from S3.
         *
         * @param string $post_id ID of the post for which audio should be deleted.
         * @since 2.0.0
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
