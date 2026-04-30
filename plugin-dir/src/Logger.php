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
	public function log( $log ) {
		Factory::getLogger()->log( LogLevel::DEBUG, $log );
	}
}
