<?php
/**
 * Class for running an action in the background.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AmazonAI_BackgroundTask {


	const CRON_HOOK          = 'itron_aws_polly';
	const CRON_HANDLERS_HOOK = 'itron_aws_polly_cron_';

	public function queue( string $task, $args = array(), $unique = false ) {
		$cron_data = new AmazonAI_CronData( $task, $args, $unique );

		if ( $unique && wp_next_scheduled( self::CRON_HOOK, array( $cron_data ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + MINUTE_IN_SECONDS * 1, self::CRON_HOOK, array( $cron_data ) );
	}

	public function queue_audio( int $post_id ) {
		$this->queue( AmazonAI_PollyService::GENERATE_POST_AUDIO_TASK, array( $post_id ), true );

		$common = new AmazonAI_Common();
		if ( ! $common->has_post_audio( $post_id ) ) {
			$common->set_post_audio_state( $post_id, AmazonAI_Common::AUDIO_STATE_QUEUED );
		}
	}

	public function has_queued_audio( int $post_id ) {
		$cron_data = new AmazonAI_CronData( AmazonAI_PollyService::GENERATE_POST_AUDIO_TASK, array( $post_id ), true );

		return wp_next_scheduled( self::CRON_HOOK, array( $cron_data ) );
	}

	public function handle_cron( AmazonAI_CronData $cron_data ) {
		try {
			$logger = new AmazonAI_Logger();
			$logger->log( sprintf( '%s Running cron task %s', __METHOD__, $cron_data->task ) );

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Internal dynamic hook with an explicitly prefixed base name.
			do_action_ref_array( self::CRON_HANDLERS_HOOK . $cron_data->task, $cron_data->data );
		} catch ( CronRechedulerException $e ) {
			$this->queue( $cron_data->task, $cron_data->data, $cron_data->unique );
		}
	}
}
