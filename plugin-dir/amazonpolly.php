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
 * Plugin Name:       AWS Text-to-Speech
 * Plugin URI:        https://wordpress.org/plugins/amazon-polly/
 * Description:       Create audio versions of your posts using Amazon Polly text-to-speech service.
 * Version:           0.6
 * Author:            AWS Labs, WP Engine
 * Author URI:        https://aws.amazon.com/
 * License:           GPL-3.0 ONLY
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP:      8.1
 * Text Domain:       amazonpolly
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
function activate_amazonpolly() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-activator.php';
	Amazonpolly_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amazonpolly-deactivator.php
 */
function deactivate_amazonpolly() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amazonpolly-deactivator.php';
	Amazonpolly_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_amazonpolly' );
register_deactivation_hook( __FILE__, 'deactivate_amazonpolly' );

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
function run_amazonpolly() {

	$plugin = new Amazonpolly();
	$plugin->run();

}
run_amazonpolly();
