<?php
/**
 * Fired during plugin deactivation
 *
 * @since      0.1
 *
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1
 * @author     iTRON
 */
class Amazonpolly_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since      0.1
	 */
	public static function deactivate() {

		flush_rewrite_rules();
	}

}
