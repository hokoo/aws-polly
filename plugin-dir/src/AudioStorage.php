<?php

namespace iTRON\PollyTTS;

/**
 * Persists immutable audio locators and performs guarded cleanup.
 *
 * @since 1.0.8
 */
class AudioStorage {

	public const POST_META_KEY                 = 'itron_polly_tts_audio_storage';
	public const ATTACHMENT_PROVENANCE_META_KEY = 'itron_polly_tts_audio_provenance';
	public const DESCRIPTOR_VERSION            = 1;
	public const PROVENANCE                    = 'itron-polly-tts';

	private const FAILURE_OPTION_PREFIX = 'itron_polly_tts_cleanup_failure_';
	private const DISMISS_ACTION         = 'itron_polly_tts_dismiss_cleanup_failure';

	private static $hooks_registered = false;

	/**
	 * Register persistent cleanup-notice hooks once per request.
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render_failure_notices' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'handle_dismiss_failure' ) );
		self::$hooks_registered = true;
	}

	public function expected_filename( int $post_id ): string {
		if ( $post_id < 1 ) {
			throw new \InvalidArgumentException( 'A positive post ID is required for audio storage.' );
		}

		return 'itron_polly_tts_' . $post_id . '.mp3';
	}

	/**
	 * Resolve and validate the local destination from one prefix calculation.
	 *
	 * @return array{path:string,url:string,relative_path:string}
	 */
	public function get_local_destination( int $post_id, string $filename, string $prefix ): array {
		if ( $this->expected_filename( $post_id ) !== $filename ) {
			throw new \InvalidArgumentException( 'Unexpected generated audio filename.' );
		}

		$prefix = str_replace( '\\', '/', $prefix );
		if ( str_starts_with( $prefix, '/' ) || $this->has_unsafe_path_segment( $prefix ) ) {
			throw new \InvalidArgumentException( 'Unsafe generated audio prefix.' );
		}

		$uploads = $this->get_upload_configuration();
		$relative_path = ltrim( $prefix, '/' ) . $filename;
		$path          = $this->normalize_path( trailingslashit( $uploads['basedir'] ) . $relative_path );
		$base_path     = $this->normalize_path( $uploads['basedir'] );

		if ( ! $this->path_is_within( $path, $base_path ) ) {
			throw new \InvalidArgumentException( 'Generated audio path is outside the uploads directory.' );
		}

		$url_path = implode( '/', array_map( 'rawurlencode', explode( '/', $relative_path ) ) );

		return array(
			'path'          => $path,
			'url'           => trailingslashit( $uploads['baseurl'] ) . $url_path,
			'relative_path' => $relative_path,
		);
	}

	/**
	 * Validate a final local path at every filesystem boundary.
	 */
	public function validate_local_path( string $path, int $post_id, bool $must_exist = false ): string {
		return $this->validate_upload_path( $path, $this->expected_filename( $post_id ), $must_exist );
	}

	/**
	 * Validate a temporary synthesis path at every filesystem boundary.
	 */
	public function validate_temporary_path( string $path, int $post_id ): string {
		$filename = basename( str_replace( '\\', '/', $path ) );
		$pattern  = '/^temp_' . preg_quote( $this->expected_filename( $post_id ), '/' ) . '[0-9]+$/';

		if ( 1 !== preg_match( $pattern, $filename ) ) {
			throw new \InvalidArgumentException( 'Unexpected temporary audio filename.' );
		}

		return $this->validate_upload_path( $path, $filename, true );
	}

	/**
	 * Build a validated S3 descriptor from the values used for PutObject.
	 */
	public function create_s3_descriptor( int $post_id, string $bucket, string $region, string $key, string $delivery_url ): array {
		return $this->validate_descriptor(
			$post_id,
			array(
				'version'      => self::DESCRIPTOR_VERSION,
				'provenance'   => self::PROVENANCE,
				'asset_id'     => wp_generate_uuid4(),
				'post_id'      => $post_id,
				'type'         => 's3',
				'filename'     => $this->expected_filename( $post_id ),
				'delivery_url' => $delivery_url,
				'bucket'       => $bucket,
				'region'       => $region,
				'key'          => $key,
			)
		);
	}

