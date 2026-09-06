<?php

namespace iTRON\PollyTTS\Loggers;

use iTRON\PollyTTS\Logger;
use Psr\Log\AbstractLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Stream extends AbstractLogger {

	public function log( $level, $message, array $context = array(), string $module = 'general' ) : void {
		if ( ! Logger::is_enabled() ) {
			return;
		}

		$calling = function () use ( $level, $message, $context, $module ) {
			if ( ! Logger::is_enabled() ) {
				return;
			}

			$meta = array();
			if ( ! empty( $context ) ) {
				$meta = array_map(
					function ( $value ) {
						return is_scalar( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
					},
					$context
				);
			}
			do_action( 'itron_polly_tts_stream_logger_write', $level, $message, $meta, $module );
		};

		if ( ! did_action( 'wp_stream_after_connectors_registration' ) ) {
			add_action(
				'wp_stream_after_connectors_registration',
				$calling
			);

			return;
		};

		$calling();
	}
}
