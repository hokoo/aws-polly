<?php
/**
 * Plugin exceptions
 *
 * @link       amazon.com
 * @since      2.5.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin
 */

class CredsException extends Exception { }
class S3BucketNotAccException extends Exception { }
class S3BucketNotCreException extends Exception { }
class PollyException extends Exception { }
class TranslateAccessException extends Exception { }
class IdenticalAudio extends Exception {
	public function __construct( $message = 'Identical audio already exists' ) {
		parent::__construct( $message );
	}
}
class CronRechedulerException extends Exception {}
class ConcurrentAudioGenerating extends Exception {}
