<?php

/**
 * Post meta box to enable/disable polly
 */
class AmazonAI_PostMetaBox {
  /**
   * @var AmazonAI_Common
   */
  private $common;

  /**
   * AmazonAI_PostMetaBox constructor.
   *
   * @param AmazonAI_Common $common
   */
  public function __construct(AmazonAI_Common $common) {
    $this->common = $common;
  }

  /**
   * Initialize box with 'Enable Amazon Polly' checkbox under the new post form.
   *
   * @param string $post New post.
   *
   * @since    1.0.0
   */
  public function display_box_content($post) {
    $this->display_polly_gui($post);
    $this->display_translate_gui($post);
  }

  private function render_voice_options( $language_code, $selected_voice_id ) {
    $neural_requested = $this->common->is_polly_neural_requested();
    $voice_groups = $this->common->get_grouped_polly_voices( $language_code );
    $has_voices = false;

    foreach ( $voice_groups as $group_key => $group ) {
      if ( empty( $group['voices'] ) ) {
        continue;
      }

      $has_voices = true;
      echo '<optgroup label="' . esc_attr( $group['label'] ) . '">';
      foreach ( $group['voices'] as $voice ) {
        $is_neural_only = 'neural_only' === $group_key;
        $is_disabled = $is_neural_only && ! $neural_requested;

        echo '<option value="' . esc_attr($voice['Id']) . '"';
        echo ' data-supported-engines="' . esc_attr( implode( ',', $voice['SupportedEngines'] ?? [] ) ) . '"';
        echo ' data-neural-only="' . esc_attr( $is_neural_only ? '1' : '0' ) . '"';
        echo ' data-standard-supported="' . esc_attr( $this->common->is_standard_supported_for_voice( $voice ) ? '1' : '0' ) . '"';
        if ( $is_disabled ) {
          echo ' disabled="disabled"';
        }
        if ( strcmp($selected_voice_id, $voice['Id']) === 0 ) {
          echo ' selected="selected"';
        }
        echo '>' . esc_attr($voice['LanguageName']) . ' - ' . esc_attr($voice['Id']) . ' [' . esc_attr( $this->common->get_polly_voice_capability_label( $voice ) ) . ']</option>';
      }
      echo '</optgroup>';
    }

    return $has_voices;
  }

  /**
   * Display Polly GUI on page for saving new post.
   *
   * @param string $post New post.
   *
   * @since    2.5.0
   */
  public function display_polly_gui($post) {
    $nonce = wp_create_nonce('amazon-polly');

    echo '<input type="hidden" name="amazon-polly-post-nonce" value="' . esc_attr($nonce) . '" />';

    // Check if Text-To-Speech (Amazon Polly) functionality is enabled.
    if ($this->common->is_polly_enabled()) {
      // Check if Amazon Polly is enabled for specific post.
      // 1 - Means that it's enabled for post
      // 0 - Means that it's not enabled for the post
      // No value - Means that it's new post
      $is_polly_enabled_for_post = get_post_meta($post->ID, 'amazon_polly_enable', true);
      if ('1' === $is_polly_enabled_for_post) {
        $polly_checked = 'checked';
      }
      elseif ('0' === $is_polly_enabled_for_post) {
        $polly_checked = '';
      }
      else {
        if ($this->common->is_polly_enabled_for_new_posts()) {
          $polly_checked = 'checked';
        }
        else {
          $polly_checked = '';
        }
      }

      $post_options_visibility = '';

      echo '<p><input type="checkbox" name="amazon_polly_enable" id="amazon_polly_enable" value="1"  ' . esc_attr($polly_checked) . '/><label for="amazon_polly_enable">Enable Text-To-Speech (Amazon Polly)</label> </p>';
      echo '<div id="amazon_polly_post_options" style="' . esc_attr($post_options_visibility) . '">';

	      try {
	        $language_code = $this->common->get_post_source_language( $post->ID );
	        $post_voice_id = get_post_meta($post->ID, 'amazon_polly_voice_id', true);
	        $is_post_voice_override_disabled = $this->common->is_post_voice_override_disabled();
	        $global_voice_id = $this->common->resolve_polly_voice_id( $language_code, $this->common->get_voice_id(), 'Matthew' );
	        $voice_id = $is_post_voice_override_disabled
	          ? $global_voice_id
	          : $this->common->resolve_polly_voice_id( $language_code, $post_voice_id, $global_voice_id );

	        if ( $is_post_voice_override_disabled && ! empty( $post_voice_id ) ) {
	          delete_post_meta( $post->ID, 'amazon_polly_voice_id' );
	        }

	        $compatible_voices = $this->common->get_compatible_polly_voices( $language_code );

	        if ( ! $is_post_voice_override_disabled && $voice_id !== $post_voice_id ) {
	          update_post_meta( $post->ID, 'amazon_polly_voice_id', $voice_id );
	        }

	        if ( empty( $compatible_voices ) ) {
	          echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
	        } elseif ( $is_post_voice_override_disabled ) {
	          echo '<p>Voice name: <strong>' . esc_html( $voice_id ) . '</strong></p>';
	          echo '<p class="description">Custom per-post voice selection is disabled in plugin settings. This post will use the global voice.</p>';
	        } else {
	          echo '<p>Voice name: <select name="amazon_polly_voice_id" id="amazon_polly_voice_id" >';
	          if ( ! $this->render_voice_options( $language_code, $voice_id ) ) {
	            echo '</select></p>';
            echo '<p class="description">No supported voices are currently available for this language in the selected AWS region.</p>';
            echo '</div>';
            return;
          }
          echo '</select></p>';
          echo '<p class="description">Neural-only voices require the global Neural setting to stay enabled.</p>';
        }
      } catch ( Exception $e ) {
        echo '<p class="description">Unable to load supported Amazon Polly voices right now.</p>';
      }

      echo '</div>';
    }
  }

