<?php

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['test_options']    = array();
	$GLOBALS['test_settings']   = array();
	$GLOBALS['test_fields']     = array();
	$GLOBALS['test_sections']   = array();
	$GLOBALS['test_access_key'] = '';
	$GLOBALS['test_secret_key'] = '';

	function __( string $text, string $domain = '' ): string {
		return $text;
	}

	function register_setting( string $group, string $name, array $args = array() ): bool {
		$GLOBALS['test_settings'][ $name ] = array( $group, $args );
		return true;
	}

	function add_settings_section( string $id, string $title, callable $callback, string $page ): void {
		$GLOBALS['test_sections'][ $id ] = array( $title, $callback, $page );
	}

	function add_settings_field( string $id, string $title, callable $callback, string $page, string $section, array $args = array() ): void {
		$GLOBALS['test_fields'][ $id ] = array( $title, $callback, $page, $section, $args );
	}

	function get_option( string $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['test_options'] ) ? $GLOBALS['test_options'][ $name ] : $default;
	}
	function add_settings_error( ...$args ): void {
		$GLOBALS['test_errors'][] = $args; }

	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}

	function sanitize_textarea_field( $value ): string {
		return sanitize_text_field( $value );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function esc_attr( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function checked( $checked, $current = true, bool $display = true ): string {
		$result = $checked == $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}

		return $result;
	}

	function selected( $selected, $current = true, bool $display = true ): string {
		$result = $selected == $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}

		return $result;
	}
}

namespace iTRON\PollyTTS {
	class GeneralConfiguration {
		public static function get_aws_access_key(): string {
			return $GLOBALS['test_access_key'];
		}

		public static function get_aws_secret_key(): string {
			return $GLOBALS['test_secret_key'];
		}
	}

	class Common {
		public function is_s3_enabled(): bool {
			return ! empty( get_option( 'itron_polly_tts_s3', '' ) ); }
		public function is_medialibrary_enabled(): bool {
			return ! empty( get_option( 'itron_polly_tts_medialibrary_enabled', '' ) ); }
		public function get_polly_voices() {
			++$this->catalog_calls;
			throw new \RuntimeException( 'Sensitive AWS exception must not escape.' );
		}
		public function has_aws_credentials(): bool {
			return '' !== GeneralConfiguration::get_aws_access_key() && '' !== GeneralConfiguration::get_aws_secret_key();
		}
		public bool $enabled = false;
		public int $catalog_calls = 0;
		public int $access_validation_calls = 0;

		public function is_polly_enabled(): bool {
			return $this->enabled;
		}

		public function normalize_polly_speaking_style( $style ): string {
			return in_array( $style, array( 'news', 'conversational' ), true ) ? $style : '';
		}

		public function is_ssml_enabled(): bool {
			return ! empty( get_option( 'itron_polly_tts_ssml', '' ) );
		}

		public function get_resolved_polly_voice_option() {
			++$this->catalog_calls;
			throw new \RuntimeException( 'Voice catalog access was not expected.' );
		}

		public function get_available_polly_voices() {
			++$this->catalog_calls;
			throw new \RuntimeException( 'Voice catalog access was not expected.' );
		}

		public function get_grouped_polly_voices() {
			++$this->catalog_calls;
			throw new \RuntimeException( 'Voice catalog access was not expected.' );
		}

		public function validate_itron_polly_tts_access(): bool {
			++$this->access_validation_calls;
			throw new \RuntimeException( 'AWS access validation was not expected.' );
		}
	}

	require_once dirname( __DIR__ ) . '/plugin-dir/src/PollyConfiguration.php';
}

namespace {
	use iTRON\PollyTTS\Common;
	use iTRON\PollyTTS\PollyConfiguration;

