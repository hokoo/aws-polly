<?php

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Polly\PollyClient;
use Aws\Result;
use GuzzleHttp\Psr7\Utils;
use iTRON\PollyTTS\AudioConsent;
use iTRON\PollyTTS\AudioStorage;
use iTRON\PollyTTS\Common;
use iTRON\PollyTTS\PollyService;
use Psr\Http\Message\RequestInterface;

if ( ! defined( 'AWS_POLLY_WP_INTEGRATION_QA' ) ) {
	fwrite( STDOUT, "SKIP: WordPress integration fixture was not explicitly enabled.\n" );
	return;
}

$aws_polly_qa_expected_prefix = 'qa_d89af972dcbc_';
if ( AWS_POLLY_WP_INTEGRATION_QA !== $aws_polly_qa_expected_prefix ) {
	fwrite( STDERR, "FAIL: AWS_POLLY_WP_INTEGRATION_QA does not name the isolated fixture.\n" );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'get_bloginfo' ) ) {
	fwrite( STDERR, "FAIL: Bootstrap the isolated WordPress fixture before this test.\n" );
	exit( 1 );
}

global $table_prefix, $wpdb;
if ( $aws_polly_qa_expected_prefix !== $table_prefix ) {
	fwrite( STDERR, "FAIL: Refusing to use an unexpected WordPress table prefix.\n" );
	exit( 1 );
}

if (
	'7.1' !== get_bloginfo( 'version' )
	|| ! defined( 'WP_DEBUG' ) || ! WP_DEBUG
	|| ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON
	|| ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
	|| 'local' !== wp_get_environment_type()
	|| 'http://127.0.0.1:8027' !== home_url()
) {
	fwrite( STDERR, "FAIL: The required isolated WordPress 7.1 safety fixture is not active.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'ai-text-to-speech-using-aws-polly/itron-polly-tts.php' ) ) {
	fwrite( STDERR, "FAIL: The candidate plugin must remain inactive and be loaded explicitly by the test.\n" );
	exit( 1 );
}

$aws_polly_qa_autoload = getenv( 'AWS_POLLY_VENDOR_AUTOLOAD' );
require_once $aws_polly_qa_autoload ? $aws_polly_qa_autoload : __DIR__ . '/../plugin-dir/vendor/autoload.php';

$aws_polly_qa_checks = 0;

function aws_polly_qa_check( bool $condition, string $message ): void {
	global $aws_polly_qa_checks;

	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}

	++$aws_polly_qa_checks;
}

function aws_polly_qa_insert_post( array $post_data ): int {
	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( 'Could not create a synthetic QA post: ' . $post_id->get_error_code() );
	}

	$post_id = (int) $post_id;
	aws_polly_qa_check( $post_id > 5, 'Synthetic QA posts must not reuse fixture baseline IDs 4 or 5.' );

	return $post_id;
}

function aws_polly_qa_temp_files( int $post_id ): array {
	$uploads = wp_upload_dir();
	$matches = glob( trailingslashit( $uploads['basedir'] ) . 'temp_itron_polly_tts_' . $post_id . '.mp3*' );

	return is_array( $matches ) ? $matches : array();
}

function aws_polly_qa_attachment( array $descriptor ): ?WP_Post {
	$attachment_id = (int) ( $descriptor['attachment']['id'] ?? 0 );
	$attachment    = $attachment_id > 0 ? get_post( $attachment_id ) : null;

	return $attachment instanceof WP_Post ? $attachment : null;
}

$aws_polly_qa_option_names = array(
	'itron_polly_tts_polly_enable',
	'itron_polly_tts_s3',
	'itron_polly_tts_medialibrary_enabled',
	'itron_polly_tts_posttypes',
	'itron_polly_tts_source_language',
	'itron_polly_tts_voice_id',
	'itron_polly_tts_sample_rate',
	'itron_polly_tts_speed',
	'itron_polly_tts_lexicons',
	'itron_polly_tts_neural',
	'itron_polly_tts_speaking_style',
	'itron_polly_tts_auto_breaths',
	'itron_polly_tts_ssml',
	'itron_polly_tts_add_post_title',
	'itron_polly_tts_add_post_excerpt',
	'itron_polly_tts_skip_tags',
	'itron_polly_tts_disable_post_voice_override',
	'itron_polly_tts_valid_keys',
	'uploads_use_yearmonth_folders',
);
$aws_polly_qa_missing          = new stdClass();
$aws_polly_qa_option_snapshot = array();
foreach ( $aws_polly_qa_option_names as $aws_polly_qa_option_name ) {
	$aws_polly_qa_value = get_option( $aws_polly_qa_option_name, $aws_polly_qa_missing );
	$aws_polly_qa_option_snapshot[ $aws_polly_qa_option_name ] = array(
		'exists' => $aws_polly_qa_value !== $aws_polly_qa_missing,
		'value'  => $aws_polly_qa_value,
	);
}

