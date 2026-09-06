<?php

// Standalone regression: no WordPress bootstrap, persistence or network.
define( 'ABSPATH', __DIR__ . '/../wordpress/' );
require __DIR__ . '/../plugin-dir/src/Logger.php';
require __DIR__ . '/../plugin-dir/src/Common.php';
require __DIR__ . '/../plugin-dir/src/GeneralConfiguration.php';

$options = array(
	'itron_polly_tts_s3'             => 'on',
	'itron_polly_tts_skip_tags'      => 'script style',
	'itron_polly_tts_sample_rate'    => '24000',
	'itron_polly_tts_s3_region'      => 'us-east-1',
);
$post = array(
	'post_title'    => 'Test title',
	'post_excerpt'  => 'Test excerpt',
	'post_content'  => '<p>Test speech</p>',
	'post_modified' => '2026-09-07 10:00:00',
);
$meta = array();

function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function get_post_field( $key, $id ) { return $GLOBALS['post'][ $key ] ?? ''; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function apply_filters( $tag, $value, ...$args ) {
	return 'the_title' === $tag ? $value . ( $GLOBALS['title_suffix'] ?? '' ) : $value;
}
function do_shortcode( $text ) { return str_replace( '[fixture]', 'Shortcode text', $text ); }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }

class FingerprintCommon extends \iTRON\PollyTTS\Common {
	public function get_polly_voices( $force_refresh = false ) {
		throw new RuntimeException( 'Fingerprint must not request AWS voices.' );
	}
}

$common = new FingerprintCommon();
$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['checks']++;
}

$initial = $common->get_audio_hash( 1 );
check( 64 === strlen( $initial ), 'Fingerprint uses SHA-256.' );
$title_suffix = ' Filtered title';
check( $initial !== $common->get_audio_hash( 1 ), 'Title filters remain part of prepared speech.' );
$title_suffix = '';
$post['post_modified'] = '2026-09-08 12:00:00';
check( $initial === $common->get_audio_hash( 1 ), 'An unchanged save must not invalidate audio.' );
$meta['unrelated_field'] = 'new metadata';
$options['itron_polly_tts_position'] = 'After post';
$options['itron_polly_tts_s3_bucket_name'] = 'a-different-bucket';
$options['itron_polly_tts_cloudfront'] = 'cdn.example.test';
check( $initial === $common->get_audio_hash( 1 ), 'Player and delivery settings are not speech inputs.' );
$post['post_content'] = '<div>Test speech</div>';
check( $initial === $common->get_audio_hash( 1 ), 'Equivalent prepared text should remain current.' );
$post['post_content'] = '<p>Different speech</p>';
check( $initial !== $common->get_audio_hash( 1 ), 'Changed speech must invalidate audio.' );
$post['post_content'] = '<p>Test speech</p>';
foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
	$before = $post[ $field ];
	$post[ $field ] .= ' changed';
	check( $initial !== $common->get_audio_hash( 1 ), 'Included ' . $field . ' must affect the hash.' );
	$post[ $field ] = $before;
}
$options['itron_polly_tts_add_post_title'] = '';
$options['itron_polly_tts_add_post_excerpt'] = '';
$without_heading = $common->get_audio_hash( 1 );
$post['post_title'] = 'Ignored title';
$post['post_excerpt'] = 'Ignored excerpt';
check( $without_heading === $common->get_audio_hash( 1 ), 'Excluded title/excerpt must not invalidate audio.' );

foreach ( array(
	'voice_id' => 'Joanna', 'sample_rate' => '16000', 'speed' => '110',
	'lexicons' => 'test-lexicon', 'neural' => 'on', 'auto_breaths' => '',
	'ssml' => '', 'source_language' => 'de', 's3_region' => 'eu-west-1',
) as $setting => $value ) {
	$key = 'itron_polly_tts_' . $setting;
	$before = $options;
	$options[ $key ] = $value;
	check( $without_heading !== $common->get_audio_hash( 1 ), $setting . ' must affect the hash.' );
	$options = $before;
}
$options['itron_polly_tts_neural'] = 'on';
$before = $common->get_audio_hash( 1 );
$options['itron_polly_tts_speaking_style'] = 'news';
check( $before !== $common->get_audio_hash( 1 ), 'Active speaking style must affect the hash.' );
$options['itron_polly_tts_neural'] = '';
$meta['itron_polly_tts_voice_id'] = 'Joanna';
$overridden = $common->get_audio_hash( 1 );
$options['itron_polly_tts_voice_id'] = 'Amy';
check( $overridden === $common->get_audio_hash( 1 ), 'An active post voice overrides the global voice.' );
$options['itron_polly_tts_disable_post_voice_override'] = 'on';
check( $overridden !== $common->get_audio_hash( 1 ), 'Locking the global voice changes effective input.' );
$meta['itron_polly_tts_voice_id'] = 'Matthew';
$locked = $common->get_audio_hash( 1 );
unset( $meta['itron_polly_tts_voice_id'] );
check( $locked === $common->get_audio_hash( 1 ), 'Removing an ignored override does not invalidate audio.' );
$meta['itron_polly_tts_audio_hash'] = $locked;
check( ! $common->is_post_audio_current( 1 ), 'A hash without audio is not current audio.' );
$meta['itron_polly_tts_audio_link_location'] = 'https://example.test/audio.mp3';
check( $common->is_post_audio_current( 1 ), 'Matching speech inputs and existing audio are current.' );
$post['post_content'] = 'New text';
check( ! $common->is_post_audio_current( 1 ), 'The shared predicate detects changed speech.' );
check( $common->is_post_audio_current( 1, $locked ), 'A prepared-input snapshot can be compared without rereading text.' );
$prepared = $common->clean_text( 1, true, false );
check( $common->get_audio_hash( 1 ) === $common->get_audio_hash( 1, $prepared ), 'Prepared text is reused consistently.' );

$resolved = $common->get_audio_hash( 1, $prepared, 'Matthew' );
$meta['itron_polly_tts_audio_voice'] = array( 'request' => $common->get_audio_voice_request( 1 ), 'resolved' => 'Matthew' );
check( $resolved === $common->get_audio_hash( 1 ), 'Offline comparison reuses the actual resolved voice.' );
check( $resolved !== $common->get_audio_hash( 1, $prepared, 'Joanna' ), 'A changed resolved voice invalidates audio even with locked global voice.' );
$options['itron_polly_tts_voice_id'] = 'Joanna';
check( $resolved !== $common->get_audio_hash( 1 ), 'A new requested voice does not reuse the old resolution.' );
$options['itron_polly_tts_voice_id'] = 'Amy';
$options['itron_polly_tts_source_language'] = 'de';
check( $resolved !== $common->get_audio_hash( 1 ), 'A language change invalidates the cached voice resolution.' );
unset( $options['itron_polly_tts_source_language'] );
$options['itron_polly_tts_voice_id'] = 'RequestB';
$meta['itron_polly_tts_audio_hash'] = $resolved;
check( $common->is_post_audio_current( 1, $common->get_audio_hash( 1, $prepared, 'Matthew' ) ), 'Different requests resolving to the same voice reuse existing audio.' );
$meta['itron_polly_tts_audio_voice'] = array( 'request' => $common->get_audio_voice_request( 1 ), 'resolved' => 'Matthew' );
check( $common->is_post_audio_current( 1 ), 'Refreshing an identical-audio resolution preserves subsequent offline comparison.' );

echo "Audio fingerprint: {$checks} checks passed.\n";
