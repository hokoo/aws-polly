<?php

declare(strict_types=1);

// Standalone conversion safety regression. All AWS commands use SDK MockHandler responses.
define( 'ABSPATH', sys_get_temp_dir() . '/aws-polly-conversion-safety-wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Post {
	public int $ID;
	public string $post_type = 'post';
	public string $post_status = 'publish';
	public string $post_password = '';
	public string $post_content = '';
}

require dirname( __DIR__ ) . '/plugin-dir/vendor/autoload.php';

use Aws\MockHandler;
use Aws\Polly\PollyClient;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use iTRON\PollyTTS\AudioStorage;
use iTRON\PollyTTS\Common;
use iTRON\PollyTTS\FileHandler;
use iTRON\PollyTTS\PollyService;
use iTRON\PollyTTS\S3FileHandler;

$GLOBALS['conversion_safety_options']       = array();
$GLOBALS['conversion_safety_meta']          = array();
$GLOBALS['conversion_safety_posts']         = array();
$GLOBALS['conversion_safety_enabled']       = true;
$GLOBALS['conversion_safety_uuid']          = 0;
$GLOBALS['conversion_safety_rand']          = 100000;
$GLOBALS['conversion_safety_meta_mutation'] = null;
$GLOBALS['conversion_safety_checks']        = 0;
$GLOBALS['conversion_safety_root']          = sys_get_temp_dir() . '/aws-polly-conversion-safety-' . getmypid();
$GLOBALS['conversion_safety_uploads']       = $GLOBALS['conversion_safety_root'] . '/uploads';

mkdir( $GLOBALS['conversion_safety_uploads'], 0777, true );

class ConversionSafetyWpdb {
	public string $options = 'wp_options';
	public string $prefix = 'wp_';
	public string $last_error = '';
	public int $insert_id = 0;
	public int $lock_acquires = 0;
	public int $lock_releases = 0;

	public function esc_like( string $value ): string {
		return $value;
	}

	public function prepare( string $query, ...$args ): string {
		if ( str_contains( $query, 'INSERT INTO' ) ) {
			++$this->lock_acquires;
		}
		if ( str_contains( $query, 'DELETE FROM' ) ) {
			++$this->lock_releases;
		}

		return (string) ( $args[0] ?? $query );
	}

	public function get_col( string $query ): array {
		return array();
	}

	public function get_results( string $query, $output = null ): array {
		return array();
	}

	public function get_var( string $query ) {
		return 1;
	}

	public function get_row( string $query, $output = null ) {
		return null;
	}

	public function suppress_errors( $suppress = null ): bool {
		return false;
	}

	public function query( string $query ): int {
		++$this->insert_id;
		return 1;
	}
}

$GLOBALS['wpdb'] = new ConversionSafetyWpdb();

function get_option( string $key, $default = false ) {
	return $GLOBALS['conversion_safety_options'][ $key ] ?? $default;
}

function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS['conversion_safety_options'][ $key ] = $value;
	return true;
}

function add_option( string $key, $value, string $deprecated = '', $autoload = true ): bool {
	if ( array_key_exists( $key, $GLOBALS['conversion_safety_options'] ) ) {
		return false;
	}

	$GLOBALS['conversion_safety_options'][ $key ] = $value;
	return true;
}

function delete_option( string $key ): bool {
	unset( $GLOBALS['conversion_safety_options'][ $key ] );
	return true;
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
	return $GLOBALS['conversion_safety_meta'][ $post_id ][ $key ] ?? '';
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	$GLOBALS['conversion_safety_meta'][ $post_id ][ $key ] = $value;
	$mutation = $GLOBALS['conversion_safety_meta_mutation'];

	if (
		is_array( $mutation )
		&& $post_id === $mutation['post_id']
		&& $key === $mutation['key']
		&& ( ! array_key_exists( 'value', $mutation ) || $value === $mutation['value'] )
	) {
		$GLOBALS['conversion_safety_meta_mutation'] = null;
		$GLOBALS['conversion_safety_posts'][ $post_id ]->post_content = $mutation['speech'];
	}

	return true;
}

function delete_post_meta( int $post_id, string $key ): bool {
	unset( $GLOBALS['conversion_safety_meta'][ $post_id ][ $key ] );
	return true;
}

