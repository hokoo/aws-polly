<?php
/**
 * Class for running an action in the background.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AmazonAI_BackgroundTask {


	const ADMIN_POST_ACTION  = 'amazon_polly_run_background_task';
	const CRON_HOOK          = 'itron_aws_polly';
	const CRON_HANDLERS_HOOK = 'itron_aws_polly_cron_';

	/**
	 * Trigger an action in the background
	 *
	 * Triggers a background action by making an HTTP call to the local server and not waiting for a response.
	 * Similar to how WP triggers cron events in wp-includes/cron.php.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/admin_post_action/ Fires on an authenticated admin post request for the given action
	 * @see https://developer.wordpress.org/reference/hooks/https_local_ssl_verify/ Filters whether SSL should be verified for local requests
	 * @see https://developer.wordpress.org/reference/classes/WP_Http/request/ Documents args used by `wp_remote_post(...)`
	 * @see https://developer.wordpress.org/reference/functions/apply_filters/ To filter `https_local_ssl_verify`
	 * @see https://developer.wordpress.org/reference/functions/admin_url/ Generate an admin URL
	 * @see https://developer.wordpress.org/reference/functions/wp_create_nonce/ Create a cryptographic token
	 * @see https://developer.wordpress.org/reference/functions/wp_remote_post/ Used to make an HTTP call to this site
	 *
	 * @param string $task Task to be called in the background
	 *
	 * @return bool True if http request to trigger background task is successful, false otherwise
	 */
	public function trigger( $task, $args = array()) {
		$url = admin_url( 'admin-post.php' );

		$request_args = array(
			'timeout'  => 0.01,
			'blocking' => false,
			/** This filter is documented in WordPress Core wp-includes/class-wp-http-streams.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter.
		'sslverify'    => apply_filters( 'https_local_ssl_verify', false ),
			'body'     => array(
				'nonce'  => wp_create_nonce( $this->nonce_action_for_task( $task ) ),
				'action' => self::ADMIN_POST_ACTION,
				'task'   => $task,
				'args'   => wp_json_encode( $args ),
			),
			'headers'  => array(
				'cookie' => implode( '; ', $this->get_cookies() ),
			),
		);

		$logger = new AmazonAI_Logger();
		$logger->log( sprintf( '%s Triggering background task %s', __METHOD__, $task ) );

		return wp_remote_post( $url, $request_args );
	}

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

	/**
	 * Run task as a WP action
	 *
	 * @see https://developer.wordpress.org/reference/functions/__/ Localize string
	 * @see https://developer.wordpress.org/reference/functions/do_action_ref_array/ Run action
	 * @see https://developer.wordpress.org/reference/functions/wp_die/ Kill request and display message
	 * @see https://developer.wordpress.org/reference/functions/wp_verify_nonce/ Verify cryptographic token
	 */
	public function run() {
		$logger = new AmazonAI_Logger();
		$task   = array_key_exists( 'task', $_POST ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		$nonce  = array_key_exists( 'nonce', $_POST ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( empty( $task ) ) {
			$logger->log( sprintf( '%s Invalid background task. Missing task.', __METHOD__ ) );
			wp_die( esc_html__( 'Invalid background task.', 'ai-text-to-speech-using-aws-polly' ), esc_html__( 'Invalid Request', 'ai-text-to-speech-using-aws-polly' ), 400 );
		}

		if ( ! $nonce || 1 !== wp_verify_nonce( $nonce, $this->nonce_action_for_task( $task ) ) ) {
			$logger->log( sprintf( '%s Expired background task request for task %s', __METHOD__, $task ) );
			wp_die( esc_html__( 'Expired background task request.', 'ai-text-to-speech-using-aws-polly' ), esc_html__( 'Expired Request', 'ai-text-to-speech-using-aws-polly' ), 403 );
		}

		$args_json = array_key_exists( 'args', $_POST ) ? sanitize_textarea_field( wp_unslash( $_POST['args'] ) ) : '';
		$args      = '' === $args_json ? array() : json_decode( $args_json, true );
		if ( ! is_array( $args ) ) {
			$logger->log( sprintf( '%s Invalid background task args.', __METHOD__ ) );
			wp_die( esc_html__( 'Invalid background task args.', 'ai-text-to-speech-using-aws-polly' ), esc_html__( 'Invalid Request', 'ai-text-to-speech-using-aws-polly' ), 400 );
		}

		$logger->log( sprintf( '%s Running background task %s', __METHOD__, $task ) );

		/**
		 * Fires when running a background task
		 *
		 * The dynamic portion of the hook name, `$task`, refers to the task
		 * that being run.
		 */
	    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Internal dynamic hook with an explicitly prefixed base name.
		do_action_ref_array( sprintf( 'itron_aws_polly_background_task_%s', $task ), $args );
	}

	/**
	 * Return current user's cookies to authenticate a background request as the current user
	 *
	 * @return array Sanitized cookies
	 */
	private function get_cookies() {
		$cookies = array();

		foreach ( $_COOKIE as $name => $value ) {
			$sanitized_value = is_array( $value ) ? serialize( $value ) : $value;
			$sanitized_value = urlencode( $sanitized_value );

			$cookies[] = sprintf( '%s=%s', $name, $sanitized_value );
		}

		return $cookies;
	}

	/**
	 * Generate nonce action name for task
	 *
	 * @param $task
	 *
	 * @return string
	 */
	private function nonce_action_for_task( $task) {
		return sprintf( '%s:%s', self::ADMIN_POST_ACTION, $task );
	}
}
