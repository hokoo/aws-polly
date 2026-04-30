<?php

namespace iTRON\PollyTTS;
/**
 * Fired during plugin activation
 *
 * @since      0.1
 *
 */

class Activator {

	/**
	 * Initial configuration of the plugin.
	 *
	 * @since      0.1
	 */
	public static function activate() {
		flush_rewrite_rules();
	}
}
