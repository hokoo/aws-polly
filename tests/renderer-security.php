<?php

// Standalone availability and visibility checks; all HTTP is intercepted.
define( 'ABSPATH', __DIR__ . '/../wordpress/' );
require __DIR__ . '/../plugin-dir/src/Logger.php';
require __DIR__ . '/../plugin-dir/src/Common.php';
require __DIR__ . '/../plugin-dir/src/GeneralConfiguration.php';
require __DIR__ . '/../plugin-dir/src/AudioConsent.php';
require __DIR__ . '/../plugin-dir/src/AudioStorage.php';
require __DIR__ . '/../plugin-dir/src/ObjectCache.php';
require __DIR__ . '/../plugin-dir/src/PublicRenderer.php';

$options = array( 'itron_polly_tts_polly_enable' => 'on' );
$meta = array( 'itron_polly_tts_enable' => '1' );
$http = array();
$singular = true;
$password_required = false;
$can_read = true;
$post = (object) array(
	'ID' => 1,
	'post_status' => 'publish',
);
$checks = 0;
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default; }
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['meta'][ $key ] ?? ''; }
function apply_filters( $tag, $value, ...$args ) {
	return $value; }
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); }
function wp_generate_uuid4() {
	return '12345678-1234-4234-9234-123456789abc'; }
function is_singular() {
	return $GLOBALS['singular']; }
function post_password_required( $id ) {
	return $GLOBALS['password_required']; }
function get_post_status( $id ) {
	return $GLOBALS['post']->post_status; }
function current_user_can( ...$args ) {
	return $GLOBALS['can_read']; }
function wp_safe_remote_head( $url, $args ) {
	$GLOBALS['http'][] = array( $url, $args );
	return array( 'response' => array( 'code' => 200 ) ); }
function wp_remote_head( ...$args ) {
	throw new RuntimeException( 'Unsafe HTTP must not be called.' ); }
function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code']; }
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
class RendererCache extends \iTRON\PollyTTS\ObjectCache {
	public ?array $fixture = null;
	public function get_audio_head_status( int $post_id ): ?array {
		return $this->fixture; }
	public function is_audio_head_status_current( array $status, string $url ): bool {
		return $status['url'] === $url; }
	public function set_audio_head_status( int $post_id, string $url, bool $exists ): void {
		$this->fixture = array(
			'url' => $url,
			'exists' => $exists,
		); }
	public function delete_audio_head_status( int $post_id ): void {
		$this->fixture = null; }
}
$common = new \iTRON\PollyTTS\Common();
$cache = new RendererCache( $common );
$renderer = new \iTRON\PollyTTS\PublicRenderer( 'polly', '1.0.8', $common, $cache );
$availability = new ReflectionMethod( $renderer, 'has_available_audio' );
$availability->setAccessible( true );
$url = 'https://s3.us-east-1.amazonaws.com/fixture-bucket/itron_polly_tts_1.mp3';
$storage = new \iTRON\PollyTTS\AudioStorage();
$meta[ \iTRON\PollyTTS\AudioStorage::POST_META_KEY ] = $storage->create_s3_descriptor( 1, 'fixture-bucket', 'us-east-1', 'itron_polly_tts_1.mp3', $url );
$meta['itron_polly_tts_audio_link_location'] = $url;
check( $availability->invoke( $renderer, 1, $url ), 'Owned remote audio is checked through safe HTTP.' );
check( true === $http[0][1]['sslverify'] && 0 === $http[0][1]['redirection'], 'TLS verification is on and redirects are disabled.' );
check( $availability->invoke( $renderer, 1, $url ) && 1 === count( $http ), 'An owned cached URL does not repeat HTTP.' );
check( ! $availability->invoke( $renderer, 1, 'http://127.0.0.1/private' ), 'Forged audio-link metadata is not fetched.' );
check( 1 === count( $http ), 'Descriptor mismatch blocks networking before cache lookup.' );
$meta[ \iTRON\PollyTTS\AudioStorage::POST_META_KEY ]['delivery_url'] = str_replace( 'https:', 'http:', $url );
check( ! $availability->invoke( $renderer, 1, str_replace( 'https:', 'http:', $url ) ), 'Unencrypted remote destinations are not fetched.' );
$meta[ \iTRON\PollyTTS\AudioStorage::POST_META_KEY ]['delivery_url'] = $url;
$options['itron_polly_tts_polly_enable'] = '';
check( 'content' === $renderer->content_filter( 'content' ), 'OFF suppresses the player before HTTP.' );
$options['itron_polly_tts_polly_enable'] = 'on';
$singular = false;
check( 'content' === $renderer->content_filter( 'content' ), 'Non-player archive views do not fetch remote audio.' );
$marked_content = '-AMAZONPOLLY-ONLYAUDIO-START-hidden-AMAZONPOLLY-ONLYAUDIO-END--AMAZONPOLLY-ONLYWORDS-START-visible-AMAZONPOLLY-ONLYWORDS-END-';
check( 'visible' === $renderer->content_filter( $marked_content ), 'Archive text still strips audio-only text and internal markers.' );
$singular = true;
$options['itron_polly_tts_position'] = 'Do not show';
check( 'content' === $renderer->content_filter( 'content' ), 'Hidden player does not check remote audio.' );
check( 'visible' === $renderer->content_filter( $marked_content ), 'Hiding the player does not expose internal text markers.' );
$options['itron_polly_tts_position'] = 'Before post';
$password_required = true;
check( 'content' === $renderer->content_filter( 'content' ), 'A locked password post does not expose the player.' );
$password_required = false;
$post->post_status = 'private';
$can_read = false;
check( 'content' === $renderer->content_filter( 'content' ), 'An unreadable private post does not expose the player.' );
check( 1 === count( $http ), 'All visibility guards precede HTTP.' );
echo "Renderer security: {$checks} checks passed.\n";