function get_post( int $post_id ) {
	return $GLOBALS['conversion_safety_posts'][ $post_id ] ?? null;
}

function wp_upload_dir(): array {
	return array(
		'basedir' => $GLOBALS['conversion_safety_uploads'],
		'baseurl' => 'https://uploads.example.test',
		'error'   => false,
	);
}

function trailingslashit( string $path ): string {
	return rtrim( $path, '/\\' ) . '/';
}

function untrailingslashit( string $path ): string {
	return rtrim( $path, '/\\' );
}

function get_the_date( string $format, int $post_id ): string {
	return '2026';
}

function wp_rand( int $min, int $max ): int {
	return ++$GLOBALS['conversion_safety_rand'];
}

function wp_generate_uuid4(): string {
	++$GLOBALS['conversion_safety_uuid'];
	return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['conversion_safety_uuid'] );
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string {
	return strip_tags( $value );
}

function esc_attr( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function apply_filters( string $hook, $value, ...$args ) {
	return $value;
}

function do_action( string $hook, ...$args ): void {
}

function do_action_ref_array( string $hook, array $args ): void {
}

function clean_post_cache( int $post_id ): void {
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	return true;
}

function wp_read_audio_metadata( string $path ): array {
	return array();
}

function wp_salt( string $scheme = 'auth' ): string {
	return 'conversion-safety-salt';
}

function sanitize_key( string $value ): string {
	return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
}

function add_query_arg( $key, $value = null, $url = null ): string {
	if ( is_array( $key ) ) {
		return (string) $value . '?' . http_build_query( $key );
	}

	return (string) $url . '?' . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
}

class ConversionSafetyFilesystem {
	public bool $fail_master_write = false;
	public bool $fail_id3_rewrite = false;
	public bool $fail_merged_read = false;
	private array $writes = array();

	public function exists( string $path ): bool {
		return file_exists( $path );
	}

	public function delete( string $path ): bool {
		return ! file_exists( $path ) || unlink( $path );
	}

	public function put_contents( string $path, string $contents ): bool {
		if (
			$this->fail_master_write
			&& ! str_contains( $path, '_part_' )
			&& preg_match( '/temp_itron_polly_tts_\d+\.mp3\d+$/', $path )
		) {
			return false;
		}

		$this->writes[ $path ] = ( $this->writes[ $path ] ?? 0 ) + 1;
		if ( $this->fail_id3_rewrite && str_ends_with( $path, '_part_1' ) && $this->writes[ $path ] > 1 ) {
			return false;
		}

		return false !== file_put_contents( $path, $contents );
	}

	public function get_contents( string $path ) {
		if ( $this->fail_merged_read && ! str_contains( $path, '_part_' ) ) {
			return false;
		}

		return file_get_contents( $path );
	}

	public function move( string $source, string $destination, bool $overwrite = false ): bool {
		if ( $overwrite && file_exists( $destination ) ) {
			unlink( $destination );
		}

		return rename( $source, $destination );
	}

	public function is_dir( string $path ): bool {
		return is_dir( $path );
	}
}

class ConversionSafetyThrowingHandler extends FileHandler {
	public function save( $wp_filesystem, $temp, $directory, $final, $post_id, $file_name ) {
		throw new RuntimeException( 'Conversion unexpectedly reached publication.' );
	}

	public function delete( $wp_filesystem, array $descriptor, $post_id ) {
		return true;
	}

	public function get_type() {
		return 'test';
	}
}

class ConversionSafetyS3Handler extends S3FileHandler {
	private bool $disable_after_save;

	public function __construct( Common $common, AudioStorage $storage, bool $disable_after_save ) {
		parent::__construct( $common, $storage );
		$this->disable_after_save = $disable_after_save;
	}

	public function save( $wp_filesystem, $temp, $directory, $final, $post_id, $file_name ) {
		$url = parent::save( $wp_filesystem, $temp, $directory, $final, $post_id, $file_name );
		if ( $this->disable_after_save ) {
			$GLOBALS['conversion_safety_enabled'] = false;
		}

		return $url;
	}
}

class ConversionSafetyMutatingStorage extends AudioStorage {
	public function set_delivery_url( int $post_id, string $delivery_url ): bool {
		$GLOBALS['conversion_safety_posts'][ $post_id ]->post_content = 'Changed during descriptor persistence';
		return parent::set_delivery_url( $post_id, $delivery_url );
	}
}

class ConversionSafetyCommon extends Common {
	private PollyClient $polly;
	private FileHandler $handler;
	private AudioStorage $storage;
	private ConversionSafetyFilesystem $filesystem;
	public int $physical_deletes = 0;

	public function __construct( PollyClient $polly, FileHandler $handler, AudioStorage $storage, ConversionSafetyFilesystem $filesystem ) {
		$this->polly      = $polly;
		$this->handler    = $handler;
		$this->storage    = $storage;
		$this->filesystem = $filesystem;
		parent::__construct();
	}

	public function set_file_handler( FileHandler $handler ): void {
		$this->handler = $handler;
	}

	public function is_polly_enabled() {
		return $GLOBALS['conversion_safety_enabled'];
	}

	public function get_posttypes_array() {
		return array( 'post' );
	}

	public function get_audio_voice_request( int $post_id ): array {
		return array(
			'voice'    => 'Joanna',
			'language' => 'en-US',
			'region'   => 'us-east-1',
		);
	}

	public function get_audio_hash( $post_id, ?string $clean_text = null, ?string $resolved_voice_id = null ): string {
		$post = get_post( (int) $post_id );
		return hash( 'sha256', (string) ( $post->post_content ?? '' ) . "\0" . (string) $resolved_voice_id );
	}

	public function get_voice_id() {
		return 'Joanna';
	}

	public function resolve_polly_voice_id( $language_code, $requested_voice_id = '', $fallback_voice_id = '', array $args = array() ) {
		return 'Joanna';
	}

	public function validate_itron_polly_tts_access( $persist_state = true, $show_notices = true ): bool {
		return true;
	}

	public function clean_text( $post_id, $with_title, $only_title ) {
		return (string) ( get_post( (int) $post_id )->post_content ?? '' );
	}

	public function get_sample_rate() {
		return '24000';
	}

	public function break_text( $text ) {
		return array( (string) $text );
	}

	public function prepare_wp_filesystem() {
		return $this->filesystem;
	}

	public function get_polly_client() {
		return $this->polly;
	}

	public function get_polly_engine( $voice ) {
		return 'standard';
	}

	public function should_news_style_be_used( $voice ) {
		return false;
	}

	public function get_lexicons() {
		return '';
	}

	public function is_auto_breaths_enabled() {
		return false;
	}

	public function is_ssml_enabled() {
		return false;
	}

	public function get_file_handler() {
		return $this->handler;
	}

	public function get_audio_storage(): AudioStorage {
		return $this->storage;
	}

	public function set_post_audio_state( int $post_id, string $state ): void {
		update_post_meta( $post_id, self::AUDIO_STATE_META_KEY, $state );
	}

	public function delete_post_audio( $post_id ) {
		$post_id    = (int) $post_id;
		$descriptor = $this->storage->get_descriptor( $post_id );
		if ( null !== $descriptor ) {
			$deleted = $this->storage->cleanup_post_audio(
				$post_id,
				function ( array $saved ) use ( $post_id ): bool {
					++$this->physical_deletes;
					return (bool) $this->handler->delete( null, $saved, $post_id );
				}
			);
			if ( ! $deleted ) {
				return false;
			}
		}

		foreach (
			array(
				AudioStorage::POST_META_KEY,
				'itron_polly_tts_audio_link_location',
				'itron_polly_tts_audio_location',
				'itron_polly_tts_generated_voice_id',
				'itron_polly_tts_audio_hash',
				'itron_polly_tts_audio_voice',
			) as $meta_key
		) {
			delete_post_meta( $post_id, $meta_key );
		}
		update_post_meta( $post_id, self::AUDIO_STATE_META_KEY, self::AUDIO_STATE_NONE );

		return true;
	}
}

function conversion_safety_post( int $post_id, string $speech ): void {
	$post               = new WP_Post();
	$post->ID           = $post_id;
	$post->post_content = $speech;

	$GLOBALS['conversion_safety_posts'][ $post_id ] = $post;
	$GLOBALS['conversion_safety_meta'][ $post_id ]  = array( 'itron_polly_tts_enable' => '1' );
	$GLOBALS['conversion_safety_enabled']            = true;
	$GLOBALS['conversion_safety_meta_mutation']      = null;
}

function conversion_safety_polly( array $audio_payloads ): array {
	$responses = array_map(
		static fn( string $audio ): Result => new Result( array( 'AudioStream' => Utils::streamFor( $audio ) ) ),
		$audio_payloads
	);
	$mock      = new MockHandler( $responses );
	$client    = new PollyClient(
		array(
			'credentials' => array(
				'key'    => 'test-key',
				'secret' => 'test-secret',
			),
			'endpoint'    => 'https://polly.mock.invalid',
			'handler'     => $mock,
			'region'      => 'us-east-1',
			'version'     => 'latest',
		)
	);

	return array( $client, $mock );
}

function conversion_safety_s3( int $response_count ): array {
	$mock   = new MockHandler( array_fill( 0, $response_count, new Result() ) );
	$client = new S3Client(
		array(
			'credentials'             => array(
				'key'    => 'test-key',
				'secret' => 'test-secret',
			),
			'endpoint'                => 'https://s3.mock.invalid',
			'handler'                 => $mock,
			'region'                  => 'us-east-1',
			'use_path_style_endpoint' => true,
			'version'                 => 'latest',
		)
	);

	return array( $client, $mock );
}

function conversion_safety_case( int $post_id, string $speech, ?AudioStorage $storage = null, bool $disable_after_save = false ): array {
	conversion_safety_post( $post_id, $speech );
	list( $polly, $polly_mock ) = conversion_safety_polly( array( 'mock-mp3' ) );
	list( $s3, $s3_mock )       = conversion_safety_s3( 2 );
	$storage                    = $storage ?? new AudioStorage();
	$filesystem                 = new ConversionSafetyFilesystem();
	$common                     = new ConversionSafetyCommon( $polly, new ConversionSafetyThrowingHandler(), $storage, $filesystem );
	$handler                    = new ConversionSafetyS3Handler( $common, $storage, $disable_after_save );
	$handler->set_s3_client( $s3 );
	$common->set_file_handler( $handler );
	$snapshot = array(
		'request'  => $common->get_audio_voice_request( $post_id ),
		'resolved' => 'Joanna',
	);

	return array(
		'common'      => $common,
		'filesystem'  => $filesystem,
		'handler'     => $handler,
		'polly_mock'  => $polly_mock,
		'post_id'     => $post_id,
		's3_mock'     => $s3_mock,
		'service'     => new PollyService( $common ),
		'snapshot'    => $snapshot,
		'storage'     => $storage,
		'expected'    => $common->get_audio_hash( $post_id, null, 'Joanna' ),
	);
}

function conversion_safety_convert( array $case ): void {
	$case['service']->convert_to_audio(
		$case['post_id'],
		'24000',
		'Joanna',
		array( get_post( $case['post_id'] )->post_content ),
		$case['filesystem'],
		$case['expected'],
		$case['snapshot']
	);
}

function conversion_safety_expect_cancelled( array $case, string $message ): void {
	$cancelled = false;
	try {
		conversion_safety_convert( $case );
	} catch ( RuntimeException $exception ) {
		$cancelled = str_contains( $exception->getMessage(), 'cancelled' );
	}

	conversion_safety_check( $cancelled, $message );
}

function conversion_safety_temp_files( int $post_id ): array {
	$matches = glob( trailingslashit( $GLOBALS['conversion_safety_uploads'] ) . 'temp_itron_polly_tts_' . $post_id . '.mp3*' );
	return is_array( $matches ) ? $matches : array();
}

function conversion_safety_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}

	++$GLOBALS['conversion_safety_checks'];
}

