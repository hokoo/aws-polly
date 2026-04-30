<?php

namespace iTRON\PollyTTS;
/**
 * Class responsible for providing GUI for Amazon Polly configuration.
 *
 * @since      0.1
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PollyConfiguration {
	/**
	 * @var Common
	 */
	private $common;
	private $polly_access_available = null;

	/**
	 * PollyConfiguration constructor.
	 *
	 * @param Common $common
	 */
	public function __construct( Common $common) {
		$this->common = $common;
	}

	private function can_render_polly_settings(): bool {
		if ( null === $this->polly_access_available ) {
			$this->polly_access_available = $this->common->validate_itron_polly_tts_access( false, true );
		}

		return (bool) $this->polly_access_available;
	}

	public function itron_polly_tts_add_menu() {
		$this->plugin_screen_hook_suffix = add_submenu_page( 'itron_polly_tts', 'Text-To-Speech', 'Text-To-Speech', 'manage_options', 'itron_polly_tts_polly', array( $this, 'render_text_to_speech_page' ) );

	}

	public function render_text_to_speech_page() {
		?>
			 <div class="wrap">
			 <div id="icon-options-polly" class="icon32"></div>
			 <h1>AI Text-to-Speech using AWS Polly</h1>
			 <form method="post" action="options.php">
					 <?php

						settings_errors();
						settings_fields( 'itron_polly_tts_polly' );
						do_settings_sections( 'itron_polly_tts_polly' );
						submit_button();

						?>
			 </form>

	 </div>
		<?php
	}

	function display_options() {
		$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_polly_enable', array( $this, 'sanitize_checkbox_option' ) );
		add_settings_section( 'itron_polly_tts_polly', 'Amazon Polly configuration', array( $this, 'polly_gui' ), 'itron_polly_tts_polly' );
		add_settings_field( 'itron_polly_tts_source_language', __( 'Source language:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'source_language_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_source_language' ) );
		$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_source_language', array( $this, 'sanitize_source_language' ) );
		add_settings_field( 'itron_polly_tts_polly_enable', __( 'Enable text-to-speech support:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'polly_enabled_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_polly_enable' ) );

		if ($this->common->is_polly_enabled() ) {
			if ($this->can_render_polly_settings()) {
				if ($this->common->is_language_supported_for_polly()) {
					add_settings_field( 'itron_polly_tts_disable_post_voice_override', __( 'Lock post voice to global setting:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'disable_post_voice_override_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_disable_post_voice_override' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_disable_post_voice_override', array( $this, 'sanitize_checkbox_option' ) );
					add_settings_field( 'itron_polly_tts_voice_id', __( 'Voice name:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'voices_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_voice_id' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_voice_id', array( $this, 'sanitize_voice_id' ) );

					add_settings_field( 'itron_polly_tts_neural', __( 'Neural Text-To-Speech:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'neural_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_neural' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_neural', array( $this, 'sanitize_checkbox_option' ) );
					add_settings_field( 'itron_polly_tts_speaking_style', __( 'Speaking Style:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'speaking_style_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_speaking_style' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_speaking_style', array( $this, 'sanitize_speaking_style' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_news', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_conversational', array( $this, 'sanitize_checkbox_option' ) );
					add_settings_field( 'itron_polly_tts_sample_rate', __( 'Sample rate:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'sample_rate_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_sample_rate' ) );
					add_settings_field( 'itron_polly_tts_auto_breaths', __( 'Automated breaths:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'auto_breaths_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_auto_breaths_id' ) );
					add_settings_field( 'itron_polly_tts_ssml', __( 'Enable SSML support:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'ssml_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_ssml' ) );
					add_settings_field( 'itron_polly_tts_lexicons', __( 'Lexicons:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'lexicons_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_lexicons' ) );
					add_settings_field( 'itron_polly_tts_speed', __( 'Audio speed [%]:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'audio_speed_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_polly', array( 'label_for' => 'itron_polly_tts_speed' ) );

					add_settings_section( 'itron_polly_tts_playersettings', __( 'Player settings', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'playersettings_gui' ), 'itron_polly_tts_polly' );
					add_settings_field( 'itron_polly_tts_position', __( 'Player position:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'playerposition_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_playersettings', array( 'label_for' => 'itron_polly_tts_position' ) );
					add_settings_field( 'itron_polly_tts_player_label', __( 'Player label:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'playerlabel_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_playersettings', array( 'label_for' => 'itron_polly_tts_player_label' ) );
					add_settings_field( 'itron_polly_tts_defconf', __( 'New post default:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'defconf_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_playersettings', array( '' => 'itron_polly_tts_defconf' ) );
					add_settings_field( 'itron_polly_tts_autoplay', __( 'Autoplay:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'autoplay_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_playersettings', array( 'label_for' => 'itron_polly_tts_autoplay' ) );
					add_settings_field( 'itron_polly_tts_coming_soon_text', __( 'Coming Soon Text:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'coming_soon_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_playersettings', array( 'label_for' => 'itron_polly_tts_coming_soon' ) );

					add_settings_section( 'itron_polly_tts_pollyadditional', __( 'Additional configuration', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'pollyadditional_gui' ), 'itron_polly_tts_polly' );
					//                  add_settings_field( 'itron_polly_tts_update_all', __( 'Bulk update all posts:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'update_all_gui' ),'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_update_all' ) );
					add_settings_field( 'itron_polly_tts_add_post_title', __( 'Add post title to audio:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'add_post_title_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_add_post_title' ) );
					add_settings_field( 'itron_polly_tts_add_post_excerpt', __( 'Add post excerpt to audio:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'add_post_excerpt_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_add_post_excerpt' ) );
					add_settings_field( 'itron_polly_tts_medialibrary_enabled', __( 'Enable Media Library support:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'medialibrary_enabled_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_medialibrary_enabled' ) );
					add_settings_field( 'itron_polly_tts_skip_tags', __( 'Skip tags:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'skiptags_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_skip_tags' ) );
					add_settings_field( 'itron_polly_tts_download_enabled', __( 'Enable download audio:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'download_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_download_enabled' ) );

					add_settings_field( 'itron_polly_tts_s3', __( 'Store audio in Amazon S3:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 's3_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_s3' ) );
					add_settings_field( 'itron_polly_tts_posttypes', __( 'Post types:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'posttypes_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_posttypes' ) );
					add_settings_field( 'itron_polly_tts_cloudfront', __( 'Amazon CloudFront (CDN) domain name:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'cloudfront_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_cloudfront' ) );
					add_settings_field( 'itron_polly_tts_poweredby', __( 'Display public AWS Polly credit:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'poweredby_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_poweredby' ) );
					add_settings_field( 'itron_polly_tts_logging', __( 'Enable logging:', 'ai-text-to-speech-using-aws-polly' ), array( $this, 'logging_gui' ), 'itron_polly_tts_polly', 'itron_polly_tts_pollyadditional', array( 'label_for' => 'itron_polly_tts_logging' ) );

					//Registration
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_s3', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_cloudfront', array( $this, 'sanitize_text_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_poweredby', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_logging', array( $this, 'sanitize_checkbox_option' ) );

					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_sample_rate', array( $this, 'sanitize_sample_rate' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_auto_breaths', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_ssml', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_lexicons', array( $this, 'sanitize_text_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_speed', array( $this, 'sanitize_audio_speed' ) );

					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_position', array( $this, 'sanitize_player_position' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_player_label', array( $this, 'sanitize_text_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_defconf', array( $this, 'sanitize_default_configuration' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_autoplay', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_coming_soon_text', array( $this, 'sanitize_textarea_option' ) );

					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_add_post_title', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_add_post_excerpt', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_medialibrary_enabled', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_skip_tags', array( $this, 'sanitize_text_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_download_enabled', array( $this, 'sanitize_checkbox_option' ) );
					$this->register_sanitized_setting( 'itron_polly_tts_polly', 'itron_polly_tts_posttypes', array( $this, 'sanitize_posttypes' ) );
				}
			}
		}

	}

	private function register_sanitized_setting( string $option_group, string $option_name, callable $sanitize_callback ): void {
		register_setting(
			$option_group,
			$option_name,
			array(
				'sanitize_callback' => $sanitize_callback,
			)
		);
	}

	private function is_option_enabled( string $option_name ): bool {
		return ! empty( get_option( $option_name, '' ) );
	}

	public function sanitize_checkbox_option( $value ): string {
		return empty( $value ) ? '' : 'on';
	}

	public function sanitize_text_option( $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	public function sanitize_textarea_option( $value ): string {
		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	public function sanitize_source_language( $language_code ): string {
		$language_code = sanitize_text_field( wp_unslash( (string) $language_code ) );

		if ( in_array( $language_code, $this->common->get_all_languages(), true ) ) {
			return $language_code;
		}

		return $this->common->get_source_language();
	}

	public function sanitize_player_position( $position ): string {
		$position = sanitize_text_field( wp_unslash( (string) $position ) );
		$allowed  = array( 'Before post', 'After post', 'Do not show' );

		if ( in_array( $position, $allowed, true ) ) {
			return $position;
		}

		return 'Before post';
	}

	public function sanitize_default_configuration( $value ): string {
		$value   = sanitize_text_field( wp_unslash( (string) $value ) );
		$allowed = array( 'Amazon Polly enabled', 'Amazon Polly disabled' );

		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return 'Amazon Polly disabled';
	}

	/**
	   * Render the Enable Text-To-Speech functionality option.
	   *
	   * @since      0.1
	   */
	public function polly_enabled_gui() {
		if ($this->common->is_language_supported_for_polly()) {
			if ($this->can_render_polly_settings()) {
				echo '<input type="checkbox" name="itron_polly_tts_polly_enable" id="itron_polly_tts_polly_enable"' . checked( $this->is_option_enabled( 'itron_polly_tts_polly_enable' ), true, false ) . '> ';
			} else {
				echo '<p>Verify that your AWS credentials are accurate</p>';
			}
		} else {
			echo '<p>Text-To-Speech functionality is not supported for this language</p>';
		}
	}

	/**
	 * Render the public AWS Polly credit option.
	 *
	 * @since      0.1
	 */
	function poweredby_gui() {
		echo '<input type="checkbox" name="itron_polly_tts_poweredby" id="itron_polly_tts_poweredby"' . checked( $this->common->is_poweredby_enabled(), true, false ) . '> <p class="description"></p>';
		echo '<p class="description">Optional public text credit for AWS Polly. Disabled by default. The plugin does not add external links to the public site.</p>';
	}

	/**
	 * Render the translation source language input.
	 *
	 * @since      0.1
	 */
	public function source_language_gui() {
		$selected_source_language = $this->common->get_source_language();
		echo '<select name="itron_polly_tts_source_language" id="itron_polly_tts_source_language" >';
		foreach ($this->common->get_all_languages() as $language_code) {
			$language_name = $this->common->get_language_name( $language_code );
			echo '<option label="' . esc_attr( $language_name ) . '" value="' . esc_attr( $language_code ) . '"' . selected( $selected_source_language, $language_code, false ) . '>';
			echo esc_html( $language_name ) . '</option>';
		}
		echo '</select>';
	}

	private function is_language_supported() {

		$selected_source_language = $this->common->get_source_language();

		foreach ($this->common->get_all_polly_languages() as $language_code) {
			if (strcmp( $selected_source_language, $language_code ) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the Update All input for this plugin
	 *
	 * @since      0.1
	 */
	public function update_all_gui() {

			$message = $this->common->get_price_message_for_update_all();
			echo '<p>';
				echo '<button type="button" class="button" name="itron_polly_tts_update_all" id="itron_polly_tts_update_all" disabled>Bulk Update</button>';
				echo '<label id="label_itron_polly_tts_update_all" for="itron_polly_tts_update_all"> Changes must be saved before proceeding with a bulk update.</label>';
		echo '<p class="description" for="itron_polly_tts_update_all">Functionality is disabled in this plugin version.</p>';
			echo '</p>';
			echo '<div id="itron_polly_tts_bulk_update_div">';
				echo '<p id="itron_polly_tts_update_all_pricing_message" class="description">' . esc_html( $message ) . '</p>';
				echo '<p><button type="button" class="button button-primary" id="itron_polly_tts_batch_transcribe" >Bulk Update</button></p>';
				echo '<div id="itron-polly-tts-progressbar"><div class="itron-polly-tts-progress-label">Loading...</div></div>';
			echo '</div>';

	}

	/**
	 * Render the 'use CloudFront' input.
	 *
	 * @since      0.1
	 */
	public function cloudfront_gui() {
		$is_s3_enabled = $this->common->is_s3_enabled();
		if ( $is_s3_enabled ) {
			$cloudfront_domain_name = get_option( 'itron_polly_tts_cloudfront' );
			echo '<input type="text" name="itron_polly_tts_cloudfront" class="regular-text" "id="itron_polly_tts_cloudfront" value="' . esc_attr( $cloudfront_domain_name ) . '" > ';
			echo '<p class="description">If you have a CloudFront distribution for your S3 bucket, enter the domain name. For more information and pricing, see <a target="_blank" href="https://aws.amazon.com/cloudfront">https://aws.amazon.com/cloudfront</a> </p>';
		} else {
			echo '<p class="description">Amazon S3 storage needs to be enabled</p>';
		}
	}

	/**
	 * Render the 'store in S3' input.
	 *
	 * @since      0.1
	 */
	function s3_gui() {
		$is_s3_enabled = $this->common->is_s3_enabled();
		if ( $is_s3_enabled ) {
			$checked                = ' checked ';
			$bucket_name_visibility = ' ';
		} else {
			$checked                = ' ';
			$bucket_name_visibility = 'display:none';
		}
		echo '<input type="checkbox" name="itron_polly_tts_s3" id="itron_polly_tts_s3" ' . esc_attr( $checked ) . ' > <p class="description"></p>';
		echo '<p class="description">Audio files are saved to and streamed from Amazon S3. For more information, see <a target="_blank" href="https://aws.amazon.com/s3">https://aws.amazon.com/s3</a></p>';
	}

	/**
	 * Render the 'Enable Logging' input.
	 *
	 * @since      0.1
	 */
	function logging_gui() {
		$checked = $this->common->checked_validator( 'itron_polly_tts_logging' );
		echo '<input type="checkbox" name="itron_polly_tts_logging" id="itron_polly_tts_logging" ' . esc_attr( $checked ) . ' > <p class="description"></p>';
	}

	/**
	 * Render the Add post excerpt to audio input.
	 *
	 * @since      0.1
	 */
	public function add_post_excerpt_gui() {

			echo '<input type="checkbox" name="itron_polly_tts_add_post_excerpt" id="itron_polly_tts_add_post_excerpt"' . checked( $this->is_option_enabled( 'itron_polly_tts_add_post_excerpt' ), true, false ) . '> ';
			echo '<p class="description" for="itron_polly_tts_add_post_excerpt">If enabled, each audio file will have an excerpt of the post at the beginning.</p>';

	}


	public function download_gui() {

		echo '<input type="checkbox" name="itron_polly_tts_download_enabled" id="itron_polly_tts_download_enabled"' . checked( $this->is_option_enabled( 'itron_polly_tts_download_enabled' ), true, false ) . '> ';
		echo '<p class="description" for="itron_polly_tts_add_post_excerpt">If enabled, viewers will see a download button next to the audio</p>';

	}


	/**
	 * Render the Add post title to audio input.
	 *
	 * @since      0.1
	 */
	public function add_post_title_gui() {

			echo '<input type="checkbox" name="itron_polly_tts_add_post_title" id="itron_polly_tts_add_post_title"' . checked( $this->is_option_enabled( 'itron_polly_tts_add_post_title' ), true, false ) . '> ';
			echo '<p class="description" for="itron_polly_tts_add_post_title">If enabled, each audio file will start from the post\'s title.</p>';

	}

	/**
	 * Render the Post Type input box.
	 *
	 * @since      0.1
	 */
	public function posttypes_gui() {
		$posttypes = $this->common->get_posttypes();
		echo '<input type="text" class="regular-text" name="itron_polly_tts_posttypes" id="itron_polly_tts_posttypes" value="' . esc_attr( $posttypes ) . '"> ';
		echo '<p class="description" for="itron_polly_tts_posttypes">Post types in your WordPress environment</p>';
	}

	public function sanitize_voice_id( $voice_id ) {
	  // phpcs:disable WordPress.Security.NonceVerification.Missing -- Settings API request is verified by options.php before sanitize callbacks run.
		$language_code = isset( $_POST['itron_polly_tts_source_language'] )
		? sanitize_text_field( wp_unslash( $_POST['itron_polly_tts_source_language'] ) )
		: $this->common->get_source_language();
	  // phpcs:enable WordPress.Security.NonceVerification.Missing

		return $this->common->get_resolved_polly_voice_option(
			'itron_polly_tts_voice_id',
			$language_code,
			'Matthew',
			array(
				'requested_voice_id' => sanitize_text_field( wp_unslash( $voice_id ) ),
			)
		);
	}

	public function sanitize_sample_rate( $sample_rate ) {
		return $this->common->normalize_sample_rate( sanitize_text_field( wp_unslash( $sample_rate ) ) );
	}

	public function sanitize_posttypes( $posttypes ) {
		return $this->common->normalize_posttypes( wp_unslash( $posttypes ) );
	}

	public function sanitize_audio_speed( $speed ) {
		return $this->common->normalize_audio_speed( wp_unslash( $speed ) );
	}

	public function sanitize_speaking_style( $style ) {
		return $this->common->sync_polly_speaking_style( sanitize_text_field( wp_unslash( $style ) ), false );
	}

	private function render_dynamic_checkbox_option( $option_name, $option_id, $is_checked, $show_checkbox, $description, $message, array $data_attributes = array() ) {
		echo '<div id="' . esc_attr( $option_id ) . '_ui" class="itron-polly-tts-dynamic-option"';
		foreach ( $data_attributes as $attribute_name => $attribute_value ) {
			echo ' data-' . esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';
		}
		echo '>';
		echo '<div class="itron-polly-tts-dynamic-option-input"' . ( $show_checkbox ? '' : ' style="display:none;"' ) . '>';
		echo '<input type="checkbox" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_id ) . '"' . checked( (bool) $is_checked, true, false ) . '> ';
		echo '</div>';
		echo '<p class="description itron-polly-tts-dynamic-option-description"' . ( $show_checkbox ? '' : ' style="display:none;"' ) . '>' . esc_html( $description ) . '</p>';
		echo '<p class="description itron-polly-tts-dynamic-option-message"' . ( $show_checkbox ? ' style="display:none;"' : '' ) . '>' . esc_html( $message ) . '</p>';
		echo '</div>';
	}

	private function render_dynamic_radio_option( $option_name, $option_id, $selected_value, $show_group, $description, $message, array $choices, array $data_attributes = array() ) {
		echo '<div id="' . esc_attr( $option_id ) . '_ui" class="itron-polly-tts-dynamic-option"';
		foreach ( $data_attributes as $attribute_name => $attribute_value ) {
			echo ' data-' . esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';
		}
			echo '>';
			echo '<div class="itron-polly-tts-dynamic-option-input"' . ( $show_group ? '' : ' style="display:none;"' ) . '>';

		foreach ( $choices as $choice ) {
			$choice_value = (string) $choice['value'];
			$choice_key   = '' === $choice_value ? 'default' : $choice_value;
			$choice_id    = $option_id . '_' . $choice_key;

			echo '<label class="itron-polly-tts-style-choice itron-polly-tts-style-choice-' . esc_attr( $choice_key ) . '"';
			if ( empty( $choice['visible'] ) ) {
				echo ' style="display:none;"';
			}
				echo '>';
				echo '<input type="radio" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $choice_id ) . '" value="' . esc_attr( $choice_value ) . '"';
			if ( strcmp( $selected_value, $choice_value ) === 0 ) {
				echo ' checked="checked"';
			}
				echo '> ' . esc_html( $choice['label'] ) . '</label><br>';
		}

			echo '</div>';
			echo '<p class="description itron-polly-tts-dynamic-option-description"' . ( $show_group ? '' : ' style="display:none;"' ) . '>' . esc_html( $description ) . '</p>';
			echo '<p class="description itron-polly-tts-dynamic-option-message"' . ( $show_group ? ' style="display:none;"' : '' ) . '>' . esc_html( $message ) . '</p>';
			echo '</div>';
	}

	/**
	 * Render the Neural GUI
	 *
	 */
	public function neural_gui() {

		$voice_id            = $this->common->get_resolved_polly_voice_option( 'itron_polly_tts_voice_id', $this->common->get_source_language(), 'Matthew' );
		$is_region_supported = $this->common->is_neural_supported_in_region();
		$is_voice_supported  = $this->common->is_neural_supported_for_voice( $voice_id );
		$show_checkbox       = $is_region_supported && $is_voice_supported;
		$message             = $is_region_supported
		? 'Option not supported for this voice. Choose a voice from the Standard + Neural or Neural-only groups.'
		: 'Option not supported in this region';

		$this->render_dynamic_checkbox_option(
			'itron_polly_tts_neural',
			'itron_polly_tts_neural',
			$this->common->is_polly_neural_requested(),
			$show_checkbox,
			'Controls whether the plugin uses the Neural engine for compatible voices. Neural-only voices require this setting to remain enabled.',
			$message,
			array(
				'region-supported' => $is_region_supported ? '1' : '0',
				'message-region'   => 'Option not supported in this region',
				'message-voice'    => 'Option not supported for this voice. Choose a voice from the Standard + Neural or Neural-only groups.',
			)
		);

	}

	public function speaking_style_gui() {

		$voice_id                = $this->common->get_resolved_polly_voice_option( 'itron_polly_tts_voice_id', $this->common->get_source_language(), 'Matthew' );
		$is_region_supported     = $this->common->is_neural_supported_in_region();
		$is_neural_requested     = $this->common->is_polly_neural_requested();
		$supports_news           = $this->common->is_news_style_for_voice( $voice_id );
		$supports_conversational = $this->common->is_conversational_style_for_voice( $voice_id );
		$show_group              = $is_region_supported && $is_neural_requested && ( $supports_news || $supports_conversational );
		$selected_style          = $this->common->get_active_polly_speaking_style( $voice_id );

		if ( ! $is_region_supported ) {
			$message = 'Option not supported in this region';
		} elseif ( ! $is_neural_requested ) {
			$message = 'Neural needs to be enabled';
		} else {
			$message = 'The current voice does not support Newscaster or Conversational styles';
		}

			$this->render_dynamic_radio_option(
				'itron_polly_tts_speaking_style',
				'itron_polly_tts_speaking_style',
				$selected_style,
				$show_group,
				'Choose one Neural speaking style. Newscaster and Conversational are mutually exclusive.',
				$message,
				array(
					array(
						'value'   => '',
						'label'   => 'Default',
						'visible' => true,
					),
					array(
						'value'   => 'news',
						'label'   => 'Newscaster Style',
						'visible' => $supports_news,
					),
					array(
						'value'   => 'conversational',
						'label'   => 'Conversational Style',
						'visible' => $supports_conversational,
					),
				),
				array(
					'region-supported' => $is_region_supported ? '1' : '0',
					'message-region'   => 'Option not supported in this region',
					'message-neural'   => 'Neural needs to be enabled',
					'message-voice'    => 'The current voice does not support Newscaster or Conversational styles',
				)
			);
	}

	/**
	 * Render the Neural GUI
	 *
	 */
	public function news_gui() {

		$voice_id            = $this->common->get_resolved_polly_voice_option( 'itron_polly_tts_voice_id', $this->common->get_source_language(), 'Matthew' );
		$is_region_supported = $this->common->is_neural_supported_in_region();
		$is_neural_requested = $this->common->is_polly_neural_requested();
		$is_voice_supported  = $this->common->is_news_style_for_voice( $voice_id );
		$show_checkbox       = $is_region_supported && $is_neural_requested && $is_voice_supported;

		if ( ! $is_region_supported ) {
			$message = 'Option not supported in this region';
		} elseif ( ! $is_neural_requested ) {
			$message = 'Neural needs to be enabled';
		} else {
			$message = 'Option not supported for this voice';
		}

		$this->render_dynamic_checkbox_option(
			'itron_polly_tts_news',
			'itron_polly_tts_news',
			(bool) $this->common->is_polly_news_enabled(),
			$show_checkbox,
			'Available only for supported Neural voices.',
			$message,
			array(
				'region-supported' => $is_region_supported ? '1' : '0',
				'message-region'   => 'Option not supported in this region',
				'message-neural'   => 'Neural needs to be enabled',
				'message-voice'    => 'Option not supported for this voice',
			)
		);
	}

	/**
	 * Render the Conversational GUI
	 *
	 */
	public function conversational_gui() {

		$voice_id            = $this->common->get_resolved_polly_voice_option( 'itron_polly_tts_voice_id', $this->common->get_source_language(), 'Matthew' );
		$is_region_supported = $this->common->is_neural_supported_in_region();
		$is_neural_requested = $this->common->is_polly_neural_requested();
		$is_voice_supported  = $this->common->is_conversational_style_for_voice( $voice_id );
		$is_news_enabled     = (bool) $this->common->is_polly_news_enabled();
		$show_checkbox       = $is_region_supported && $is_neural_requested && $is_voice_supported && ! $is_news_enabled;

		if ( ! $is_region_supported ) {
			$message = 'Option not supported in this region';
		} elseif ( ! $is_neural_requested ) {
			$message = 'Neural needs to be enabled';
		} elseif ( ! $is_voice_supported ) {
			$message = 'Option not supported for this voice';
		} else {
			$message = 'Only one style can be used';
		}

		$this->render_dynamic_checkbox_option(
			'itron_polly_tts_conversational',
			'itron_polly_tts_conversational',
			(bool) $this->common->is_polly_conversational_enabled(),
			$show_checkbox,
			'Available only for supported Neural voices.',
			$message,
			array(
				'region-supported'  => $is_region_supported ? '1' : '0',
				'message-region'    => 'Option not supported in this region',
				'message-neural'    => 'Neural needs to be enabled',
				'message-voice'     => 'Option not supported for this voice',
				'message-exclusive' => 'Only one style can be used',
			)
		);

	}

	/**
	 * Render the autoplay input.
	 *
	 * @since      0.1
	 */
	public function autoplay_gui() {

			$selected_autoplay = get_option( 'itron_polly_tts_autoplay' );

		if ( empty( $selected_autoplay ) ) {
			$checked = ' ';
		} else {
			$checked = ' checked ';
		}
			echo '<input type="checkbox" name="itron_polly_tts_autoplay" id="itron_polly_tts_autoplay" ' . esc_attr( $checked ) . '> ';
			echo '<p class="description" for="itron_polly_tts_autoplay">Automatically play audio content when page loads</p>';

	}

	public function coming_soon_gui() {
		$coming_soon = get_option( 'itron_polly_tts_coming_soon_text' );
		echo '<input type="text" class="regular-text" name="itron_polly_tts_coming_soon_text" id="itron_polly_tts_coming_soon_text" value="' . esc_attr( $coming_soon ) . '"> ';
	}

	/**
	 * Render the Default Configuration input.
	 *
	 * @since      0.1
	 */
	public function defconf_gui() {

			$selected_defconf = get_option( 'itron_polly_tts_defconf' );
			$defconf_values   = array( 'Amazon Polly enabled', 'Amazon Polly disabled' );

			echo '<select name="itron_polly_tts_defconf" id="itron_polly_tts_defconf" >';
		foreach ( $defconf_values as $defconf ) {
			echo '<option value="' . esc_attr( $defconf ) . '" ';
			if ( strcmp( $selected_defconf, $defconf ) === 0 ) {
				echo 'selected="selected"';
			}
			echo '>' . esc_attr( $defconf ) . '</option>';
		}
			echo '</select>';

	}

	/**
	 * Render the Player Label input.
	 *
	 * @since      0.1
	 */
	public function skiptags_gui() {

		$tags = get_option( 'itron_polly_tts_skip_tags' );
		echo '<input type="text" class="regular-text" name="itron_polly_tts_skip_tags" id="itron_polly_tts_skip_tags" value="' . esc_attr( $tags ) . '"> ';

	}

	/**
	 * Render the Player Label input.
	 *
	 * @since      0.1
	 */
	public function playerlabel_gui() {

		$player_label = get_option( 'itron_polly_tts_player_label' );
		echo '<input type="text" class="regular-text" name="itron_polly_tts_player_label" id="itron_polly_tts_player_label" value="' . esc_attr( $player_label ) . '"> ';

	}

	/**
	 * Render the Position input.
	 *
	 * @since      0.1
	 */
	public function playerposition_gui() {

			$selected_position = get_option( 'itron_polly_tts_position' );
			$positions_values  = array( 'Before post', 'After post', 'Do not show' );

			echo '<select name="itron_polly_tts_position" id="itron_polly_tts_position" >';
		foreach ( $positions_values as $position ) {
			echo '<option value="' . esc_attr( $position ) . '" ';
			if ( strcmp( $selected_position, $position ) === 0 ) {
				echo 'selected="selected"';
			}
			echo '>' . esc_attr( $position ) . '</option>';
		}
			echo '</select>';

	}

	/**
	 * Render the Sample Rate input for this plugin
	 *
	 * @since      0.1
	 */
	public function sample_rate_gui() {

			$sample_rate  = $this->common->get_sample_rate();
			$sample_array = array( '24000', '22050', '16000', '8000' );

			echo '<select name="itron_polly_tts_sample_rate" id="itron_polly_tts_sample_rate" >';
		foreach ( $sample_array as $rate ) {
			echo '<option value="' . esc_attr( $rate ) . '" ';
			if ( strcmp( $sample_rate, $rate ) === 0 ) {
				echo 'selected="selected"';
			}
			echo '>' . esc_attr( $rate ) . '</option>';
		}
			echo '</select>';

	}

	/**
	 * Render the Player Label input.
	 *
	 * @since      0.1
	 */
	public function lexicons_gui() {

			$lexicons = $this->common->get_lexicons();
			echo '<input type="text" class="regular-text" name="itron_polly_tts_lexicons" id="itron_polly_tts_lexicons" value="' . esc_attr( $lexicons ) . '"> ';
			echo '<p class="description" for="itron_polly_tts_lexicons">Specify the lexicons names, seperated by spaces, that you have uploaded to your AWS account</p>';

	}

	/**
	 * Render the autoplay input.
	 *
	 * @since      0.1
	 */
	public function audio_speed_gui() {

			$speed = $this->common->get_audio_speed();
			echo '<input type="number" name="itron_polly_tts_speed" id="itron_polly_tts_speed" value="' . esc_attr( $speed ) . '">';

	}

	public function disable_post_voice_override_gui() {
		echo '<input type="checkbox" name="itron_polly_tts_disable_post_voice_override" id="itron_polly_tts_disable_post_voice_override"' . checked( $this->common->is_post_voice_override_disabled(), true, false ) . '> ';
		echo '<p class="description">When enabled, posts cannot override the global Polly voice. Existing per-post voice selections are ignored.</p>';
	}


	/**
	 * Render the enable SSML input.
	 *
	 * @since      0.1
	 */
	public function medialibrary_enabled_gui() {

		$is_s3_enabled = $this->common->is_s3_enabled();
		if ( ! $is_s3_enabled ) {
			$is_medialibrary_enabled = $this->common->is_medialibrary_enabled();

			if ( $is_medialibrary_enabled ) {
				$checked = ' checked ';
			} else {
				$checked = ' ';
			}

			echo '<input type="checkbox" name="itron_polly_tts_medialibrary_enabled" id="itron_polly_tts_medialibrary_enabled" ' . esc_attr( $checked ) . '> ';
		} else {
			echo '<p class="description">Local storage needs to be enabled</p>';
		}

	}

	/**
	 * Render the enable SSML input.
	 *
	 * @since      0.1
	 */
	public function ssml_gui() {

			$is_s3_enabled = $this->common->is_s3_enabled();
		if ( $is_s3_enabled ) {
			$is_ssml_enabled = $this->common->is_ssml_enabled();

			if ( $is_ssml_enabled ) {
				$checked = ' checked ';
			} else {
				$checked = ' ';
			}

			echo '<input type="checkbox" name="itron_polly_tts_ssml" id="itron_polly_tts_ssml" ' . esc_attr( $checked ) . '> ';
		} else {
			echo '<p class="description">Amazon S3 storage needs to be enabled</p>';
		}

	}

	/**
	 * Render the Automated Breath input.
	 *
	 * @since      0.1
	 */
	public function auto_breaths_gui() {
		echo '<input type="checkbox" name="itron_polly_tts_auto_breaths" id="itron_polly_tts_auto_breaths"' . checked( $this->is_option_enabled( 'itron_polly_tts_auto_breaths' ), true, false ) . '> ';
		echo '<p class="description" for="itron_polly_tts_auto_breaths">Creates breathing noises at appropriate intervals</p>';
	}

	private function render_voice_options( $language_code, $selected_voice_id ) {
		$voice_groups = $this->common->get_grouped_polly_voices( $language_code );
		$has_voices   = false;

		foreach ( $voice_groups as $group_key => $group ) {
			if ( empty( $group['voices'] ) ) {
				continue;
			}

			$has_voices = true;
			echo '<optgroup label="' . esc_attr( $group['label'] ) . '">';
			foreach ( $group['voices'] as $voice ) {
				$is_neural_only    = 'neural_only' === $group_key;
				$supported_engines = implode( ',', $voice['SupportedEngines'] ?? array() );
				$capability_label  = $this->common->get_polly_voice_capability_label( $voice );

					echo '<option value="' . esc_attr( $voice['Id'] ) . '"';
					echo ' data-supported-engines="' . esc_attr( $supported_engines ) . '"';
					echo ' data-neural-only="' . esc_attr( $is_neural_only ? '1' : '0' ) . '"';
					echo ' data-standard-supported="' . esc_attr( $this->common->is_standard_supported_for_voice( $voice ) ? '1' : '0' ) . '"';
					echo ' data-supports-neural="' . esc_attr( $this->common->is_neural_supported_for_voice( $voice['Id'] ) ? '1' : '0' ) . '"';
					echo ' data-supports-news="' . esc_attr( $this->common->is_news_style_for_voice( $voice['Id'] ) ? '1' : '0' ) . '"';
					echo ' data-supports-conversational="' . esc_attr( $this->common->is_conversational_style_for_voice( $voice['Id'] ) ? '1' : '0' ) . '"';
				if ( strcmp( $selected_voice_id, $voice['Id'] ) === 0 ) {
					echo ' selected="selected"';
				}
				echo '>' . esc_attr( $voice['LanguageName'] ) . ' - ' . esc_attr( $voice['Id'] ) . ' [' . esc_attr( $capability_label ) . ']</option>';
			}
			echo '</optgroup>';
		}

		return $has_voices;
	}

	/**
	 * Render the Polly Voice input for this plugin
	 *
	 * @since      0.1
	 */
	public function voices_gui() {
		$language_code        = $this->common->get_source_language();
		$voice_id             = $this->common->get_resolved_polly_voice_option( 'itron_polly_tts_voice_id', $language_code, 'Matthew' );
		$available_voice_list = $this->common->get_available_polly_voices( $language_code );

		if ( empty( $available_voice_list ) ) {
			echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
			return;
		}

		echo '<select name="itron_polly_tts_voice_id" id="itron_polly_tts_voice_id">';
		if ( ! $this->render_voice_options( $language_code, $voice_id ) ) {
			echo '</select>';
			echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
			return;
		}
		echo '</select>';
		echo '<p class="description">Voices are grouped by engine support. All voices stay selectable; choosing a Standard voice turns Neural off, and choosing a Neural-only voice turns Neural on automatically.</p>';

	}

	function playersettings_gui() {
		// Empty
	}

	function polly_gui() {
			//Empty
	}

	function pollyadditional_gui() {
		//Empty
	}
}
