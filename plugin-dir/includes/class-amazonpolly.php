<?php

/**
 * The file that defines the core plugin class
 *
 * @link       amazon.com
 * @since      1.0.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/includes
 */

use iTRON\AWS\Polly\Factory;
use iTRON\AWS\Polly\Integrations\StreamConnector;
use iTRON\AWS\Polly\Loggers\Stream;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Amazonpolly
 * @subpackage Amazonpolly/includes
 */
class Amazonpolly {

	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $common;
	protected $object_cache;

	public function __construct() {
		$this->plugin_name = 'amazonpolly';
		$this->version     = '1.0.0';
		$this->load_dependencies();

		$this->common = new AmazonAI_Common();
		$this->common->init();
		$this->object_cache = new Amazonpolly_Object_Cache( $this->common );

		$this->define_global_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-amazonpolly-loader.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-PostMetaBox.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-BackgroundTask.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-Exceptions.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-FileHandler.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-LocalFileHandler.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-S3FileHandler.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-Logger.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-Common.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-amazonpolly-object-cache.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-GeneralConfiguration.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-PollyService.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-PollyConfiguration.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/AmazonAI-AudioAdmin.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-amazonpolly-public.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';

		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-amazonpolly-CronHandler.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-amazonpolly-CronData.php';

		$this->loader = new Amazonpolly_Loader();
	}

	private function define_global_hooks() {
		add_filter('amazon_polly_logging_enabled', [ $this->common, 'is_logging_enabled' ]);
	}

