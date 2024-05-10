<?php

use iTRON\AWS\Polly\Factory;
use Psr\Log\LogLevel;

/**
 * Logger for AWS AI plugin.
 *
 * @link       amazon.com
 * @since      2.6.2
 *
 */
class AmazonAI_Logger {
	public function log( $log ) {
		Factory::getLogger()->log( LogLevel::DEBUG, $log );
	}
}
