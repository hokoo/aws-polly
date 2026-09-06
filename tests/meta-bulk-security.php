<?php

declare(strict_types=1);

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
	define( 'WPINC', 'wp-includes' );

	$GLOBALS['test_filters']         = array();
	$GLOBALS['test_posts']           = array();
	$GLOBALS['test_editable_posts']  = array();
	$GLOBALS['test_post_meta']       = array();
	$GLOBALS['test_meta_reads']      = array();
	$GLOBALS['test_meta_writes']     = array();
	$GLOBALS['test_queued_posts']    = array();
	$GLOBALS['test_supported_types'] = array( 'post', 'book' );

	class WP_Post {
		public int $ID;
		public string $post_type;

		public function __construct( int $id, string $post_type ) {
			$this->ID        = $id;
			$this->post_type = $post_type;
		}
	}

	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['test_filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
		return true;
	}

	function apply_filters( string $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['test_filters'][ $hook ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['test_filters'][ $hook ] );
		foreach ( $GLOBALS['test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $registered_callback ) {
				list( $callback, $accepted_args ) = $registered_callback;
				$value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
			}
		}

		return $value;
	}


	require_once ABSPATH . WPINC . '/meta.php';

	// Full WordPress role mapping is intentionally not bootstrapped in this standalone test.
	function current_user_can( string $capability, ...$args ): bool {
		if ( 'edit_post' === $capability ) {
			return ! empty( $GLOBALS['test_editable_posts'][ (int) $args[0] ] );
		}

		if ( 'edit_post_meta' === $capability ) {
			$post_id  = (int) $args[0];
			$meta_key = (string) $args[1];
			return current_user_can( 'edit_post', $post_id ) && ! is_protected_meta( $meta_key, 'post' );
		}

		return false;
	}

	function get_post( int $post_id ): ?WP_Post {
		return $GLOBALS['test_posts'][ $post_id ] ?? null;
	}

	function get_post_meta( int $post_id, string $meta_key, bool $single = false ) {
		$GLOBALS['test_meta_reads'][] = array( $post_id, $meta_key );
		return $GLOBALS['test_post_meta'][ $post_id ][ $meta_key ] ?? '';
	}

	function update_post_meta( int $post_id, string $meta_key, $meta_value ): bool {
		$GLOBALS['test_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		$GLOBALS['test_meta_writes'][]                       = array( $post_id, $meta_key, $meta_value );
		return true;
	}

	function absint( $value ): int {
		return abs( (int) $value );
	}

	function wp_create_nonce( string $action ): string {
		return 'nonce:' . $action;
	}

	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}
}

namespace iTRON\PollyTTS {
	class Common {
		public function get_posttypes_array(): array {
			return $GLOBALS['test_supported_types'];
		}

		public function get_voice_id(): string {
			return 'Matthew';
		}

		public function is_logging_enabled(): bool {
			return false;
		}
	}

	class BackgroundTask {
		public function queue_audio( int $post_id ): void {
			$GLOBALS['test_queued_posts'][] = $post_id;
		}
	}

	require_once dirname( __DIR__ ) . '/plugin-dir/src/Plugin.php';
	require_once dirname( __DIR__ ) . '/plugin-dir/src/AudioAdmin.php';
}

namespace {
	use iTRON\PollyTTS\AudioAdmin;
	use iTRON\PollyTTS\Common;
	use iTRON\PollyTTS\Plugin;

