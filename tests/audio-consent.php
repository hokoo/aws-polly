<?php

declare(strict_types=1);

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );

	$GLOBALS['test_actions']         = array();
	$GLOBALS['test_delete_failure']  = false;
	$GLOBALS['test_editable_posts']  = array();
	$GLOBALS['test_meta']            = array();
	$GLOBALS['test_posts']           = array();
	$GLOBALS['test_rest_fields']     = array();
	$GLOBALS['test_supported_types'] = array( 'post', 'book', 'secret' );

	class WP_Post {
		public int $ID;
		public string $post_type;
		public string $post_password;
		public string $speech;

		public function __construct( int $id, string $post_type, string $password, string $speech ) {
			$this->ID            = $id;
			$this->post_type     = $post_type;
			$this->post_password = $password;
			$this->speech        = $speech;
		}
	}

	class TestRestRequest {
		private string $nonce;

		public function __construct( string $nonce ) {
			$this->nonce = $nonce;
		}

		public function get_header( string $name ): string {
			return 'X-WP-Nonce' === $name ? $this->nonce : '';
		}
	}

	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['test_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
		return true;
	}

	function get_post( int $post_id ): ?WP_Post {
		return $GLOBALS['test_posts'][ $post_id ] ?? null;
	}

	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		unset( $single );
		return $GLOBALS['test_meta'][ $post_id ][ $key ] ?? '';
	}

	function update_post_meta( int $post_id, string $key, $value ): bool {
		$GLOBALS['test_meta'][ $post_id ][ $key ] = $value;
		return true;
	}

	function delete_post_meta( int $post_id, string $key ): bool {
		if ( $GLOBALS['test_delete_failure'] ) {
			return false;
		}

		unset( $GLOBALS['test_meta'][ $post_id ][ $key ] );
		return true;
	}

	function current_user_can( string $capability, int $post_id ): bool {
		return 'edit_post' === $capability && ! empty( $GLOBALS['test_editable_posts'][ $post_id ] );
	}

	function wp_create_nonce( string $action ): string {
		return 'nonce:' . $action;
	}

	function wp_verify_nonce( string $nonce, string $action ): bool {
		return 'nonce:' . $action === $nonce;
	}

	function wp_salt( string $scheme ): string {
		return 'test-salt:' . $scheme;
	}

	function wp_json_encode( $value ) {
		return json_encode( $value );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function sanitize_text_field( string $value ): string {
		return $value;
	}

	function esc_attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	function __( string $value, string $domain ): string {
		unset( $domain );
		return $value;
	}

	function get_post_type_object( string $post_type ): ?object {
		$types = array(
			'post'   => (object) array(
				'name'           => 'post',
				'show_in_rest'   => true,
				'rest_namespace' => 'wp/v2',
				'rest_base'      => 'posts',
			),
			'book'   => (object) array(
				'name'           => 'book',
				'show_in_rest'   => true,
				'rest_namespace' => 'library/v1',
				'rest_base'      => 'volumes',
			),
			'secret' => (object) array(
				'name'         => 'secret',
				'show_in_rest' => false,
			),
		);

		return $types[ $post_type ] ?? null;
	}

	function rest_get_route_for_post_type_items( string $post_type ): string {
		$object = get_post_type_object( $post_type );
		if ( ! $object || empty( $object->show_in_rest ) ) {
			return '';
		}

		return '/' . $object->rest_namespace . '/' . $object->rest_base;
	}

	function register_rest_field( string $post_type, string $field, array $args ): void {
		$GLOBALS['test_rest_fields'][ $post_type ][ $field ] = $args;
	}
}

namespace iTRON\PollyTTS {
	class Common {
		public function get_posttypes_array(): array {
			return $GLOBALS['test_supported_types'];
		}

		public function clean_text( int $post_id, bool $with_title, bool $only_title ): string {
			unset( $with_title, $only_title );
			return $GLOBALS['test_posts'][ $post_id ]->speech;
		}

		public function is_polly_enabled(): bool {
			return true;
		}
	}

	require_once dirname( __DIR__ ) . '/plugin-dir/src/AudioConsent.php';
}

namespace {
	use iTRON\PollyTTS\AudioConsent;
	use iTRON\PollyTTS\Common;

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

	function reset_consent( int $post_id ): void {
		unset( $GLOBALS['test_meta'][ $post_id ][ AudioConsent::META_KEY ] );
	}

	$GLOBALS['test_posts'] = array(
		1 => new WP_Post( 1, 'post', '', 'Public speech' ),
		2 => new WP_Post( 2, 'post', 'first-password', 'Protected speech' ),
		3 => new WP_Post( 3, 'post', 'password', 'Unauthorized speech' ),
		4 => new WP_Post( 4, 'page', 'password', 'Unsupported speech' ),
		5 => new WP_Post( 5, 'book', 'book-password', 'Book speech' ),
	);
	$GLOBALS['test_editable_posts'] = array(
		1 => true,
		2 => true,
		3 => false,
		4 => true,
		5 => true,
	);

