<?php

declare(strict_types=1);

namespace {
	define( 'ABSPATH', sys_get_temp_dir() . '/aws-polly-storage-test-wordpress/' );

	$GLOBALS['storage_test_root']            = sys_get_temp_dir() . '/aws-polly-storage-' . getmypid();
	$GLOBALS['storage_test_uploads']         = $GLOBALS['storage_test_root'] . '/uploads';
	$GLOBALS['storage_test_options']         = array();
	$GLOBALS['storage_test_meta']            = array();
	$GLOBALS['storage_test_posts']           = array();
	$GLOBALS['storage_test_attached_files']  = array();
	$GLOBALS['storage_test_mime_types']      = array();
	$GLOBALS['storage_test_deleted']         = array();
	$GLOBALS['storage_test_delete_failures'] = array();
	$GLOBALS['storage_test_delete_nulls']    = array();
	$GLOBALS['storage_test_meta_failures']   = array();
	$GLOBALS['storage_test_hooks']           = array();
	$GLOBALS['storage_test_can_manage']      = false;
	$GLOBALS['storage_test_prefix']          = '2024/03/';
	$GLOBALS['storage_test_uuid']            = 0;

	class StorageTestWpdb {
		public string $options = 'wp_options';

		public function esc_like( string $value ): string {
			return $value;
		}

		public function prepare( string $query, string $value ): string {
			return rtrim( $value, '%' );
		}

		public function get_col( string $prefix ): array {
			return array_values(
				array_filter(
					array_keys( $GLOBALS['storage_test_options'] ),
					static fn( string $name ): bool => str_starts_with( $name, $prefix )
				)
			);
		}
	}

	$GLOBALS['wpdb'] = new StorageTestWpdb();

	function add_action( string $hook, callable $callback ): bool {
		$GLOBALS['storage_test_hooks'][ $hook ][] = $callback;
		return true;
	}

	function apply_filters( string $hook, $value ) {
		return $value;
	}

	function get_option( string $name, $default = false ) {
		if ( 'uploads_use_yearmonth_folders' === $name ) {
			return '' !== $GLOBALS['storage_test_prefix'];
		}

		return $GLOBALS['storage_test_options'][ $name ] ?? $default;
	}

