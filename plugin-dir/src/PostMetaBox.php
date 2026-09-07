<?php

namespace iTRON\PollyTTS;

/**
 * Post meta box to enable/disable polly
 */
class PostMetaBox {
	/**
	 * @var Common
	 */
	private $common;

	public function __construct( Common $common) {
		$this->common = $common;
	}

	/**
	 * Initialize box with 'Enable Amazon Polly' checkbox under the new post form.
	 *
	 * @param string $post New post.
	 *
	 * @since      0.1
	 */
	public function display_box_content( $post) {
		$this->display_polly_gui( $post );
	}

	private function render_voice_options( $language_code, $selected_voice_id ) {
		$neural_requested = $this->common->is_polly_neural_requested();
		$voice_groups     = $this->common->get_grouped_polly_voices( $language_code );
		$has_voices       = false;

		foreach ( $voice_groups as $group_key => $group ) {
			if ( empty( $group['voices'] ) ) {
				continue;
			}

			$has_voices = true;
			echo '<optgroup label="' . esc_attr( $group['label'] ) . '">';
			foreach ( $group['voices'] as $voice ) {
				$is_neural_only = 'neural_only' === $group_key;
				$is_disabled    = $is_neural_only && ! $neural_requested;

				echo '<option value="' . esc_attr( $voice['Id'] ) . '"';
				echo ' data-supported-engines="' . esc_attr( implode( ',', $voice['SupportedEngines'] ?? array() ) ) . '"';
				echo ' data-neural-only="' . esc_attr( $is_neural_only ? '1' : '0' ) . '"';
				echo ' data-standard-supported="' . esc_attr( $this->common->is_standard_supported_for_voice( $voice ) ? '1' : '0' ) . '"';
				if ( $is_disabled ) {
					echo ' disabled="disabled"';
				}
				if ( strcmp( $selected_voice_id, $voice['Id'] ) === 0 ) {
					echo ' selected="selected"';
				}
				echo '>' . esc_attr( $voice['LanguageName'] ) . ' - ' . esc_attr( $voice['Id'] ) . ' [' . esc_attr( $this->common->get_polly_voice_capability_label( $voice ) ) . ']</option>';
			}
			echo '</optgroup>';
		}

		return $has_voices;
	}

	/**
	 * Display Polly GUI on page for saving new post.
	 *
	 * @param string $post New post.
	 *
	 * @since      0.1
	 */
	public function display_polly_gui( $post) {
		$nonce = wp_create_nonce( 'itron-polly-tts' );

		echo '<input type="hidden" name="itron-polly-tts-post-nonce" value="' . esc_attr( $nonce ) . '" />';

		if ($this->common->is_polly_enabled()) {
			echo '<input type="hidden" name="itron_polly_tts_settings_present" value="1" />';
			$is_polly_enabled_for_post = get_post_meta( $post->ID, 'itron_polly_tts_enable', true );
			if ('1' === $is_polly_enabled_for_post) {
				$polly_checked = 'checked';
			} elseif ('0' === $is_polly_enabled_for_post) {
				$polly_checked = '';
			} else {
				if ($this->common->is_polly_enabled_for_new_posts()) {
					$polly_checked = 'checked';
				} else {
					$polly_checked = '';
				}
			}

			$post_options_visibility = '';

			echo '<p><input type="checkbox" name="itron_polly_tts_enable" id="itron_polly_tts_enable" value="1"  ' . esc_attr( $polly_checked ) . '/><label for="itron_polly_tts_enable">Enable Text-To-Speech (Amazon Polly)</label> </p>';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The constant contains the itron_polly_tts_ prefixed consent hook.
			do_action( AudioConsent::META_BOX_HOOK, $post );
			echo '<div id="itron_polly_tts_post_options" style="' . esc_attr( $post_options_visibility ) . '">';

			try {
				$language_code                   = $this->common->get_post_source_language( $post->ID );
				$post_voice_id                   = get_post_meta( $post->ID, 'itron_polly_tts_voice_id', true );
				$is_post_voice_override_disabled = $this->common->is_post_voice_override_disabled();
				$global_voice_id                 = $this->common->resolve_polly_voice_id( $language_code, $this->common->get_voice_id(), 'Matthew' );
				$voice_id                        = $is_post_voice_override_disabled
				? $global_voice_id
				: $this->common->resolve_polly_voice_id( $language_code, $post_voice_id, $global_voice_id );

				$compatible_voices = $this->common->get_compatible_polly_voices( $language_code );

				if ( empty( $compatible_voices ) ) {
					echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
				} elseif ( $is_post_voice_override_disabled ) {
					echo '<p>Voice name: <strong>' . esc_html( $voice_id ) . '</strong></p>';
					echo '<p class="description">Custom per-post voice selection is disabled in plugin settings. This post will use the global voice.</p>';
				} else {
					echo '<p>Voice name: <select name="itron_polly_tts_voice_id" id="itron_polly_tts_voice_id" >';
					if ( ! $this->render_voice_options( $language_code, $voice_id ) ) {
						echo '</select></p>';
						echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
						echo '</div>';
						return;
					}
					echo '</select></p>';
					echo '<p class="description">Neural-only voices require the global Neural setting to stay enabled.</p>';
				}
			} catch ( \Exception $e ) {
				echo '<p class="description">Unable to load supported Amazon Polly voices right now.</p>';
			}

			echo '</div>';
		} elseif ( $this->common->has_post_audio( (int) $post->ID ) ) {
			echo '<p class="notice notice-warning">' . esc_html__( 'Text-to-speech is off. If this update changes the speech content or synthesis settings, the existing audio will be removed without generating a replacement. An unchanged save will keep it.', 'ai-text-to-speech-using-aws-polly' ) . '</p>';
		}

		echo '<p>' . esc_html__( 'AWS charges may apply.', 'ai-text-to-speech-using-aws-polly' ) . ' <a href="https://aws.amazon.com/polly/pricing/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View official Amazon Polly pricing.', 'ai-text-to-speech-using-aws-polly' ) . '</a></p>';
	}
}
