<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      0.1
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\WP_Lock\WP_Lock;

class Amazonpolly_Public {

	private $plugin_name;
	private $version;
	private $common;
	private $object_cache;
	private $styles_enqueued = false;

	public function __construct( $plugin_name, $version, AmazonAI_Common $common, Amazonpolly_Object_Cache $object_cache ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->common       = $common;
		$this->object_cache = $object_cache;
	}

	/**
	 * WordPress filter, responsible for adding audio player to post content.
	 *
	 * @since      0.1
	 */
	public function content_filter( $content ) {

		if ( ! isset( $GLOBALS ) || ! array_key_exists( 'post', $GLOBALS )) {
			return $content;
		}

		$post_id = isset( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : null;
		if ( ! $post_id ) {
			return $content;
		}

		$common = $this->common;

		if ( ! $common->is_polly_enabled() ) {
			return $content;
		}

		if ( get_post_meta( $post_id, 'amazon_polly_enable', true ) !== '1' ) {
			return $content;
		}

		$audio_location = (string) get_post_meta( $post_id, 'amazon_polly_audio_link_location', true );

		if ( ! $this->has_available_audio( (int) $post_id, $audio_location ) ) {
			$lock            = new WP_Lock( AmazonAI_PollyService::LOCK_PREFIX . $post_id );
			$background_task = new AmazonAI_BackgroundTask();

			if ( $lock->lock_exists() || $background_task->has_queued_audio( $post_id ) ) {
				return $this->include_coming_soon() . $content;
			}

			return $content;
		}

		$selected_autoplay = get_option( 'amazon_polly_autoplay' );
		$player_label      = get_option( 'amazon_polly_player_label' );

		if ( is_singular() && ! empty( $selected_autoplay ) ) {
			$autoplay = 'autoplay';
		} else {
			$autoplay = '';
		}

		// Prepare "Powered By" label.
		$voice_by_part = '';
		if ( $common->is_poweredby_enabled() ) {
			if ( is_singular() ) {
				$credit        = sprintf(
					'<span class="itron-aws-polly-credit-text">%s</span>',
					esc_html__( 'Text-to-speech via AWS Polly', 'ai-text-to-speech-using-aws-polly' )
				);
				$voice_by_part = wp_kses_post( apply_filters( 'amazon_polly_voiced_by_html', $credit, get_locale() ) );
			}
		}

		// Removing Amazon Polly special tags.
		$content = preg_replace( '/-AMAZONPOLLY-ONLYAUDIO-START-[\S\s]*?-AMAZONPOLLY-ONLYAUDIO-END-/', '', $content );
		$content = str_replace( '-AMAZONPOLLY-ONLYWORDS-START-', '', $content );
		$content = str_replace( '-AMAZONPOLLY-ONLYWORDS-END-', '', $content );

		// Create player area.
		$polly_content = '';
		if ( is_singular() ) {
			$this->enqueue_styles();
			$audio_part = $this->include_audio_player( $audio_location, $autoplay );

			$polly_content = '
			<table id="amazon-polly-audio-table">
				<tr>
				<td id="amazon-polly-audio-tab">
					<div id="amazon-ai-player-label">' . esc_html( (string) $player_label ) . '</div>
					' . $audio_part . '
					<div id="amazon-polly-by-tab">' . $voice_by_part . '</div>
				</td>
				</tr>
			</table>';
		}

		// Put plugin content in the correct position.
		$selected_position = get_option( 'amazon_polly_position' );
		if ( strcmp( $selected_position, 'Do not show' ) === 0 ) {
			// no change
		} elseif ( strcmp( $selected_position, 'After post' ) === 0 ) {
			$content = $content . $polly_content;
		} else {
			$content = $polly_content . $content;
		}

		return $content;
	}

	private function has_available_audio( int $post_id, string $audio_location ): bool {
		if ( '' === $audio_location ) {
			$this->object_cache->delete_audio_head_status( $post_id );
			return false;
		}

		$cached_status = $this->object_cache->get_audio_head_status( $post_id );
		if ( is_array( $cached_status ) && $this->object_cache->is_audio_head_status_current( $cached_status, $audio_location ) ) {
			return (bool) $cached_status['exists'];
		}

		$result = wp_remote_head( $audio_location, array( 'sslverify' => false ) );
		$exists = 200 === wp_remote_retrieve_response_code( $result );

		$this->object_cache->set_audio_head_status( $post_id, $audio_location, $exists );

		return $exists;
	}

	private function include_coming_soon() {
		$template         = '<div id="amazon-polly-coming-soon">%s</div>';
		$coming_soon_text = wpautop( esc_html( (string) get_option( 'amazon_polly_coming_soon_text' ) ) );
		return sprintf( $template, $coming_soon_text );
	}

	private function include_audio_player( $audio_location, $autoplay ) {
		$common       = $this->common;
		$controlsList = '';
		if ( ! $common->is_audio_download_enabled() ) {
			$controlsList = ' controlsList="nodownload" ';
		}

		return '<div id="amazon-ai-player-container">
			<audio class="amazon-ai-player" id="amazon-ai-player" preload="none" controls ' . $autoplay . ' ' . $controlsList . '>
				<source type="audio/mpeg" src="' . esc_url( $audio_location ) . '">
			</audio>
		</div>';
	}

	public function enqueue_styles() {
		if ( $this->styles_enqueued ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/amazonpolly-public.css', array(), $this->version, 'all' );
		$this->styles_enqueued = true;
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/amazonpolly-public.js', array( 'jquery' ), $this->version, true );
	}
}