  /**
   * Display Translate GUI on page for saving new post.
   *
   * @param string $post New post.
   *
   * @since    2.5.0
   */
  public function display_translate_gui($post) {
    $post_source_language = get_post_meta($post->ID, 'amazon_ai_source_language', true);
    if (! empty($post_source_language)) {
      echo '<p><input type="checkbox" name="amazon_ai_deactive_translation" id="amazon_ai_deactive_translation" value="1"/><label for="amazon_polly_enable">Deactive Translation</label> </p>';
    }

    // Check if Translate (Amazon Translate) functionality is enabled.
    if ($this->common->is_translation_enabled()) {
      $number_of_translations = 0;

      foreach ($this->common->get_all_translatable_languages() as $language_code) {
        $number_of_translations = $number_of_translations + $this->inc_trans($language_code);
      }

      echo '<div id="amazon_ai_translate_gui">';

      if (! empty($number_of_translations)) {
        echo '<div id="amazon_polly_trans_div">';
        echo '<p style="display:inline;"><button type="button" class="button button-primary" id="amazon_polly_trans_button">Translate</button></p>';
        echo '<div style="display:inline" id="amazon-polly-trans-info"><p style="display:inline; color: blue;">&nbsp; You will translate it into </p><div id="amazon_polly_number_of_trans" style="display:inline; color: blue;">' . $number_of_translations . '</div><p style="display:inline; color: blue;"> language(s).</p></div>';
        echo '<div id="amazon_polly_trans-progressbar"><div class="amazon_polly_trans-label"></div></div>';
        echo '</div>';
      }

      echo '</div>';
    }
    echo '<p><button type="button" class="button" id="amazon_polly_price_checker_button" >How much will this cost to convert?</button></p>';
    echo '<div id="amazon_ai_plugin_cost_info">';
    if ($this->common->is_polly_enabled()) {
      echo '<p><b>-> Text-To-Speech Functionality</b><p>';
      echo '<p>For Amazon Polly’s Standard voices, <b>the free tier includes 5 million characters per month</b> for speech or Speech Marks requests, for the first 12 months, starting from your first request for speech. For Neural voices, the free tier includes 1 million characters per month for speech or Speech Marks requests, for the first 12 months, starting from your first request. <p>';
      echo '<p>You are billed monthly for the number of characters of text that you processed. Amazon Polly’s Standard voices are priced at $4.00 per 1 million characters for speech or Speech Marks requests (when outside the free tier). Amazon Polly’s Neural voices are priced at $16.00 per 1 million characters for speech or Speech Marks requested (when outside the free tier). <p>';
      echo '<p>When you update your post, plugin needs to conver the whole content to audio again.  <p>';
      echo '<p>You can find full information about pricing of Amazon Polly here: https://aws.amazon.com/polly/pricing/ <p>';
    }
    if ($this->common->is_translation_enabled()) {
      echo '<p><b>-> Translate Functionality</b><p>';
      echo '<p>Your <b>Free Tier is 2 million characters per month for 12 months</b>, starting from the date on which you create your first translation request. When your free usage expires, or if your application use exceeds the free usage tier, you simply pay standard, pay-as-you-go service rates.  <p>';
      echo '<p>After this, you are billed monthly for the total number of characters sent to the API for processing, including white space characters. Amazon Translate is priced at $15 per million characters ($0.000015 per character). <p>';
      echo '<p>You can find full information about pricing of Amazon Translate here: https://aws.amazon.com/translate/pricing/  <p>';
    }
    echo '</div>';
  }

  /**
   * Method for calculating number of languages to which text should be converted.
   *
   * @param $language_code
   *
   * @return
   * @since    2.5.0
   */
  public function inc_trans($language_code) {
    $is_language_translatable = get_option('amazon_polly_trans_langs_' . $language_code, '');
    $source_language = $this->common->get_source_language();

    if ('on' == $is_language_translatable) {
      $value = 1;
    }
    else {
      $value = 0;
    }

    if (('en' != $source_language) && ('en' == $language_code)) {
      $value = 1;
    }

    return apply_filters('amazon_polly_trans_langs_' . $language_code, $value);
  }
}