	function add_option( string $name, $value, string $deprecated = '', $autoload = 'yes' ): bool {
		if ( array_key_exists( $name, $GLOBALS['storage_test_options'] ) ) {
			return false;
		}

		$GLOBALS['storage_test_options'][ $name ] = $value;
		$GLOBALS['storage_test_autoload'][ $name ] = $autoload;
		return true;
	}

	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['storage_test_options'][ $name ] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['storage_test_autoload'][ $name ] = $autoload;
		}
		return true;
	}

	function delete_option( string $name ): bool {
		unset( $GLOBALS['storage_test_options'][ $name ], $GLOBALS['storage_test_autoload'][ $name ] );
		return true;
	}

	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		return $GLOBALS['storage_test_meta'][ $post_id ][ $key ] ?? '';
	}

	function update_post_meta( int $post_id, string $key, $value ): bool {
		if ( ! empty( $GLOBALS['storage_test_meta_failures'][ $post_id ][ $key ] ) ) {
			return false;
		}

		$GLOBALS['storage_test_meta'][ $post_id ][ $key ] = $value;
		return true;
	}

	function wp_upload_dir(): array {
		return array(
			'basedir' => $GLOBALS['storage_test_uploads'],
			'baseurl' => 'https://example.test/uploads',
			'url'     => 'https://example.test/uploads',
			'error'   => false,
		);
	}

	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}

	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}

	function wp_generate_uuid4(): string {
		++$GLOBALS['storage_test_uuid'];
		return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['storage_test_uuid'] );
	}

	function wp_parse_url( string $url ) {
		return parse_url( $url );
	}

	function get_the_date( string $format, int $post_id ): string {
		return 'Y' === $format ? '2024' : '03';
	}

	function wp_mkdir_p( string $path ): bool {
		return is_dir( $path ) || mkdir( $path, 0777, true );
	}

	function wp_check_filetype( string $filename, $mimes = null ): array {
		return array(
			'ext'  => 'mp3',
			'type' => 'audio/mpeg',
		);
	}

	function wp_insert_attachment( array $attachment, string $filename, int $parent_post_id ) {
		$attachment_id = 900 + count( $GLOBALS['storage_test_posts'] );
		$GLOBALS['storage_test_posts'][ $attachment_id ] = (object) array(
			'ID'          => $attachment_id,
			'post_type'   => 'attachment',
			'post_parent' => $parent_post_id,
		);
		$GLOBALS['storage_test_attached_files'][ $attachment_id ] = $filename;
		$GLOBALS['storage_test_mime_types'][ $attachment_id ]     = $attachment['post_mime_type'];
		return $attachment_id;
	}

	function is_wp_error( $value ): bool {
		return false;
	}

	function wp_generate_attachment_metadata( int $attachment_id, string $filename ): array {
		return array( 'file' => basename( $filename ) );
	}

	function wp_update_attachment_metadata( int $attachment_id, array $metadata ): bool {
		return true;
	}

	function get_post( int $post_id ) {
		return $GLOBALS['storage_test_posts'][ $post_id ] ?? null;
	}

	function get_post_mime_type( int $post_id ): string {
		return $GLOBALS['storage_test_mime_types'][ $post_id ] ?? '';
	}

	function get_attached_file( int $post_id, bool $unfiltered = false ) {
		return $GLOBALS['storage_test_attached_files'][ $post_id ] ?? false;
	}

	function wp_delete_attachment( int $post_id, bool $force_delete = false ) {
		$GLOBALS['storage_test_deleted'][] = $post_id;
		if ( ! empty( $GLOBALS['storage_test_delete_nulls'][ $post_id ] ) ) {
			return null;
		}
		if ( ! empty( $GLOBALS['storage_test_delete_failures'][ $post_id ] ) ) {
			return false;
		}

		$file = $GLOBALS['storage_test_attached_files'][ $post_id ] ?? '';
		if ( is_string( $file ) && is_file( $file ) ) {
			unlink( $file );
		}
		unset(
			$GLOBALS['storage_test_posts'][ $post_id ],
			$GLOBALS['storage_test_attached_files'][ $post_id ],
			$GLOBALS['storage_test_mime_types'][ $post_id ]
		);
		return (object) array( 'ID' => $post_id );
	}

	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability && $GLOBALS['storage_test_can_manage'];
	}

	function wp_create_nonce( string $action ): string {
		return 'nonce-' . $action;
	}

	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}

	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
	}

	function wp_unslash( string $value ): string {
		return stripslashes( $value );
	}

	function check_admin_referer( string $action ): bool {
		return true;
	}

	function wp_get_referer() {
		return false;
	}

	function remove_query_arg( array $keys, string $url ): string {
		return $url;
	}

	function wp_safe_redirect( string $url ): bool {
		$GLOBALS['storage_test_redirect'] = $url;
		return true;
	}

	function wp_die( string $message, $title = '', $args = array() ): void {
		throw new RuntimeException( $message );
	}
}

namespace iTRON\PollyTTS {
	class Common {
		private bool $media_library_enabled;

		public function __construct( bool $media_library_enabled = false ) {
			$this->media_library_enabled = $media_library_enabled;
		}

		public function is_medialibrary_enabled(): bool {
			return $this->media_library_enabled;
		}
	}

	class GeneralConfiguration {
		public static function get_bucket_name(): string {
			return (string) ( $GLOBALS['storage_test_options']['bucket'] ?? '' );
		}

		public static function get_aws_region(): string {
			return (string) ( $GLOBALS['storage_test_options']['region'] ?? '' );
		}
	}

	require_once dirname( __DIR__ ) . '/plugin-dir/src/AudioStorage.php';
	require_once dirname( __DIR__ ) . '/plugin-dir/src/FileHandler.php';
	require_once dirname( __DIR__ ) . '/plugin-dir/src/LocalFileHandler.php';
	require_once dirname( __DIR__ ) . '/plugin-dir/src/S3FileHandler.php';
}

namespace {
	use iTRON\PollyTTS\AudioStorage;
	use iTRON\PollyTTS\Common;
	use iTRON\PollyTTS\LocalFileHandler;
	use iTRON\PollyTTS\S3FileHandler;

	class StorageTestFilesystem {
		public array $deleted_paths = array();
		public array $failed_deletes = array();
		public array $moves = array();

		public function exists( string $path ): bool {
			return file_exists( $path );
		}

		public function delete( string $path ): bool {
			$this->deleted_paths[] = $path;
			if ( in_array( $path, $this->failed_deletes, true ) ) {
				return false;
			}

			return ! file_exists( $path ) || unlink( $path );
		}

