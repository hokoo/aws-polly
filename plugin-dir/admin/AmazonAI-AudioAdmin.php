<?php
/**
 * Admin enhancements for Polly audio: columns, meta box, filter, bulk actions, settings button.
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin
 */

class AmazonAI_AudioAdmin {

	/**
	 * @var AmazonAI_Common
	 */
	private $common;

	public function __construct( AmazonAI_Common $common ) {
		$this->common = $common;
	}

	/**
	 * Get post types supported by Polly.
	 */
	private function get_post_types(): array {
		return $this->common->get_posttypes_array();
	}

	// =========================================================================
	// Columns: Audio & Voice
	// =========================================================================

	public function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['polly_audio'] = 'Audio';
				$new['polly_voice'] = 'Voice';
			}
		}
		return $new;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( 'polly_audio' === $column ) {
			$link = get_post_meta( $post_id, 'amazon_polly_audio_link_location', true );
			if ( ! empty( $link ) ) {
				echo '<span style="color:#46b450;" title="' . esc_attr( $link ) . '">&#9835; Yes</span>';
			} else {
				echo '<span style="color:#a00;">&#10005; No</span>';
			}
		}

		if ( 'polly_voice' === $column ) {
			$voice = get_post_meta( $post_id, 'amazon_polly_voice_id', true );
			$link  = get_post_meta( $post_id, 'amazon_polly_audio_link_location', true );
			if ( ! empty( $voice ) && ! empty( $link ) ) {
				echo esc_html( $voice );
			} else {
				echo '&mdash;';
			}
		}
	}

	public function sortable_columns( array $columns ): array {
		$columns['polly_audio'] = 'polly_audio';
		$columns['polly_voice'] = 'polly_voice';
		return $columns;
	}

	public function handle_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'polly_audio' === $orderby ) {
			$query->set( 'meta_key', 'amazon_polly_audio_link_location' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'polly_voice' === $orderby ) {
			$query->set( 'meta_key', 'amazon_polly_voice_id' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	public function column_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		echo '<style>
			.column-polly_audio { width: 70px; text-align: center; }
			.column-polly_voice { width: 100px; }
		</style>';
	}

	// =========================================================================
	// Meta box on post edit screen
	// =========================================================================

	public function add_audio_meta_box(): void {
		foreach ( $this->get_post_types() as $post_type ) {
			add_meta_box(
				'polly_audio_status_box',
				'Polly Audio Status',
				[ $this, 'render_audio_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render_audio_meta_box( \WP_Post $post ): void {
		$link     = get_post_meta( $post->ID, 'amazon_polly_audio_link_location', true );
		$voice    = get_post_meta( $post->ID, 'amazon_polly_voice_id', true );
		$playtime = get_post_meta( $post->ID, 'amazon_polly_audio_playtime', true );

		if ( ! empty( $link ) ) {
			echo '<p style="color:#46b450;font-weight:bold;">&#9835; Audio available</p>';
			if ( ! empty( $voice ) ) {
				echo '<p><strong>Voice:</strong> ' . esc_html( $voice ) . '</p>';
			}
			if ( ! empty( $playtime ) ) {
				echo '<p><strong>Duration:</strong> ' . esc_html( $playtime ) . '</p>';
			}
			echo '<p><a href="' . esc_url( $link ) . '" target="_blank" class="button button-small">Open audio file &rarr;</a></p>';
		} else {
			echo '<p style="color:#a00;font-weight:bold;">&#10005; Audio not available</p>';
			$enabled = get_post_meta( $post->ID, 'amazon_polly_enable', true );
			if ( '1' === $enabled ) {
				$bg_task = new AmazonAI_BackgroundTask();
				if ( $bg_task->has_queued_audio( $post->ID ) ) {
					echo '<p><em>Audio generation is queued&hellip;</em></p>';
				} else {
					echo '<p><em>Polly is enabled but audio has not been generated yet.</em></p>';
				}
			} else {
				echo '<p><em>Enable Amazon Polly for this post to generate audio.</em></p>';
			}
		}
	}

	// =========================================================================
	// Filter: Posts without audio
	// =========================================================================

	public function render_filter_dropdown(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_post_types(), true ) ) {
			return;
		}

		$selected = isset( $_GET['polly_audio_filter'] ) ? sanitize_text_field( $_GET['polly_audio_filter'] ) : '';

		echo '<select name="polly_audio_filter">';
		echo '<option value="">All (audio)</option>';
		echo '<option value="no_audio"' . selected( $selected, 'no_audio', false ) . '>Without audio</option>';
		echo '<option value="has_audio"' . selected( $selected, 'has_audio', false ) . '>With audio</option>';
		echo '</select>';
	}

	public function handle_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( empty( $_GET['polly_audio_filter'] ) ) {
			return;
		}

		$filter = sanitize_text_field( $_GET['polly_audio_filter'] );

		$meta_query = $query->get( 'meta_query' ) ?: [];

		if ( 'no_audio' === $filter ) {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => 'amazon_polly_audio_link_location',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'amazon_polly_audio_link_location',
					'value'   => '',
					'compare' => '=',
				],
			];
		} elseif ( 'has_audio' === $filter ) {
			$meta_query[] = [
				'key'     => 'amazon_polly_audio_link_location',
				'value'   => '',
				'compare' => '!=',
			];
		}

		$query->set( 'meta_query', $meta_query );
	}

	// =========================================================================
	// Bulk action: Generate audio
	// =========================================================================

	public function add_bulk_actions( array $actions ): array {
		$actions['polly_generate_audio'] = 'Generate Audio (Polly)';
		return $actions;
	}

	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'polly_generate_audio' !== $action ) {
			return $redirect_to;
		}

		$queued = 0;
		foreach ( $post_ids as $post_id ) {
			$is_enabled = get_post_meta( $post_id, 'amazon_polly_enable', true );
			if ( '1' !== $is_enabled ) {
				update_post_meta( $post_id, 'amazon_polly_enable', 1 );
			}

			$voice = get_post_meta( $post_id, 'amazon_polly_voice_id', true );
			if ( empty( $voice ) ) {
				$global_voice = get_option( 'amazon_polly_voice_id', '' );
				if ( ! empty( $global_voice ) ) {
					update_post_meta( $post_id, 'amazon_polly_voice_id', $global_voice );
				}
			}

			$bg_task = new AmazonAI_BackgroundTask();
			$bg_task->queue_audio( (int) $post_id );
			$queued++;
		}

		return add_query_arg( 'polly_queued', $queued, $redirect_to );
	}

	public function bulk_action_notice(): void {
		if ( empty( $_GET['polly_queued'] ) ) {
			return;
		}

		$count = (int) $_GET['polly_queued'];
		printf(
			'<div class="notice notice-success is-dismissible"><p>Audio generation queued for %d post(s). It will be processed in the background via WP-Cron.</p></div>',
			$count
		);
	}

	// =========================================================================
	// Button on TTS settings page: "Find posts without audio"
	// =========================================================================

	public function render_settings_button(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'aws_page_amazon_ai_polly' !== $screen->id ) {
			return;
		}

		$post_types = $this->get_post_types();
		$post_type  = ! empty( $post_types[0] ) ? $post_types[0] : 'post';
		$url        = admin_url( 'edit.php?post_type=' . $post_type . '&polly_audio_filter=no_audio' );

		?>
		<script>
		(function() {
			var form = document.querySelector('.wrap form');
			if (!form) return;

			var div = document.createElement('div');
			div.style.marginTop = '20px';
			div.style.padding = '15px';
			div.style.background = '#fff';
			div.style.border = '1px solid #ccd0d4';
			div.style.borderLeft = '4px solid #0073aa';

			div.innerHTML = '<h3 style="margin-top:0;">Posts without audio</h3>'
				+ '<p>Find and select posts that do not have generated audio, then use bulk actions to generate it.</p>'
				+ '<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">Find posts without audio &rarr;</a>';

			form.parentNode.insertBefore(div, form.nextSibling);
		})();
		</script>
		<?php
	}
}
