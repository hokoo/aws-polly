<?php

namespace iTRON\PollyTTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records explicit approval for publicly accessible audio of password-protected posts.
 */
class AudioConsent {
	public const META_KEY           = 'itron_polly_tts_public_audio_consent';
	public const CLASSIC_NONCE_NAME = 'itron_polly_tts_audio_consent_nonce';
	public const CLASSIC_CHOICE     = 'itron_polly_tts_audio_consent_choice';
	public const BULK_NONCE_ACTION  = 'itron_polly_tts_bulk_audio_consent';
	public const BULK_NONCE_NAME    = 'itron_polly_tts_bulk_audio_consent_nonce';
	public const BULK_CHOICE        = 'itron_polly_tts_bulk_audio_consent_choice';
	public const REST_FIELD         = 'itron_polly_tts_public_audio_consent';
	public const META_BOX_HOOK      = 'itron_polly_tts_post_meta_box_consent';

	private const CLASSIC_NONCE_ACTION = 'itron_polly_tts_audio_consent_';
	private const FINGERPRINT_VERSION  = 1;

	private Common $common;

	public function __construct( Common $common ) {
		$this->common = $common;
	}

	/**
	 * Register consent-specific core hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
		add_action( 'wp_after_insert_post', array( $this, 'capture_classic_choice' ), 8, 3 );
		add_action( self::META_BOX_HOOK, array( $this, 'render_classic_fields' ) );
	}

	/**
	 * Whether public audio requires confirmation.
	 */
	public function needs_confirmation( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post && '' !== (string) $post->post_password;
	}

	/**
	 * Public posts are allowed; protected posts need a matching recorded grant.
	 */
	public function is_allowed( int $post_id ): bool {
		if ( ! $this->needs_confirmation( $post_id ) ) {
			return true;
		}

		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return false;
		}

