<?php
/**
 * Plugin exceptions
 *
 * @since      0.1
 *
 */

class CredsException extends Exception { }
class S3BucketNotAccException extends Exception { }
class S3BucketNotCreException extends Exception { }
class PollyException extends Exception { }
class IdenticalAudio extends Exception {
	public function __construct( $message = 'Identical audio already exists' ) {
		parent::__construct( $message );
	}
}
class CronRechedulerException extends Exception {}
class ConcurrentAudioGenerating extends Exception {}
