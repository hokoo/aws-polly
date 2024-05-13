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
        add_settings_section('amazon_ai_general', "", array(
            $this,
            'general_gui'
        ), 'amazon_ai');
        add_settings_field('amazon_polly_access_key', __('AWS access key:', 'amazonpolly'), array(
            $this,
            'access_key_gui'
        ), 'amazon_ai', 'amazon_ai_general', array(
            'label_for' => 'amazon_polly_access_key'
        ));
        add_settings_field('amazon_polly_secret_key_fake', __('AWS secret key:', 'amazonpolly'), array(
            $this,
            'secret_key_gui'
        ), 'amazon_ai', 'amazon_ai_general', array(
            'label_for' => 'amazon_polly_secret_key_fake'
        ));
	    add_settings_field( 'amazon_polly_s3_bucket_name',
		    __( 'Amazon S3 bucket name:', 'amazonpolly' ),
		    array( $this, 's3_bucket_gui' ),
		    'amazon_ai',
		    'amazon_ai_general',
		    array( 'label_for' => 'amazon_polly_s3_bucket_name' ) );

        register_setting('amazon_ai', 'amazon_polly_access_key');
        register_setting('amazon_ai', 'amazon_polly_secret_key_fake');
	    register_setting('amazon_ai', 'amazon_polly_s3_bucket_name');

          add_settings_field('amazon_polly_region', __('AWS Region:', 'amazonpolly'), array(
              $this,
              'region_gui'
          ), 'amazon_ai', 'amazon_ai_general', array(
              'label_for' => 'amazon_polly_region'
          ));

          register_setting('amazon_ai', 'amazon_polly_region');


    }


    /**
     * Render the Access Key input for this plugin
     *
     * @since  1.0.0
     */
    function access_key_gui() {
        $access_key = get_option('amazon_polly_access_key');
        echo '<input type="text" class="regular-text" name="amazon_polly_access_key" id="amazon_polly_access_key" value="' . esc_attr($access_key) . '" autocomplete="off"> ';
        echo '<p class="description" id="amazon_polly_access_key">Required only if you aren\'t using IAM roles</p>';
    }



    /**
     * Render the Secret Key input for this plugin
     *
     * @since  1.0.0
     */
    function secret_key_gui() {
        $secret_key = get_option('amazon_polly_secret_key_fake','********************');
        echo '<input type="password" class="regular-text" name="amazon_polly_secret_key_fake" id="amazon_polly_secret_key_fake" value="' . esc_attr($secret_key) . '" autocomplete="off"> ';
        echo '<p class="description" id="amazon_polly_access_key">Required only if you aren\'t using IAM roles</p>';
    }

    /**
     * Render the region input.
     *
     * @since  1.0.3
     */
    function region_gui() {

            $selected_region = $this->common->get_aws_region();

            $regions = array(
                'us-east-1' => 'US East (N. Virginia)',
                'us-east-2' => 'US East (Ohio)',
                'us-west-1' => 'US West (N. California)',
                'us-west-2' => 'US West (Oregon)',
                'eu-west-1' => 'EU (Ireland)',
                'eu-west-2' => 'EU (London)',
                'eu-west-3' => 'EU (Paris)',
                'eu-central-1' => 'EU (Frankfurt)',
                'ca-central-1' => 'Canada (Central)',
                'sa-east-1' => 'South America (Sao Paulo)',
                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                'ap-southeast-2' => 'Asia Pacific (Sydney)',
                'ap-northeast-2' => 'Asia Pacific (Seoul)',
                'ap-south-1' => 'Asia Pacific (Mumbai)'
            );

            echo '<select name="amazon_polly_region" id="amazon_polly_region" >';
            foreach ($regions as $region_name => $region_label) {
                echo '<option label="' . esc_attr($region_label) . '" value="' . esc_attr($region_name) . '" ';
                if (strcmp($selected_region, $region_name) === 0) {
                    echo 'selected="selected"';
                }
                echo '>' . esc_attr__($region_label, 'amazon_polly') . '</option>';
            }
            echo '</select>';


    }



    function general_gui()
    {
        //Empty
    }

    function storage_gui()
    {
        //Empty
    }

	function s3_bucket_gui() {
		echo '<input type="text" class="regular-text" name="amazon_polly_s3_bucket_name" id="amazon_polly_s3_bucket_name" value="' . esc_attr( self::get_bucket_name() ) . '"> ';
	}

	/**
	 * Get S3 bucket name. The method uses filter 'amazon_polly_s3_bucket_name,
	 * which allows to use customer S3 bucket name instead of default one.
	 *
	 * @since  1.0.6
	 */
	public static function get_bucket_name() {
		return apply_filters( 'amazon_polly_s3_bucket_name', get_option( 'amazon_polly_s3_bucket_name' ) );
	}
}