		return hash_equals( $this->get_fingerprint( $post_id ), $stored );
	}

	/**
	 * Convert only explicit boolean or 1/0 choices.
	 */
	public function normalize_choice( $choice ): ?bool {
		if ( true === $choice || 1 === $choice || '1' === $choice ) {
			return true;
		}

		if ( false === $choice || 0 === $choice || '0' === $choice ) {
			return false;
		}

		return null;
	}

	/**
	 * Render classic-editor fields. JavaScript fills the choice only after confirmation.
	 */
	public function render_classic_fields( \WP_Post $post ): void {
		if ( ! $this->common->is_polly_enabled() || ! $this->is_supported_post( $post ) ) {
			return;
		}

		echo '<input type="hidden" name="' . esc_attr( self::CLASSIC_NONCE_NAME ) . '" value="' . esc_attr( wp_create_nonce( $this->get_classic_nonce_action( $post->ID ) ) ) . '" />';
		echo '<input type="hidden" id="' . esc_attr( self::CLASSIC_CHOICE ) . '" name="' . esc_attr( self::CLASSIC_CHOICE ) . '" value="" />';
	}

	/**
	 * Capture classic-editor choices before the generation save pipeline runs.
	 */
	public function capture_classic_choice( int $post_id, \WP_Post $post, bool $updated ): void {
		unset( $updated );

		if ( ! $this->is_supported_post( $post ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Removing protection invalidates any old protected-context grant.
		if ( ! $this->needs_confirmation( $post_id ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		if ( ! isset( $_POST[ self::CLASSIC_NONCE_NAME ], $_POST[ self::CLASSIC_CHOICE ] ) ) {
			return;
		}

		$this->record_classic_choice(
			$post_id,
			wp_unslash( $_POST[ self::CLASSIC_CHOICE ] ),
			wp_unslash( $_POST[ self::CLASSIC_NONCE_NAME ] )
		);
	}

	/**
	 * Record a classic-editor choice with its post-specific nonce.
	 */
	public function record_classic_choice( int $post_id, $choice, $nonce ): bool {
		if ( ! $this->verify_nonce( $nonce, $this->get_classic_nonce_action( $post_id ) ) ) {
			return false;
		}

		return $this->record_choice( $post_id, $choice );
	}

	/**
	 * Record one bulk choice against each currently protected, authorized post.
	 */
	public function record_bulk_choice( int $post_id, $choice, $nonce ): bool {
		if ( ! $this->verify_nonce( $nonce, self::BULK_NONCE_ACTION ) ) {
			return false;
		}

		return $this->record_choice( $post_id, $choice );
	}

	/**
	 * Register a write-only boolean field on supported REST-enabled post types.
	 */
	public function register_rest_fields(): void {
		foreach ( $this->common->get_posttypes_array() as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( ! $post_type_object || empty( $post_type_object->show_in_rest ) ) {
				continue;
			}

			register_rest_field(
				$post_type,
				self::REST_FIELD,
				array(
					'update_callback' => array( $this, 'record_rest_choice' ),
					'schema'          => array(
						'description' => __( 'Whether public audio is approved for the current protected post content.', 'ai-text-to-speech-using-aws-polly' ),
						'type'        => 'boolean',
						'context'     => array( 'edit' ),
					),
				)
			);
		}
	}

	/**
	 * Record a block-editor choice after core REST authentication.
	 */
	public function record_rest_choice( $choice, \WP_Post $post, string $field_name, $request ): bool {
		unset( $field_name );

		if ( ! $this->is_supported_post( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}

		$nonce = is_object( $request ) && is_callable( array( $request, 'get_header' ) )
			? $request->get_header( 'X-WP-Nonce' )
			: '';
		if ( ! $this->verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		$this->record_choice( $post->ID, $choice );

		// Consent failure must not cancel the ordinary post save.
		return true;
	}

	/**
	 * Return exact collection routes, including custom REST namespaces and bases.
	 */
	public function get_supported_rest_routes(): array {
		$routes = array();
		foreach ( $this->common->get_posttypes_array() as $post_type ) {
			$route = function_exists( 'rest_get_route_for_post_type_items' )
				? rest_get_route_for_post_type_items( $post_type )
				: '';
			if ( '' !== $route ) {
				$routes[] = $route;
			}
		}

		return array_values( array_unique( $routes ) );
	}

	private function record_choice( int $post_id, $raw_choice ): bool {
		$choice = $this->normalize_choice( $raw_choice );
		$post   = get_post( $post_id );

		if ( null === $choice || ! $this->is_supported_post( $post ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		if ( ! $choice ) {
			delete_post_meta( $post_id, self::META_KEY );
			if ( '' !== get_post_meta( $post_id, self::META_KEY, true ) ) {
				update_post_meta( $post_id, self::META_KEY, 'revoked' );
			}
			return true;
		}

		if ( ! $this->needs_confirmation( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, self::META_KEY, $this->get_fingerprint( $post_id ) );

		return $this->is_allowed( $post_id );
	}

	private function get_fingerprint( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$context = array(
			'version'    => self::FINGERPRINT_VERSION,
			'post_id'    => $post_id,
			'speech'     => $this->common->clean_text( $post_id, true, false ),
			'protection' => array(
				'type'     => 'password',
				'password' => (string) $post->post_password,
			),
		);
		$encoded = wp_json_encode( $context );
		if ( ! is_string( $encoded ) ) {
			throw new \RuntimeException( 'Unable to fingerprint protected audio consent.' );
		}

		return hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
	}

	private function get_classic_nonce_action( int $post_id ): string {
		return self::CLASSIC_NONCE_ACTION . $post_id;
	}

	private function verify_nonce( $nonce, string $action ): bool {
		return is_string( $nonce )
			&& '' !== $nonce
			&& (bool) wp_verify_nonce( sanitize_text_field( $nonce ), $action );
	}

	private function is_supported_post( $post ): bool {
		return $post instanceof \WP_Post
			&& in_array( $post->post_type, $this->common->get_posttypes_array(), true );
	}
}