	private function define_admin_hooks() {
		$background_task = new AmazonAI_BackgroundTask();
		$general_configuration = new AmazonAI_GeneralConfiguration($this->common);
		$polly_configuration = new AmazonAI_PollyConfiguration($this->common);
		$polly_service = new AmazonAI_PollyService($this->common);
		$cron_handler = new AmazonAI_CronHandler( $polly_service, new AmazonAI_Logger() );
		$object_cache = $this->object_cache;
		$audio_generation_options = array(
			'amazon_ai_source_language',
			'amazon_polly_voice_id',
			'amazon_polly_sample_rate',
			'amazon_polly_s3',
			'amazon_polly_cloudfront',
			'amazon_polly_ssml',
			'amazon_polly_auto_breaths',
			'amazon_polly_speed',
			'amazon_polly_lexicons',
			'amazon_polly_neural',
			'amazon_polly_speaking_style',
			'amazon_polly_add_post_title',
			'amazon_polly_add_post_excerpt',
			'amazon_ai_skip_tags',
			'amazon_polly_disable_post_voice_override',
			'amazon_polly_posttypes',
			'aws_polly_s3_bucket_name',
		);

		$plugin_name = get_option('amazon_plugin_name');
		$this->loader->add_action( 'init', $this, 'load_integrations', 5 );
		$this->loader->add_filter( "plugin_action_links_$plugin_name", $this->common, 'add_settings_link');

		$this->loader->add_action( sprintf('admin_post_%s', AmazonAI_BackgroundTask::ADMIN_POST_ACTION), $background_task, 'run');
		$this->loader->add_action( AmazonAI_BackgroundTask::CRON_HOOK, $background_task, 'handle_cron', 50, 3 );

		$this->loader->add_action( 'admin_print_footer_scripts', $this->common, 'add_quicktags');
		$this->loader->add_action( 'admin_enqueue_scripts', $this->common, 'enqueue_styles');
		$this->loader->add_action( 'admin_enqueue_scripts', $this->common, 'enqueue_scripts');
		// Removed: method enqueue_custom_scripts does not exist in AmazonAI_Common.
		// $this->loader->add_action( 'admin_enqueue_scripts', $this->common, 'enqueue_custom_scripts');
		$this->loader->add_action( 'add_meta_boxes', $this->common, 'field_checkbox');
		$this->loader->add_action( 'save_post', $polly_service, 'save_post', 10, 3);
		$this->loader->add_action( AmazonAI_BackgroundTask::CRON_HANDLERS_HOOK . AmazonAI_PollyService::GENERATE_POST_AUDIO_TASK, $cron_handler, 'generate_audio', 10, 1);

		$this->loader->add_action( 'before_delete_post', $this->common, 'delete_post' );
		$this->loader->add_action( 'before_delete_post', $object_cache, 'handle_before_delete_post' );
		$this->loader->add_action( 'added_post_meta', $object_cache, 'handle_added_post_meta', 10, 4 );
		$this->loader->add_action( 'updated_post_meta', $object_cache, 'handle_updated_post_meta', 10, 4 );
		$this->loader->add_action( 'deleted_post_meta', $object_cache, 'handle_deleted_post_meta', 10, 4 );
		$this->loader->add_action( 'amazon_polly_clear_post_audio_runtime_cache', $object_cache, 'handle_clear_post_audio_runtime_cache', 10, 1 );
		$this->loader->add_action( 'update_option_aws_polly_s3_region', $object_cache, 'handle_polly_voices_region_change', 10, 3 );
		$this->loader->add_action( 'update_option_aws_polly_s3_access_key', $object_cache, 'handle_polly_voices_credentials_change', 10, 3 );
		$this->loader->add_action( 'update_option_aws_polly_s3_secret_key', $object_cache, 'handle_polly_voices_credentials_change', 10, 3 );
		foreach ( $audio_generation_options as $option_name ) {
			$this->loader->add_action( 'update_option_' . $option_name, $object_cache, 'handle_audio_generation_setting_change', 10, 3 );
		}
		$this->loader->add_action( 'wp_ajax_polly_transcribe', $polly_service, 'ajax_bulk_synthesize' );

		$this->loader->add_action( 'admin_menu', $general_configuration, 'amazon_ai_add_menu' );
		$this->loader->add_action( 'admin_init', $general_configuration, 'display_options' );

		$this->loader->add_action( 'admin_menu', $polly_configuration, 'amazon_ai_add_menu' );
		$this->loader->add_action( 'admin_menu', $polly_configuration, 'display_options' );

		// Audio admin: columns, meta box, filter, bulk actions, settings button.
		$audio_admin = new AmazonAI_AudioAdmin( $this->common );

		foreach ( $this->common->get_posttypes_array() as $post_type ) {
			$this->loader->add_filter( "manage_{$post_type}_posts_columns", $audio_admin, 'add_columns' );
			$this->loader->add_action( "manage_{$post_type}_posts_custom_column", $audio_admin, 'render_column', 10, 2 );
			$this->loader->add_filter( "manage_edit-{$post_type}_sortable_columns", $audio_admin, 'sortable_columns' );
			$this->loader->add_filter( "bulk_actions-edit-{$post_type}", $audio_admin, 'add_bulk_actions' );
			$this->loader->add_filter( "handle_bulk_actions-edit-{$post_type}", $audio_admin, 'handle_bulk_action', 10, 3 );
		}

		$this->loader->add_action( 'pre_get_posts', $audio_admin, 'handle_sorting' );
		$this->loader->add_filter( 'posts_clauses', $audio_admin, 'apply_sorting_clauses', 10, 2 );
		$this->loader->add_action( 'pre_get_posts', $audio_admin, 'handle_filter' );
		$this->loader->add_action( 'restrict_manage_posts', $audio_admin, 'render_filter_dropdown' );
		$this->loader->add_action( 'add_meta_boxes', $audio_admin, 'add_audio_meta_box' );
		$this->loader->add_action( 'admin_head', $audio_admin, 'column_styles' );
		$this->loader->add_action( 'admin_footer', $audio_admin, 'render_settings_button' );
		$this->loader->add_action( 'admin_notices', $audio_admin, 'bulk_action_notice' );

		$this->loader->add_filter( 'wp_kses_allowed_html', $this->common, 'allowed_tags_kses');
		$this->loader->add_filter( 'tiny_mce_before_init', $this->common, 'allowed_tags_tinymce');
	}

	private function define_public_hooks() {
		$plugin_public = new Amazonpolly_Public( $this->get_plugin_name(), $this->get_version(), $this->common, $this->object_cache );

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
			add_filter( 'wp_stream_connectors', fn( $connectors ) => array_merge( $connectors, [ new StreamConnector() ] ) );
		}
	}
}
