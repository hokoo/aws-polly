<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since      0.1
 *
 * @wordpress-plugin
 * Plugin Name:       AI Text-to-Speech using AWS Polly
 * Description:       Independent WordPress text-to-speech integration using AWS Polly.
 * Version:           1.0.4
 * Author:            iTRON
 * Author URI:        https://itron.pro/
 * License:           GPL-3.0-only
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       ai-text-to-speech-using-aws-polly
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

const ITRON_POLLY_TTS_PLUGIN_NAME = 'itron-polly-tts';
const ITRON_POLLY_TTS_VERSION = '1.0.4';

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( iTRON\PollyTTS\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( iTRON\PollyTTS\Deactivator::class, 'deactivate' ) );

$itron_polly_tts_plugin = new iTRON\PollyTTS\Plugin();
$itron_polly_tts_plugin->run();
