<?php

namespace iTRON\PollyTTS;

use iTRON\PollyTTS\Factory;
use Psr\Log\LogLevel;

/**
 * Logger for AWS AI plugin.
 *
 * @since      0.1
 *
 */
class Logger {
	private static bool $checking_enabled = false;

	public static function is_enabled(): bool {
		if ( empty( get_option( 'itron_polly_tts_logging', false ) ) || self::$checking_enabled ) {
			return false;
		}

		self::$checking_enabled = true;

		try {
			return (bool) apply_filters( 'itron_polly_tts_logging_enabled', true );
		} finally {
			self::$checking_enabled = false;
		}
	}

	public function log( $log ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		Factory::getLogger()->log( LogLevel::DEBUG, $log );
	}
}