		public function is_dir( string $path ): bool {
			return is_dir( $path );
		}

		public function move( string $source, string $destination, bool $overwrite = false ): bool {
			$this->moves[] = array(
				'source'      => $source,
				'destination' => $destination,
				'overwrite'   => $overwrite,
			);
			if ( $overwrite && file_exists( $destination ) && ! unlink( $destination ) ) {
				return false;
			}

			return rename( $source, $destination );
		}
	}

	class StorageTestS3Client {
		public array $puts = array();
		public array $deletes = array();
		public ?Throwable $delete_exception = null;
		private string $region;

		public function __construct( ?string $region = null ) {
			$this->region = $region ?? (string) ( $GLOBALS['storage_test_options']['region'] ?? '' );
		}

		public function getRegion(): string {
			return $this->region;
		}

		public function putObject( array $arguments ): array {
			$this->puts[] = $arguments;
			return array( 'ObjectURL' => 'unused' );
		}

		public function deleteObject( array $arguments ): array {
			$this->deletes[] = $arguments;
			if ( null !== $this->delete_exception ) {
				throw $this->delete_exception;
			}

			return array();
		}
	}

	class StorageTestAwsException extends RuntimeException {
		private string $aws_code;
		private int $status;
		private bool $connection_error;

		public function __construct( string $aws_code, int $status = 0, bool $connection_error = false ) {
			parent::__construct( 'SECRET raw AWS exception details must never persist' );
			$this->aws_code         = $aws_code;
			$this->status           = $status;
			$this->connection_error = $connection_error;
		}

		public function getAwsErrorCode(): string {
			return $this->aws_code;
		}

		public function getStatusCode(): int {
			return $this->status;
		}

		public function isConnectionError(): bool {
			return $this->connection_error;
		}
	}

