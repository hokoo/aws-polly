<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://itron.pro/
 * @since      1.0.0
 *
 * @package    Awspolly
 */

 // If uninstall not called from WordPress, then exit.
 if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	 exit;
 }

// General Options
delete_option('amazon_polly_access_key');
delete_option('amazon_polly_secret_key');

delete_option('amazon_ai_source_language');
delete_option('amazon_polly_region');
delete_option('amazon_polly_s3');
delete_option('amazon_polly_posttypes');
delete_option('amazon_polly_poweredby');
delete_option('amazon_ai_logging');

// Text-To-Speech Options
delete_option('amazon_polly_sample_rate');
delete_option('amazon_polly_voice_id');
delete_option('amazon_polly_auto_breaths');
delete_option('amazon_polly_ssml');
delete_option('amazon_polly_lexicons');
delete_option('amazon_polly_speed');
delete_option('amazon_polly_position');
delete_option('amazon_polly_player_label');
delete_option('amazon_polly_defconf');
delete_option('amazon_polly_autoplay');
delete_option('amazon_polly_update_all');
delete_option('amazon_polly_add_post_title');
delete_option('amazon_polly_add_post_excerpt');
delete_option('amazon_ai_medialibrary_enabled');
delete_option('amazon_ai_skip_tags');
