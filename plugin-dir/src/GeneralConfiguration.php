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
	private const DEFAULT_REGION = 'us-east-1';
	private const REGIONS        = array(
		'us-east-1'      => 'US East (N. Virginia)',
		'us-east-2'      => 'US East (Ohio)',
		'us-west-1'      => 'US West (N. California)',
		'us-west-2'      => 'US West (Oregon)',
		'af-south-1'     => 'Africa (Cape Town)',
		'ap-east-1'      => 'Asia Pacific (Hong Kong)',
		'ap-southeast-5' => 'Asia Pacific (Malaysia)',
		'ap-south-1'     => 'Asia Pacific (Mumbai)',
		'ap-northeast-3' => 'Asia Pacific (Osaka)',
		'ap-northeast-2' => 'Asia Pacific (Seoul)',
		'ap-southeast-1' => 'Asia Pacific (Singapore)',
		'ap-southeast-2' => 'Asia Pacific (Sydney)',
		'ap-southeast-7' => 'Asia Pacific (Thailand)',
		'ap-northeast-1' => 'Asia Pacific (Tokyo)',
		'ca-central-1'   => 'Canada (Central)',
		'eu-central-1'   => 'Europe (Frankfurt)',
		'eu-west-1'      => 'Europe (Ireland)',
		'eu-west-2'      => 'Europe (London)',
		'eu-west-3'      => 'Europe (Paris)',
		'eu-south-2'     => 'Europe (Spain)',
		'eu-north-1'     => 'Europe (Stockholm)',
		'eu-central-2'   => 'Europe (Zurich)',
		'me-south-1'     => 'Middle East (Bahrain)',
		'sa-east-1'      => 'South America (Sao Paulo)',
		'us-gov-west-1'  => 'AWS GovCloud (US-West)',
	);

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
		return self::REGIONS;
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
		$value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';

		if ( isset( self::REGIONS[ $value ] ) ) {
			return $value;
		}

		$saved_region = get_option( self::OPTION_PREFIX . 's3_region', '' );
		$saved_region = is_string( $saved_region ) && isset( self::REGIONS[ $saved_region ] )
			? $saved_region
			: self::DEFAULT_REGION;

		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				self::OPTION_PREFIX . 's3_region',
				'itron_polly_tts_invalid_s3_region',
				__( 'The submitted AWS Region is not supported by Amazon Polly. The current valid region remains in use; select a supported region and try again.', 'ai-text-to-speech-using-aws-polly' ),
				'error'
			);
		}

		return $saved_region;
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
		printf(
			'<p class="description"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
			esc_url( 'https://github.com/hokoo/aws-polly/blob/master/plugin-dir/readme.txt' ),
			esc_html__( 'View the plugin setup and AWS credentials guide.', 'ai-text-to-speech-using-aws-polly' )
		);
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

		return '' === $region ? self::DEFAULT_REGION : $region;
	}
}
