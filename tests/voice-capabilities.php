<?php

// Region catalogs are fixtures; no WordPress bootstrap or AWS requests.
define( 'ABSPATH', __DIR__ . '/../wordpress/' );
require __DIR__ . '/../plugin-dir/src/Logger.php';
require __DIR__ . '/../plugin-dir/src/Common.php';
require __DIR__ . '/../plugin-dir/src/GeneralConfiguration.php';
require __DIR__ . '/../plugin-dir/src/CredentialsException.php';
require __DIR__ . '/../plugin-dir/src/S3BucketNotAccessibleException.php';

$options = array();
$checks = 0;
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default; }
function apply_filters( $tag, $value, ...$args ) {
	return $value; }
function update_option( $key, $value ) {
	$GLOBALS['options'][ $key ] = $value; }
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}

$common = new \iTRON\PollyTTS\Common();
check( ! $common->is_polly_enabled(), 'TTS requires explicit opt-in.' );
check( array( 'Voices' => array() ) === $common->get_polly_voices( true ), 'OFF bypasses network even for forced catalog refresh.' );
check( ! $common->validate_itron_polly_tts_access( false, false ), 'OFF cannot validate AWS access.' );
$options['itron_polly_tts_polly_enable'] = 'on';
check( array( 'Voices' => array() ) === $common->get_polly_voices(), 'Missing credentials must not invoke AWS or its implicit credential chain.' );
check( ! $common->validate_itron_polly_tts_access( false, false ), 'Unconfigured access validation fails without network.' );
try {
	$common->get_s3_client_for_region( 'us-east-1' );
	throw new LogicException( 'Unconfigured cleanup invoked the AWS credential chain.' );
} catch ( \iTRON\PollyTTS\CredentialsException $e ) {
	++$checks;
}
$options['itron_polly_tts_s3'] = '';
check( $common->is_ssml_enabled(), 'SSML is available with local storage.' );
$options['itron_polly_tts_ssml'] = '';
check( ! $common->is_ssml_enabled(), 'SSML still honors its own switch.' );

class CatalogCommon extends \iTRON\PollyTTS\Common {
	public int $catalog_reads = 0;
	public function get_polly_voices( $force_refresh = false ) {
		++$this->catalog_reads;
		return array(
			'Voices' => array(
				array(
					'Id' => 'Arlet',
					'LanguageCode' => 'ca-ES',
					'SupportedEngines' => array( 'neural' ),
				),
				array(
					'Id' => 'Gabrielle',
					'LanguageCode' => 'fr-CA',
					'SupportedEngines' => array( 'neural' ),
				),
				array(
					'Id' => 'Lea',
					'LanguageCode' => 'fr-FR',
					'SupportedEngines' => array( 'standard', 'neural' ),
				),
				array(
					'Id' => 'Aditi',
					'LanguageCode' => 'en-IN',
					'AdditionalLanguageCodes' => array( 'hi-IN' ),
					'SupportedEngines' => array( 'standard' ),
				),
				array(
					'Id' => 'Jitka',
					'LanguageCode' => 'cs-CZ',
					'SupportedEngines' => array( 'neural' ),
				),
				array(
					'Id' => 'Future',
					'LanguageCode' => 'cs-CZ',
					'SupportedEngines' => array( 'generative' ),
				),
			),
		);
	}
}
$common = new CatalogCommon();
$options['itron_polly_tts_neural'] = 'on';
foreach ( array(
	'fr-CA' => 'Gabrielle',
	'hi' => 'Aditi',
	'cs' => 'Jitka',
	'ca' => 'Arlet',
	'ca-ES' => 'Arlet',
) as $language => $voice ) {
	$options['itron_polly_tts_source_language'] = $language;
	check( $common->is_polly_enabled(), 'Language does not impose a stale local enable gate.' );
	check( $voice === $common->resolve_polly_voice_id( $language ), 'Catalog resolves ' . $language . '.' );
}
check( array( 'Gabrielle' ) === array_column( $common->get_available_polly_voices( 'fr-CA' ), 'Id' ), 'A selected locale does not fall back to a different French locale.' );
check( in_array( 'ca', $common->get_all_languages(), true ) && 'Catalan' === $common->get_language_name( 'ca' ), 'Catalan is selectable as a source language.' );
$options['itron_polly_tts_speaking_style'] = 'conversational';
check( '' === $common->get_requested_polly_speaking_style(), 'Obsolete Conversational input normalizes to the default style.' );
check( '' === $common->get_active_polly_speaking_style( 'Joanna' ), 'The old style cannot activate obsolete SSML.' );
check( 'news' === $common->normalize_polly_speaking_style( 'news' ), 'Documented Newscaster style remains configurable.' );
$options['itron_polly_tts_speaking_style'] = '';
foreach ( array( 'eu-west-3', 'ap-south-1' ) as $region ) {
	$options['itron_polly_tts_s3_region'] = $region;
	check( $common->is_neural_supported_in_region(), 'Region support comes from the current catalog.' );
	check( 'neural' === $common->get_polly_engine( 'Jitka' ), 'Neural synthesis honors the catalog in ' . $region . '.' );
}
$options['itron_polly_tts_neural'] = '';
check( '' === $common->resolve_polly_voice_id( 'cs' ), 'Neural-only voices are rejected when Neural is off.' );
check( 'standard' === $common->get_polly_engine( 'Aditi' ), 'A supported Standard voice remains usable.' );
check( ! $common->is_standard_supported_for_voice( 'Unknown' ), 'Unknown voices cannot use stale Standard fallback.' );
check( ! $common->is_neural_supported_for_voice( 'Unknown' ), 'Unknown voices cannot use stale Neural fallback.' );
try {
	$common->get_polly_engine( 'Jitka' );
	throw new LogicException( 'Unsupported engine was accepted.' );
} catch ( RuntimeException $e ) {
	++$checks;
}
$options['itron_polly_tts_s3_access_key'] = 'fixture';
$options['itron_polly_tts_s3_secret_key'] = 'fixture';
check( $common->validate_itron_polly_tts_access( false, false ), 'Local storage still validates Polly access.' );
check( $common->catalog_reads > 0, 'Local validation consulted Polly catalog.' );
$options['itron_polly_tts_s3'] = 'on';
$property = new ReflectionProperty( \iTRON\PollyTTS\Common::class, 's3_handler' );
$property->setAccessible( true );
$property->setValue(
	$common,
	new class {
		public function is_bucket_accessible(): bool {
			return false; }
	}
);
check( ! $common->validate_itron_polly_tts_access( false, false ), 'A missing S3 bucket cannot silently pass validation.' );
check( $common->is_polly_enabled(), 'A temporary service error does not change the administrator opt-in.' );
echo "Voice capabilities: {$checks} checks passed.\n";
