<?php

namespace iTRON\AWS\Polly;

use iTRON\AWS\Polly\Loggers\Stream;
use Psr\Log\LoggerInterface;
class Factory {
	private static LoggerInterface $logger;

	public static function getLogger(): LoggerInterface {
		if ( isset( self::$logger ) ) {
			return self::$logger;
		}

		self::$logger = apply_filters( 'itron/aws-polly/get-logger', new Stream() );

		return self::$logger;
	}
}