	/**
	 * Build a validated local descriptor from the path used for the final move.
	 */
	public function create_local_descriptor( int $post_id, string $path, string $delivery_url, ?array $attachment = null ): array {
		$asset_id   = wp_generate_uuid4();
		$descriptor = array(
			'version'      => self::DESCRIPTOR_VERSION,
			'provenance'   => self::PROVENANCE,
			'asset_id'     => $asset_id,
			'post_id'      => $post_id,
			'type'         => 'local',
			'filename'     => $this->expected_filename( $post_id ),
			'delivery_url' => $delivery_url,
			'path'         => $path,
		);

		if ( null !== $attachment ) {
			$attachment['asset_id'] = $asset_id;
			$descriptor['attachment'] = $attachment;
		}

		return $this->validate_descriptor( $post_id, $descriptor );
	}

	/**
	 * Validate an immutable storage descriptor before it reaches a delete sink.
	 */
	public function validate_descriptor( int $post_id, array $descriptor ): array {
		$required = array( 'version', 'provenance', 'asset_id', 'post_id', 'type', 'filename', 'delivery_url' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $descriptor ) ) {
				throw new \InvalidArgumentException( 'Incomplete audio storage descriptor.' );
			}
		}

		if (
			self::DESCRIPTOR_VERSION !== (int) $descriptor['version']
			|| self::PROVENANCE !== $descriptor['provenance']
			|| $post_id !== (int) $descriptor['post_id']
			|| $this->expected_filename( $post_id ) !== $descriptor['filename']
			|| 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', (string) $descriptor['asset_id'] )
		) {
			throw new \InvalidArgumentException( 'Invalid audio storage provenance.' );
		}

		$descriptor['delivery_url'] = $this->validate_delivery_url( (string) $descriptor['delivery_url'] );

		if ( 's3' === $descriptor['type'] ) {
			$this->validate_s3_descriptor( $descriptor, $post_id );
		} elseif ( 'local' === $descriptor['type'] ) {
			$descriptor['path'] = $this->validate_local_path( (string) ( $descriptor['path'] ?? '' ), $post_id );
			$this->validate_attachment_descriptor( $descriptor, $post_id );
		} else {
			throw new \InvalidArgumentException( 'Unsupported audio storage type.' );
		}

		return $descriptor;
	}

	/**
	 * Persist a newly saved asset and clear a stale failure for the same locator.
	 */
	public function record_saved_asset( int $post_id, array $descriptor ): bool {
		$descriptor = $this->validate_descriptor( $post_id, $descriptor );
		$updated = update_post_meta( $post_id, self::POST_META_KEY, $descriptor );

		if ( ! $updated && $descriptor !== get_post_meta( $post_id, self::POST_META_KEY, true ) ) {
			$this->persist_failure( $post_id, $descriptor, array( 'descriptor_write_failed' ) );
			return false;
		}

		$this->resolve_failure( $post_id, $descriptor, true );

		return true;
	}

	/**
	 * Preserve a known locator when a later save step cannot complete.
	 */
	public function record_save_failure( int $post_id, array $descriptor, string $code ): void {
		$descriptor = $this->validate_descriptor( $post_id, $descriptor );
		$this->persist_failure( $post_id, $descriptor, array( $code ) );
	}

	public function get_descriptor( int $post_id ): ?array {
		$descriptor = get_post_meta( $post_id, self::POST_META_KEY, true );
		if ( ! is_array( $descriptor ) ) {
			return null;
		}

		try {
			return $this->validate_descriptor( $post_id, $descriptor );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	public function set_delivery_url( int $post_id, string $delivery_url ): bool {
		$descriptor = $this->get_descriptor( $post_id );
		if ( null === $descriptor ) {
			return false;
		}

		$descriptor['delivery_url'] = $this->validate_delivery_url( $delivery_url );

		return $this->record_saved_asset( $post_id, $descriptor );
	}

	public function delivery_url_matches( int $post_id, string $delivery_url ): bool {
		$descriptor = $this->get_descriptor( $post_id );

		return null !== $descriptor && hash_equals( $descriptor['delivery_url'], $delivery_url );
	}

	public function local_file_exists( int $post_id ): bool {
		$descriptor = $this->get_descriptor( $post_id );
		if ( null === $descriptor || 'local' !== $descriptor['type'] ) {
			return false;
		}

		try {
			$path = $this->validate_local_path( $descriptor['path'], $post_id, true );
		} catch ( \Throwable $e ) {
			return false;
		}

		return is_file( $path ) && ! is_link( $path );
	}

	/**
	 * Delete an asset through a handler selected from its validated descriptor.
	 */
	public function cleanup_post_audio( int $post_id, callable $delete_asset ): bool {
		$stored_descriptor = get_post_meta( $post_id, self::POST_META_KEY, true );
		if ( ! is_array( $stored_descriptor ) ) {
			if ( ! empty( $stored_descriptor ) ) {
				$this->persist_failure( $post_id, null, array( 'descriptor_invalid' ) );
				return false;
			}

			if ( ! $this->has_unlocated_audio_reference( $post_id ) ) {
				return true;
			}

			$this->persist_failure( $post_id, null, array( 'descriptor_missing' ) );
			return false;
		}

		try {
			$descriptor = $this->validate_descriptor( $post_id, $stored_descriptor );
		} catch ( \Throwable $e ) {
			$this->persist_failure( $post_id, null, array( 'descriptor_invalid' ) );
			return false;
		}

		$errors             = array();
		$skip_physical_file = false;

		if ( isset( $descriptor['attachment'] ) ) {
			try {
				$attachment_error = $this->validate_attachment_ownership( $post_id, $descriptor );
			} catch ( \Throwable $e ) {
				$attachment_error = 'attachment_ownership_mismatch';
			}
			if ( null !== $attachment_error ) {
				$errors[] = $attachment_error;
				$skip_physical_file = true;
			} else {
				try {
					if ( ! wp_delete_attachment( $descriptor['attachment']['id'], true ) ) {
						$errors[] = 'attachment_delete_failed';
					}
				} catch ( \Throwable $e ) {
					$errors[] = 'attachment_delete_failed';
				}
			}
		}

		if ( ! $skip_physical_file ) {
			try {
				if ( false === $delete_asset( $descriptor ) ) {
					$errors[] = 'local_delete_failed';
				}
			} catch ( \Throwable $e ) {
				$errors[] = $this->classify_delete_exception( $e, $descriptor['type'] );
			}
		}

		$errors = array_values( array_unique( $errors ) );
		if ( ! empty( $errors ) ) {
			$this->persist_failure( $post_id, $descriptor, $errors );
			return false;
		}

		$this->resolve_failure( $post_id, $descriptor );

		return true;
	}

	/**
	 * Render cleanup failures only to administrators who can resolve them.
	 */
	public function render_failure_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $this->get_failure_records() as $record ) {
			$causes = array();
			foreach ( $record['codes'] as $code ) {
				$causes[] = $this->failure_message( $code );
			}

			$dismiss_url = add_query_arg(
				array(
					'action'   => self::DISMISS_ACTION,
					'failure'  => $record['id'],
					'_wpnonce' => wp_create_nonce( self::DISMISS_ACTION . '_' . $record['id'] ),
				),
				admin_url( 'admin-post.php' )
			);

			echo '<div class="notice notice-error"><p><strong>';
			echo esc_html( 'AWS Polly audio cleanup needs manual resolution.' );
			echo '</strong> ' . esc_html( 'Codes: ' . implode( ', ', $record['codes'] ) . '.' );
			echo ' ' . esc_html( implode( ' ', $causes ) );
			echo ' ' . esc_html( 'Original asset: ' . $this->failure_location_label( $record ) );
			echo '</p><p><a href="' . esc_url( $dismiss_url ) . '">';
			echo esc_html( 'Dismiss after resolving manually' );
			echo '</a></p></div>';
		}
	}

	/**
	 * Dismiss one independently persisted record after explicit administrator action.
	 */
	public function handle_dismiss_failure(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'You are not allowed to dismiss audio cleanup failures.' ), '', array( 'response' => 403 ) );
		}

		$failure_id = isset( $_GET['failure'] ) ? sanitize_key( wp_unslash( $_GET['failure'] ) ) : '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $failure_id ) ) {
			wp_die( esc_html( 'Invalid audio cleanup failure identifier.' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( self::DISMISS_ACTION . '_' . $failure_id );
		delete_option( self::FAILURE_OPTION_PREFIX . $failure_id );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url();
		}

		wp_safe_redirect( remove_query_arg( array( 'action', 'failure', '_wpnonce' ), $redirect ) );
		exit;
	}

	private function validate_s3_descriptor( array $descriptor, int $post_id ): void {
		$bucket = (string) ( $descriptor['bucket'] ?? '' );
		$region = (string) ( $descriptor['region'] ?? '' );
		$key    = (string) ( $descriptor['key'] ?? '' );

		if ( 1 !== preg_match( '/^(?=.{3,63}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])$/', $bucket ) ) {
			throw new \InvalidArgumentException( 'Invalid original S3 bucket.' );
		}

		if ( 1 !== preg_match( '/^[a-z0-9-]{3,32}$/', $region ) ) {
			throw new \InvalidArgumentException( 'Invalid original S3 region.' );
		}

		if (
			'' === $key
			|| strlen( $key ) > 1024
			|| str_starts_with( $key, '/' )
			|| str_contains( $key, '\\' )
			|| preg_match( '/[\x00-\x1f\x7f]/', $key )
			|| $this->has_unsafe_path_segment( $key )
			|| $this->expected_filename( $post_id ) !== basename( $key )
		) {
			throw new \InvalidArgumentException( 'Invalid original S3 key.' );
		}

		if ( isset( $descriptor['attachment'] ) ) {
			throw new \InvalidArgumentException( 'S3 audio cannot own a local attachment.' );
		}
	}

	private function validate_attachment_descriptor( array $descriptor, int $post_id ): void {
		if ( ! isset( $descriptor['attachment'] ) ) {
			return;
		}

		$attachment = $descriptor['attachment'];
		if (
			! is_array( $attachment )
			|| (int) ( $attachment['id'] ?? 0 ) < 1
			|| $post_id !== (int) ( $attachment['parent_post_id'] ?? 0 )
			|| 'audio/mpeg' !== ( $attachment['mime_type'] ?? '' )
			|| $descriptor['asset_id'] !== ( $attachment['asset_id'] ?? '' )
			|| $descriptor['path'] !== $this->normalize_path( (string) ( $attachment['path'] ?? '' ) )
		) {
			throw new \InvalidArgumentException( 'Invalid media attachment provenance.' );
		}
	}

	private function validate_attachment_ownership( int $post_id, array $descriptor ): ?string {
		$attachment    = $descriptor['attachment'];
		$attachment_id = (int) $attachment['id'];
		$post          = get_post( $attachment_id );

		if (
			! is_object( $post )
			|| 'attachment' !== ( $post->post_type ?? '' )
			|| $post_id !== (int) ( $post->post_parent ?? 0 )
			|| 'audio/mpeg' !== get_post_mime_type( $attachment_id )
		) {
			return 'attachment_ownership_mismatch';
		}

		try {
			$attached_path = $this->validate_local_path( (string) get_attached_file( $attachment_id, true ), $post_id );
		} catch ( \Throwable $e ) {
			return 'attachment_ownership_mismatch';
		}

		$provenance = get_post_meta( $attachment_id, self::ATTACHMENT_PROVENANCE_META_KEY, true );
		if (
			$descriptor['path'] !== $attached_path
			|| ! is_array( $provenance )
			|| $post_id !== (int) ( $provenance['post_id'] ?? 0 )
			|| $descriptor['asset_id'] !== ( $provenance['asset_id'] ?? '' )
			|| $attached_path !== $this->normalize_path( (string) ( $provenance['path'] ?? '' ) )
		) {
			return 'attachment_ownership_mismatch';
		}

		return null;
	}

	private function validate_delivery_url( string $delivery_url ): string {
		$parts = wp_parse_url( $delivery_url );
		if (
			false === $parts
			|| ! is_array( $parts )
			|| ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true )
			|| empty( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			throw new \InvalidArgumentException( 'Invalid audio delivery URL.' );
		}

		return $delivery_url;
	}

	private function validate_upload_path( string $path, string $expected_filename, bool $must_exist ): string {
		if ( '' === $path || str_contains( $path, "\0" ) || $this->has_unsafe_path_segment( str_replace( '\\', '/', $path ) ) ) {
			throw new \InvalidArgumentException( 'Unsafe local audio path.' );
		}

		$uploads        = $this->get_upload_configuration();
		$base_path      = $this->normalize_path( $uploads['basedir'] );
		$normalized     = $this->normalize_path( $path );
		$real_base_path = realpath( $base_path );

		if (
			'' === $normalized
			|| $expected_filename !== basename( $normalized )
			|| ! $this->path_is_within( $normalized, $base_path )
			|| false === $real_base_path
			|| is_link( $normalized )
		) {
			throw new \InvalidArgumentException( 'Unsafe local audio path.' );
		}

		$real_base_path = $this->normalize_path( $real_base_path );
		if ( file_exists( $normalized ) ) {
			$real_path = realpath( $normalized );
			if ( false === $real_path || ! $this->path_is_within( $this->normalize_path( $real_path ), $real_base_path ) ) {
				throw new \InvalidArgumentException( 'Local audio path escapes the uploads directory.' );
			}
		} else {
			$real_parent = realpath( dirname( $normalized ) );
			if ( false === $real_parent || ! $this->path_is_within( $this->normalize_path( $real_parent ), $real_base_path ) ) {
				throw new \InvalidArgumentException( 'Local audio parent escapes the uploads directory.' );
			}
		}

		if ( $must_exist && ! is_file( $normalized ) ) {
			throw new \InvalidArgumentException( 'Local audio file does not exist.' );
		}

		return $normalized;
	}

	private function get_upload_configuration(): array {
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			throw new \RuntimeException( 'Uploads directory is unavailable.' );
		}

		return $uploads;
	}

	private function normalize_path( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );

		return rtrim( (string) $path, '/' );
	}

	private function path_is_within( string $path, string $base_path ): bool {
		return $path === $base_path || str_starts_with( $path, trailingslashit( $base_path ) );
	}

	private function has_unsafe_path_segment( string $path ): bool {
		return (bool) preg_match( '#(?:^|/)(?:\.{1,2})(?:/|$)#', $path );
	}

	private function has_unlocated_audio_reference( int $post_id ): bool {
		foreach ( array( 'itron_polly_tts_audio_link_location', 'itron_polly_tts_audio_location', 'itron_polly_tts_media_library_attachment_id' ) as $meta_key ) {
			if ( '' !== (string) get_post_meta( $post_id, $meta_key, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function classify_delete_exception( \Throwable $exception, string $storage_type ): string {
		if ( $exception instanceof CredentialsException ) {
			return 's3_credentials_missing';
		}
		if ( 's3' !== $storage_type ) {
			return 'local_delete_failed';
		}

		$aws_code = method_exists( $exception, 'getAwsErrorCode' ) ? (string) $exception->getAwsErrorCode() : '';
		$status   = method_exists( $exception, 'getStatusCode' ) ? (int) $exception->getStatusCode() : 0;
		if ( in_array( $aws_code, array( 'AccessDenied', 'AllAccessDisabled', 'InvalidAccessKeyId', 'SignatureDoesNotMatch' ), true ) || in_array( $status, array( 401, 403 ), true ) ) {
			return 's3_access_denied';
		}

		if ( 'NoSuchBucket' === $aws_code ) {
			return 's3_bucket_missing';
		}

		$is_connection_error = method_exists( $exception, 'isConnectionError' ) && $exception->isConnectionError();
		if ( $is_connection_error || $status >= 500 || in_array( $aws_code, array( 'RequestTimeout', 'RequestTimeoutException', 'SlowDown' ), true ) ) {
			return 's3_network_error';
		}

		return 's3_delete_failed';
	}

	private function persist_failure( int $post_id, ?array $descriptor, array $codes ): void {
		$locator_id = $this->locator_id( $post_id, $descriptor );
		foreach ( array_values( array_unique( $codes ) ) as $code ) {
			$scope       = $this->is_attachment_failure( $code ) ? 'attachment' : ( null === $descriptor ? 'unlocated' : 'asset' );
			$attachment  = null !== $descriptor && isset( $descriptor['attachment']['id'] ) ? (int) $descriptor['attachment']['id'] : 0;
			$id          = hash( 'sha256', implode( "\0", array( $scope, $locator_id, $attachment, $code ) ) );
			$option_name = self::FAILURE_OPTION_PREFIX . $id;
			$existing    = get_option( $option_name, array() );
			$record      = array(
				'version'         => 1,
				'id'              => $id,
				'post_id'         => $post_id,
				'asset_id'        => $descriptor['asset_id'] ?? '',
				'locator_id'      => $locator_id,
				'scope'           => $scope,
				'storage_type'    => $descriptor['type'] ?? 'unknown',
				'location'        => $this->safe_location( $descriptor ),
				'codes'           => array( $code ),
				'created_at'      => is_array( $existing ) && isset( $existing['created_at'] ) ? (int) $existing['created_at'] : time(),
				'last_attempt_at' => time(),
			);

			if ( ! add_option( $option_name, $record, '', false ) ) {
				update_option( $option_name, $record, false );
			}
		}
	}

	private function resolve_failure( int $post_id, array $descriptor, bool $saved_over_locator = false ): void {
		$locator_id = $this->locator_id( $post_id, $descriptor );
		foreach ( $this->get_failure_records() as $record ) {
			if ( $post_id !== (int) $record['post_id'] || $locator_id !== ( $record['locator_id'] ?? '' ) ) {
				continue;
			}

			$option_name = self::FAILURE_OPTION_PREFIX . $record['id'];
			if ( $saved_over_locator && 'attachment' === ( $record['scope'] ?? '' ) ) {
				$attachment_id           = (int) ( $record['location']['attachment_id'] ?? 0 );
				$record['storage_type']  = 'attachment';
				$record['location']      = array( 'attachment_id' => $attachment_id );
				$record['superseded_by'] = $descriptor['asset_id'];
				update_option( $option_name, $record, false );
				continue;
			}
			if (
				! $saved_over_locator
				&& 'attachment' === ( $record['scope'] ?? '' )
				&& ! empty( $record['superseded_by'] )
				&& ( $record['asset_id'] ?? '' ) !== $descriptor['asset_id']
			) {
				continue;
			}

			delete_option( $option_name );
		}
	}

	private function locator_id( int $post_id, ?array $descriptor ): string {
		if ( null === $descriptor ) {
			return hash( 'sha256', 'post:' . $post_id . ':unlocated' );
		}

		if ( 's3' === $descriptor['type'] ) {
			$locator = implode( "\0", array( 's3', $descriptor['bucket'], $descriptor['region'], $descriptor['key'] ) );
		} else {
			$locator = "local\0" . $descriptor['path'];
		}

		return hash( 'sha256', $locator );
	}

	private function is_attachment_failure( string $code ): bool {
		return in_array( $code, array( 'attachment_ownership_mismatch', 'attachment_delete_failed', 'attachment_provenance_failed' ), true );
	}

	private function safe_location( ?array $descriptor ): array {
		if ( null === $descriptor ) {
			return array();
		}

		if ( 's3' === $descriptor['type'] ) {
			return array(
				'bucket' => $descriptor['bucket'],
				'region' => $descriptor['region'],
				'key'    => $descriptor['key'],
			);
		}

		return array(
			'path'          => $descriptor['path'],
			'attachment_id' => isset( $descriptor['attachment'] ) ? (int) $descriptor['attachment']['id'] : 0,
		);
	}

	private function get_failure_records(): array {
		global $wpdb;

		$like = $wpdb->esc_like( self::FAILURE_OPTION_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Failure records are intentionally independent nonautoload options and must survive deleted parent posts.
		$option_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		$records      = array();

		foreach ( $option_names as $option_name ) {
			$record = get_option( $option_name );
			if ( is_array( $record ) && isset( $record['id'], $record['codes'], $record['location'] ) && is_array( $record['codes'] ) ) {
				$records[] = $record;
			}
		}

		return $records;
	}

	private function failure_message( string $code ): string {
		$messages = array(
			'descriptor_missing'             => 'The original locator is missing, so no file was deleted.',
			'descriptor_invalid'             => 'The original locator failed validation, so no file was deleted.',
			'descriptor_write_failed'        => 'The generated file was saved, but its locator could not be attached to the post; use the original locator below for manual recovery.',
			'attachment_create_failed'       => 'The local file was saved, but WordPress could not create its media attachment; verify the file manually.',
			'attachment_provenance_failed'   => 'The local file and media attachment were created, but ownership provenance could not be saved; verify both manually.',
			's3_access_denied'               => 'Current AWS credentials do not permit deletion; verify the credentials and DeleteObject access.',
			's3_credentials_missing'         => 'Configure AWS credentials with DeleteObject access to the original bucket, or remove the object manually.',
			's3_bucket_missing'              => 'The original S3 bucket is missing or unavailable; verify the bucket before resolving the object manually.',
			's3_network_error'               => 'AWS could not be reached reliably; verify connectivity and the original object manually.',
			's3_delete_failed'               => 'Amazon S3 did not complete the deletion; verify current credentials and the original object manually.',
			'local_delete_failed'            => 'WordPress could not remove the local file; verify filesystem permissions and remove it manually.',
			'attachment_ownership_mismatch'  => 'The saved attachment no longer agrees with its parent, type, provenance, and path, so neither it nor its file was deleted.',
			'attachment_delete_failed'       => 'WordPress could not remove the owned media attachment; verify the attachment record manually.',
		);

		return $messages[ $code ] ?? 'Audio cleanup failed; verify the original asset manually.';
	}

	private function failure_location_label( array $record ): string {
		$location = $record['location'];
		if ( 'attachment' === $record['storage_type'] && ! empty( $location['attachment_id'] ) ) {
			return 'media attachment ID ' . (int) $location['attachment_id'] . ' (its former file locator is now used by a newer generated asset; preserve the current file while resolving the stale attachment record)';
		}

		if ( 's3' === $record['storage_type'] && isset( $location['bucket'], $location['region'], $location['key'] ) ) {
			return 's3://' . $location['bucket'] . '/' . $location['key'] . ' (region ' . $location['region'] . ')';
		}

		if ( 'local' === $record['storage_type'] && isset( $location['path'] ) ) {
			$attachment = ! empty( $location['attachment_id'] ) ? '; attachment ID ' . (int) $location['attachment_id'] : '';
			return $location['path'] . $attachment;
		}

		return 'unavailable (post ' . (int) $record['post_id'] . ')';
	}
}