	function assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException( $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) );
		}
	}

	function assert_true( bool $actual, string $message ): void {
		assert_same( true, $actual, $message );
	}

	function assert_false( bool $actual, string $message ): void {
		assert_same( false, $actual, $message );
	}

	function remove_storage_test_tree( string $path ): void {
		if ( is_link( $path ) || is_file( $path ) ) {
			unlink( $path );
			return;
		}

		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
			remove_storage_test_tree( $path . '/' . $entry );
		}
		rmdir( $path );
	}

	function make_temp_audio( int $post_id, string $contents = 'audio' ): string {
		$path = $GLOBALS['storage_test_uploads'] . '/temp_itron_polly_tts_' . $post_id . '.mp35';
		file_put_contents( $path, $contents );
		return $path;
	}

	function failure_records(): array {
		return array_values(
			array_filter(
				$GLOBALS['storage_test_options'],
				static fn( $value, string $key ): bool => str_starts_with( $key, 'itron_polly_tts_cleanup_failure_' ),
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	function has_failure_code( string $code ): bool {
		foreach ( failure_records() as $record ) {
			if ( in_array( $code, $record['codes'], true ) ) {
				return true;
			}
		}
		return false;
	}

	function create_s3_asset( AudioStorage $storage, int $post_id, string $bucket, string $region, string $key ): array {
		$url = 'https://s3.' . $region . '.amazonaws.com/' . $bucket . '/' . $key;
		$descriptor = $storage->create_s3_descriptor( $post_id, $bucket, $region, $key, $url );
		assert_true( $storage->record_saved_asset( $post_id, $descriptor ), 'S3 descriptor fixture must persist.' );
		return $descriptor;
	}

	remove_storage_test_tree( $GLOBALS['storage_test_root'] );
	mkdir( $GLOBALS['storage_test_uploads'], 0777, true );

	try {
		$storage = new AudioStorage();
		$storage->register_hooks();
		assert_true( isset( $GLOBALS['storage_test_hooks']['admin_notices'] ), 'Persistent notice hook must be registered.' );
		assert_true( isset( $GLOBALS['storage_test_hooks']['admin_post_itron_polly_tts_dismiss_cleanup_failure'] ), 'Nonce-protected dismissal hook must be registered.' );
		( new AudioStorage() )->register_hooks();
		assert_same( 1, count( $GLOBALS['storage_test_hooks']['admin_notices'] ), 'Notice hooks must register only once per request.' );
		assert_same( 1, count( $GLOBALS['storage_test_hooks']['admin_post_itron_polly_tts_dismiss_cleanup_failure'] ), 'Dismissal hooks must register only once per request.' );

		// S3 A -> B cleanup must retain the exact original region, bucket and key.
		$GLOBALS['storage_test_options']['bucket'] = 'bucket-a';
		$GLOBALS['storage_test_options']['region'] = 'us-east-1';
		$GLOBALS['storage_test_prefix']            = '2024/03/';
		$s3_filesystem = new StorageTestFilesystem();
		$s3_client     = new StorageTestS3Client( 'us-east-1' );
		$s3_handler    = new S3FileHandler( new Common(), $storage );
		$s3_handler->set_s3_client( $s3_client );
		$GLOBALS['storage_test_options']['region'] = 'eu-central-1';
		$s3_temp = make_temp_audio( 11 );
		$s3_url  = $s3_handler->save( $s3_filesystem, $s3_temp, '', '', 11, 'itron_polly_tts_11.mp3' );
		$s3_descriptor = $storage->get_descriptor( 11 );

		assert_false( array_key_exists( 'ACL', $s3_client->puts[0] ), 'S3 uploads must not request public-read ACL.' );
		assert_same( 'audio/mpeg', $s3_client->puts[0]['ContentType'], 'S3 uploads must set the MPEG content type.' );
		assert_same( 'bucket-a', $s3_descriptor['bucket'], 'Saved S3 descriptor must preserve bucket A.' );
		assert_same( 'us-east-1', $s3_descriptor['region'], 'Saved S3 descriptor must preserve the actual client region rather than a changed option.' );
		assert_same( '2024/03/itron_polly_tts_11.mp3', $s3_descriptor['key'], 'Saved S3 descriptor must preserve the exact key.' );
		assert_same( $s3_url, $s3_descriptor['delivery_url'], 'Saved S3 descriptor must preserve its generated URL.' );
		assert_same( 'itron-polly-tts', $s3_descriptor['provenance'], 'Saved S3 descriptor must identify plugin provenance.' );

		$GLOBALS['storage_test_options']['bucket'] = 'bucket-b';
		$GLOBALS['storage_test_options']['region'] = 'eu-west-1';
		$GLOBALS['storage_test_prefix']            = '';
		$delete_client   = new StorageTestS3Client();
		$selected_region = '';
		assert_true(
			$storage->cleanup_post_audio(
				11,
				function ( array $descriptor ) use ( $storage, $delete_client, &$selected_region ): bool {
					$selected_region = $descriptor['region'];
					$handler = new S3FileHandler( new Common(), $storage );
					$handler->set_s3_client( $delete_client );
					return $handler->delete( null, $descriptor, 11 );
				}
			),
			'Original S3 asset cleanup must succeed.'
		);
		assert_same( 'us-east-1', $selected_region, 'Cleanup must select a client for the original region.' );
		assert_same(
			array(
				'Bucket' => 'bucket-a',
				'Key'    => '2024/03/itron_polly_tts_11.mp3',
			),
			$delete_client->deletes[0],
			'Cleanup must ignore current bucket and prefix settings.'
		);

		// Local cleanup must retain the original path after folder settings change.
		$GLOBALS['storage_test_prefix'] = '2024/03/';
		$local_filesystem = new StorageTestFilesystem();
		$local_handler    = new LocalFileHandler( new Common( true ), $storage );
		$local_temp       = make_temp_audio( 12 );
		$local_destination = $local_handler->get_destination( 12, 'itron_polly_tts_12.mp3' );
		$local_directory  = trailingslashit( dirname( $local_destination['path'] ) );
		$local_path       = $local_destination['path'];
		$local_url        = $local_handler->save( $local_filesystem, $local_temp, $local_directory, $local_path, 12, 'itron_polly_tts_12.mp3' );
		$local_descriptor = $storage->get_descriptor( 12 );
		$attachment_id    = $local_descriptor['attachment']['id'];
		$attachment_mark  = get_post_meta( $attachment_id, AudioStorage::ATTACHMENT_PROVENANCE_META_KEY, true );

		assert_same( $local_path, $local_descriptor['path'], 'Local descriptor must save the validated original path.' );
		assert_same( 'https://example.test/uploads/2024/03/itron_polly_tts_12.mp3', $local_url, 'Local URL must use the same validated relative path.' );
		assert_false( $local_filesystem->moves[0]['overwrite'], 'Local saves must never request destination overwrite.' );
		assert_same( $local_descriptor['asset_id'], $attachment_mark['asset_id'], 'Attachment marker must agree with descriptor provenance.' );
		assert_true( $storage->local_file_exists( 12 ), 'Validated local availability must find the exact saved file.' );
		assert_true( $storage->delivery_url_matches( 12, $local_url ), 'Exact saved delivery URL must match.' );
		assert_false( $storage->delivery_url_matches( 12, $local_url . '?other=1' ), 'A different delivery URL must fail closed.' );

		$GLOBALS['storage_test_prefix'] = '';
		assert_true(
			$storage->cleanup_post_audio( 12, static fn( array $descriptor ): bool => $local_handler->delete( $local_filesystem, $descriptor, 12 ) ),
			'Local cleanup must use the saved path after settings change.'
		);
		assert_false( file_exists( $local_path ), 'The original local file must be removed.' );
		assert_true( in_array( $attachment_id, $GLOBALS['storage_test_deleted'], true ), 'Owned attachment must be deleted through the guarded sink.' );

		// A failed or ownership-blocked cleanup must never be followed by a blind overwrite.
		$GLOBALS['storage_test_prefix'] = '2024/03/';
		$occupied_filesystem = new StorageTestFilesystem();
		$occupied_handler    = new LocalFileHandler( new Common(), $storage );
		$occupied_destination = $occupied_handler->get_destination( 36, 'itron_polly_tts_36.mp3' );
		$occupied_directory  = trailingslashit( dirname( $occupied_destination['path'] ) );
		$occupied_path       = $occupied_destination['path'];
		$occupied_temp       = make_temp_audio( 36, 'candidate' );
		file_put_contents( $occupied_path, 'existing-unowned-audio' );
		$occupied_threw = false;
		try {
			$occupied_handler->save( $occupied_filesystem, $occupied_temp, $occupied_directory, $occupied_path, 36, 'itron_polly_tts_36.mp3' );
		} catch ( RuntimeException $e ) {
			$occupied_threw = true;
		}
		assert_true( $occupied_threw, 'An occupied local destination must make save fail closed.' );
		assert_same( 'existing-unowned-audio', file_get_contents( $occupied_path ), 'An occupied destination must retain its original bytes.' );
		assert_same( array(), $occupied_filesystem->moves, 'An occupied destination must never reach the filesystem move sink.' );
		assert_true( file_exists( $occupied_temp ), 'The caller must retain control of temporary-file cleanup after refusal.' );
		assert_same( null, $storage->get_descriptor( 36 ), 'A refused local save must not record a descriptor.' );
		unlink( $occupied_temp );
		unlink( $occupied_path );

		// Missing descriptor must fail closed when legacy audio metadata says an asset exists.
		$GLOBALS['storage_test_meta'][13]['itron_polly_tts_audio_link_location'] = 'https://legacy.invalid/audio.mp3';
		$missing_delete_called = false;
		assert_false(
			$storage->cleanup_post_audio(
				13,
				static function () use ( &$missing_delete_called ): bool {
					$missing_delete_called = true;
					return true;
				}
			),
			'Old-format metadata without an immutable locator must fail closed.'
		);
		assert_false( $missing_delete_called, 'Missing descriptors must never reach a delete sink.' );
		assert_true( has_failure_code( 'descriptor_missing' ), 'Missing descriptors must create a persistent safe failure code.' );

		// AWS failures must be classified without persisting raw exception details.
		$failure_cases = array(
			21 => array( new StorageTestAwsException( 'AccessDenied', 403 ), 's3_access_denied' ),
			22 => array( new StorageTestAwsException( 'NoSuchBucket', 404 ), 's3_bucket_missing' ),
			23 => array( new StorageTestAwsException( '', 0, true ), 's3_network_error' ),
		);
		foreach ( $failure_cases as $post_id => $failure_case ) {
			$descriptor = create_s3_asset( $storage, $post_id, 'original-bucket-' . $post_id, 'ap-south-1', 'old/itron_polly_tts_' . $post_id . '.mp3' );
			$client = new StorageTestS3Client();
			$client->delete_exception = $failure_case[0];
			$handler = new S3FileHandler( new Common(), $storage );
			$handler->set_s3_client( $client );
			assert_false(
				$storage->cleanup_post_audio( $post_id, static fn( array $saved ): bool => $handler->delete( null, $saved, $post_id ) ),
				'AWS delete errors must not report cleanup success.'
			);
			assert_true( has_failure_code( $failure_case[1] ), 'AWS failure must use the expected safe code: ' . $failure_case[1] );
			assert_false( str_contains( serialize( failure_records() ), 'SECRET raw AWS' ), 'Failure options must not persist raw exception messages.' );
		}

		// Independent options must survive deletion of all parent post metadata.
		unset( $GLOBALS['storage_test_meta'][21] );
		assert_true( has_failure_code( 's3_access_denied' ), 'Cleanup failure must survive parent post deletion.' );
		foreach ( $GLOBALS['storage_test_autoload'] as $name => $autoload ) {
			if ( str_starts_with( $name, 'itron_polly_tts_cleanup_failure_' ) ) {
				assert_same( false, $autoload, 'Cleanup failure options must be nonautoload.' );
			}
		}

		// A successful save at the same locator resolves the old-locator warning.
		assert_true( $storage->record_saved_asset( 22, get_post_meta( 22, AudioStorage::POST_META_KEY, true ) ), 'Same-location save must remain recordable.' );
		assert_false( has_failure_code( 's3_bucket_missing' ), 'Same-location success must resolve its stale failure.' );

		// An unrelated or tampered attachment must block both deletion sinks.
		$unrelated_directory = $GLOBALS['storage_test_uploads'] . '/owned/';
		mkdir( $unrelated_directory, 0777, true );
		$unrelated_path = $unrelated_directory . 'itron_polly_tts_31.mp3';
		file_put_contents( $unrelated_path, 'unrelated' );
		$unrelated_descriptor = $storage->create_local_descriptor(
			31,
			$unrelated_path,
			'https://example.test/uploads/owned/itron_polly_tts_31.mp3',
			array(
				'id'             => 931,
				'parent_post_id' => 31,
				'path'           => $unrelated_path,
				'mime_type'      => 'audio/mpeg',
			)
		);
		$storage->record_saved_asset( 31, $unrelated_descriptor );
		$GLOBALS['storage_test_posts'][931] = (object) array(
			'ID'          => 931,
			'post_type'   => 'attachment',
			'post_parent' => 999,
		);
		$GLOBALS['storage_test_attached_files'][931] = $unrelated_path;
		$GLOBALS['storage_test_mime_types'][931]     = 'audio/mpeg';
		$physical_delete_called = false;
		assert_false(
			$storage->cleanup_post_audio(
				31,
				static function () use ( &$physical_delete_called ): bool {
					$physical_delete_called = true;
					return true;
				}
			),
			'Attachment ownership disagreement must fail cleanup.'
		);
		assert_false( in_array( 931, $GLOBALS['storage_test_deleted'], true ), 'Unrelated attachment must never reach wp_delete_attachment().' );
		assert_false( $physical_delete_called, 'Attachment ownership disagreement must also protect its path.' );
		assert_true( file_exists( $unrelated_path ), 'Unrelated attachment file must remain untouched.' );
		assert_true( has_failure_code( 'attachment_ownership_mismatch' ), 'Ownership rejection must be actionable.' );

		// An owned attachment deletion failure must be reported even if direct file cleanup succeeds.
		$failed_attachment_path = $unrelated_directory . 'itron_polly_tts_32.mp3';
		file_put_contents( $failed_attachment_path, 'owned' );
		$failed_attachment_descriptor = $storage->create_local_descriptor(
			32,
			$failed_attachment_path,
			'https://example.test/uploads/owned/itron_polly_tts_32.mp3',
			array(
				'id'             => 932,
				'parent_post_id' => 32,
				'path'           => $failed_attachment_path,
				'mime_type'      => 'audio/mpeg',
			)
		);
		$storage->record_saved_asset( 32, $failed_attachment_descriptor );
		$GLOBALS['storage_test_posts'][932] = (object) array(
			'ID'          => 932,
			'post_type'   => 'attachment',
			'post_parent' => 32,
		);
		$GLOBALS['storage_test_attached_files'][932] = $failed_attachment_path;
		$GLOBALS['storage_test_mime_types'][932]     = 'audio/mpeg';
		$GLOBALS['storage_test_meta'][932][ AudioStorage::ATTACHMENT_PROVENANCE_META_KEY ] = array(
			'post_id'  => 32,
			'asset_id' => $failed_attachment_descriptor['asset_id'],
			'path'     => $failed_attachment_path,
		);
		$GLOBALS['storage_test_delete_failures'][932] = true;
		$failed_attachment_handler = new LocalFileHandler( new Common(), $storage );
		assert_false(
			$storage->cleanup_post_audio( 32, static fn( array $saved ): bool => $failed_attachment_handler->delete( new StorageTestFilesystem(), $saved, 32 ) ),
			'Failed attachment deletion must prevent a success result.'
		);
		assert_true( has_failure_code( 'attachment_delete_failed' ), 'Attachment deletion failure must be persisted.' );
		assert_false( file_exists( $failed_attachment_path ), 'Owned physical file cleanup should still be attempted.' );

		file_put_contents( $failed_attachment_path, 'replacement' );
		$replacement_descriptor = $storage->create_local_descriptor(
			32,
			$failed_attachment_path,
			'https://example.test/uploads/owned/itron_polly_tts_32.mp3'
		);
		$storage->record_saved_asset( 32, $replacement_descriptor );
		$stale_attachment_retained = false;
		foreach ( failure_records() as $record ) {
			if ( in_array( 'attachment_delete_failed', $record['codes'], true ) && 32 === $record['post_id'] ) {
				$stale_attachment_retained = 'attachment' === $record['storage_type']
					&& 932 === $record['location']['attachment_id']
					&& ! isset( $record['location']['path'] );
			}
		}
		assert_true( $stale_attachment_retained, 'Same-location save must retain only the stale attachment ID, not a path now used by the replacement.' );

		// WordPress can return null from wp_delete_attachment(); null is not success.
		$null_attachment_path = $unrelated_directory . 'itron_polly_tts_33.mp3';
		file_put_contents( $null_attachment_path, 'owned-null' );
		$null_attachment_descriptor = $storage->create_local_descriptor(
			33,
			$null_attachment_path,
			'https://example.test/uploads/owned/itron_polly_tts_33.mp3',
			array(
				'id'             => 933,
				'parent_post_id' => 33,
				'path'           => $null_attachment_path,
				'mime_type'      => 'audio/mpeg',
			)
		);
		$storage->record_saved_asset( 33, $null_attachment_descriptor );
		$GLOBALS['storage_test_posts'][933] = (object) array(
			'ID'          => 933,
			'post_type'   => 'attachment',
			'post_parent' => 33,
		);
		$GLOBALS['storage_test_attached_files'][933] = $null_attachment_path;
		$GLOBALS['storage_test_mime_types'][933]     = 'audio/mpeg';
		$GLOBALS['storage_test_meta'][933][ AudioStorage::ATTACHMENT_PROVENANCE_META_KEY ] = array(
			'post_id'  => 33,
			'asset_id' => $null_attachment_descriptor['asset_id'],
			'path'     => $null_attachment_path,
		);
		$GLOBALS['storage_test_delete_nulls'][933] = true;
		$null_attachment_handler = new LocalFileHandler( new Common(), $storage );
		assert_false(
			$storage->cleanup_post_audio( 33, static fn( array $saved ): bool => $null_attachment_handler->delete( new StorageTestFilesystem(), $saved, 33 ) ),
			'A null attachment deletion result must not report success.'
		);
		assert_true( has_failure_code( 'attachment_delete_failed' ), 'Null attachment deletion must produce the safe failure code.' );

		// A local filesystem deletion failure must be retained and must not report success.
		$failed_local_path = $unrelated_directory . 'itron_polly_tts_35.mp3';
		file_put_contents( $failed_local_path, 'undeletable' );
		$failed_local_descriptor = $storage->create_local_descriptor(
			35,
			$failed_local_path,
			'https://example.test/uploads/owned/itron_polly_tts_35.mp3'
		);
		$storage->record_saved_asset( 35, $failed_local_descriptor );
		$failed_local_filesystem = new StorageTestFilesystem();
		$failed_local_filesystem->failed_deletes[] = $failed_local_path;
		$failed_local_handler = new LocalFileHandler( new Common(), $storage );
		assert_false(
			$storage->cleanup_post_audio( 35, static fn( array $saved ): bool => $failed_local_handler->delete( $failed_local_filesystem, $saved, 35 ) ),
			'Local filesystem deletion failure must not report success.'
		);
		assert_true( has_failure_code( 'local_delete_failed' ), 'Local filesystem deletion failure must persist its safe code.' );
		assert_true( file_exists( $failed_local_path ), 'Failed local deletion must leave the original file in place.' );

		// A physical upload followed by a descriptor-write failure must retain the known locator independently.
		$GLOBALS['storage_test_options']['bucket'] = 'descriptor-failure-bucket';
		$GLOBALS['storage_test_options']['region'] = 'us-west-2';
		$GLOBALS['storage_test_prefix']            = '';
		$GLOBALS['storage_test_meta_failures'][34][ AudioStorage::POST_META_KEY ] = true;
		$descriptor_failure_client  = new StorageTestS3Client();
		$descriptor_failure_handler = new S3FileHandler( new Common(), $storage );
		$descriptor_failure_handler->set_s3_client( $descriptor_failure_client );
		$descriptor_failure_thrown = false;
		try {
			$descriptor_failure_handler->save(
				new StorageTestFilesystem(),
				make_temp_audio( 34 ),
				'',
				'',
				34,
				'itron_polly_tts_34.mp3'
			);
		} catch ( RuntimeException $e ) {
			$descriptor_failure_thrown = true;
		}
		assert_true( $descriptor_failure_thrown, 'Descriptor persistence failure must fail the save operation.' );
		assert_true( has_failure_code( 'descriptor_write_failed' ), 'Descriptor persistence failure must create a safe independent record.' );
		$known_locator_retained = false;
		foreach ( failure_records() as $record ) {
			if ( in_array( 'descriptor_write_failed', $record['codes'], true ) ) {
				$known_locator_retained = 'descriptor-failure-bucket' === $record['location']['bucket']
					&& 'itron_polly_tts_34.mp3' === $record['location']['key'];
			}
		}
		assert_true( $known_locator_retained, 'Descriptor write failure must retain the exact known bucket and key.' );

		// Traversal and symlink escapes must be rejected before storage use.
		$traversal_rejected = false;
		try {
			$storage->get_local_destination( 40, 'itron_polly_tts_40.mp3', '../outside/' );
		} catch ( InvalidArgumentException $e ) {
			$traversal_rejected = true;
		}
		assert_true( $traversal_rejected, 'Traversal in a filtered prefix must be rejected.' );

		$outside = $GLOBALS['storage_test_root'] . '/outside';
		mkdir( $outside, 0777, true );
		file_put_contents( $outside . '/itron_polly_tts_41.mp3', 'outside' );
		symlink( $outside, $GLOBALS['storage_test_uploads'] . '/escape' );
		$symlink_rejected = false;
		try {
			$storage->create_local_descriptor(
				41,
				$GLOBALS['storage_test_uploads'] . '/escape/itron_polly_tts_41.mp3',
				'https://example.test/uploads/escape/itron_polly_tts_41.mp3'
			);
		} catch ( InvalidArgumentException $e ) {
			$symlink_rejected = true;
		}
		assert_true( $symlink_rejected, 'A symlink escape from uploads must be rejected.' );

		// Persistent notices are capability-scoped and include safe recovery details plus nonce action.
		ob_start();
		$storage->render_failure_notices();
		assert_same( '', ob_get_clean(), 'Cleanup notices must be hidden without manage_options.' );
		$GLOBALS['storage_test_can_manage'] = true;
		ob_start();
		$storage->render_failure_notices();
		$notice_output = ob_get_clean();
		assert_true( str_contains( $notice_output, 'Original asset:' ), 'Administrator notice must include the original locator.' );
		assert_true( str_contains( $notice_output, 'Dismiss after resolving manually' ), 'Administrator notice must make manual-resolution dismissal explicit.' );
		assert_true( str_contains( $notice_output, '_wpnonce=' ), 'Dismissal URL must contain an explicit nonce.' );
		assert_false( str_contains( $notice_output, 'SECRET raw AWS' ), 'Administrator notice must not expose raw exception details.' );

		// Final delivery URL is updated only through the validated descriptor.
		$new_delivery_url = 'https://s3.ap-south-1.amazonaws.com/original-bucket-23/old/itron_polly_tts_23.mp3?version=123';
		assert_true( $storage->set_delivery_url( 23, $new_delivery_url ), 'Final cache-busted URL must update the saved descriptor.' );
		assert_true( $storage->delivery_url_matches( 23, $new_delivery_url ), 'Availability checks must compare the exact final URL.' );

		echo "PASS: storage locator and cleanup regression checks\n";
	} finally {
		remove_storage_test_tree( $GLOBALS['storage_test_root'] );
	}
}
