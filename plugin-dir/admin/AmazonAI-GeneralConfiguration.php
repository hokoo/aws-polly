<?php
/**
 * Class responsible for providing GUI for general configuration of the plugin
 *
 * @link       amazon.com
 * @since      2.5.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin
 */

class AmazonAI_GeneralConfiguration
{
	/**
	 * @var AmazonAI_Common
	 */
	private $common;

	private const OPTION_PREFIX = 'aws_polly_';
	private const CONST_PREFIX = 'AWS_POLLY_';

	/**
	 * AmazonAI_GeneralConfiguration constructor.
	 *
	 * @param AmazonAI_Common $common
	 */
	public function __construct(AmazonAI_Common $common) {
		$this->common = $common;
	}

    public function amazon_ai_add_menu()
    {
	    $this->plugin_screen_hook_suffix = add_menu_page( __( 'AWS', 'amazon-ai' ),
		    __( 'AWS', 'amazon-ai' ),
		    'manage_options',
		    'amazon_ai',
		    array(
			    $this,
			    'amazonai_gui'
		    ),
		    'dashicons-amazon'
	    );
	    $this->plugin_screen_hook_suffix = add_submenu_page( 'amazon_ai',
		    'General',
		    'General',
		    'manage_options',
		    'amazon_ai',
		    array(
			    $this,
			    'amazonai_gui'
		    ) );
    }

    public function amazonai_gui()
    {
?>
				 <div class="wrap">
				 <div id="icon-options-general" class="icon32"></div>
				 <h1>AWS</h1>
				 <form method="post" action="options.php">
						 <?php

				settings_errors();
        settings_fields("amazon_ai");
        do_settings_sections("amazon_ai");
        submit_button();

?>
				 </form>

		 </div>
		 <?php
    }

    function display_options()
    {

        // ************************************************* *
        // ************** GENERAL SECTION ************** *
	    add_settings_section( 'amazon_ai_general', "", array(
		    $this,
		    'general_gui'
	    ), 'amazon_ai' );
	    add_settings_field( self::OPTION_PREFIX . 's3_access_key', __( 'AWS access key:', 'amazonpolly' ), array(
		    $this,
		    'access_key_gui'
	    ), 'amazon_ai', 'amazon_ai_general', array(
		    'label_for' => 'amazon_polly_access_key'
	    ) );
	    add_settings_field( self::OPTION_PREFIX . 's3_secret_key', __( 'AWS secret key:', 'amazonpolly' ), array(
		    $this,
		    'secret_key_gui'
	    ), 'amazon_ai', 'amazon_ai_general', array(
		    'label_for' => 'amazon_polly_secret_key_fake'
	    ) );
	    add_settings_field( self::OPTION_PREFIX . 's3_bucket_name',
		    __( 'Amazon S3 bucket name:', 'amazonpolly' ),
		    array( $this, 's3_bucket_gui' ),
		    'amazon_ai',
		    'amazon_ai_general',
		    array( 'label_for' => 'amazon_polly_s3_bucket_name' ) );
	    add_settings_field( self::OPTION_PREFIX . 's3_region', __( 'AWS Region:', 'amazonpolly' ), array(
		    $this,
		    'region_gui'
	    ), 'amazon_ai', 'amazon_ai_general', array(
		    'label_for' => 'amazon_polly_region'
	    ) );

	    register_setting( 'amazon_ai', self::OPTION_PREFIX . 's3_access_key' );
	    register_setting( 'amazon_ai', self::OPTION_PREFIX . 's3_secret_key' );
	    register_setting( 'amazon_ai', self::OPTION_PREFIX . 's3_bucket_name' );
	    register_setting( 'amazon_ai', self::OPTION_PREFIX . 's3_region' );
    }


    /**
     * Render the Access Key input for this plugin
     *
     * @since  1.0.0
     */
    function access_key_gui() {
		$name = 's3_access_key';
		$option = self::OPTION_PREFIX . $name;
        $disabled = self::is_overloaded( $name ) ? 'disabled ' : '';
        echo '<input '. $disabled .'type="text" class="regular-text" name="'. $option .'" id="'. $option .'" value="' . esc_attr( self::get_aws_access_key() ) . '" autocomplete="off"> ';
	    if ( $disabled ) {
			echo '<p class="description" id="'. $option .'">Defined as php constant</p>';
	    }
    }



