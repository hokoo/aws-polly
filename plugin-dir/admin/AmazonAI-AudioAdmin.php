<?php
/**
 * Admin enhancements for Polly audio: columns, meta box, filter, bulk actions, settings button.
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\WP_Lock\WP_Lock;

class AmazonAI_AudioAdmin {
	private const AUDIO_SORT_META_ALIAS = 'amazon_polly_audio_sort_meta';
	private const VOICE_SORT_META_ALIAS = 'amazon_polly_voice_sort_meta';
	private const GENERATED_VOICE_SORT_META_ALIAS = 'amazon_polly_generated_voice_sort_meta';

	/**
	 * @var AmazonAI_Common
	 */
	private $common;
	private array $audio_status_cache = [];
	private array $display_voice_cache = [];

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
			$status = $this->get_audio_status( $post_id );

			if ( 'yes' === $status['status'] ) {
				echo '<span style="color:#46b450;" title="' . esc_attr( $status['title'] ) . '">&#9835; Yes</span>';
			} elseif ( 'generating' === $status['status'] ) {
				if ( 'running' === $status['phase'] ) {
					echo '<span style="color:#996800;" title="' . esc_attr( $status['title'] ) . '">&#8635; Running</span>';
				} else {
					echo '<span style="color:#996800;" title="' . esc_attr( $status['title'] ) . '">&#8226; Queued</span>';
				}
			} else {
				echo '<span style="color:#a00;" title="' . esc_attr( $status['title'] ) . '">&#10005; No</span>';
			}
		}

		if ( 'polly_voice' === $column ) {
			$voice = $this->get_display_voice( $post_id );
			if ( ! empty( $voice ) && 'yes' === $this->get_audio_status( $post_id )['status'] ) {
				echo esc_html( $voice );
			} else {
				echo '&mdash;';
			}
		}
	}

	public function sortable_columns( array $columns ): array {
		$columns['polly_audio'] = [ 'polly_audio', false ];
		$columns['polly_voice'] = [ 'polly_voice', false ];
		return $columns;
	}

	public function handle_sorting( \WP_Query $query ): void {
		if ( ! $this->is_supported_posts_query( $query ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'polly_audio' !== $orderby && 'polly_voice' !== $orderby ) {
			return;
		}

		$query->set( 'order', $this->normalize_sort_order( $query->get( 'order' ) ) );
	}

	public function apply_sorting_clauses( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		if ( ! $this->is_supported_posts_query( $query ) ) {
			return $clauses;
		}

		$orderby = $query->get( 'orderby' );
		$is_audio_sort = 'polly_audio' === $orderby;
		$is_voice_sort = 'polly_voice' === $orderby;

		if ( ! $is_audio_sort && ! $is_voice_sort ) {
			return $clauses;
		}

		$order = $this->normalize_sort_order( $query->get( 'order' ) );
		$clauses['distinct'] = 'DISTINCT';
		$clauses['join'] = $this->add_sort_meta_join(
			$clauses['join'],
			self::AUDIO_SORT_META_ALIAS,
			'amazon_polly_audio_link_location'
		);

		$audio_presence = sprintf(
			"CASE WHEN %s.meta_value IS NULL OR %s.meta_value = '' THEN 0 ELSE 1 END",
			self::AUDIO_SORT_META_ALIAS,
			self::AUDIO_SORT_META_ALIAS
		);

		if ( $is_audio_sort ) {
			$clauses['orderby'] = sprintf(
				'%s %s, %s.post_title ASC, %s.ID ASC',
				$audio_presence,
				$order,
				$wpdb->posts,
				$wpdb->posts
			);
			return $clauses;
		}

		$clauses['join'] = $this->add_sort_meta_join(
			$clauses['join'],
			self::VOICE_SORT_META_ALIAS,
			'amazon_polly_voice_id'
		);

		$clauses['join'] = $this->add_sort_meta_join(
			$clauses['join'],
			self::GENERATED_VOICE_SORT_META_ALIAS,
			'amazon_polly_generated_voice_id'
		);

		$voice_value = sprintf(
			'COALESCE(NULLIF(%1$s.meta_value, \'\'), NULLIF(%2$s.meta_value, \'\'))',
			self::GENERATED_VOICE_SORT_META_ALIAS,
			self::VOICE_SORT_META_ALIAS
		);

		$voice_missing = sprintf(
			"CASE WHEN %s IS NULL OR %s = '' THEN 1 ELSE 0 END",
			$voice_value,
			$voice_value
		);

		// Voice sorting always keeps posts with generated audio at the top.
		$clauses['orderby'] = sprintf(
			'%s DESC, %s ASC, %s %s, %s.post_title ASC, %s.ID ASC',
			$audio_presence,
			$voice_missing,
			$voice_value,
			$order,
			$wpdb->posts,
			$wpdb->posts
		);

		return $clauses;
	}

	public function column_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		echo '<style>
			.column-polly_audio { width: 120px; text-align: center; }
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
		$status   = $this->get_audio_status( $post->ID );
		$link     = $status['link'];
		$voice    = $this->get_display_voice( $post->ID );
		$playtime = get_post_meta( $post->ID, 'amazon_polly_audio_playtime', true );

		if ( 'yes' === $status['status'] ) {
			echo '<p style="color:#46b450;font-weight:bold;">&#9835; Audio available</p>';
			if ( ! empty( $voice ) ) {
				echo '<p><strong>Voice:</strong> ' . esc_html( $voice ) . '</p>';
			}
			if ( ! empty( $playtime ) ) {
				echo '<p><strong>Duration:</strong> ' . esc_html( $playtime ) . '</p>';
			}
			echo '<p><a href="' . esc_url( $link ) . '" target="_blank" class="button button-small">Open audio file &rarr;</a></p>';
		} else {
			if ( 'generating' === $status['status'] ) {
				if ( 'running' === $status['phase'] ) {
					echo '<p style="color:#996800;font-weight:bold;">&#8635; Audio running</p>';
					echo '<p><em>Audio generation is currently running.</em></p>';
				} else {
					echo '<p style="color:#996800;font-weight:bold;">&#8226; Audio queued</p>';
					echo '<p><em>Audio generation is queued and will start via WP-Cron.</em></p>';
				}
			} else {
				echo '<p style="color:#a00;font-weight:bold;">&#10005; Audio not available</p>';
				if ( 'enabled' === $status['phase'] ) {
					echo '<p><em>Polly is enabled but audio has not been generated yet.</em></p>';
				} else {
					echo '<p><em>Enable Amazon Polly for this post to generate audio.</em></p>';
				}
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

		$selected = isset( $_GET['polly_audio_filter'] ) ? sanitize_key( wp_unslash( $_GET['polly_audio_filter'] ) ) : '';

		echo '<select name="polly_audio_filter">';
		echo '<option value="">All (audio)</option>';
		echo '<option value="no_audio"' . selected( $selected, 'no_audio', false ) . '>Without audio</option>';
		echo '<option value="has_audio"' . selected( $selected, 'has_audio', false ) . '>With audio</option>';
		echo '</select>';
	}

	public function handle_filter( \WP_Query $query ): void {
		if ( ! $this->is_supported_posts_query( $query ) ) {
			return;
		}

		$filter = $this->get_audio_filter_value();
		if ( '' !== $filter ) {
			$this->common->backfill_legacy_audio_states();
			$query->set( 'amazon_polly_audio_filter', $filter );
			$query->set(
				'meta_query',
				$this->merge_meta_query_clause(
					$query->get( 'meta_query' ),
					$this->build_audio_filter_meta_query( $filter )
				)
			);
		}
	}

	private function is_supported_posts_query( \WP_Query $query ): bool {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) ) {
			return false;
		}

		if ( empty( $post_type ) ) {
			$post_type = isset( $_GET['post_type'] )
				? sanitize_key( wp_unslash( $_GET['post_type'] ) )
				: 'post';
		}

		return in_array( $post_type, $this->get_post_types(), true );
	}

	private function normalize_sort_order( $order ): string {
		return 'DESC' === strtoupper( (string) $order ) ? 'DESC' : 'ASC';
	}

	private function add_sort_meta_join( string $join, string $alias, string $meta_key ): string {
		global $wpdb;

		if ( false !== strpos( $join, " {$alias} " ) ) {
			return $join;
		}

		return $join . $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = %s)",
			$meta_key
		);
	}

	private function get_audio_status( int $post_id ): array {
		if ( isset( $this->audio_status_cache[ $post_id ] ) ) {
			return $this->audio_status_cache[ $post_id ];
		}

		$state = $this->common->get_post_audio_state( $post_id );
		$link = (string) get_post_meta( $post_id, 'amazon_polly_audio_link_location', true );
		$enabled = '1' === (string) get_post_meta( $post_id, 'amazon_polly_enable', true );

		if ( AmazonAI_Common::AUDIO_STATE_READY === $state && '' !== $link ) {
			return $this->audio_status_cache[ $post_id ] = [
				'status' => 'yes',
				'phase'  => 'ready',
				'link'   => $link,
				'title'  => $link,
			];
		}

		if ( AmazonAI_Common::AUDIO_STATE_RUNNING === $state ) {
			return $this->audio_status_cache[ $post_id ] = [
				'status' => 'generating',
				'phase'  => 'running',
				'link'   => '',
				'title'  => 'Audio generation is currently running.',
			];
		}

		if ( AmazonAI_Common::AUDIO_STATE_QUEUED === $state ) {
			return $this->audio_status_cache[ $post_id ] = [
				'status' => 'generating',
				'phase'  => 'queued',
				'link'   => '',
				'title'  => 'Audio generation is queued and will start via WP-Cron.',
			];
		}

		// Legacy fallback for posts that have not been transitioned to the canonical audio state yet.
		if ( '' === $state && '' !== $link ) {
			return $this->audio_status_cache[ $post_id ] = [
				'status' => 'yes',
				'phase'  => 'ready',
				'link'   => $link,
				'title'  => $link,
			];
		}

		if ( $enabled && ! in_array( $state, [ AmazonAI_Common::AUDIO_STATE_READY, AmazonAI_Common::AUDIO_STATE_RUNNING, AmazonAI_Common::AUDIO_STATE_QUEUED ], true ) ) {
			$lock = new WP_Lock( AmazonAI_PollyService::LOCK_PREFIX . $post_id );
			if ( $lock->lock_exists() ) {
				return $this->audio_status_cache[ $post_id ] = [
					'status' => 'generating',
					'phase'  => 'running',
					'link'   => '',
					'title'  => 'Audio generation is currently running.',
				];
			}

			if ( $this->get_background_task()->has_queued_audio( $post_id ) ) {
				return $this->audio_status_cache[ $post_id ] = [
					'status' => 'generating',
					'phase'  => 'queued',
					'link'   => '',
					'title'  => 'Audio generation is queued and will start via WP-Cron.',
				];
			}
		}

		if ( AmazonAI_Common::AUDIO_STATE_ERROR === $state ) {
			return $this->audio_status_cache[ $post_id ] = [
				'status' => 'no',
				'phase'  => $enabled ? 'enabled' : 'disabled',
				'link'   => '',
				'title'  => 'The last audio generation attempt failed.',
			];
		}

		return $this->audio_status_cache[ $post_id ] = [
			'status' => 'no',
			'phase'  => $enabled ? 'enabled' : 'disabled',
			'link'   => '',
			'title'  => $enabled
				? 'Polly is enabled, but audio has not been generated yet.'
				: 'Audio is not available.',
		];
	}

	private function get_display_voice( int $post_id ): string {
		if ( isset( $this->display_voice_cache[ $post_id ] ) ) {
			return $this->display_voice_cache[ $post_id ];
		}

		foreach ( [ 'amazon_polly_generated_voice_id', 'amazon_polly_voice_id' ] as $meta_key ) {
			$voice = (string) get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $voice ) {
				return $this->display_voice_cache[ $post_id ] = $voice;
			}
		}

		if ( 'yes' !== $this->get_audio_status( $post_id )['status'] ) {
			return $this->display_voice_cache[ $post_id ] = '';
		}

		try {
			$voice = $this->common->resolve_polly_voice_id(
				$this->common->get_post_source_language( $post_id ),
				'',
				$this->common->get_voice_id()
			);
		} catch ( Exception $e ) {
			$voice = '';
		}

		return $this->display_voice_cache[ $post_id ] = (string) $voice;
	}

	private function get_background_task(): AmazonAI_BackgroundTask {
		static $background_task = null;

		if ( null === $background_task ) {
			$background_task = new AmazonAI_BackgroundTask();
		}

		return $background_task;
	}

	private function get_audio_filter_value( ?\WP_Query $query = null ): string {
		$filter = '';

		if ( null !== $query ) {
			$filter = (string) $query->get( 'amazon_polly_audio_filter' );
		}

		if ( '' === $filter && isset( $_GET['polly_audio_filter'] ) ) {
			$filter = sanitize_key( wp_unslash( $_GET['polly_audio_filter'] ) );
		}

		return in_array( $filter, [ 'no_audio', 'has_audio' ], true ) ? $filter : '';
	}

	private function build_audio_filter_meta_query( string $filter ): array {
		$state_key = $this->common->get_audio_state_meta_key();

		if ( 'has_audio' === $filter ) {
			return [
				[
					'key'     => $state_key,
					'value'   => AmazonAI_Common::AUDIO_STATE_READY,
					'compare' => '=',
				],
			];
		}

		return [
			[
				'key'     => $state_key,
				'value'   => AmazonAI_Common::AUDIO_STATE_READY,
				'compare' => '!=',
			],
		];
	}

	private function merge_meta_query_clause( $meta_query, array $clause ): array {
		if ( ! is_array( $meta_query ) || [] === $meta_query ) {
			return [ $clause ];
		}

		return [
			'relation' => 'AND',
			$meta_query,
			$clause,
		];
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
				$global_voice = $this->common->get_voice_id();
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
		if ( ! isset( $_GET['polly_queued'] ) ) {
			return;
		}

		$count = absint( wp_unslash( $_GET['polly_queued'] ) );
		if ( 0 === $count ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: queued posts count. */
			__( 'Audio generation queued for %d post(s). It will be processed in the background via WP-Cron.', 'ai-text-to-speech' ),
			$count
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	// =========================================================================
	// Button on TTS settings page: "Find posts without audio"
	// =========================================================================

	public function render_settings_button(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'aws-tts_page_amazon_ai_polly' !== $screen->id ) {
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