	function assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
			);
		}
	}

	function assert_contains( string $needle, string $haystack, string $message ): void {
		if ( false === strpos( $haystack, $needle ) ) {
			throw new RuntimeException( $message . "\nMissing: " . $needle . "\nOutput: " . $haystack );
		}
	}

	function render_callback( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	$expected_settings = array(
		'itron_polly_tts_polly_enable',
		'itron_polly_tts_source_language',
		'itron_polly_tts_disable_post_voice_override',
		'itron_polly_tts_voice_id',
		'itron_polly_tts_neural',
		'itron_polly_tts_speaking_style',
		'itron_polly_tts_sample_rate',
		'itron_polly_tts_auto_breaths',
		'itron_polly_tts_ssml',
		'itron_polly_tts_lexicons',
		'itron_polly_tts_speed',
		'itron_polly_tts_position',
		'itron_polly_tts_player_label',
		'itron_polly_tts_defconf',
		'itron_polly_tts_autoplay',
		'itron_polly_tts_coming_soon_text',
		'itron_polly_tts_add_post_title',
		'itron_polly_tts_add_post_excerpt',
		'itron_polly_tts_medialibrary_enabled',
		'itron_polly_tts_skip_tags',
		'itron_polly_tts_download_enabled',
		'itron_polly_tts_s3',
		'itron_polly_tts_posttypes',
		'itron_polly_tts_cloudfront',
		'itron_polly_tts_poweredby',
		'itron_polly_tts_logging',
	);
	$expected_fields   = $expected_settings;
	$expected_fields[0] = 'itron_polly_tts_source_language';
	$expected_fields[1] = 'itron_polly_tts_polly_enable';

	foreach ( array( false, true ) as $enabled ) {
		$GLOBALS['test_settings'] = array();
		$GLOBALS['test_fields']   = array();
		$GLOBALS['test_sections'] = array();
		$common                   = new Common();
		$common->enabled          = $enabled;
		$configuration            = new PollyConfiguration( $common );
		$configuration->display_options();

		assert_same( $expected_settings, array_keys( $GLOBALS['test_settings'] ), 'Every active setting must register regardless of enablement or credentials.' );
		assert_same( $expected_fields, array_keys( $GLOBALS['test_fields'] ), 'Every active option field must remain available while text-to-speech is OFF.' );
		assert_same( 0, $common->catalog_calls, 'Settings registration must not load the voice catalog.' );
		assert_same( 0, $common->access_validation_calls, 'Settings registration must not validate AWS access.' );
	}

	assert_same( false, isset( $GLOBALS['test_settings']['itron_polly_tts_news'] ), 'The obsolete news option must not register.' );
	assert_same( false, isset( $GLOBALS['test_settings']['itron_polly_tts_conversational'] ), 'The obsolete conversational option must not register.' );

	$GLOBALS['test_options'] = array(
		'itron_polly_tts_voice_id'       => 'Amy',
		'itron_polly_tts_neural'         => 'on',
		'itron_polly_tts_speaking_style' => 'news',
		'itron_polly_tts_ssml'           => 'on',
	);
	$GLOBALS['test_access_key'] = 'configured-key';
	$GLOBALS['test_secret_key'] = 'configured-secret';
	$common                     = new Common();
	$configuration              = new PollyConfiguration( $common );

	$off_output = render_callback(
		static function () use ( $configuration ): void {
			$configuration->polly_enabled_gui();
			$configuration->voices_gui();
			$configuration->neural_gui();
			$configuration->speaking_style_gui();
			$configuration->ssml_gui();
		}
	);
	assert_contains( 'name="itron_polly_tts_polly_enable"', $off_output, 'The enabled checkbox must render while OFF.' );
	assert_contains( 'type="hidden" name="itron_polly_tts_voice_id" value="Amy"', $off_output, 'The saved voice must be posted unchanged while OFF.' );
	assert_contains( 'name="itron_polly_tts_neural"', $off_output, 'The Neural setting must remain configurable while OFF.' );
	assert_contains( 'name="itron_polly_tts_speaking_style"', $off_output, 'The speaking style must remain configurable while OFF.' );
	assert_contains( 'name="itron_polly_tts_ssml"', $off_output, 'SSML must remain available without an S3 prerequisite.' );
	assert_same( 0, $common->catalog_calls, 'Rendering voice-related settings while OFF must not access AWS or the voice catalog.' );
	assert_same( 'Amy', $configuration->sanitize_voice_id( 'Amy' ), 'Voice sanitization must preserve the submitted value while OFF.' );
	assert_same( 'news', $configuration->sanitize_speaking_style( 'news' ), 'Speaking style sanitization must retain the active normalized setting.' );
	assert_same( 0, $common->catalog_calls, 'Sanitizing settings while OFF must not access the voice catalog.' );

	$common->enabled             = true;
	$GLOBALS['test_access_key'] = '';
	$GLOBALS['test_secret_key'] = '';
	$missing_credentials_output = render_callback(
		static function () use ( $configuration ): void {
			$configuration->voices_gui();
			$configuration->neural_gui();
			$configuration->speaking_style_gui();
		}
	);
	assert_contains( 'type="hidden" name="itron_polly_tts_voice_id" value="Amy"', $missing_credentials_output, 'The saved voice must be preserved when credentials are missing.' );
	assert_same( 'Amy', $configuration->sanitize_voice_id( 'Amy' ), 'Missing credentials must not clear the saved voice.' );
	assert_same( 0, $common->catalog_calls, 'Missing credentials must prevent voice catalog access.' );

	$GLOBALS['test_access_key'] = 'bad-key';
	$GLOBALS['test_secret_key'] = 'bad-secret';
	$GLOBALS['test_errors'] = array();
	$configuration = new PollyConfiguration( $common );
	$error_output = render_callback(
		static function () use ( $configuration ): void {
			$configuration->voices_gui();
			$configuration->neural_gui();
			$configuration->speaking_style_gui();
		}
	);
	assert_same( 1, $common->catalog_calls, 'Failed catalog reads must be caught and attempted only once per request.' );
	assert_same( 1, count( $GLOBALS['test_errors'] ), 'A failed catalog produces one safe actionable notice.' );
	assert_contains( 'polly:DescribeVoices', $GLOBALS['test_errors'][0][2], 'The warning identifies a permission to check.' );
	assert_contains( 'value="Amy"', $error_output, 'Failed AWS access preserves the voice control.' );
	assert_same( 'Amy', $configuration->sanitize_voice_id( 'Amy' ), 'Failed AWS access cannot crash saving the voice.' );
	assert_same( 1, $common->catalog_calls, 'Sanitization reuses the same failed-catalog result.' );
	$GLOBALS['test_options']['itron_polly_tts_cloudfront'] = 'cdn.example.test';
	$GLOBALS['test_options']['itron_polly_tts_medialibrary_enabled'] = 'on';
	$GLOBALS['test_options']['itron_polly_tts_s3'] = '';
	assert_contains( 'type="hidden" name="itron_polly_tts_cloudfront" value="cdn.example.test"', render_callback( array( $configuration, 'cloudfront_gui' ) ), 'Local storage preserves the registered CloudFront setting.' );
	$GLOBALS['test_options']['itron_polly_tts_s3'] = 'on';
	assert_contains( 'type="hidden" name="itron_polly_tts_medialibrary_enabled" value="on"', render_callback( array( $configuration, 'medialibrary_enabled_gui' ) ), 'S3 preserves the registered local media setting.' );
	echo "settings-registration: PASS\n";
}
