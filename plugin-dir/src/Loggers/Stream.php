<?php

namespace iTRON\AWS\Polly\Loggers;

use Psr\Log\AbstractLogger;

class Stream extends AbstractLogger {

	public function log( $level, $message, array $context = [], string $module = 'general' ) : void {
		$calling = function () use ( $level, $message, $context, $module ) {
			$meta = [];
			if ( ! empty( $context ) ) {
				$meta = array_map( function ( $value ) {
					return is_scalar( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
				}, $context );
			}
			do_action( 'aws_polly_stream_logger_write', $level, $message, $meta, $module );
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