	$common  = new Common();
	$consent = new AudioConsent( $common );
	$consent->register();

	assert_same( 8, $GLOBALS['test_actions']['wp_after_insert_post'][0][1], 'Classic consent must run before the parent generation pipeline.' );
	assert_same( 3, $GLOBALS['test_actions']['wp_after_insert_post'][0][2], 'Classic consent must receive the complete post save context.' );
	assert_true( isset( $GLOBALS['test_actions'][ AudioConsent::META_BOX_HOOK ] ), 'Consent fields must attach through the PostMetaBox hook.' );

	$consent->register_rest_fields();
	assert_same( array( 'post', 'book' ), array_keys( $GLOBALS['test_rest_fields'] ), 'Only supported REST-enabled post types receive the consent field.' );
	assert_same( 'boolean', $GLOBALS['test_rest_fields']['post'][ AudioConsent::REST_FIELD ]['schema']['type'], 'The REST choice must be boolean.' );
	assert_same( array( '/wp/v2/posts', '/library/v1/volumes' ), $consent->get_supported_rest_routes(), 'Custom REST namespaces and bases must be preserved.' );

	assert_false( $consent->needs_confirmation( 1 ), 'A public post does not need confirmation.' );
	assert_true( $consent->is_allowed( 1 ), 'A public post is allowed without consent metadata.' );
	assert_true( $consent->needs_confirmation( 2 ), 'A non-empty password needs confirmation.' );
	assert_false( $consent->is_allowed( 2 ), 'A protected post without consent is denied.' );

	$_POST = array();
	$consent->capture_classic_choice( 2, $GLOBALS['test_posts'][2], true );
	assert_false( $consent->is_allowed( 2 ), 'Missing classic input must not grant consent.' );
	assert_false( $consent->record_classic_choice( 2, '1', '' ), 'A missing classic nonce must be rejected.' );
	assert_false( $consent->record_classic_choice( 2, '1', 'invalid' ), 'An invalid classic nonce must be rejected.' );
	assert_false( $consent->record_classic_choice( 3, '1', 'nonce:itron_polly_tts_audio_consent_3' ), 'A user without edit_post cannot grant consent.' );
	assert_false( $consent->record_classic_choice( 4, '1', 'nonce:itron_polly_tts_audio_consent_4' ), 'Unsupported post types cannot receive consent.' );
	assert_false( $consent->record_classic_choice( 2, 'true', 'nonce:itron_polly_tts_audio_consent_2' ), 'Non-boolean-like consent values must be rejected.' );

	assert_true( $consent->record_classic_choice( 2, '1', 'nonce:itron_polly_tts_audio_consent_2' ), 'A valid explicit classic grant must be recorded.' );
	$fingerprint = $GLOBALS['test_meta'][2][ AudioConsent::META_KEY ];
	assert_same( 64, strlen( $fingerprint ), 'Only a SHA-256 HMAC fingerprint is persisted.' );
	assert_false( str_contains( $fingerprint, 'first-password' ), 'The raw password must not be persisted.' );
	assert_false( str_contains( $fingerprint, 'Protected speech' ), 'The prepared speech text must not be persisted.' );
	assert_true( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'A matching grant must work across helper instances.' );

	$GLOBALS['test_posts'][2]->speech = 'Changed speech';
	assert_false( $consent->is_allowed( 2 ), 'Changed prepared speech must invalidate consent.' );
	$GLOBALS['test_posts'][2]->speech = 'Protected speech';
	assert_true( $consent->is_allowed( 2 ), 'Restoring the granted speech restores the matching context.' );
	$GLOBALS['test_posts'][2]->post_password = 'changed-password';
	assert_false( $consent->is_allowed( 2 ), 'A changed password must invalidate consent.' );
	$GLOBALS['test_posts'][2]->post_password = 'first-password';

	$_POST = array(
		AudioConsent::CLASSIC_NONCE_NAME => 'nonce:itron_polly_tts_audio_consent_2',
		AudioConsent::CLASSIC_CHOICE     => '',
	);
	$consent->capture_classic_choice( 2, $GLOBALS['test_posts'][2], true );
	assert_same( $fingerprint, $GLOBALS['test_meta'][2][ AudioConsent::META_KEY ], 'An empty metabox-extra choice must not revoke a matching protected-post grant.' );

	$GLOBALS['test_delete_failure'] = true;
	assert_true( $consent->record_classic_choice( 2, '0', 'nonce:itron_polly_tts_audio_consent_2' ), 'An explicit refusal must be handled.' );
	$GLOBALS['test_delete_failure'] = false;
	assert_same( 'revoked', $GLOBALS['test_meta'][2][ AudioConsent::META_KEY ], 'A surviving grant must be overwritten with a persistent revocation marker.' );
	assert_false( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'A fresh helper instance must observe refusal.' );

