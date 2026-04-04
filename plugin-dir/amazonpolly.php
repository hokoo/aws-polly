<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              amazon.com
 * @since             0.2.2
 * @package           Amazonpolly
 *
 * @wordpress-plugin
 * Plugin Name:       AI Text-to-Speech from AWS Polly
 * Plugin URI:        https://wordpress.org/plugins/ai-text-to-speech-from-aws-polly/
 * Description:       Generate post audio with AI text-to-speech powered by AWS Polly.
 * Version:           1.0.0
 * Author:            AWS Labs, WP Engine
 * Author URI:        https://aws.amazon.com/
 * License:           GPL-3.0-only
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       ai-text-to-speech-from-aws-polly
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-amazonpolly-activator.php
 */
function ai_text_to_speech_activate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-activator.php';
	Amazonpolly_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amazonpolly-deactivator.php
 */
function ai_text_to_speech_deactivate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-deactivator.php';
	Amazonpolly_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'ai_text_to_speech_activate_plugin' );
register_deactivation_hook( __FILE__, 'ai_text_to_speech_deactivate_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function ai_text_to_speech_run_plugin() {

	$plugin = new Amazonpolly();
	$plugin->run();

}
ai_text_to_speech_run_plugin();
