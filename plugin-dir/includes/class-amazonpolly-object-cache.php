<?php
/**
 * Object cache helpers for Amazon Polly runtime state.
 *
 * @since      0.1
 *
 */

class Amazonpolly_Object_Cache {

	const GROUP = 'amazon_polly';
	const AUDIO_HEAD_TTL = 300;
	const AUDIO_HEAD_CONTEXT_VERSION_KEY = 'audio_head_context_version';

	/**
	 * @var AmazonAI_Common
	 */
	private $common;

	/**
	 * @param AmazonAI_Common $common
	 */
	public function __construct( AmazonAI_Common $common ) {
		$this->common = $common;
	}

	private function get_audio_head_key( int $post_id ): string {
		return 'audio_head:' . $post_id;
	}

	private function get_polly_voices_transient_key( string $region ): string {
		return AmazonAI_Common::POLLY_VOICES_TRANSIENT_PREFIX . md5( $region );
	}

	private function get_audio_head_context_version(): string {
		$version = wp_cache_get( self::AUDIO_HEAD_CONTEXT_VERSION_KEY, self::GROUP );

		return false === $version ? '0' : (string) $version;
	}

	private function bump_audio_head_context_version(): void {
		wp_cache_set( self::AUDIO_HEAD_CONTEXT_VERSION_KEY, sprintf( '%.6F', microtime( true ) ), self::GROUP );
	}

	private function get_audio_head_context_signature(): string {
		return md5(
			wp_json_encode(
				array(
					'version'    => $this->get_audio_head_context_version(),
					's3'         => (string) get_option( 'amazon_polly_s3', '' ),
					'cloudfront' => (string) get_option( 'amazon_polly_cloudfront', '' ),
					'region'     => (string) AmazonAI_GeneralConfiguration::get_aws_region(),
					'bucket'     => (string) AmazonAI_GeneralConfiguration::get_bucket_name(),
				)
			)
		);
	}

	private function should_invalidate_post_audio_cache_for_meta_key( $meta_key ): bool {
		$meta_key = (string) $meta_key;

		return 'amazon_ai_source_language' === $meta_key || 0 === strpos( $meta_key, 'amazon_polly_' );
	}

	public function get_audio_head_status( int $post_id ): ?array {
		$status = wp_cache_get( $this->get_audio_head_key( $post_id ), self::GROUP );
		if ( ! is_array( $status ) ) {
			return null;
		}

		if ( ! isset( $status['url'], $status['exists'], $status['checked_at'], $status['context'] ) ) {
			return null;
		}

		return array(
			'url'        => (string) $status['url'],
			'exists'     => (bool) $status['exists'],
			'checked_at' => (int) $status['checked_at'],
			'context'    => (string) $status['context'],
		);
	}

	public function is_audio_head_status_current( array $status, string $url ): bool {
		return isset( $status['url'], $status['context'] )
			&& $url === (string) $status['url']
			&& $this->get_audio_head_context_signature() === (string) $status['context'];
	}

	public function set_audio_head_status( int $post_id, string $url, bool $exists ): void {
		wp_cache_set(
			$this->get_audio_head_key( $post_id ),
			array(
				'url'        => $url,
				'exists'     => $exists,
				'checked_at' => time(),
				'context'    => $this->get_audio_head_context_signature(),
			),
			self::GROUP,
			self::AUDIO_HEAD_TTL
		);
	}

	public function delete_audio_head_status( int $post_id ): void {
		wp_cache_delete( $this->get_audio_head_key( $post_id ), self::GROUP );
	}

	public function delete_polly_voices_cache( ?string $region = null ): void {
		$region = '' !== trim( (string) $region )
			? (string) $region
			: (string) $this->common->get_aws_region();

		delete_transient( $this->get_polly_voices_transient_key( $region ) );
	}

	public function handle_added_post_meta( $meta_id, $post_id, $meta_key, $meta_value ): void {
		$this->maybe_invalidate_post_meta_cache( $post_id, $meta_key );
	}

	public function handle_updated_post_meta( $meta_id, $post_id, $meta_key, $meta_value ): void {
		$this->maybe_invalidate_post_meta_cache( $post_id, $meta_key );
	}

	public function handle_deleted_post_meta( $meta_ids, $post_id, $meta_key, $meta_value ): void {
		$this->maybe_invalidate_post_meta_cache( $post_id, $meta_key );
	}

	public function handle_before_delete_post( $post_id ): void {
		$this->delete_audio_head_status( (int) $post_id );
	}

	public function handle_clear_post_audio_runtime_cache( $post_id ): void {
		$this->delete_audio_head_status( (int) $post_id );
	}

	public function handle_polly_voices_region_change( $old_value, $value, $option ): void {
		if ( (string) $old_value === (string) $value ) {
			return;
		}

		$this->bump_audio_head_context_version();
		$this->delete_polly_voices_cache( (string) $old_value );
		$this->delete_polly_voices_cache( (string) $value );
	}

	public function handle_polly_voices_credentials_change( $old_value, $value, $option ): void {
		if ( (string) $old_value === (string) $value ) {
			return;
		}

		$this->delete_polly_voices_cache();
	}

	public function handle_audio_generation_setting_change( $old_value, $value, $option ): void {
		if ( (string) $old_value === (string) $value ) {
			return;
		}

		$this->bump_audio_head_context_version();
	}

	private function maybe_invalidate_post_meta_cache( $post_id, $meta_key ): void {
		if ( $this->should_invalidate_post_audio_cache_for_meta_key( $meta_key ) ) {
			$this->delete_audio_head_status( (int) $post_id );
		}
	}
}
