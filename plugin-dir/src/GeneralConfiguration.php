<?php

namespace iTRON\PollyTTS;
/**
 * Class responsible for providing GUI for general configuration of the plugin
 *
 * @since      0.1
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GeneralConfiguration {

	/**
	 * @var Common
	 */
	private $common;

	private const OPTION_PREFIX = 'itron_polly_tts_';
	private const CONST_PREFIX  = 'ITRON_POLLY_TTS_';

	/**
	 * GeneralConfiguration constructor.
	 *
	 * @param Common $common
	 */
	public function __construct( Common $common) {
		$this->common = $common;
	}

	public function itron_polly_tts_add_menu() {
		$this->plugin_screen_hook_suffix = add_menu_page(
			__( 'AI Text-to-Speech', 'ai-text-to-speech-using-aws-polly' ),
			__( 'AI TTS', 'ai-text-to-speech-using-aws-polly' ),
			'manage_options',
			'itron_polly_tts',
			array(
				$this,
				'render_settings_page',
			),
			'dashicons-controls-volumeon'
		);
		$this->plugin_screen_hook_suffix = add_submenu_page(
			'itron_polly_tts',
			'General',
			'General',
			'manage_options',
			'itron_polly_tts',
			array(
				$this,
				'render_settings_page',
			)
		);
	}

	public function render_settings_page() {
		?>
				 <div class="wrap">
				 <div id="icon-options-general" class="icon32"></div>
				 <h1><?php esc_html_e( 'AI Text-to-Speech', 'ai-text-to-speech-using-aws-polly' ); ?></h1>
				 <form method="post" action="options.php">
						 <?php

							settings_errors();
							settings_fields( 'itron_polly_tts' );
							do_settings_sections( 'itron_polly_tts' );
							submit_button();

							?>
				 </form>

		 </div>
		 <?php
	}

	function display_options() {

		// ************************************************* *
		// ************** GENERAL SECTION ************** *
		add_settings_section(
			'itron_polly_tts_general',
			'',
			array(
				$this,
				'general_gui',
			),
			'itron_polly_tts'
		);
		add_settings_field(
			self::OPTION_PREFIX . 's3_access_key',
			__( 'AWS access key:', 'ai-text-to-speech-using-aws-polly' ),
			array(
				$this,
				'access_key_gui',
			),
			'itron_polly_tts',
			'itron_polly_tts_general',
			array(
				'label_for' => 'itron_polly_tts_s3_access_key',
			)
		);
		add_settings_field(
			self::OPTION_PREFIX . 's3_secret_key',
			__( 'AWS secret key:', 'ai-text-to-speech-using-aws-polly' ),
			array(
				$this,
				'secret_key_gui',
			),
			'itron_polly_tts',
			'itron_polly_tts_general',
			array(
				'label_for' => 'itron_polly_tts_secret_key_fake',
			)
		);
		add_settings_field(
			self::OPTION_PREFIX . 's3_bucket_name',
			__( 'Amazon S3 bucket name:', 'ai-text-to-speech-using-aws-polly' ),
			array( $this, 's3_bucket_gui' ),
			'itron_polly_tts',
			'itron_polly_tts_general',
			array( 'label_for' => 'itron_polly_tts_s3_bucket_name' )
		);
		add_settings_field(
			self::OPTION_PREFIX . 's3_region',
			__( 'AWS Region:', 'ai-text-to-speech-using-aws-polly' ),
			array(
				$this,
				'region_gui',
			),
			'itron_polly_tts',
			'itron_polly_tts_general',
			array(
				'label_for' => 'itron_polly_tts_s3_region',
			)
		);

		$this->register_sanitized_setting( 'itron_polly_tts', self::OPTION_PREFIX . 's3_access_key', array( $this, 'sanitize_text_option' ) );
		$this->register_sanitized_setting( 'itron_polly_tts', self::OPTION_PREFIX . 's3_secret_key', array( $this, 'sanitize_secret_option' ) );
		$this->register_sanitized_setting( 'itron_polly_tts', self::OPTION_PREFIX . 's3_bucket_name', array( $this, 'sanitize_text_option' ) );
		$this->register_sanitized_setting( 'itron_polly_tts', self::OPTION_PREFIX . 's3_region', array( $this, 'sanitize_region' ) );
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

	private function get_regions(): array {
		return array(
			'us-east-1'      => 'US East (N. Virginia)',
			'us-east-2'      => 'US East (Ohio)',
			'us-west-1'      => 'US West (N. California)',
			'us-west-2'      => 'US West (Oregon)',
			'eu-west-1'      => 'EU (Ireland)',
			'eu-west-2'      => 'EU (London)',
			'eu-west-3'      => 'EU (Paris)',
			'eu-central-1'   => 'EU (Frankfurt)',
			'ca-central-1'   => 'Canada (Central)',
			'sa-east-1'      => 'South America (Sao Paulo)',
			'ap-southeast-1' => 'Asia Pacific (Singapore)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ap-northeast-2' => 'Asia Pacific (Seoul)',
			'ap-south-1'     => 'Asia Pacific (Mumbai)',
		);
	}

	public function sanitize_text_option( $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitizes the AWS secret key without using sanitize_text_field().
	 *
	 * AWS secret keys may contain characters that are valid for credentials but
	 * can be stripped or altered by text-field sanitization. Keep the secret
	 * value intact while removing invalid UTF-8 and single-line control chars.
	 */
	public function sanitize_secret_option( $value ): string {
		$value = wp_check_invalid_utf8( wp_unslash( (string) $value ) );
		$value = str_replace( array( "\r", "\n", "\t", "\0", "\x0B" ), '', $value );

		return trim( $value );
	}

	public function sanitize_region( $value ): string {
		$value   = sanitize_text_field( wp_unslash( (string) $value ) );
		$regions = $this->get_regions();

		if ( isset( $regions[ $value ] ) ) {
			return $value;
		}

		return array_key_first( $regions );
	}


	/**
	 * Render the Access Key input for this plugin
	 *
	 * @since      0.1
	 */
	function access_key_gui() {
		$name        = 's3_access_key';
		$option      = self::OPTION_PREFIX . $name;
		$is_disabled = self::is_overloaded( $name );
		echo '<input' . disabled( $is_disabled, true, false ) . ' type="text" class="regular-text" name="' . esc_attr( $option ) . '" id="' . esc_attr( $option ) . '" value="' . esc_attr( self::get_aws_access_key() ) . '" autocomplete="off"> ';
		if ( $is_disabled ) {
			echo '<p class="description" id="' . esc_attr( $option ) . '">Defined as php constant</p>';
		}
	}



	/**
	 * Render the Secret Key input for this plugin
	 *
	 * @since      0.1
	 */
	function secret_key_gui() {
		$name        = 's3_secret_key';
		$option      = self::OPTION_PREFIX . $name;
		$is_disabled = self::is_overloaded( $name );
		echo '<input' . disabled( $is_disabled, true, false ) . ' type="password" class="regular-text" name="' . esc_attr( $option ) . '" id="' . esc_attr( $option ) . '" value="' . esc_attr( self::get_aws_secret_key() ) . '" autocomplete="off"> ';
		if ( $is_disabled ) {
			echo '<p class="description" id="' . esc_attr( $option ) . '">Defined as php constant</p>';
		}
	}

	function s3_bucket_gui() {
		$name        = 's3_bucket_name';
		$option      = self::OPTION_PREFIX . $name;
		$is_disabled = self::is_overloaded( $name );
		echo '<input' . disabled( $is_disabled, true, false ) . ' type="text" class="regular-text" name="' . esc_attr( $option ) . '" id="' . esc_attr( $option ) . '" value="' . esc_attr( self::get_bucket_name() ) . '" autocomplete="off"> ';
		if ( $is_disabled ) {
			echo '<p class="description" id="' . esc_attr( $option ) . '">Defined as php constant</p>';
		}
	}

	/**
	 * Render the region input.
	 *
	 * @since      0.1
	 */
	function region_gui() {
		$name            = 's3_region';
		$option          = self::OPTION_PREFIX . $name;
		$selected_region = $this->get_option( $name );
		$regions         = $this->get_regions();

		if ( empty( $selected_region ) ) {
			$selected_region = array_key_first( $regions );
		}

		$is_disabled = self::is_overloaded( $name );

		echo '<select name="' . esc_attr( $option ) . '" id="' . esc_attr( $option ) . '"' . disabled( $is_disabled, true, false ) . '>';
		foreach ( $regions as $region_name => $region_label ) {
			echo '<option label="' . esc_attr( $region_label ) . '" value="' . esc_attr( $region_name ) . '"' . selected( $selected_region, $region_name, false ) . '>';
			echo esc_html( $region_label ) . '</option>';
		}
		echo '</select>';

		if ( $is_disabled ) {
			echo '<p class="description" id="itron_polly_tts_s3_region">Defined as php constant</p>';
		}
	}



	function general_gui() {
		//Empty
	}

	function storage_gui() {
		//Empty
	}

	private static function is_overloaded( $option_slug ): bool {
		return defined( self::CONST_PREFIX . strtoupper( $option_slug ) );
	}

	private static function get_overloaded( $option_slug ) {
		return constant( self::CONST_PREFIX . strtoupper( $option_slug ) );
	}

	public static function get_option( $option_name ) {
		if ( self::is_overloaded( $option_name ) ) {
			return self::get_overloaded( $option_name );
		}

		return (string) get_option( self::OPTION_PREFIX . $option_name );
	}

	/**
	 * Get S3 bucket name. The method uses filter 'itron_polly_tts_s3_bucket_name,
	 * which allows to use customer S3 bucket name instead of default one.
	 *
	 * @since      0.1
	 */
	public static function get_bucket_name() {
		return self::get_option( 's3_bucket_name' );
	}

	public static function get_aws_access_key() {
		return self::get_option( 's3_access_key' );
	}

	public static function get_aws_secret_key() {
		return self::get_option( 's3_secret_key' );
	}

	public static function get_aws_region() {
		$region = self::get_option( 's3_region' );

		return '' === $region ? 'us-east-1' : $region;
	}
}
