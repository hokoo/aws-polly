<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://itron.pro/
 * @since      1.0.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Amazonpolly
 * @subpackage Amazonpolly/includes
 * @author     iTRON
 */
class Amazonpolly_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		flush_rewrite_rules();
	}

}
