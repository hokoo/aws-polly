<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      0.1
 *
 */

 // If uninstall not called from WordPress, then exit.
 if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	 exit;
 }

// General Options
delete_option( 'itron_polly_tts_s3_access_key' );
delete_option( 'itron_polly_tts_s3_secret_key' );
delete_option( 'itron_polly_tts_s3_bucket_name' );
delete_option( 'itron_polly_tts_s3_region' );

delete_option( 'itron_polly_tts_source_language' );
delete_option( 'itron_polly_tts_s3' );
delete_option( 'itron_polly_tts_posttypes' );
delete_option( 'itron_polly_tts_poweredby' );
delete_option( 'itron_polly_tts_logging' );

// Text-To-Speech Options
delete_option( 'itron_polly_tts_polly_enable' );
delete_option( 'itron_polly_tts_sample_rate' );
delete_option( 'itron_polly_tts_voice_id' );
delete_option( 'itron_polly_tts_auto_breaths' );
delete_option( 'itron_polly_tts_ssml' );
delete_option( 'itron_polly_tts_lexicons' );
delete_option( 'itron_polly_tts_speed' );
delete_option( 'itron_polly_tts_position' );
delete_option( 'itron_polly_tts_player_label' );
delete_option( 'itron_polly_tts_defconf' );
delete_option( 'itron_polly_tts_autoplay' );
delete_option( 'itron_polly_tts_update_all' );
delete_option( 'itron_polly_tts_add_post_title' );
delete_option( 'itron_polly_tts_add_post_excerpt' );
delete_option( 'itron_polly_tts_medialibrary_enabled' );
delete_option( 'itron_polly_tts_skip_tags' );
delete_option( 'itron_polly_tts_download_enabled' );
delete_option( 'itron_polly_tts_disable_post_voice_override' );
delete_option( 'itron_polly_tts_neural' );
delete_option( 'itron_polly_tts_speaking_style' );
delete_option( 'itron_polly_tts_news' );
delete_option( 'itron_polly_tts_conversational' );
delete_option( 'itron_polly_tts_cloudfront' );
delete_option( 'itron_polly_tts_coming_soon_text' );
delete_option( 'itron_polly_tts_valid_keys' );
