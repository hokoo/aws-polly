<?php
/**
 * Fired during plugin activation
 *
 * @link       amazon.com
 * @since      1.0.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/includes
 */

class Amazonpolly_Activator {

	/**
	 * Initial configuration of the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		flush_rewrite_rules();
	}
}
