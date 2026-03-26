<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       amazon.com
 * @since      1.0.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/public
 */

use iTRON\WP_Lock\WP_Lock;

class Amazonpolly_Public {

	private $plugin_name;
	private $version;
	private $common;

	public function __construct( $plugin_name, $version, AmazonAI_Common $common ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->common      = $common;
	}

	/**
	 * WordPress filter, responsible for adding audio player to post content.
	 *
	 * @since    1.0.0
	 */
	public function content_filter( $content ) {

		if (!isset($GLOBALS) || !array_key_exists('post', $GLOBALS)) {
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

		$audio_location = get_post_meta( $post_id, 'amazon_polly_audio_link_location', true );

		// Check if audio file exists.
		$result = wp_remote_head( $audio_location, ['sslverify' => false] );
		if ( 200 !== wp_remote_retrieve_response_code( $result ) ) {
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
				$image = __('<img src="https://d12ee1u74lotna.cloudfront.net/images/Voiced_by_Amazon_Polly_EN.png" width="100" alt="Voiced by Amazon Polly" >', $this->plugin_name);
				$image = apply_filters('amazon_polly_voiced_by_html', $image, get_locale());
				$voice_by_part = '<a href="https://aws.amazon.com/polly/" target="_blank" rel="noopener noreferrer">' . $image . '</a>';
			}
		}

		// Removing Amazon Polly special tags.
		$content = preg_replace( '/-AMAZONPOLLY-ONLYAUDIO-START-[\S\s]*?-AMAZONPOLLY-ONLYAUDIO-END-/', '', $content );
		$content = str_replace( '-AMAZONPOLLY-ONLYWORDS-START-', '', $content );
		$content = str_replace( '-AMAZONPOLLY-ONLYWORDS-END-', '', $content );

		// Create player area.
		$polly_content = '';
		if ( is_singular() ) {
			$audio_part = $this->include_audio_player( $audio_location, $autoplay );

			$polly_content = '
			<table id="amazon-polly-audio-table">
				<tr>
				<td id="amazon-polly-audio-tab">
					<div id="amazon-ai-player-label">' . $player_label . '</div>
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

	private function include_coming_soon() {
		$template = '<div id="amazon-polly-coming-soon">%s</div>';
		$coming_soon_text = wpautop( get_option( 'amazon_polly_coming_soon_text' ) );
		return sprintf( $template, $coming_soon_text );
	}

	private function include_audio_player( $audio_location, $autoplay ) {
		$common = $this->common;
		$controlsList = '';
		if ( !$common->is_audio_download_enabled() ) {
			$controlsList = ' controlsList="nodownload" ';
		}

		return '<div id="amazon-ai-player-container">
			<audio class="amazon-ai-player" id="amazon-ai-player" preload="none" controls ' . $autoplay . ' ' . $controlsList . '>
				<source type="audio/mpeg" src="' . esc_url( $audio_location ) . '">
			</audio>
		</div>';
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/amazonpolly-public.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/amazonpolly-public.js', array( 'jquery' ), $this->version, false );
	}
}
