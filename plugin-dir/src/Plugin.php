<?php

namespace iTRON\PollyTTS;

/**
 * The file that defines the core plugin class
 *
 * @since      0.1
 */

use iTRON\PollyTTS\Integrations\StreamConnector;
use iTRON\PollyTTS\Loggers\Stream;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      0.1
 */
class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $common;
	protected $object_cache;

	public function __construct() {
		$this->plugin_name = 'itron-polly-tts';
		$this->version     = '1.0.1';
		$this->load_dependencies();

		$this->common = new Common();
		$this->common->init();
		$this->object_cache = new ObjectCache( $this->common );

		$this->define_global_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		$this->loader = new HookLoader();
	}

	private function define_global_hooks() {
		add_filter( 'itron_polly_tts_logging_enabled', array( $this->common, 'is_logging_enabled' ) );
	}

	private function define_admin_hooks() {
		$background_task          = new BackgroundTask();
		$general_configuration    = new GeneralConfiguration( $this->common );
		$polly_configuration      = new PollyConfiguration( $this->common );
		$polly_service            = new PollyService( $this->common );
		$cron_handler             = new CronHandler( $polly_service, new Logger() );
		$object_cache             = $this->object_cache;
		$audio_generation_options = array(
			'itron_polly_tts_source_language',
			'itron_polly_tts_voice_id',
			'itron_polly_tts_sample_rate',
			'itron_polly_tts_s3',
			'itron_polly_tts_cloudfront',
			'itron_polly_tts_ssml',
			'itron_polly_tts_auto_breaths',
			'itron_polly_tts_speed',
			'itron_polly_tts_lexicons',
			'itron_polly_tts_neural',
			'itron_polly_tts_speaking_style',
			'itron_polly_tts_add_post_title',
			'itron_polly_tts_add_post_excerpt',
			'itron_polly_tts_skip_tags',
			'itron_polly_tts_disable_post_voice_override',
			'itron_polly_tts_posttypes',
			'itron_polly_tts_s3_bucket_name',
		);

		$plugin_basename = plugin_basename( dirname( __DIR__ ) . '/itron-polly-tts.php' );
		/** @uses Plugin::load_integrations() */
		$this->loader->add_action( 'init', $this, 'load_integrations', 5 );
		$this->loader->add_filter( "plugin_action_links_{$plugin_basename}", $this->common, 'add_settings_link' );

		/** @uses BackgroundTask::handle_cron() */
		$this->loader->add_action( BackgroundTask::CRON_HOOK, $background_task, 'handle_cron', 50, 3 );

		/** @uses Common::add_quicktags() */
		$this->loader->add_action( 'admin_print_footer_scripts', $this->common, 'add_quicktags' );

		/** @uses Common::enqueue_styles() */
		$this->loader->add_action( 'admin_enqueue_scripts', $this->common, 'enqueue_styles' );

		/** @uses Common::enqueue_scripts() */
		$this->loader->add_action( 'admin_enqueue_scripts', $this->common, 'enqueue_scripts' );

		/** @uses Common::field_checkbox() */
		$this->loader->add_action( 'add_meta_boxes', $this->common, 'field_checkbox' );

		/** @uses PollyService::save_post() */
		$this->loader->add_action( 'save_post', $polly_service, 'save_post', 10, 3 );

		/** @uses CronHandler::generate_audio() */
		$this->loader->add_action( BackgroundTask::CRON_HANDLERS_HOOK . PollyService::GENERATE_POST_AUDIO_TASK, $cron_handler, 'generate_audio', 10, 1 );

		/** @uses Common::delete_post() */
		$this->loader->add_action( 'before_delete_post', $this->common, 'delete_post' );

		/** @uses ObjectCache::handle_before_delete_post() */
		$this->loader->add_action( 'before_delete_post', $object_cache, 'handle_before_delete_post' );

		/** @uses ObjectCache::handle_added_post_meta() */
		$this->loader->add_action( 'added_post_meta', $object_cache, 'handle_added_post_meta', 10, 4 );

		/** @uses ObjectCache::handle_updated_post_meta() */
		$this->loader->add_action( 'updated_post_meta', $object_cache, 'handle_updated_post_meta', 10, 4 );

		/** @uses ObjectCache::handle_deleted_post_meta() */
		$this->loader->add_action( 'deleted_post_meta', $object_cache, 'handle_deleted_post_meta', 10, 4 );

		/** @uses ObjectCache::handle_clear_post_audio_runtime_cache() */
		$this->loader->add_action( 'itron_polly_tts_clear_post_audio_runtime_cache', $object_cache, 'handle_clear_post_audio_runtime_cache', 10, 1 );

		/** @uses ObjectCache::handle_polly_voices_region_change() */
		$this->loader->add_action( 'update_option_itron_polly_tts_s3_region', $object_cache, 'handle_polly_voices_region_change', 10, 3 );

		/** @uses ObjectCache::handle_polly_voices_credentials_change() */
		$this->loader->add_action( 'update_option_itron_polly_tts_s3_access_key', $object_cache, 'handle_polly_voices_credentials_change', 10, 3 );

		/** @uses ObjectCache::handle_polly_voices_credentials_change() */
		$this->loader->add_action( 'update_option_itron_polly_tts_s3_secret_key', $object_cache, 'handle_polly_voices_credentials_change', 10, 3 );

		foreach ( $audio_generation_options as $option_name ) {
			/** @uses ObjectCache::handle_audio_generation_setting_change() */
			$this->loader->add_action( 'update_option_' . $option_name, $object_cache, 'handle_audio_generation_setting_change', 10, 3 );
		}

		/** @uses PollyService::ajax_bulk_synthesize() */
		$this->loader->add_action( 'wp_ajax_itron_polly_tts_transcribe', $polly_service, 'ajax_bulk_synthesize' );

		/** @uses GeneralConfiguration::itron_polly_tts_add_menu() */
		$this->loader->add_action( 'admin_menu', $general_configuration, 'itron_polly_tts_add_menu' );

		/** @uses GeneralConfiguration::display_options() */
		$this->loader->add_action( 'admin_init', $general_configuration, 'display_options' );

		/** @uses PollyConfiguration::itron_polly_tts_add_menu() */
		$this->loader->add_action( 'admin_menu', $polly_configuration, 'itron_polly_tts_add_menu' );

		/** @uses PollyConfiguration::display_options() */
		$this->loader->add_action( 'admin_menu', $polly_configuration, 'display_options' );

		// Audio admin: columns, meta box, filter, bulk actions, settings button.
		$audio_admin = new AudioAdmin( $this->common );

		foreach ( $this->common->get_posttypes_array() as $post_type ) {
			$this->loader->add_filter( "manage_{$post_type}_posts_columns", $audio_admin, 'add_columns' );

			/** @uses AudioAdmin::render_column() */
			$this->loader->add_action( "manage_{$post_type}_posts_custom_column", $audio_admin, 'render_column', 10, 2 );
			$this->loader->add_filter( "manage_edit-{$post_type}_sortable_columns", $audio_admin, 'sortable_columns' );
			$this->loader->add_filter( "bulk_actions-edit-{$post_type}", $audio_admin, 'add_bulk_actions' );
			$this->loader->add_filter( "handle_bulk_actions-edit-{$post_type}", $audio_admin, 'handle_bulk_action', 10, 3 );
		}

		/** @uses AudioAdmin::handle_sorting() */
		$this->loader->add_action( 'pre_get_posts', $audio_admin, 'handle_sorting' );
		$this->loader->add_filter( 'posts_clauses', $audio_admin, 'apply_sorting_clauses', 10, 2 );

		/** @uses AudioAdmin::handle_filter() */
		$this->loader->add_action( 'pre_get_posts', $audio_admin, 'handle_filter' );

		/** @uses AudioAdmin::render_filter_dropdown() */
		$this->loader->add_action( 'restrict_manage_posts', $audio_admin, 'render_filter_dropdown' );

		/** @uses AudioAdmin::add_audio_meta_box() */
		$this->loader->add_action( 'add_meta_boxes', $audio_admin, 'add_audio_meta_box' );

		/** @uses AudioAdmin::enqueue_admin_assets() */
		$this->loader->add_action( 'admin_enqueue_scripts', $audio_admin, 'enqueue_admin_assets', 20 );

		/** @uses AudioAdmin::bulk_action_notice() */
		$this->loader->add_action( 'admin_notices', $audio_admin, 'bulk_action_notice' );

		$this->loader->add_filter( 'wp_kses_allowed_html', $this->common, 'allowed_tags_kses' );
		$this->loader->add_filter( 'tiny_mce_before_init', $this->common, 'allowed_tags_tinymce' );
	}

	private function define_public_hooks() {
		$plugin_public = new PublicRenderer( $this->get_plugin_name(), $this->get_version(), $this->common, $this->object_cache );

		$this->loader->add_filter( 'the_content', $plugin_public, 'content_filter', 99999 );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}

	public function load_integrations() {
		if ( is_a( Factory::getLogger(), Stream::class ) ) {
			add_filter( 'wp_stream_connectors', fn( $connectors ) => array_merge( $connectors, array( new StreamConnector() ) ) );
		}
	}
}
