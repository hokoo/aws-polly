<?php

namespace iTRON\AWS\Polly;

use iTRON\AWS\Polly\Integrations\StreamConnector;
use iTRON\AWS\Polly\Loggers\Stream;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionException;

class Factory {
	private static LoggerInterface $logger;

	/**
	 * @throws ReflectionException
	 */
	public static function create( $class ) {
		$reflection = new ReflectionClass( $class );
		$constructor = $reflection->getConstructor();
		$parameters = $constructor->getParameters();
		$dependencies = [];
		foreach ( $parameters as $parameter ) {
			$dependencies[] = self::create( $parameter->getClass()->getName() );
		}
		return $reflection->newInstanceArgs( $dependencies );
	}

	public static function getLogger(): LoggerInterface {
		if ( isset( self::$logger ) ) {
			return self::$logger;
		}

		self::$logger = apply_filters( 'itron/aws-polly/get-logger', new NullLogger() );
		if ( ! is_a( self::$logger, LoggerInterface::class ) || self::$logger instanceof NullLogger ) {
			if ( class_exists( 'WP_Stream\Connector' ) ) {
				self::$logger = new Stream();

				// This filter fires at the init hook with priority = 9.
				add_filter( 'wp_stream_connectors', fn( $connectors ) => array_merge( $connectors, [ new StreamConnector() ] ) );
			}
		}

		return self::$logger;
	}
}