$aws_polly_qa_voice_transient = 'itron_polly_tts_voices_' . md5( 'us-east-1' );
$aws_polly_qa_voice_snapshot  = get_transient( $aws_polly_qa_voice_transient );
$aws_polly_qa_voice_timeout   = (int) get_option( '_transient_timeout_' . $aws_polly_qa_voice_transient, 0 );
$aws_polly_qa_previous_user   = get_current_user_id();
$aws_polly_qa_previous_post   = $_POST;
$aws_polly_qa_post_ids        = array();
$aws_polly_qa_known_paths     = array();
$aws_polly_qa_common          = null;
$aws_polly_qa_failure         = null;

try {
	$aws_polly_qa_admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);
	aws_polly_qa_check( ! empty( $aws_polly_qa_admins ), 'The QA fixture must contain an administrator.' );
	wp_set_current_user( (int) $aws_polly_qa_admins[0] );
	$_POST = array();

	$aws_polly_qa_options = array(
		'itron_polly_tts_polly_enable'               => 'on',
		'itron_polly_tts_s3'                         => '',
		'itron_polly_tts_medialibrary_enabled'        => 'on',
		'itron_polly_tts_posttypes'                   => 'post',
		'itron_polly_tts_source_language'             => 'en',
		'itron_polly_tts_voice_id'                    => 'Matthew',
		'itron_polly_tts_sample_rate'                 => '24000',
		'itron_polly_tts_lexicons'                    => '',
		'itron_polly_tts_neural'                      => '',
		'itron_polly_tts_speaking_style'              => '',
		'itron_polly_tts_auto_breaths'                => '',
		'itron_polly_tts_ssml'                        => 'on',
		'itron_polly_tts_add_post_title'              => '',
		'itron_polly_tts_add_post_excerpt'            => '',
		'itron_polly_tts_skip_tags'                   => 'script style',
		'itron_polly_tts_disable_post_voice_override' => '',
		'uploads_use_yearmonth_folders'                => 0,
	);
	foreach ( $aws_polly_qa_options as $aws_polly_qa_option_name => $aws_polly_qa_value ) {
		update_option( $aws_polly_qa_option_name, $aws_polly_qa_value );
	}
	delete_option( 'itron_polly_tts_speed' );

	set_transient(
		$aws_polly_qa_voice_transient,
		array(
			'Voices' => array(
				array(
					'Id'               => 'Matthew',
					'LanguageCode'     => 'en-US',
					'LanguageName'     => 'US English',
					'Gender'           => 'Male',
					'SupportedEngines' => array( 'standard', 'neural' ),
				),
				array(
					'Id'               => 'Joanna',
					'LanguageCode'     => 'en-US',
					'LanguageName'     => 'US English',
					'Gender'           => 'Female',
					'SupportedEngines' => array( 'standard', 'neural' ),
				),
			),
		),
		600
	);

	$aws_polly_qa_marker = 'aws-polly-wp-integration-d89af972dcbc-' . wp_generate_uuid4();
	$aws_polly_qa_main_id = aws_polly_qa_insert_post(
		array(
			'post_author'  => (int) $aws_polly_qa_admins[0],
			'post_title'   => $aws_polly_qa_marker . '-lifecycle',
			'post_content' => 'Lifecycle speech input.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);
	$aws_polly_qa_post_ids[] = $aws_polly_qa_main_id;
	$aws_polly_qa_global_stale_id = aws_polly_qa_insert_post(
		array(
			'post_author'  => (int) $aws_polly_qa_admins[0],
			'post_title'   => $aws_polly_qa_marker . '-global-stale',
			'post_content' => 'Global setting race input.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);
	$aws_polly_qa_post_ids[] = $aws_polly_qa_global_stale_id;
	$aws_polly_qa_text_stale_id = aws_polly_qa_insert_post(
		array(
			'post_author'  => (int) $aws_polly_qa_admins[0],
			'post_title'   => $aws_polly_qa_marker . '-text-stale',
			'post_content' => 'Text race input.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);
	$aws_polly_qa_post_ids[] = $aws_polly_qa_text_stale_id;
	$aws_polly_qa_protected_id = aws_polly_qa_insert_post(
		array(
			'post_author'   => (int) $aws_polly_qa_admins[0],
			'post_title'    => $aws_polly_qa_marker . '-protected',
			'post_content'  => 'Protected speech input.',
			'post_password' => 'qa-only-password',
			'post_status'   => 'publish',
			'post_type'     => 'post',
		)
	);
	$aws_polly_qa_post_ids[] = $aws_polly_qa_protected_id;
	foreach ( $aws_polly_qa_post_ids as $aws_polly_qa_post_id ) {
		update_post_meta( $aws_polly_qa_post_id, 'itron_polly_tts_enable', 1 );
	}

	$aws_polly_qa_commands = array();
	$aws_polly_qa_response = static function ( string $bytes, ?callable $mutation = null ) use ( &$aws_polly_qa_commands ): callable {
		return static function ( CommandInterface $command, RequestInterface $request ) use ( $bytes, $mutation, &$aws_polly_qa_commands ): Result {
			unset( $request );
			$aws_polly_qa_commands[] = $command->toArray();
			if ( null !== $mutation ) {
				$mutation();
			}

			return new Result(
				array(
					'AudioStream'       => Utils::streamFor( $bytes ),
					'ContentType'       => 'audio/mpeg',
					'RequestCharacters' => strlen( (string) $command['Text'] ),
				)
			);
		};
	};
	$aws_polly_qa_mock = new MockHandler(
		array(
			$aws_polly_qa_response( 'qa-audio-matthew-v1' ),
			$aws_polly_qa_response( 'qa-audio-joanna-v2' ),
			$aws_polly_qa_response(
				'qa-audio-global-stale',
				static function (): void {
					update_option( 'itron_polly_tts_polly_enable', '' );
				}
			),
			$aws_polly_qa_response(
				'qa-audio-text-stale',
				static function () use ( $aws_polly_qa_text_stale_id ): void {
					wp_update_post(
						array(
							'ID'           => $aws_polly_qa_text_stale_id,
							'post_content' => 'Text changed while synthesis was in flight.',
						)
					);
				}
			),
			$aws_polly_qa_response( 'qa-audio-protected-consented' ),
		)
	);
	$aws_polly_qa_client = new PollyClient(
		array(
			'credentials' => array(
				'key'    => 'qa-mock-access-key',
				'secret' => 'qa-mock-secret-key',
			),
			'endpoint'    => 'https://polly.mock.invalid',
			'handler'     => $aws_polly_qa_mock,
			'region'      => 'us-east-1',
			'version'     => 'latest',
		)
	);

	$aws_polly_qa_common = new Common();
	$aws_polly_qa_common->init();
	$aws_polly_qa_client_property = new ReflectionProperty( Common::class, 'polly_client' );
	$aws_polly_qa_client_property->setAccessible( true );
	$aws_polly_qa_client_property->setValue( $aws_polly_qa_common, $aws_polly_qa_client );
	$aws_polly_qa_service = new PollyService( $aws_polly_qa_common );

	aws_polly_qa_check( '100' === $aws_polly_qa_common->get_audio_speed(), 'An absent speed option must resolve to the default 100 percent.' );
	$aws_polly_qa_service->generate_audio( $aws_polly_qa_main_id );
	aws_polly_qa_check( 4 === count( $aws_polly_qa_mock ), 'Initial generation must consume exactly one mocked synthesis response.' );
	aws_polly_qa_check( 1 === count( $aws_polly_qa_commands ), 'Initial generation must issue exactly one synthesis command.' );
	aws_polly_qa_check( 'SynthesizeSpeech' === $aws_polly_qa_mock->getLastCommand()->getName(), 'The real SDK client must issue SynthesizeSpeech.' );
	aws_polly_qa_check( 'Matthew' === $aws_polly_qa_commands[0]['VoiceId'], 'Initial generation must use the configured voice.' );
	aws_polly_qa_check( 'standard' === $aws_polly_qa_commands[0]['Engine'], 'Initial generation must use the catalog-backed standard engine.' );
	aws_polly_qa_check( 0 === preg_match( '/<prosody rate="(?!100%")/', $aws_polly_qa_commands[0]['Text'] ), 'Default generation must not request a speech rate other than 100 percent.' );

	$aws_polly_qa_descriptor_v1 = get_post_meta( $aws_polly_qa_main_id, AudioStorage::POST_META_KEY, true );
	aws_polly_qa_check( is_array( $aws_polly_qa_descriptor_v1 ) && 'local' === ( $aws_polly_qa_descriptor_v1['type'] ?? '' ), 'Initial generation must persist a local storage descriptor.' );
	$aws_polly_qa_path_v1 = (string) $aws_polly_qa_descriptor_v1['path'];
	$aws_polly_qa_known_paths[] = $aws_polly_qa_path_v1;
	aws_polly_qa_check( is_file( $aws_polly_qa_path_v1 ), 'Initial generation must create the final MP3 through WordPress filesystem storage.' );
	aws_polly_qa_check( 'qa-audio-matthew-v1' === file_get_contents( $aws_polly_qa_path_v1 ), 'The generated MP3 must contain the mocked stream bytes.' );
	$aws_polly_qa_hash_v1      = (string) get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_hash', true );
	$aws_polly_qa_file_hash_v1 = hash_file( 'sha256', $aws_polly_qa_path_v1 );
	$aws_polly_qa_link_v1      = (string) get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_link_location', true );
	aws_polly_qa_check( '' !== $aws_polly_qa_hash_v1 && str_contains( $aws_polly_qa_link_v1, 'itron_polly_tts_' . $aws_polly_qa_main_id . '.mp3' ), 'Initial generation must publish a link and semantic hash.' );

	$aws_polly_qa_attachment_v1 = aws_polly_qa_attachment( $aws_polly_qa_descriptor_v1 );
	aws_polly_qa_check( $aws_polly_qa_attachment_v1 instanceof WP_Post, 'Media Library mode must create a real WordPress attachment.' );
	$aws_polly_qa_attachment_v1_id = (int) $aws_polly_qa_attachment_v1->ID;
	$aws_polly_qa_provenance_v1 = get_post_meta( $aws_polly_qa_attachment_v1_id, AudioStorage::ATTACHMENT_PROVENANCE_META_KEY, true );
	aws_polly_qa_check( $aws_polly_qa_main_id === (int) $aws_polly_qa_attachment_v1->post_parent, 'The generated attachment must belong to its synthetic post.' );
	aws_polly_qa_check( 'audio/mpeg' === get_post_mime_type( $aws_polly_qa_attachment_v1_id ), 'The generated attachment must use the MPEG audio MIME type.' );
	aws_polly_qa_check( $aws_polly_qa_path_v1 === get_attached_file( $aws_polly_qa_attachment_v1_id, true ), 'The attachment must point to the generated file.' );
	aws_polly_qa_check( is_array( $aws_polly_qa_provenance_v1 ) && $aws_polly_qa_descriptor_v1['asset_id'] === ( $aws_polly_qa_provenance_v1['asset_id'] ?? '' ), 'Attachment provenance must match the immutable storage descriptor.' );

	$aws_polly_qa_service->generate_audio( $aws_polly_qa_main_id );
	$aws_polly_qa_descriptor_same = get_post_meta( $aws_polly_qa_main_id, AudioStorage::POST_META_KEY, true );
	aws_polly_qa_check( 4 === count( $aws_polly_qa_mock ) && 1 === count( $aws_polly_qa_commands ), 'Unchanged generation must not call synthesis.' );
	aws_polly_qa_check( $aws_polly_qa_hash_v1 === get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_hash', true ), 'Unchanged generation must retain the semantic hash.' );
	aws_polly_qa_check( $aws_polly_qa_path_v1 === ( $aws_polly_qa_descriptor_same['path'] ?? '' ) && $aws_polly_qa_file_hash_v1 === hash_file( 'sha256', $aws_polly_qa_path_v1 ), 'Unchanged generation must retain the same file and bytes.' );

	update_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_voice_id', 'Joanna' );
	$aws_polly_qa_service->generate_audio( $aws_polly_qa_main_id );
	$aws_polly_qa_descriptor_v2 = get_post_meta( $aws_polly_qa_main_id, AudioStorage::POST_META_KEY, true );
	$aws_polly_qa_path_v2       = (string) $aws_polly_qa_descriptor_v2['path'];
	$aws_polly_qa_known_paths[] = $aws_polly_qa_path_v2;
	$aws_polly_qa_attachment_v2 = aws_polly_qa_attachment( $aws_polly_qa_descriptor_v2 );
	aws_polly_qa_check( 3 === count( $aws_polly_qa_mock ) && 2 === count( $aws_polly_qa_commands ), 'A voice change must synthesize exactly one replacement.' );
	aws_polly_qa_check( 'Joanna' === $aws_polly_qa_commands[1]['VoiceId'], 'The replacement must use the changed voice.' );
	aws_polly_qa_check( $aws_polly_qa_path_v1 === $aws_polly_qa_path_v2 && 'qa-audio-joanna-v2' === file_get_contents( $aws_polly_qa_path_v2 ), 'Voice regeneration must safely replace the same owned file.' );
	aws_polly_qa_check( $aws_polly_qa_hash_v1 !== get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_hash', true ), 'Voice regeneration must publish a new semantic hash.' );
	aws_polly_qa_check( null === get_post( $aws_polly_qa_attachment_v1_id ), 'Voice regeneration must delete the superseded attachment.' );
	aws_polly_qa_check( $aws_polly_qa_attachment_v2 instanceof WP_Post && $aws_polly_qa_attachment_v1_id !== (int) $aws_polly_qa_attachment_v2->ID, 'Voice regeneration must create a new owned attachment.' );

	$aws_polly_qa_hash_v2      = (string) get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_hash', true );
	$aws_polly_qa_file_hash_v2 = hash_file( 'sha256', $aws_polly_qa_path_v2 );
	$aws_polly_qa_attachment_v2_id = (int) $aws_polly_qa_attachment_v2->ID;
	update_option( 'itron_polly_tts_polly_enable', '' );
	wp_update_post(
		array(
			'ID'             => $aws_polly_qa_main_id,
			'post_date'      => '2026-01-02 03:04:05',
			'post_date_gmt'  => '2026-01-01 23:04:05',
		)
	);
	update_post_meta( $aws_polly_qa_main_id, 'aws_polly_qa_unrelated_marker', $aws_polly_qa_marker );
	$aws_polly_qa_service->save_post( $aws_polly_qa_main_id, get_post( $aws_polly_qa_main_id ), true );
	aws_polly_qa_check( 3 === count( $aws_polly_qa_mock ), 'A date-only and unrelated-meta save while globally OFF must not synthesize.' );
	aws_polly_qa_check( $aws_polly_qa_hash_v2 === get_post_meta( $aws_polly_qa_main_id, 'itron_polly_tts_audio_hash', true ), 'A date-only and unrelated-meta save while OFF must retain the audio hash.' );
	aws_polly_qa_check( is_file( $aws_polly_qa_path_v2 ) && $aws_polly_qa_file_hash_v2 === hash_file( 'sha256', $aws_polly_qa_path_v2 ), 'A date-only and unrelated-meta save while OFF must retain the audio file.' );

	wp_update_post(
		array(
			'ID'           => $aws_polly_qa_main_id,
			'post_content' => 'Lifecycle speech changed while globally disabled.',
		)
	);
	$aws_polly_qa_service->save_post( $aws_polly_qa_main_id, get_post( $aws_polly_qa_main_id ), true );
	aws_polly_qa_check( 3 === count( $aws_polly_qa_mock ), 'A voiced-content save while globally OFF must not synthesize.' );
	aws_polly_qa_check( ! $aws_polly_qa_common->has_post_audio( $aws_polly_qa_main_id ) && ! is_file( $aws_polly_qa_path_v2 ), 'A voiced-content save while OFF must delete stale audio.' );
	aws_polly_qa_check( null === get_post( $aws_polly_qa_attachment_v2_id ), 'Stale OFF-mode cleanup must delete the owned attachment.' );
	update_option( 'itron_polly_tts_polly_enable', 'on' );

	$aws_polly_qa_service->generate_audio( $aws_polly_qa_global_stale_id );
	update_option( 'itron_polly_tts_polly_enable', 'on' );
	aws_polly_qa_check( 2 === count( $aws_polly_qa_mock ) && 3 === count( $aws_polly_qa_commands ), 'The global-OFF race must execute only its mocked synthesis call.' );
	aws_polly_qa_check( ! $aws_polly_qa_common->has_post_audio( $aws_polly_qa_global_stale_id ), 'Global OFF inside MockHandler must cancel publication.' );
	aws_polly_qa_check( array() === aws_polly_qa_temp_files( $aws_polly_qa_global_stale_id ), 'Global-OFF cancellation must remove all temporary audio parts.' );
	aws_polly_qa_check( ! is_file( trailingslashit( wp_upload_dir()['basedir'] ) . 'itron_polly_tts_' . $aws_polly_qa_global_stale_id . '.mp3' ), 'Global-OFF cancellation must not create a final file.' );

	$aws_polly_qa_service->generate_audio( $aws_polly_qa_text_stale_id );
	aws_polly_qa_check( 1 === count( $aws_polly_qa_mock ) && 4 === count( $aws_polly_qa_commands ), 'The text race must execute only its mocked synthesis call.' );
	aws_polly_qa_check( ! $aws_polly_qa_common->has_post_audio( $aws_polly_qa_text_stale_id ), 'A text change inside MockHandler must cancel publication.' );
	aws_polly_qa_check( array() === aws_polly_qa_temp_files( $aws_polly_qa_text_stale_id ), 'Text-race cancellation must remove all temporary audio parts.' );
	aws_polly_qa_check( ! is_file( trailingslashit( wp_upload_dir()['basedir'] ) . 'itron_polly_tts_' . $aws_polly_qa_text_stale_id . '.mp3' ), 'Text-race cancellation must not create a final file.' );

	$aws_polly_qa_consent = new AudioConsent( $aws_polly_qa_common );
	aws_polly_qa_check( ! $aws_polly_qa_consent->is_allowed( $aws_polly_qa_protected_id ), 'A protected post without consent must be refused.' );
	$aws_polly_qa_service->generate_audio( $aws_polly_qa_protected_id );
	aws_polly_qa_check( 1 === count( $aws_polly_qa_mock ), 'Missing protected-post consent must not synthesize.' );
	$aws_polly_qa_consent_nonce = wp_create_nonce( 'itron_polly_tts_audio_consent_' . $aws_polly_qa_protected_id );
	aws_polly_qa_check( $aws_polly_qa_consent->record_classic_choice( $aws_polly_qa_protected_id, false, $aws_polly_qa_consent_nonce ), 'A valid explicit refusal must be recorded without cancelling the post operation.' );
	$aws_polly_qa_service->generate_audio( $aws_polly_qa_protected_id );
	aws_polly_qa_check( 1 === count( $aws_polly_qa_mock ) && ! $aws_polly_qa_consent->is_allowed( $aws_polly_qa_protected_id ), 'Explicit protected-post refusal must not synthesize.' );
	aws_polly_qa_check( $aws_polly_qa_consent->record_classic_choice( $aws_polly_qa_protected_id, true, $aws_polly_qa_consent_nonce ), 'A valid explicit protected-post grant must be accepted.' );
	$aws_polly_qa_service->generate_audio( $aws_polly_qa_protected_id );
	$aws_polly_qa_protected_descriptor = get_post_meta( $aws_polly_qa_protected_id, AudioStorage::POST_META_KEY, true );
	aws_polly_qa_check( 0 === count( $aws_polly_qa_mock ) && 5 === count( $aws_polly_qa_commands ), 'Valid protected-post consent must permit one mocked synthesis.' );
	aws_polly_qa_check( $aws_polly_qa_common->has_post_audio( $aws_polly_qa_protected_id ) && is_array( $aws_polly_qa_protected_descriptor ), 'Consented protected audio must be published with storage provenance.' );
	$aws_polly_qa_known_paths[] = (string) $aws_polly_qa_protected_descriptor['path'];
	aws_polly_qa_check( aws_polly_qa_attachment( $aws_polly_qa_protected_descriptor ) instanceof WP_Post, 'Consented protected audio must complete the real attachment lifecycle.' );
	aws_polly_qa_check( array() === aws_polly_qa_temp_files( $aws_polly_qa_protected_id ), 'Successful generation must also remove temporary audio parts.' );
} catch ( Throwable $aws_polly_qa_caught ) {
	$aws_polly_qa_failure = $aws_polly_qa_caught;
} finally {
	$_POST = array();

	if ( $aws_polly_qa_common instanceof Common ) {
		foreach ( $aws_polly_qa_post_ids as $aws_polly_qa_post_id ) {
			if ( get_post( $aws_polly_qa_post_id ) && ( $aws_polly_qa_common->has_post_audio( $aws_polly_qa_post_id ) || get_post_meta( $aws_polly_qa_post_id, AudioStorage::POST_META_KEY, true ) ) ) {
				$aws_polly_qa_common->delete_post_audio( $aws_polly_qa_post_id );
			}
		}
	}

	foreach ( $aws_polly_qa_post_ids as $aws_polly_qa_post_id ) {
		$aws_polly_qa_children = get_children(
			array(
				'post_parent' => $aws_polly_qa_post_id,
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);
		foreach ( $aws_polly_qa_children as $aws_polly_qa_attachment_id ) {
			wp_delete_attachment( (int) $aws_polly_qa_attachment_id, true );
		}
		wp_delete_post( $aws_polly_qa_post_id, true );

		foreach ( aws_polly_qa_temp_files( $aws_polly_qa_post_id ) as $aws_polly_qa_temp_path ) {
			wp_delete_file( $aws_polly_qa_temp_path );
		}
		$aws_polly_qa_known_paths[] = trailingslashit( wp_upload_dir()['basedir'] ) . 'itron_polly_tts_' . $aws_polly_qa_post_id . '.mp3';
	}

	foreach ( array_unique( $aws_polly_qa_known_paths ) as $aws_polly_qa_known_path ) {
		if ( is_string( $aws_polly_qa_known_path ) && is_file( $aws_polly_qa_known_path ) ) {
			wp_delete_file( $aws_polly_qa_known_path );
		}
	}

	$aws_polly_qa_failure_options = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'itron_polly_tts_cleanup_failure_%'"
	);
	foreach ( $aws_polly_qa_failure_options as $aws_polly_qa_failure_option ) {
		$aws_polly_qa_record = get_option( $aws_polly_qa_failure_option );
		if ( is_array( $aws_polly_qa_record ) && in_array( (int) ( $aws_polly_qa_record['post_id'] ?? 0 ), $aws_polly_qa_post_ids, true ) ) {
			delete_option( $aws_polly_qa_failure_option );
		}
	}

	delete_transient( $aws_polly_qa_voice_transient );
	if ( false !== $aws_polly_qa_voice_snapshot ) {
		$aws_polly_qa_remaining_ttl = $aws_polly_qa_voice_timeout > 0 ? max( 1, $aws_polly_qa_voice_timeout - time() ) : 0;
		set_transient( $aws_polly_qa_voice_transient, $aws_polly_qa_voice_snapshot, $aws_polly_qa_remaining_ttl );
	}

	foreach ( $aws_polly_qa_option_snapshot as $aws_polly_qa_option_name => $aws_polly_qa_snapshot ) {
		if ( $aws_polly_qa_snapshot['exists'] ) {
			update_option( $aws_polly_qa_option_name, $aws_polly_qa_snapshot['value'] );
		} else {
			delete_option( $aws_polly_qa_option_name );
		}
	}

	wp_set_current_user( $aws_polly_qa_previous_user );
	$_POST = $aws_polly_qa_previous_post;
}

if ( $aws_polly_qa_failure instanceof Throwable ) {
	fwrite( STDERR, 'FAIL: ' . $aws_polly_qa_failure->getMessage() . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "WordPress integration: {$aws_polly_qa_checks} checks passed.\n" );