function conversion_safety_check_cancelled_cleanup( array $case, string $context ): void {
	conversion_safety_check( 'DeleteObject' === $case['s3_mock']->getLastCommand()->getName(), $context . ' must delete the mocked S3 object.' );
	conversion_safety_check( 1 === $case['common']->physical_deletes, $context . ' must delete the uploaded object exactly once.' );
	conversion_safety_check( '' === get_post_meta( $case['post_id'], 'itron_polly_tts_audio_link_location', true ), $context . ' must remove the public link.' );
	conversion_safety_check( null === $case['storage']->get_descriptor( $case['post_id'] ), $context . ' must remove the saved descriptor.' );
	conversion_safety_check( array() === conversion_safety_temp_files( $case['post_id'] ), $context . ' must remove temporary files.' );
}

function conversion_safety_remove_tree( string $path ): void {
	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}
	if ( ! is_dir( $path ) ) {
		return;
	}

	foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
		conversion_safety_remove_tree( $path . '/' . $entry );
	}
	rmdir( $path );
}

$conversion_safety_failure = null;

try {
	$GLOBALS['conversion_safety_options'] = array(
		'itron_polly_tts_s3_bucket_name' => 'conversion-safety-bucket',
		'itron_polly_tts_s3_region'      => 'us-east-1',
	);

	$case = conversion_safety_case( 51, 'Speech at default speed' );
	conversion_safety_convert( $case );
	conversion_safety_check( str_contains( (string) $case['polly_mock']->getLastCommand()['Text'], 'Speech at default speed' ), 'Default speed must preserve speech in the SDK command.' );
	conversion_safety_check( str_contains( (string) $case['polly_mock']->getLastCommand()['Text'], 'rate="100%"' ), 'Default speed must normalize to 100 percent.' );
	conversion_safety_check( null === $case['s3_mock']->getLastCommand()['ACL'], 'S3 publication must omit a public-read ACL.' );
	conversion_safety_check( array() === conversion_safety_temp_files( 51 ), 'Successful publication must remove temporary files.' );

	$case = conversion_safety_case( 52, 'Stale after upload', null, true );
	conversion_safety_expect_cancelled( $case, 'Global OFF after upload must cancel publication.' );
	conversion_safety_check_cancelled_cleanup( $case, 'Global-OFF cancellation' );

	$case = conversion_safety_case( 55, 'Fresh before descriptor persistence', new ConversionSafetyMutatingStorage() );
	conversion_safety_expect_cancelled( $case, 'Speech mutation during descriptor persistence must cancel publication.' );
	conversion_safety_check_cancelled_cleanup( $case, 'Descriptor-persistence cancellation' );

	$case = conversion_safety_case( 56, 'Fresh before public-link metadata' );
	$GLOBALS['conversion_safety_meta_mutation'] = array(
		'post_id' => 56,
		'key'     => 'itron_polly_tts_audio_link_location',
		'speech'  => 'Changed from the public-link metadata hook',
	);
	conversion_safety_expect_cancelled( $case, 'Speech mutation during public-link metadata must cancel publication.' );
	conversion_safety_check_cancelled_cleanup( $case, 'Public-link cancellation' );

	foreach (
		array(
			'itron_polly_tts_audio_hash'  => null,
			'itron_polly_tts_audio_voice' => null,
			Common::AUDIO_STATE_META_KEY  => Common::AUDIO_STATE_READY,
		) as $meta_key => $required_value
	) {
		$post_id = 60 + $GLOBALS['conversion_safety_checks'];
		$case    = conversion_safety_case( $post_id, 'Fresh before final metadata ' . $meta_key );
		$mutation = array(
			'post_id' => $post_id,
			'key'     => $meta_key,
			'speech'  => 'Changed from the final metadata hook for ' . $meta_key,
		);
		if ( null !== $required_value ) {
			$mutation['value'] = $required_value;
		}
		$GLOBALS['conversion_safety_meta_mutation'] = $mutation;
		$lock_acquires_before = $GLOBALS['wpdb']->lock_acquires;
		$lock_releases_before = $GLOBALS['wpdb']->lock_releases;
		$case['service']->generate_audio( $post_id );
		conversion_safety_check_cancelled_cleanup( $case, 'Final ' . $meta_key . ' cancellation' );
		conversion_safety_check( Common::AUDIO_STATE_ERROR === get_post_meta( $post_id, Common::AUDIO_STATE_META_KEY, true ), 'Final metadata cancellation must leave an actionable error state.' );
		conversion_safety_check( $lock_acquires_before + 1 === $GLOBALS['wpdb']->lock_acquires, 'Final metadata cancellation must run under the existing generation lock.' );
		conversion_safety_check( $lock_releases_before + 1 === $GLOBALS['wpdb']->lock_releases, 'Final metadata cancellation must release the existing generation lock.' );
	}

	conversion_safety_post( 53, 'Filesystem write failure' );
	list( $polly ) = conversion_safety_polly( array( 'mock-mp3' ) );
	$filesystem = new ConversionSafetyFilesystem();
	$filesystem->fail_master_write = true;
	$common = new ConversionSafetyCommon( $polly, new ConversionSafetyThrowingHandler(), new AudioStorage(), $filesystem );
	$snapshot = array(
		'request'  => $common->get_audio_voice_request( 53 ),
		'resolved' => 'Joanna',
	);
	$failed = false;
	try {
		( new PollyService( $common ) )->convert_to_audio( 53, '24000', 'Joanna', array( 'Filesystem write failure' ), $filesystem, $common->get_audio_hash( 53, null, 'Joanna' ), $snapshot );
	} catch ( RuntimeException $exception ) {
		$failed = str_contains( $exception->getMessage(), 'write temporary audio' );
	}
	conversion_safety_check( $failed && array() === conversion_safety_temp_files( 53 ), 'Master write failure must remove all temporary files.' );

	conversion_safety_post( 54, 'ID3 rewrite failure' );
	$id3 = 'ID3' . "\x04\x00\x00\x00\x00\x00\x01" . 'payload';
	list( $polly ) = conversion_safety_polly( array( 'first-audio', $id3 ) );
	$filesystem = new ConversionSafetyFilesystem();
	$filesystem->fail_id3_rewrite = true;
	$common = new ConversionSafetyCommon( $polly, new ConversionSafetyThrowingHandler(), new AudioStorage(), $filesystem );
	$snapshot = array(
		'request'  => $common->get_audio_voice_request( 54 ),
		'resolved' => 'Joanna',
	);
	$failed = false;
	try {
		( new PollyService( $common ) )->convert_to_audio( 54, '24000', 'Joanna', array( 'first', 'second' ), $filesystem, $common->get_audio_hash( 54, null, 'Joanna' ), $snapshot );
	} catch ( RuntimeException $exception ) {
		$failed = str_contains( $exception->getMessage(), 'rewrite' );
	}
	conversion_safety_check( $failed && array() === conversion_safety_temp_files( 54 ), 'ID3 rewrite failure must remove every temporary part without creating a derivative.' );

	conversion_safety_post( 57, 'Merged read failure' );
	list( $polly ) = conversion_safety_polly( array( 'first-audio', 'second-audio' ) );
	$filesystem = new ConversionSafetyFilesystem();
	$filesystem->fail_merged_read = true;
	$common = new ConversionSafetyCommon( $polly, new ConversionSafetyThrowingHandler(), new AudioStorage(), $filesystem );
	$snapshot = array(
		'request'  => $common->get_audio_voice_request( 57 ),
		'resolved' => 'Joanna',
	);
	$failed = false;
	try {
		( new PollyService( $common ) )->convert_to_audio( 57, '24000', 'Joanna', array( 'first', 'second' ), $filesystem, $common->get_audio_hash( 57, null, 'Joanna' ), $snapshot );
	} catch ( RuntimeException $exception ) {
		$failed = str_contains( $exception->getMessage(), 'read the temporary audio parts' );
	}
	conversion_safety_check( $failed && array() === conversion_safety_temp_files( 57 ), 'Failed merge reads must abort and remove every temporary part.' );
} catch ( Throwable $exception ) {
	$conversion_safety_failure = $exception;
} finally {
	conversion_safety_remove_tree( $GLOBALS['conversion_safety_root'] );
}

if ( $conversion_safety_failure instanceof Throwable ) {
	fwrite( STDERR, 'FAIL: ' . $conversion_safety_failure->getMessage() . "\n" );
	exit( 1 );
}

echo 'Conversion safety: ' . $GLOBALS['conversion_safety_checks'] . " checks passed.\n";