	reset_consent( 2 );
	assert_false( $consent->record_bulk_choice( 2, '1', '' ), 'A missing bulk nonce must be rejected.' );
	assert_false( $consent->record_bulk_choice( 2, '1', 'invalid' ), 'An invalid bulk nonce must be rejected.' );
	assert_true( $consent->record_bulk_choice( 2, true, 'nonce:' . AudioConsent::BULK_NONCE_ACTION ), 'A valid boolean bulk grant must be recorded.' );
	assert_true( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'Bulk consent must persist for other pipeline instances.' );
	assert_true( $consent->record_bulk_choice( 2, 0, 'nonce:' . AudioConsent::BULK_NONCE_ACTION ), 'A numeric bulk refusal must revoke consent.' );
	assert_false( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'Bulk refusal must persist across helper instances.' );

	$request_valid   = new TestRestRequest( 'nonce:wp_rest' );
	$request_invalid = new TestRestRequest( 'invalid' );
	reset_consent( 2 );
	assert_true( $consent->record_rest_choice( true, $GLOBALS['test_posts'][2], AudioConsent::REST_FIELD, $request_invalid ), 'An invalid REST consent must not cancel the post save.' );
	assert_false( $consent->is_allowed( 2 ), 'An invalid REST nonce must not grant consent.' );
	assert_true( $consent->record_rest_choice( true, $GLOBALS['test_posts'][3], AudioConsent::REST_FIELD, $request_valid ), 'A missing capability must not cancel the post save.' );
	assert_false( $consent->is_allowed( 3 ), 'A missing REST capability must not grant consent.' );
	assert_true( $consent->record_rest_choice( true, $GLOBALS['test_posts'][2], AudioConsent::REST_FIELD, $request_valid ), 'A valid REST grant must let the ordinary save continue.' );
	assert_true( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'A valid REST grant must persist.' );
	assert_true( $consent->record_rest_choice( false, $GLOBALS['test_posts'][2], AudioConsent::REST_FIELD, $request_valid ), 'REST refusal must let the ordinary save continue.' );
	assert_false( ( new AudioConsent( $common ) )->is_allowed( 2 ), 'REST refusal must revoke consent for other instances.' );

	$GLOBALS['test_meta'][1][ AudioConsent::META_KEY ] = $fingerprint;
	$consent->capture_classic_choice( 1, $GLOBALS['test_posts'][1], true );
	assert_same( '', get_post_meta( 1, AudioConsent::META_KEY, true ), 'Returning to public visibility must clear old protected-context consent.' );
	$GLOBALS['test_posts'][1]->post_password = 'new-password';
	assert_false( ( new AudioConsent( $common ) )->is_allowed( 1 ), 'Adding a password to a formerly public post must not inherit consent.' );

	$_POST = array();
	$consent->record_classic_choice( 2, '1', 'nonce:itron_polly_tts_audio_consent_2' );
	$GLOBALS['test_posts'][2]->post_password = 'changed-password';
	$consent->capture_classic_choice( 2, $GLOBALS['test_posts'][2], true );
	$GLOBALS['test_posts'][2]->post_password = 'first-password';
	$consent->capture_classic_choice( 2, $GLOBALS['test_posts'][2], true );
	assert_false( $consent->is_allowed( 2 ), 'Restoring a previously changed and saved password must not restore revoked consent.' );

	$consent->record_classic_choice( 2, '1', 'nonce:itron_polly_tts_audio_consent_2' );
	$GLOBALS['test_editable_posts'][2] = false;
	$GLOBALS['test_posts'][2]->speech = 'Programmatically changed speech';
	$consent->capture_classic_choice( 2, $GLOBALS['test_posts'][2], true );
	$GLOBALS['test_posts'][2]->speech = 'Protected speech';
	assert_false( $consent->is_allowed( 2 ), 'A core programmatic save revokes a stale grant even without a logged-in editor.' );
	$GLOBALS['test_editable_posts'][2] = true;

	ob_start();
	$consent->render_classic_fields( $GLOBALS['test_posts'][2] );
	$fields = ob_get_clean();
	assert_true( str_contains( $fields, AudioConsent::CLASSIC_NONCE_NAME ), 'Classic fields include the dedicated nonce.' );
	assert_true( str_contains( $fields, AudioConsent::CLASSIC_CHOICE ), 'Classic fields include an initially empty explicit choice.' );
	assert_false( str_contains( $fields, 'first-password' ), 'Classic fields must not expose the password.' );
	assert_false( str_contains( $fields, 'Protected speech' ), 'Classic fields must not expose prepared speech.' );

	echo "audio-consent: PASS\n";
}
