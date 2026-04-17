<?php

use iTRON\AWS\Polly\Factory;
use Psr\Log\LogLevel;

/**
 * Logger for AWS AI plugin.
 *
 * @since      0.1
 *
 */
class AmazonAI_Logger {
	public function log( $log ) {
		Factory::getLogger()->log( LogLevel::DEBUG, $log );
	}
}