	function assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				$message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true )
			);
		}
	}

	function assert_true( bool $actual, string $message ): void {
		assert_same( true, $actual, $message );
	}

	function assert_false( bool $actual, string $message ): void {
		assert_same( false, $actual, $message );
	}

	$common     = new Common();
	$reflection = new ReflectionClass( Plugin::class );
	$plugin     = $reflection->newInstanceWithoutConstructor();
	$property   = $reflection->getProperty( 'common' );
	$property->setAccessible( true );
	$property->setValue( $plugin, $common );
	$method = $reflection->getMethod( 'define_global_hooks' );
	$method->setAccessible( true );
	$method->invoke( $plugin );

	assert_true( is_protected_meta( 'itron_polly_tts_media_library_attachment_id', 'post' ), 'Internal post metadata must be protected.' );
	assert_true( is_protected_meta( 'itron_polly_tts_future_internal_key', 'post' ), 'Future internal post metadata must be protected by prefix.' );
	assert_true( is_protected_meta( 'ITRON_POLLY_TTS_AUDIO_LINK_LOCATION', 'post' ), 'Uppercase internal post metadata must be protected.' );
	assert_true( is_protected_meta( 'iTrOn_PoLlY_tTs_Media_Library_Attachment_ID', 'post' ), 'Mixed-case internal post metadata must be protected.' );
	assert_false( is_protected_meta( 'itron_polly_tts_audio_link_location', 'comment' ), 'The post metadata rule must not affect other object types.' );
	assert_false( is_protected_meta( 'public_reference', 'post' ), 'Unrelated post metadata must retain the core result.' );
	assert_true( is_protected_meta( '_core_private', 'post' ), 'Already protected metadata must remain protected.' );

	$GLOBALS['test_posts'][1]          = new WP_Post( 1, 'post' );
	$GLOBALS['test_posts'][2]          = new WP_Post( 2, 'post' );
	$GLOBALS['test_posts'][3]          = new WP_Post( 3, 'page' );
	$GLOBALS['test_posts'][4]          = new WP_Post( 4, 'book' );
	$GLOBALS['test_editable_posts'][1] = true;
	$GLOBALS['test_editable_posts'][2] = false;
	$GLOBALS['test_editable_posts'][3] = true;
	$GLOBALS['test_editable_posts'][4] = true;

	assert_false(
		current_user_can( 'edit_post_meta', 1, 'itron_polly_tts_media_library_attachment_id' ),
		'Custom Fields authorization must reject protected internal references.'
	);
	assert_false(
		current_user_can( 'edit_post_meta', 1, 'ItRoN_PoLlY_TtS_MeDiA_LiBrArY_AtTaChMeNt_Id' ),
		'Custom Fields authorization must reject mixed-case internal references.'
	);
	assert_true( current_user_can( 'edit_post_meta', 1, 'public_reference' ), 'Editable public metadata must remain available.' );

	update_post_meta( 1, 'itron_polly_tts_direct_update', 'works' );
	assert_same( 'works', get_post_meta( 1, 'itron_polly_tts_direct_update', true ), 'Direct plugin metadata writes must remain unaffected.' );

	$GLOBALS['test_post_meta'][1] = array(
		'itron_polly_tts_enable'   => '0',
		'itron_polly_tts_voice_id' => '',
	);
	$GLOBALS['test_post_meta'][2] = array(
		'itron_polly_tts_enable'   => '0',
		'itron_polly_tts_voice_id' => '',
	);
	$GLOBALS['test_post_meta'][3] = array(
		'itron_polly_tts_enable'   => '0',
		'itron_polly_tts_voice_id' => '',
	);
	$GLOBALS['test_post_meta'][4] = array(
		'itron_polly_tts_enable'   => '1',
		'itron_polly_tts_voice_id' => 'Amy',
	);
	$GLOBALS['test_meta_reads']   = array();
	$GLOBALS['test_meta_writes']  = array();
	$GLOBALS['test_queued_posts'] = array();

	$audio_admin = new AudioAdmin( $common );
	$redirect    = $audio_admin->handle_bulk_action(
		'/wp-admin/edit.php',
		'polly_generate_audio',
		array( 0, -1, 'bogus', '1.5', array( 1 ), 999, 3, 2, 1, '4' )
	);

	assert_same( array( 1, 4 ), $GLOBALS['test_queued_posts'], 'Only authorized posts of supported types may be queued.' );
	assert_same(
		array(
			array( 1, 'itron_polly_tts_enable', 1 ),
			array( 1, 'itron_polly_tts_voice_id', 'Matthew' ),
		),
		$GLOBALS['test_meta_writes'],
		'Invalid, unsupported, and unauthorized IDs must not receive metadata writes.'
	);
	assert_same(
		array(
			array( 1, 'itron_polly_tts_enable' ),
			array( 1, 'itron_polly_tts_voice_id' ),
			array( 4, 'itron_polly_tts_enable' ),
			array( 4, 'itron_polly_tts_voice_id' ),
		),
		$GLOBALS['test_meta_reads'],
		'Rejected IDs must be filtered before plugin metadata access.'
	);
	assert_true( false !== strpos( $redirect, 'polly_queued=2' ), 'The notice count must include only authorized queued posts.' );
	assert_true( false !== strpos( $redirect, 'itron_polly_tts_bulk_notice_nonce=' ), 'The existing notice nonce must be retained.' );

	$before = array( $GLOBALS['test_meta_reads'], $GLOBALS['test_meta_writes'], $GLOBALS['test_queued_posts'] );
	assert_same(
		'/wp-admin/edit.php',
		$audio_admin->handle_bulk_action( '/wp-admin/edit.php', 'unrelated_action', array( 1 ) ),
		'Unrelated bulk actions must remain unchanged.'
	);
	assert_same( $before, array( $GLOBALS['test_meta_reads'], $GLOBALS['test_meta_writes'], $GLOBALS['test_queued_posts'] ), 'Unrelated actions must have no side effects.' );

	echo "meta-bulk-security: PASS\n";
}
