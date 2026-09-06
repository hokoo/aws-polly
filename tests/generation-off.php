<?php

namespace iTRON\WP_Lock {
	class WP_Lock {
		const WRITE = 'write';
		public function __construct( $name ) {}
		public function acquire( ...$args ) { return true; }
		public function release() { ++$GLOBALS['released']; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/../wordpress/' );
	require __DIR__ . '/../plugin-dir/src/Logger.php';
	require __DIR__ . '/../plugin-dir/src/Common.php';
	require __DIR__ . '/../plugin-dir/src/GeneralConfiguration.php';
	require __DIR__ . '/../plugin-dir/src/PollyService.php';
	require __DIR__ . '/../plugin-dir/src/PostMetaBox.php';

	$options = array( 'itron_polly_tts_polly_enable' => '', 'itron_polly_tts_skip_tags' => 'script style' );
	$posts = array( 1 => (object) array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Title', 'post_content' => 'Speech', 'post_excerpt' => '' ) );
	$meta = array( 1 => array( 'itron_polly_tts_enable' => '1', 'itron_polly_tts_audio_link_location' => 'https://example.test/audio.mp3' ) );
	$deletions = array();
	$released = 0;
	$checks = 0;

	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
	function get_post_type( $id ) { return get_post( $id )->post_type ?? false; }
	function get_post_field( $key, $id ) { return get_post( $id )->$key ?? ''; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
	function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); }
	function wp_is_post_revision( $id ) { return false; }
	function wp_is_post_autosave( $id ) { return false; }
	function current_user_can( ...$args ) { return true; }
	function wp_verify_nonce( ...$args ) { return true; }
	function wp_create_nonce( ...$args ) { return 'fixture'; }
	function wp_unslash( $value ) { return $value; }
	function sanitize_text_field( $value ) { return $value; }
	function clean_post_cache( $id ) {}
	function apply_filters( $tag, $value, ...$args ) { return $value; }
	function do_action( ...$args ) {}
	function do_action_ref_array( ...$args ) {}
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function do_shortcode( $value ) { return $value; }
	function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function esc_html__( $value, $domain ) { return $value; }

	class OfflineCommon extends \iTRON\PollyTTS\Common {
		public function get_posttypes_array() { return array( 'post' ); }
		public function validate_itron_polly_tts_access( $persist_state = true, $show_notices = true ): bool { throw new RuntimeException( 'OFF must not validate AWS.' ); }
		public function get_polly_voices( $force_refresh = false ) { throw new RuntimeException( 'OFF must not discover voices.' ); }
		public function delete_post_audio( $id ) {
			$GLOBALS['deletions'][] = $id;
			delete_post_meta( $id, 'itron_polly_tts_audio_link_location' );
			delete_post_meta( $id, 'itron_polly_tts_audio_hash' );
		}
	}
	function check( $condition, $message ) {
		if ( ! $condition ) { throw new RuntimeException( $message ); }
		++$GLOBALS['checks'];
	}
	$common = new OfflineCommon();
	$service = new \iTRON\PollyTTS\PollyService( $common );
	$meta[1]['itron_polly_tts_audio_hash'] = $common->get_audio_hash( 1 );
	$box = new \iTRON\PollyTTS\PostMetaBox( $common );
	ob_start();
	$box->display_polly_gui( $posts[1] );
	$html = ob_get_clean();
	check( str_contains( $html, 'An unchanged save will keep it.' ), 'Editor warns about hash-based invalidation while OFF.' );
	check( array() === $deletions, 'Opening an editor does not delete audio.' );
	$service->save_post( 1, $posts[1], true );
	check( array() === $deletions, 'Unchanged saves keep current audio without AWS.' );
	$posts[1]->post_modified = '2099-01-01';
	$service->save_post( 1, $posts[1], true );
	check( array() === $deletions, 'A timestamp-only change keeps audio.' );
	$_POST = array( \iTRON\PollyTTS\PollyService::NONCE_NAME => 'fixture' );
	$service->save_post( 1, $posts[1], true );
	check( '1' === $meta[1]['itron_polly_tts_enable'], 'Hidden OFF controls do not disable per-post TTS.' );
	$posts[1]->post_content = 'Changed speech';
	$service->save_post( 1, $posts[1], true );
	check( array( 1 ) === $deletions, 'Changed speech deletes stale audio while OFF.' );
	check( ! $common->has_post_audio( 1 ), 'Removed stale audio cannot reappear on re-enable.' );
	$service->generate_audio( 1 );
	check( array( 1 ) === $deletions, 'A previously queued job cannot synthesize while OFF.' );
	$meta[1]['itron_polly_tts_audio_link_location'] = 'https://example.test/audio.mp3';
	$meta[1]['itron_polly_tts_audio_hash'] = $common->get_audio_hash( 1 );
	$options['itron_polly_tts_speed'] = 120;
	$service->save_post( 1, $posts[1], true );
	check( array( 1, 1 ) === $deletions, 'Changed synthesis settings apply the same predicate.' );
	$service->generate_audio( 999 );
	check( 7 === $released, 'All generation paths release the acquired lock.' );
	echo "Generation OFF: {$checks} checks passed.\n";
}
