<?php

namespace iTRON\PollyTTS;

class CronHandler {
	private PollyService $polly_service;
	private Logger $logger;

	public function __construct( PollyService $polly_service, Logger $logger ) {
		$this->polly_service = $polly_service;
		$this->logger        = $logger;
	}

	/**
	 * @throws CronRescheduleException
	 */
	public function generate_audio( $post_id ) {
		try {
			$this->polly_service->generate_audio( $post_id );
		} catch ( ConcurrentAudioGenerationException $e ) {
			throw new CronRescheduleException();
		}
	}
}
