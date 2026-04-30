<?php

namespace iTRON\PollyTTS;

class IdenticalAudioException extends \Exception {
	public function __construct( $message = 'Identical audio already exists' ) {
		parent::__construct( $message );
	}
}
