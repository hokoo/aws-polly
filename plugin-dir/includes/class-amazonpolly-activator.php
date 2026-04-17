<?php
/**
 * Fired during plugin activation
 *
 * @since      0.1
 *
 */

class Amazonpolly_Activator {

	/**
	 * Initial configuration of the plugin.
	 *
	 * @since      0.1
	 */
	public static function activate() {
		flush_rewrite_rules();
	}
}
