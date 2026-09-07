<?php

namespace Psr\Log {
	interface LoggerInterface {
		public function log( $level, $message, array $context = array() ): void;
	}

	abstract class AbstractLogger implements LoggerInterface {
		abstract public function log( $level, $message, array $context = array() ): void;
	}

	class LogLevel {
		const DEBUG = 'debug';
	}
}

namespace iTRON\PollyTTS {
	class Common {
		public function __construct() {
			++$GLOBALS['common_constructions'];
		}
	}
}

namespace {
	use iTRON\PollyTTS\Factory;
	use iTRON\PollyTTS\Logger;
	use iTRON\PollyTTS\Loggers\Stream;
	use Psr\Log\LoggerInterface;

	define( 'ABSPATH', __DIR__ );

	$GLOBALS['wp_options']           = array();
	$GLOBALS['wp_filters']           = array();
	$GLOBALS['wp_actions']           = array();
	$GLOBALS['wp_action_counts']     = array();
	$GLOBALS['stream_records']       = array();
	$GLOBALS['common_constructions'] = 0;

	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $name ] : $default;
	}

	function add_filter( $hook, $callback ): void {
		$GLOBALS['wp_filters'][ $hook ][] = $callback;
	}

	function apply_filters( $hook, $value ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value );
		}

		return $value;
	}

	function add_action( $hook, $callback ): void {
		$GLOBALS['wp_actions'][ $hook ][] = $callback;
	}

	function did_action( $hook ): int {
		return $GLOBALS['wp_action_counts'][ $hook ] ?? 0;
	}

	function do_action( $hook, ...$args ): void {
		$GLOBALS['wp_action_counts'][ $hook ] = did_action( $hook ) + 1;

		if ( 'itron_polly_tts_stream_logger_write' === $hook ) {
			$GLOBALS['stream_records'][] = $args;
		}

		foreach ( $GLOBALS['wp_actions'][ $hook ] ?? array() as $callback ) {
			$callback( ...$args );
		}
	}

	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}

	function assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			fwrite(
				STDERR,
				sprintf(
					"FAIL: %s\nExpected: %s\nActual: %s\n",
					$message,
					var_export( $expected, true ),
					var_export( $actual, true )
				)
			);
			exit( 1 );
		}
	}

	class CapturingLogger implements LoggerInterface {
		public array $records = array();

		public function log( $level, $message, array $context = array() ): void {
			$this->records[] = array( $level, $message, $context );
		}
	}

	require_once __DIR__ . '/../plugin-dir/src/Logger.php';
	require_once __DIR__ . '/../plugin-dir/src/Loggers/Stream.php';
	require_once __DIR__ . '/../plugin-dir/src/Factory.php';

	$absent_stream = new Stream();
	$absent_stream->log( 'debug', 'must not queue when the option is absent' );
	assert_same( array(), $GLOBALS['wp_actions'], 'An absent option must prevent Stream from queueing deferred output.' );

	$custom_logger         = new CapturingLogger();
	$factory_filter_calls  = 0;
	$logging_filter_result = true;

	add_filter(
		'itron_polly_tts_get_logger',
		function ( $default_logger ) use ( $custom_logger, &$factory_filter_calls ) {
			++$factory_filter_calls;
			return $custom_logger;
		}
	);
	add_filter(
		'itron_polly_tts_logging_enabled',
		function ( $enabled ) use ( &$logging_filter_result ) {
			return $enabled && $logging_filter_result;
		}
	);

	$logger = new Logger();
	$logger->log( 'must not be emitted when the option is absent' );
	assert_same( 0, $factory_filter_calls, 'An absent option must stop logging before resolving a custom Factory logger.' );
	assert_same( array(), $custom_logger->records, 'An absent option must emit no custom logger records.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = '';
	$logger->log( 'must not be emitted when explicitly disabled' );
	assert_same( 0, $factory_filter_calls, 'An explicit OFF must not be re-enabled by the logging filter.' );
	assert_same( array(), $custom_logger->records, 'An explicit OFF must emit no custom logger records.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = 'on';
	$logger->log( 'Audio generation started for post 42.' );
	assert_same( 1, $factory_filter_calls, 'An enabled log must resolve the configured Factory logger.' );
	assert_same( 'Audio generation started for post 42.', $custom_logger->records[0][1], 'An enabled safe message must reach the custom logger.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = '';
	$logger->log( 'raw post text or exception details' );
	assert_same( 1, count( $custom_logger->records ), 'Turning logging OFF must also suppress an already-resolved custom logger.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = 'on';
	$logging_filter_result                             = false;
	$logger->log( 'disabled by filter' );
	assert_same( 1, count( $custom_logger->records ), 'The logging filter must be able to disable an enabled option.' );

	$logging_filter_result = true;
	add_filter(
		'itron_polly_tts_logging_enabled',
		function ( $enabled ) use ( $logger ) {
			$logger->log( 'nested filter log' );
			return $enabled;
		}
	);
	$logger->log( 'outer operational message' );
	assert_same( 2, count( $custom_logger->records ), 'A logging-enabled filter that logs must not recurse or emit its nested message.' );
	assert_same( 'outer operational message', $custom_logger->records[1][1], 'The outer message must survive filter re-entry protection.' );
	assert_same( 0, $GLOBALS['common_constructions'], 'The logging gate must not construct Common.' );

	$GLOBALS['wp_filters']['itron_polly_tts_logging_enabled'] = array(
		function ( $enabled ) {
			return $enabled;
		},
	);
	$stream = new Stream();
	$stream->log( 'debug', 'queued operational message' );
	assert_same( array(), $GLOBALS['stream_records'], 'Stream must defer emission until connectors are registered.' );
	assert_same( 1, count( $GLOBALS['wp_actions']['wp_stream_after_connectors_registration'] ?? array() ), 'Enabled Stream logging must queue one deferred callback.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = '';
	do_action( 'wp_stream_after_connectors_registration' );
	assert_same( array(), $GLOBALS['stream_records'], 'A deferred Stream callback must recheck OFF before emission.' );

	$stream->log( 'debug', 'must not emit while off' );
	assert_same( array(), $GLOBALS['stream_records'], 'Stream must emit nothing when called while OFF.' );

	$GLOBALS['wp_options']['itron_polly_tts_logging'] = 'on';
	$stream->log( 'debug', 'Audio generation completed for post 42.', array(), 'generation' );
	assert_same( 1, count( $GLOBALS['stream_records'] ), 'Enabled Stream logging must emit after connector registration.' );
	assert_same( 'Audio generation completed for post 42.', $GLOBALS['stream_records'][0][1], 'Stream must preserve an enabled safe message.' );
	assert_same( 'generation', $GLOBALS['stream_records'][0][3], 'Stream must preserve the message module.' );

	echo "PASS: logging regression checks\n";
}
