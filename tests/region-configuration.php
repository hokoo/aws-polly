<?php

namespace {
	define( 'ABSPATH', __DIR__ . '/../wordpress/' );

	$GLOBALS['options']         = array();
	$GLOBALS['settings_errors'] = array();
	$GLOBALS['checks']          = 0;

	function __( string $text, string $domain = '' ): string {
		return $text;
	}

	function get_option( string $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
	}

	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['settings_errors'][] = array( $setting, $code, $message, $type );
	}

	function esc_attr( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html__( string $text, string $domain = '' ): string {
		return esc_html( $text );
	}

	function esc_url( $url ): string {
		return (string) $url;
	}

	function disabled( $disabled, $current = true, bool $display = true ): string {
		$result = $disabled == $current ? ' disabled="disabled"' : '';
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
	class Common {}

	require dirname( __DIR__ ) . '/plugin-dir/src/GeneralConfiguration.php';
}

namespace {
	use iTRON\PollyTTS\Common;
	use iTRON\PollyTTS\GeneralConfiguration;

	function check( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}

		++$GLOBALS['checks'];
	}

	$regions = array(
		'us-east-1',
		'us-east-2',
		'us-west-1',
		'us-west-2',
		'af-south-1',
		'ap-east-1',
		'ap-southeast-5',
		'ap-south-1',
		'ap-northeast-3',
		'ap-northeast-2',
		'ap-southeast-1',
		'ap-southeast-2',
		'ap-southeast-7',
		'ap-northeast-1',
		'ca-central-1',
		'eu-central-1',
		'eu-west-1',
		'eu-west-2',
		'eu-west-3',
		'eu-south-2',
		'eu-north-1',
		'eu-central-2',
		'me-south-1',
		'sa-east-1',
		'us-gov-west-1',
	);

	$configuration = new GeneralConfiguration( new Common() );
	ob_start();
	$configuration->general_gui();
	$general_description = (string) ob_get_clean();
	check( str_contains( $general_description, 'class="description"' ), 'General settings must include a description.' );
	check( str_contains( $general_description, 'href="https://github.com/hokoo/aws-polly/blob/master/plugin-dir/readme.txt"' ), 'General settings must link to the plugin setup guide.' );
	check( str_contains( $general_description, 'target="_blank" rel="noopener noreferrer"' ), 'The plugin-page link must open safely in a new tab.' );
	check( str_contains( $general_description, 'AWS credentials guide.' ), 'The plugin-page link must explain its purpose.' );
	foreach ( $regions as $region ) {
		check( $region === $configuration->sanitize_region( $region ), $region . ' must pass region validation.' );
	}
	check( array() === $GLOBALS['settings_errors'], 'Supported regions must not add settings errors.' );

	$GLOBALS['options']['itron_polly_tts_s3_region'] = 'ap-southeast-7';
	ob_start();
	$configuration->region_gui();
	$selector = (string) ob_get_clean();
	foreach ( $regions as $region ) {
		check( str_contains( $selector, 'value="' . $region . '"' ), $region . ' must be available in the region selector.' );
	}
	check( count( $regions ) === substr_count( $selector, '<option ' ), 'The selector must contain exactly the documented Polly regions.' );
	check( str_contains( $selector, 'value="ap-southeast-7" selected="selected"' ), 'The configured region must remain selected.' );

	$sanitized_region = $configuration->sanitize_region( 'invalid-region-1' );
	$GLOBALS['options']['itron_polly_tts_s3_region'] = $sanitized_region;
	check( 'ap-southeast-7' === $sanitized_region, 'Invalid input must preserve the previously saved valid region.' );
	check( 'ap-southeast-7' === GeneralConfiguration::get_aws_region(), 'Invalid input must not change the processing endpoint.' );
	check( 1 === count( $GLOBALS['settings_errors'] ), 'Invalid input must add one Settings API error.' );
	check( 'itron_polly_tts_invalid_s3_region' === $GLOBALS['settings_errors'][0][1], 'The Settings API error must have a stable code.' );
	check( str_contains( $GLOBALS['settings_errors'][0][2], 'current valid region remains in use' ), 'The Settings API error must explain that the valid region was preserved.' );

	set_error_handler(
		static function ( int $severity, string $message ): bool {
			throw new ErrorException( $message, 0, $severity );
		}
	);
	$malformed_result = $configuration->sanitize_region( array( 'us-east-1' ) );
	restore_error_handler();
	check( 'ap-southeast-7' === $malformed_result, 'Malformed array input must preserve the saved valid region without warnings.' );

	$GLOBALS['options']['itron_polly_tts_s3_region'] = 'ap-future-1';
	check( 'ap-future-1' === GeneralConfiguration::get_aws_region(), 'A nonempty explicitly configured region must pass through at read time.' );
	$GLOBALS['options']['itron_polly_tts_s3_region'] = '';
	check( 'us-east-1' === GeneralConfiguration::get_aws_region(), 'An unconfigured region must use the valid us-east-1 default.' );
	unset( $GLOBALS['options']['itron_polly_tts_s3_region'] );
	check( 'us-east-1' === $configuration->sanitize_region( 'not-supported' ), 'Invalid input without a saved valid region must use the default.' );
	define( 'ITRON_POLLY_TTS_S3_REGION', 'ap-constant-future-1' );
	check( 'ap-constant-future-1' === GeneralConfiguration::get_aws_region(), 'A nonempty region constant must pass through at read time.' );

	echo "Region configuration: {$GLOBALS['checks']} checks passed.\n";
}
