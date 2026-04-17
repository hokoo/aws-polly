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
 * Plugin URI:        https://itron.pro/
 * Description:       Independent WordPress text-to-speech integration using AWS Polly.
 * Version:           1.0.1
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

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-amazonpolly-activator.php
 */
function itron_aws_polly_activate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-activator.php';
	Amazonpolly_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amazonpolly-deactivator.php
 */
function itron_aws_polly_deactivate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-deactivator.php';
	Amazonpolly_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'itron_aws_polly_activate_plugin' );
register_deactivation_hook( __FILE__, 'itron_aws_polly_deactivate_plugin' );

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
 * @since      0.1
 */
function itron_aws_polly_run_plugin() {

	$plugin = new Amazonpolly();
	$plugin->run();

}
itron_aws_polly_run_plugin();
