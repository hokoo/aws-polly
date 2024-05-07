<?php

class AmazonAI_CronHandler {
	private AmazonAI_PollyService $polly_service;
	private AmazonAI_Logger $logger;

	public function __construct( AmazonAI_PollyService $polly_service, AmazonAI_Logger $logger ) {
		$this->polly_service = $polly_service;
		$this->logger        = $logger;
	}

	/**
	 * @throws CronRechedulerException
	 */
	public function generate_audio( $post_id ) {
		try {
			$this->polly_service->generate_audio( $post_id );
		} catch ( ConcurrentAudioGenerating $e ) {
			throw new CronRechedulerException();
		}
	}
}