    /**
     * Render the Secret Key input for this plugin
     *
     * @since  1.0.0
     */
    function secret_key_gui() {
	    $name = 's3_secret_key';
	    $option = self::OPTION_PREFIX . $name;
	    $disabled = self::is_overloaded( $name ) ? 'disabled ' : '';
	    echo '<input '. $disabled .'type="password" class="regular-text" name="'. $option .'" id="'. $option .'" value="' . esc_attr( self::get_aws_secret_key() ) . '" autocomplete="off"> ';
	    if ( $disabled ) {
		    echo '<p class="description" id="'. $option .'">Defined as php constant</p>';
	    }
    }

	function s3_bucket_gui() {
		$name = 's3_bucket_name';
		$option = self::OPTION_PREFIX . $name;
		$disabled = self::is_overloaded( $name ) ? 'disabled ' : '';
		echo '<input '. $disabled .'type="text" class="regular-text" name="'. $option .'" id="'. $option .'" value="' . esc_attr( self::get_bucket_name() ) . '" autocomplete="off"> ';
		if ( $disabled ) {
			echo '<p class="description" id="'. $option .'">Defined as php constant</p>';
		}
	}

    /**
     * Render the region input.
     *
     * @since  1.0.3
     */
	function region_gui() {
		$name            = 's3_region';
		$option          = self::OPTION_PREFIX . $name;
		$selected_region = $this->get_option( $name );

		$regions = array(
			'us-east-1'      => 'US East (N. Virginia)',
			'us-east-2'      => 'US East (Ohio)',
			'us-west-1'      => 'US West (N. California)',
			'us-west-2'      => 'US West (Oregon)',
			'eu-west-1'      => 'EU (Ireland)',
			'eu-west-2'      => 'EU (London)',
			'eu-west-3'      => 'EU (Paris)',
			'eu-central-1'   => 'EU (Frankfurt)',
			'ca-central-1'   => 'Canada (Central)',
			'sa-east-1'      => 'South America (Sao Paulo)',
			'ap-southeast-1' => 'Asia Pacific (Singapore)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ap-northeast-2' => 'Asia Pacific (Seoul)',
			'ap-south-1'     => 'Asia Pacific (Mumbai)'
		);

		if ( empty( $selected_region ) ) {
			$selected_region = array_key_first( $regions );
		}

		$disabled = self::is_overloaded( $name ) ? 'disabled ' : '';

		echo '<select name="' . $option . '" id="' . $option . '" >';
		foreach ( $regions as $region_name => $region_label ) {
			echo '<option ' . $disabled . 'label="' . esc_attr( $region_label ) . '" value="' . esc_attr( $region_name ) . '" ';
			selected( $selected_region, $region_name, true );
			echo '>' . esc_attr__( $region_label, 'amazon_polly' ) . '</option>';
		}
		echo '</select>';

		if ( $disabled ) {
			echo '<p class="description" id="amazon_polly_region">Defined as php constant</p>';
		}
	}



    function general_gui()
    {
        //Empty
    }

    function storage_gui()
    {
        //Empty
    }

	private static function is_overloaded( $option_slug ): bool {
		return defined( self::CONST_PREFIX . strtoupper( $option_slug ) );
	}

	private static function get_overloaded( $option_slug ) {
		return constant( self::CONST_PREFIX . strtoupper( $option_slug ) );
	}

	public static function get_option( $option_name ) {
		if ( self::is_overloaded( $option_name ) ) {
			return self::get_overloaded( $option_name );
		}

		return get_option( self::OPTION_PREFIX . $option_name );
	}

	/**
	 * Get S3 bucket name. The method uses filter 'amazon_polly_s3_bucket_name,
	 * which allows to use customer S3 bucket name instead of default one.
	 *
	 * @since  1.0.6
	 */
	public static function get_bucket_name() {
		return self::get_option( 's3_bucket_name' );
	}

	public static function get_aws_access_key() {
		return self::get_option( 's3_access_key' );
	}

	public static function get_aws_secret_key() {
		return self::get_option( 's3_secret_key' );
	}
}
