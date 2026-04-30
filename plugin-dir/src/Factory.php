<?php

namespace iTRON\PollyTTS;

use iTRON\PollyTTS\Loggers\Stream;
use Psr\Log\LoggerInterface;
class Factory {
	private static LoggerInterface $logger;

	public static function getLogger(): LoggerInterface {
		if ( isset( self::$logger ) ) {
			return self::$logger;
		}

		self::$logger = apply_filters( 'itron_polly_tts_get_logger', new Stream() );

		return self::$logger;
	}
}
