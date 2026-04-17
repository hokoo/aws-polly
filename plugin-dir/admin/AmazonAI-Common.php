<?php
/**
 * Common operations used by the AWS for wordpress plugin.
 *
 * @link       https://itron.pro/
 * @since      2.5.0
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AmazonAI_Common

{
	const POLLY_VOICES_TRANSIENT_PREFIX = 'amazon_polly_voices_';
	const POLLY_VOICES_TRANSIENT_TTL = 43200;
	const AUDIO_STATE_META_KEY = 'amazon_polly_audio_state';
	const AUDIO_STATE_NONE = 'none';
	const AUDIO_STATE_QUEUED = 'queued';
	const AUDIO_STATE_RUNNING = 'running';
	const AUDIO_STATE_READY = 'ready';
	const AUDIO_STATE_ERROR = 'error';

	// Information about languages supported by the AWS plugin
	private $languages = [

		['code' => 'af', 'name' => 'Afrikaans','polly' => '0'],
		['code' => 'sq', 'name' => 'Albanian','polly' => '0'],
		['code' => 'am', 'name' => 'Amharic','polly' => '0'],
		['code' => 'ar', 'name' => 'Arabic','polly' => '1'],
		['code' => 'az', 'name' => 'Azerbaijani','polly' => '0'],
		['code' => 'bn', 'name' => 'Bengali','polly' => '0'],
		['code' => 'bs', 'name' => 'Bosnian','polly' => '0'],
		['code' => 'bg', 'name' => 'Bulgarian','polly' => '0'],
		['code' => 'fr-CA', 'name' => 'Canadian French','polly' => '0'],
		['code' => 'da', 'name' => 'Danish','polly' => '1'],
		['code' => 'nl', 'name' => 'Dutch','polly' => '1'],
		['code' => 'zh', 'name' => 'Chinese','polly' => '1'],
		['code' => 'hr', 'name' => 'Croatian','polly' => '0'],
		['code' => 'cs', 'name' => 'Czech','polly' => '0'],
		['code' => 'fa-AF', 'name' => 'Dari','polly' => '1'],
		['code' => 'en', 'name' => 'English','polly' => '1'],
		['code' => 'et', 'name' => 'Estonian','polly' => '0'],
		['code' => 'fi', 'name' => 'Finish','polly' => ''],
		['code' => 'fr', 'name' => 'French','polly' => '1'],
		['code' => 'ka', 'name' => 'Georgian','polly' => '0'],
		['code' => 'de', 'name' => 'German','polly' => '1'],
		['code' => 'el', 'name' => 'Greek','polly' => '0'],
		['code' => 'ha', 'name' => 'Hausa','polly' => '0'],
		['code' => 'he', 'name' => 'Hebrew','polly' => ''],
		['code' => 'hi', 'name' => 'Hindi','polly' => ''],
		['code' => 'hu', 'name' => 'Hungarian','polly' => '0'],
		['code' => 'is', 'name' => 'Icelandic','polly' => '1'],
		['code' => 'it', 'name' => 'Italian','polly' => '1'],
		['code' => 'id', 'name' => 'Indonesian','polly' => ''],
		['code' => 'ja', 'name' => 'Japanese','polly' => '1'],
		['code' => 'ko', 'name' => 'Korean','polly' => '1'],
		['code' => 'lv', 'name' => 'Latvian','polly' => '0'],
		['code' => 'ms', 'name' => 'Malay','polly' => ''],
		['code' => 'no', 'name' => 'Norwegian','polly' => '1'],
		['code' => 'fa', 'name' => 'Persian','polly' => ''],
		['code' => 'pl', 'name' => 'Polish','polly' => '1'],
		['code' => 'pt', 'name' => 'Portuguese','polly' => '1'],
		['code' => 'ps', 'name' => 'Pushto','polly' => '0'],
		['code' => 'ro', 'name' => 'Romanian','polly' => '1'],
		['code' => 'sr', 'name' => 'Serbian','polly' => '0'],
		['code' => 'sk', 'name' => 'Slovak','polly' => '0'],
		['code' => 'sl', 'name' => 'Slovenian','polly' => '0'],
		['code' => 'so', 'name' => 'Somali','polly' => '0'],
		['code' => 'sw', 'name' => 'Swahili','polly' => '0'],
		['code' => 'ru', 'name' => 'Russian','polly' => '1'],
		['code' => 'es', 'name' => 'Spanish','polly' => '1'],
		['code' => 'sv', 'name' => 'Swedish','polly' => '1'],
		['code' => 'tl', 'name' => 'Tagalog','polly' => '0'],
		['code' => 'ta', 'name' => 'Tamil','polly' => '0'],
		['code' => 'th', 'name' => 'Thai','polly' => '0'],
		['code' => 'tr', 'name' => 'Turkish','polly' => '1'],
		['code' => 'uk', 'name' => 'Ukrainian','polly' => '0'],
		['code' => 'ur', 'name' => 'Urdu','polly' => '0'],
		['code' => 'vi', 'name' => 'Vietnamese','polly' => '0'],
		['code' => 'cy', 'name' => 'Welsh', 'polly' => '1']
	];

	private $sdk;
	private $polly_client;
	private $s3_handler;
	private $local_file_handler;
	private $logger;

	/**
	 * Creates SDK objects for the plugin.
	 *
	 * @since    2.5.0
	 */
	public function __construct() {
		$this->logger = new AmazonAI_Logger();
	}

	public function prepare_paragraphs($post_id) {

		$clean_content = '';
		$post_content = get_post_field('post_content', $post_id);
		$paragraphs = explode("\n", $post_content);

		foreach($paragraphs as $paragraph) {
			$clean_paragraph = $this->clean_paragraph($paragraph);

			$clean_content = $clean_content . "\n" . $clean_paragraph;
		}


		return $clean_content;
	}



	public function clean_paragraph($paragraph) {

		$clean_text = $paragraph;
		$clean_text = do_shortcode($clean_text);
		$clean_text = str_replace('&nbsp;', ' ', $clean_text);

		$is_ssml_enabled = $this->is_ssml_enabled();
	  if ($is_ssml_enabled) {
	    $clean_text = $this->encode_ssml_tags($clean_text);
	  }

		$clean_text = strip_tags($clean_text, '<break>');
		$clean_text = esc_html($clean_text);


	  $clean_text = str_replace('&nbsp;', ' ', $clean_text);
	  $clean_text = preg_replace("/https:\/\/([^\s]+)/", "", $clean_text);
		$clean_text = html_entity_decode($clean_text, ENT_QUOTES, 'UTF-8');
	  $clean_text = str_replace('&', ' and ', $clean_text);
	  $clean_text = str_replace('<', ' ', $clean_text);
	  $clean_text = str_replace('>', ' ', $clean_text);

		return $clean_text;
	}

	public function get_language_name($provided_langauge_code) {

		foreach ($this->languages as $language_data) {
			$language_code = $language_data['code'];
			$language_name = $language_data['name'];

			if ($language_code === $provided_langauge_code) {
				return $language_name;
			}
		}

		return "N/A";
	}

	public function get_all_languages() {
		$supported_languages = [];

		foreach ($this->languages as $language_data) {
			$language_code = $language_data['code'];
			array_push($supported_languages, $language_code);
		}

		return $supported_languages;
	}


	public function get_all_polly_languages() {


		$supported_languages = [];

		foreach ($this->languages as $language_data) {
			$language_code = $language_data['code'];
			$is_language_supported = $language_data['polly'];

			if ( !empty($is_language_supported) ) {
				array_push($supported_languages, $language_code);
			}
		}

		return $supported_languages;
	}

	private function get_polly_voices_transient_key() {
		return self::POLLY_VOICES_TRANSIENT_PREFIX . md5( (string) $this->get_aws_region() );
	}

	public function get_audio_state_meta_key(): string {
		return self::AUDIO_STATE_META_KEY;
	}

	public function get_valid_audio_states(): array {
		return [
			self::AUDIO_STATE_NONE,
			self::AUDIO_STATE_QUEUED,
			self::AUDIO_STATE_RUNNING,
			self::AUDIO_STATE_READY,
			self::AUDIO_STATE_ERROR,
		];
	}

	public function normalize_audio_state( $state ): string {
		$state = sanitize_key( (string) $state );

		return in_array( $state, $this->get_valid_audio_states(), true )
			? $state
			: self::AUDIO_STATE_NONE;
	}

	public function get_persisted_post_audio_state( int $post_id ): string {
		$state = sanitize_key( (string) get_post_meta( $post_id, self::AUDIO_STATE_META_KEY, true ) );

		return in_array( $state, $this->get_valid_audio_states(), true ) ? $state : '';
	}

	public function has_post_audio( int $post_id ): bool {
		return '' !== trim( (string) get_post_meta( $post_id, 'amazon_polly_audio_link_location', true ) );
	}

	public function get_post_audio_state( int $post_id ): string {
		$state = $this->get_persisted_post_audio_state( $post_id );
		if ( '' !== $state ) {
			return $state;
		}

		return $this->has_post_audio( $post_id ) ? self::AUDIO_STATE_READY : '';
	}

	public function set_post_audio_state( int $post_id, string $state ): void {
		update_post_meta( $post_id, self::AUDIO_STATE_META_KEY, $this->normalize_audio_state( $state ) );
		clean_post_cache( $post_id );
	}

	public function backfill_legacy_audio_states() {
		global $wpdb;

		$post_types = array_values( array_filter( $this->get_posttypes_array(), 'is_string' ) );
		if ( [] === $post_types ) {
			return 0;
		}

		$post_type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional single backfill query over dynamic post types with a dynamic IN() placeholder list.
		$inserted_rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				SELECT p.ID, %s,
					CASE
						WHEN EXISTS (
							SELECT 1
							FROM {$wpdb->postmeta} AS audio_meta
							WHERE audio_meta.post_id = p.ID
								AND audio_meta.meta_key = %s
								AND audio_meta.meta_value <> ''
						) THEN %s
						ELSE %s
					END
				FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS state_meta
					ON state_meta.post_id = p.ID
					AND state_meta.meta_key = %s
				WHERE p.post_type IN ({$post_type_placeholders})
					AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')
					AND state_meta.meta_id IS NULL",
				self::AUDIO_STATE_META_KEY,
				'amazon_polly_audio_link_location',
				self::AUDIO_STATE_READY,
				self::AUDIO_STATE_NONE,
				self::AUDIO_STATE_META_KEY,
				...$post_types
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( false === $inserted_rows ) {
			return false;
		}

		if ( function_exists( 'wp_cache_set_posts_last_changed' ) ) {
			wp_cache_set_posts_last_changed();
		}

		return (int) $inserted_rows;
	}

	private function sort_polly_voices_list( array &$voices ) {
		usort(
			$voices,
			static function ( $voice1, $voice2 ) {
				$label1 = sprintf( '%s %s', $voice1['LanguageName'] ?? '', $voice1['Id'] ?? '' );
				$label2 = sprintf( '%s %s', $voice2['LanguageName'] ?? '', $voice2['Id'] ?? '' );

				return strcmp( $label1, $label2 );
			}
		);
	}

	private function fetch_polly_voices_from_api() {
		$voices = [];
		$args = [
			'IncludeAdditionalLanguageCodes' => true,
		];
		$next_token = null;

		do {
			if ( $next_token ) {
				$args['NextToken'] = $next_token;
			} else {
				unset( $args['NextToken'] );
			}

			$result = $this->polly_client->describeVoices( $args );
			$result_voices = $result['Voices'] ?? [];

			if ( is_array( $result_voices ) ) {
				$voices = array_merge( $voices, $result_voices );
			}

			$next_token = $result['NextToken'] ?? null;
		} while ( ! empty( $next_token ) );

		$this->sort_polly_voices_list( $voices );

		return [
			'Voices' => $voices,
		];
	}

	private function get_language_match_data( $language_code ) {
		$language_code = strtolower( (string) $language_code );
		$primary_language = strtok( $language_code, '-' );
		$match_data = [
			'exact' => [],
			'roots' => [],
		];

		if ( ! empty( $language_code ) ) {
			$match_data['exact'][] = $language_code;
		}

		if ( ! empty( $primary_language ) ) {
			$match_data['roots'][] = $primary_language;
		}

		$aliases = [
			'ar' => [
				'exact' => [ 'arb' ],
				'roots' => [ 'arb' ],
			],
			'no' => [
				'exact' => [ 'nb-no' ],
				'roots' => [ 'nb' ],
			],
			'zh' => [
				'exact' => [ 'cmn-cn', 'yue-cn' ],
				'roots' => [ 'cmn', 'yue' ],
			],
		];

		if ( isset( $aliases[ $language_code ] ) ) {
			$match_data['exact'] = array_merge( $match_data['exact'], $aliases[ $language_code ]['exact'] );
			$match_data['roots'] = array_merge( $match_data['roots'], $aliases[ $language_code ]['roots'] );
		}

		$match_data['exact'] = array_values( array_unique( array_filter( $match_data['exact'] ) ) );
		$match_data['roots'] = array_values( array_unique( array_filter( $match_data['roots'] ) ) );

		return $match_data;
	}

	private function is_polly_language_code_match( $voice_language_code, array $match_data ) {
		$voice_language_code = strtolower( (string) $voice_language_code );
		$voice_language_root = strtok( $voice_language_code, '-' );

		if ( in_array( $voice_language_code, $match_data['exact'], true ) ) {
			return true;
		}

		return in_array( $voice_language_root, $match_data['roots'], true );
	}

	private function voice_matches_language( array $voice, $language_code ) {
		$match_data = $this->get_language_match_data( $language_code );
		$voice_language_codes = [];

		if ( ! empty( $voice['LanguageCode'] ) ) {
			$voice_language_codes[] = $voice['LanguageCode'];
		}

		if ( ! empty( $voice['AdditionalLanguageCodes'] ) && is_array( $voice['AdditionalLanguageCodes'] ) ) {
			$voice_language_codes = array_merge( $voice_language_codes, $voice['AdditionalLanguageCodes'] );
		}

		foreach ( $voice_language_codes as $voice_language_code ) {
			if ( $this->is_polly_language_code_match( $voice_language_code, $match_data ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_supported_synthesis_engines( array $voice ) {
		$supported_engines = $voice['SupportedEngines'] ?? [ 'standard' ];

		if ( ! is_array( $supported_engines ) ) {
			$supported_engines = [ 'standard' ];
		}

		$supported_engines = array_values(
			array_intersect(
				$supported_engines,
				[ 'standard', 'neural' ]
			)
		);

		if ( empty( $supported_engines ) && empty( $voice['SupportedEngines'] ) ) {
			$supported_engines = [ 'standard' ];
		}

		return $supported_engines;
	}

	public function is_standard_supported_for_voice( $voice ) {
		$voice_data = is_array( $voice ) ? $voice : $this->get_polly_voice( $voice );

		if ( is_array( $voice_data ) ) {
			return in_array( 'standard', $this->get_supported_synthesis_engines( $voice_data ), true );
		}

		return ! $this->is_neural_only_voice( $voice );
	}

	public function get_polly_voice_capability( $voice ) {
		$voice_data = is_array( $voice ) ? $voice : $this->get_polly_voice( $voice );

		if ( is_array( $voice_data ) ) {
			$supported_engines = $this->get_supported_synthesis_engines( $voice_data );

			if ( in_array( 'standard', $supported_engines, true ) && in_array( 'neural', $supported_engines, true ) ) {
				return 'standard_neural';
			}

			if ( in_array( 'neural', $supported_engines, true ) ) {
				return 'neural_only';
			}
		}

		return 'standard';
	}

	public function get_polly_voice_capability_label( $voice ) {
		switch ( $this->get_polly_voice_capability( $voice ) ) {
			case 'standard_neural':
				return 'Standard + Neural';
			case 'neural_only':
				return 'Neural only';
			default:
				return 'Standard';
		}
	}

	public function is_polly_neural_requested() {
		return ! empty( get_option( 'amazon_polly_neural', '' ) );
	}

	public function is_voice_compatible_with_neural_setting( $voice, $neural_requested = null ) {
		if ( null === $neural_requested ) {
			$neural_requested = $this->is_polly_neural_requested();
		}

		if ( $neural_requested ) {
			return true;
		}

		return $this->is_standard_supported_for_voice( $voice );
	}

	public function get_available_polly_voices( $language_code = null ) {
		if ( empty( $language_code ) ) {
			$language_code = $this->get_source_language();
		}

		$voices = $this->get_polly_voices();
		$available_voices = [];

		foreach ( $voices['Voices'] ?? [] as $voice ) {
			if ( ! $this->voice_matches_language( $voice, $language_code ) ) {
				continue;
			}

			if ( empty( $this->get_supported_synthesis_engines( $voice ) ) ) {
				continue;
			}

			$available_voices[] = $voice;
		}

		$this->sort_polly_voices_list( $available_voices );

		return $available_voices;
	}

	public function get_compatible_polly_voices( $language_code = null, $neural_requested = null ) {
		$compatible_voices = [];

		foreach ( $this->get_available_polly_voices( $language_code ) as $voice ) {
			if ( ! $this->is_voice_compatible_with_neural_setting( $voice, $neural_requested ) ) {
				continue;
			}

			$compatible_voices[] = $voice;
		}

		return $compatible_voices;
	}

	public function get_grouped_polly_voices( $language_code = null ) {
		$groups = [
			'standard' => [
				'label' => 'Standard Voices',
				'voices' => [],
			],
			'standard_neural' => [
				'label' => 'Standard + Neural Voices',
				'voices' => [],
			],
			'neural_only' => [
				'label' => 'Neural-only Voices',
				'voices' => [],
			],
		];

		foreach ( $this->get_available_polly_voices( $language_code ) as $voice ) {
			$groups[ $this->get_polly_voice_capability( $voice ) ]['voices'][] = $voice;
		}

		return $groups;
	}

	public function get_polly_voice( $voice_id ) {
		try {
			foreach ( $this->get_polly_voices()['Voices'] ?? [] as $voice ) {
				if ( ! empty( $voice['Id'] ) && $voice['Id'] === $voice_id ) {
					return $voice;
				}
			}
		} catch ( Exception $e ) {
			return null;
		}

		return null;
	}

	public function resolve_polly_voice_id( $language_code, $requested_voice_id = '', $fallback_voice_id = '', array $args = [] ) {
		$neural_requested = $args['neural_requested'] ?? $this->is_polly_neural_requested();
		$available_voices = $this->get_compatible_polly_voices( $language_code, $neural_requested );

		if ( empty( $available_voices ) ) {
			return '';
		}

		$available_voice_ids = array_column( $available_voices, 'Id' );

		foreach ( [ $requested_voice_id, $fallback_voice_id ] as $candidate_voice_id ) {
			$candidate_voice_id = (string) $candidate_voice_id;
			if ( ! empty( $candidate_voice_id ) && in_array( $candidate_voice_id, $available_voice_ids, true ) ) {
				return $candidate_voice_id;
			}
		}

		return (string) $available_voices[0]['Id'];
	}

	public function get_resolved_polly_voice_option( $option_name, $language_code, $fallback_voice_id = '', array $args = [] ) {
		$current_voice_id = array_key_exists( 'requested_voice_id', $args )
			? (string) $args['requested_voice_id']
			: (string) get_option( $option_name, '' );

		return $this->resolve_polly_voice_id( $language_code, $current_voice_id, $fallback_voice_id, $args );
	}

	public function sync_polly_voice_option( $option_name, $language_code, $fallback_voice_id = '', array $args = [] ) {
		$current_voice_id = (string) get_option( $option_name, '' );
		$resolved_voice_id = $this->get_resolved_polly_voice_option( $option_name, $language_code, $fallback_voice_id, $args );

		if ( $resolved_voice_id !== $current_voice_id ) {
			if ( '' === $resolved_voice_id ) {
				delete_option( $option_name );
			} else {
				update_option( $option_name, $resolved_voice_id );
			}
		}

		return $resolved_voice_id;
	}

	public function get_source_language_name() {

    $selected_source_language = $this->get_source_language();

    foreach ($this->languages as $language_data) {
      $language = $language_data['code'];

      if (strcmp($selected_source_language, $language) === 0) {
        return $language_data['name'];
      }
    }

    return '';
  }

	public function init() {
		$aws_sdk_config = $this->get_aws_sdk_config();
		$this->sdk = new Aws\Sdk($aws_sdk_config);
		$this->polly_client = $this->sdk->createPolly();

		$this->s3_handler = new AmazonAI_S3FileHandler($this);
		$this->local_file_handler = new AmazonAI_LocalFileHandler($this);

        $this->s3_handler->set_s3_client($this->sdk->createS3());
    }


	/**
	 * Method returns file handler which is reponsible for communicating with proper storage location.
	 *
	 * @since       2.0.3
	 */
	public function get_file_handler() {

		$is_s3_enabled = $this->is_s3_enabled();
		if ( $is_s3_enabled ) {
		  return $this->s3_handler;
		} else {
			return $this->local_file_handler;
		}

	}

	public function get_polly_client() {
		return $this->polly_client;
	}

	/**
	 * Method removes ID3 tag from audio file
	 *
	 * @param           string $filename                 File for which tag should be removed.
	 * @since           1.0.0
	 */
	public function remove_id3( $filename, $wp_filesystem = null ) {
		if ( null === $wp_filesystem ) {
			$wp_filesystem = $this->prepare_wp_filesystem();
		}

		if ( ! is_object( $wp_filesystem ) || ! $wp_filesystem->exists( $filename ) ) {
			return;
		}

		$contents = $wp_filesystem->get_contents( $filename );
		if ( ! is_string( $contents ) || strlen( $contents ) < 10 ) {
			return;
		}

		$id3_header = substr( $contents, 0, 10 );
		if ( 'ID3' !== substr( $id3_header, 0, 3 ) ) {
			return;
		}

		// Calculating the total size of IDv3 tag.
		$int_value        = 0;
		$byte_word        = substr( $id3_header, 6, 4 );
		$byte_word_length = strlen( $byte_word );
		for ( $i = 0; $i < $byte_word_length; $i++ ) {
			$int_value += ( ord( $byte_word[$i] ) & 0x7F ) * pow( 2, ( $byte_word_length - 1 - $i ) * 7 );
		}
		$offset = ( (int) $int_value ) + 10;

		if ( $offset >= strlen( $contents ) ) {
			return;
		}

		$temp_filename = $filename . 'temp';
		$trimmed_audio = substr( $contents, $offset );
		if ( false === $wp_filesystem->put_contents( $temp_filename, $trimmed_audio ) ) {
			return;
		}

		$wp_filesystem->move( $temp_filename, $filename, true );

	}

	public function startsWith ($string, $beginning) {
	    $len = strlen($beginning);
	    return (substr($string, 0, $len) === $beginning);
	}

	public function endsWith($string, $ending) {
	    $len = strlen($ending);
	    if ($len == 0) {
	        return true;
	    }
	    return (substr($string, -$len) === $ending);
	}


	/**
	 * Checks if auto breaths are enabled.
	 *
	 * @since  1.0.7
	 */
	public function is_auto_breaths_enabled() {

		//If Neural TTS is enabled breaths SSML tags are not supported.
		$neural_enabled = $this->is_polly_neural_enabled();
		if ($neural_enabled) {
			return false;
		}

		$value = get_option( 'amazon_polly_auto_breaths', 'on' );

		if ( empty( $value ) ) {
			$result = false;
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Returns source language.
	 *
	 * @since  2.0.0
	 */
	public function get_post_source_language($post_id) {
		$value = get_post_meta( $post_id, 'amazon_ai_source_language', true );

		if (empty($value)) {
			$value = $this->get_source_language();
		}

		return $value;
	}

	/**
	 * Returns source language.
	 *
	 * @since  2.0.0
	 */
	public function get_source_language()
	{
		$value = get_option('amazon_ai_source_language', 'en');
		if (empty($value)) {
			$value = 'en';
		}

		return $value;
	}

	public function replace_if_empty($value, $new_value)
	{
		if (!empty($value)) {
			return $value;
		}
		else {
			return $new_value;
		}
	}


	public function is_polly_enabled_for_new_posts() {
		if ( $this->is_polly_enabled() ) {
			$default_configuration = get_option( 'amazon_polly_defconf' );
			if ( 'Amazon Polly enabled' === $default_configuration ) {
				return true;
			} else {
				return false;
			}
		}
	}

	public function is_audio_download_enabled() {
			$value = $this->checked_validator('amazon_ai_download_enabled');
			if ('checked' == trim($value)) {
				return true;
			} else {
				return false;
			}
	}

	/**
	 * Validates if logging is enabled.
	 *
	 * @since  2.6.1
	 */
	public function is_logging_enabled() {
			$value = $this->checked_validator('amazon_ai_logging');
			if ('checked' == trim($value)) {
				return true;
			} else {
				return false;
			}
	}

	/**
	 * Validates if Amazon Polly support is enabled.
	 *
	 * @since  2.5.0
	 */
	public function is_polly_enabled() {

		if (!$this->is_language_supported_for_polly()) {
			return false;
		}


			$value = $this->checked_validator('amazon_ai_polly_enable');
			if ('checked' == trim($value)) {
				return true;
			} else {
				return false;
			}
	}

	public function is_language_supported_for_polly() {

    $selected_source_language = $this->get_source_language();

    foreach ($this->get_all_polly_languages() as $language_code) {
      if (strcmp($selected_source_language, $language_code) === 0) {
        return true;
      }
    }

    return false;
  }

	public function get_s3_object_link($post_id, $language) {

		$file_name	= 'amazon_polly_' . $post_id . $language . '.mp3';
		$s3BucketName = AmazonAI_GeneralConfiguration::get_bucket_name();

		if ( get_option('uploads_use_yearmonth_folders') ) {
			$key = get_the_date( 'Y', $post_id ) . '/' . get_the_date( 'm', $post_id ) . '/' . $file_name;
		} else {
			$key = $file_name;
		}

		$selected_region = AmazonAI_GeneralConfiguration::get_aws_region();
		$audio_location_link = 'https://s3.' . $selected_region . '.amazonaws.com/' . $s3BucketName . '/' . $key;

		return $audio_location_link;

	}

	/**
	 * Validates if AWS configuration is correct and AWS can be reached.
	 *
	 * @since    2.5.0
	 * @return bool
	 */
	public function validate_amazon_polly_access( $persist_state = true, $show_notices = true ): bool {
		try {
			$this->is_s3_enabled() && $this->check_aws_access( $persist_state ) && $this->s3_handler->is_bucket_accessible();
		}

		catch (S3BucketNotAccException $e) {
			if ( $show_notices ) {
				$this->show_error_notice("notice-info", "The S3 bucket doesn't exist or can't be accessed.");
			}
			return false;
		}

		catch(CredsException $e) {
			if ( $persist_state ) {
				$this->deactivate_all();
			}
			if ( $show_notices ) {
				$this->show_error_notice("notice-error", "Can't connect to AWS. Check your AWS credentials.");
			}
			return false;
		}

		catch(S3BucketNotCreException $e) {
			if ( $show_notices ) {
				$this->show_error_notice("notice-error", "Could not create S3 bucket.");
			}
			return false;
		}

		catch(Exception $e) {
			if ( $persist_state ) {
				$this->deactivate_all();
			}
			if ( $show_notices ) {
				$this->show_error_notice("notice-error", "Unknown error.");
			}
			return false;
		}

		return true;
	}

	public function deactivate_all() {
		$this->deactivate_polly();
	}


	public function deactivate_polly() {
		update_option( 'amazon_ai_polly_enable', '' );
	}


	public function show_error_notice($type, $message)
	{
		add_action(
			'admin_notices',
			function () use ( $type, $message ) {
				printf(
					'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}
		);
	}

	public function normalize_sample_rate( $sample_rate ) {
		$sample_rate = (string) $sample_rate;

		if ( ! in_array( $sample_rate, array( '24000', '22050', '16000', '8000' ), true ) ) {
			return '24000';
		}

		return $sample_rate;
	}

	public function normalize_posttypes( $posttypes ) {
		if ( is_array( $posttypes ) ) {
			$posttypes = implode( ' ', $posttypes );
		}

		$posttypes = str_replace( ',', ' ', (string) $posttypes );
		$posttypes = preg_replace( '!\s+!', ' ', $posttypes );
		$posttypes = trim( (string) $posttypes );
		$posttypes = array_map( 'sanitize_key', array_filter( explode( ' ', $posttypes ) ) );
		$posttypes = array_values( array_filter( $posttypes ) );

		if ( empty( $posttypes ) ) {
			$posttypes = array( 'post' );
		}

		return implode( ' ', $posttypes );
	}

	public function normalize_audio_speed( $speed ) {
		$speed = trim( (string) $speed );

		if ( '' === $speed ) {
			$speed = '100';
		}

		$speed = (string) max( 20, min( 200, (int) $speed ) );

		return $speed;
	}


	public function get_sample_rate() {
		$sample_rate = $this->normalize_sample_rate( get_option( 'amazon_polly_sample_rate' ) );

		$this->logger->log(sprintf('%s Sample rate: %s ', __METHOD__, $sample_rate));

		return $sample_rate;
	}

	public function get_voice_id() {
		$voice_id = (string) get_option( 'amazon_polly_voice_id' );
		if ( empty( $voice_id ) ) {
			$voice_id = 'Matthew';
		}

		return $voice_id;
	}

	public function is_post_voice_override_disabled() {
		return ! empty( get_option( 'amazon_polly_disable_post_voice_override', '' ) );
	}

	public function is_post_voice_override_disabled_checked() {
		return $this->is_post_voice_override_disabled() ? ' checked ' : '';
	}

	/**
	 * @todo Move to AmazonAI_GeneralConfiguration
	 * Returns the name of the AWS region, which should be used by the plugin.
	 *
	 * @since    2.5.0
	 */
	public function get_aws_region() {
		return AmazonAI_GeneralConfiguration::get_aws_region();
	}

	public function get_polly_voices( $force_refresh = false )
	{
		$transient_key = $this->get_polly_voices_transient_key();

		if ( ! $force_refresh ) {
			$voices = get_transient( $transient_key );
			if ( is_array( $voices ) && isset( $voices['Voices'] ) ) {
				return $voices;
			}
		}

		$voices = $this->fetch_polly_voices_from_api();
		set_transient( $transient_key, $voices, self::POLLY_VOICES_TRANSIENT_TTL );

		return $voices;
	}

	/**
	 * Return post type value.
	 *
	 * @since  1.0.7
	 */
	public function get_posttypes()
	{
		return $this->normalize_posttypes( get_option( 'amazon_polly_posttypes', 'post' ) );
	}

	/**
	 * Return speed for audio files.
	 *
	 * @since  1.0.5
	 */
	public function get_audio_speed()
	{
		return $this->normalize_audio_speed( get_option( 'amazon_polly_speed' ) );
	}

	/**
	 * Method returns lexicons specified in plugin configuration.
	 *
	 * @since  1.0.12
	 */
	public function get_lexicons()
	{
		$lexicons = get_option('amazon_polly_lexicons', '');
		$lexicons = trim($lexicons);
		return $lexicons;
	}

	/**
	 * Check whether the optional public AWS Polly credit is enabled.
	 *
	 * @since  1.0.7
	 */
	public function is_poweredby_enabled()
	{
		$poweredby = get_option('amazon_polly_poweredby', '');

		if (empty($poweredby)) {
			$result = false;
		}
		else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Check if SSML is enabled.
	 *
	 * @since  1.0.7
	 */
	public function is_ssml_enabled()
	{
		$ssml_enabled = get_option('amazon_polly_ssml', 'on');
		if (empty($ssml_enabled)) {
			$result = false;
		}
		else {
			$result = true;
		}

		$is_s3_enabled = $this->is_s3_enabled();
		if ($is_s3_enabled) {
			return $result;
		}

		return false;
	}

	/**
	 * Utility function which checks if checkbox for option input should be checked.
	 *
	 * @param       string $option Name of the option which should be checked.
	 * @since  2.0.0
	 */
	public function checked_validator($option)
	{
		$option_value = get_option($option, 'on');
		if (empty($option_value)) {
			return '';
		}
		else {
			return ' checked ';
		}
	}

		public function is_polly_neural_enabled() {
			return $this->is_polly_neural_requested() ? ' checked ' : '';
		}

		public function normalize_polly_speaking_style( $style ) {
			$style = (string) $style;

			if ( in_array( $style, [ 'news', 'conversational' ], true ) ) {
				return $style;
			}

			return '';
		}

		public function get_requested_polly_speaking_style() {
			$style = get_option( 'amazon_polly_speaking_style', null );
			if ( null !== $style ) {
				return $this->normalize_polly_speaking_style( $style );
			}

			if ( ! empty( get_option( 'amazon_polly_news', '' ) ) ) {
				return 'news';
			}

			if ( ! empty( get_option( 'amazon_polly_conversational', '' ) ) ) {
				return 'conversational';
			}

			return '';
		}

		private function sync_legacy_polly_speaking_style_options( $style ) {
			update_option( 'amazon_polly_news', 'news' === $style ? 'on' : '' );
			update_option( 'amazon_polly_conversational', 'conversational' === $style ? 'on' : '' );
		}

		public function sync_polly_speaking_style( $style = null, $persist_style_option = true ) {
			if ( null === $style ) {
				$style = $this->get_requested_polly_speaking_style();
			}

			$style = $this->normalize_polly_speaking_style( $style );

			if ( $persist_style_option ) {
				update_option( 'amazon_polly_speaking_style', $style );
			}

			$this->sync_legacy_polly_speaking_style_options( $style );

			return $style;
		}

		public function get_active_polly_speaking_style( $voice = null, $neural_requested = null ) {
			if ( null === $voice ) {
				$voice = $this->get_voice_id();
			}

			if ( null === $neural_requested ) {
				$neural_requested = $this->is_polly_neural_requested();
			}

			if ( ! $neural_requested ) {
				return '';
			}

			$style = $this->get_requested_polly_speaking_style();
			if ( 'news' === $style && $this->is_news_style_for_voice( $voice ) ) {
				return 'news';
			}

			if ( 'conversational' === $style && $this->is_conversational_style_for_voice( $voice ) ) {
				return 'conversational';
			}

			return '';
		}

		public function is_polly_news_enabled() {

			return 'news' === $this->get_active_polly_speaking_style() ? ' checked ' : false;
		}


			public function is_polly_conversational_enabled() {

				return 'conversational' === $this->get_active_polly_speaking_style() ? ' checked ' : false;
			}

			public function should_conversational_style_be_used($voice) {

				if ( !$this->is_conversational_style_for_voice($voice)) {
					return false;
				}

				if ( 'conversational' === $this->get_requested_polly_speaking_style() ) {
					$engine = $this->get_polly_engine($voice);
					if ('neural' == $engine) {
						return true;
				}
				return false;
			}

			return false;
		}


		public function should_news_style_be_used($voice) {

			if ( !$this->is_news_style_for_voice($voice)) {
				return false;
			}

			if ( 'news' === $this->get_requested_polly_speaking_style() ) {
				$engine = $this->get_polly_engine($voice);
				if ('neural' == $engine) {
					return true;
			}
			return false;
		}

		return false;
	}


		public function is_conversational_supported_in_region() {

			$selected_region = AmazonAI_GeneralConfiguration::get_aws_region();
			$conversational_supported_regions = array("us-east-1","us-west-2","eu-west-1");

			if (in_array($selected_region, $conversational_supported_regions)) {
				return true;
			} else {
				return false;
			}
		}

	public function is_neural_supported_in_region() {

		$selected_region = AmazonAI_GeneralConfiguration::get_aws_region();
		$neural_supported_regions = array("us-east-1","us-west-2","ap-northeast-2","ap-southeast-1","ap-southeast-2","ap-northeast-1","ca-central-1","eu-central-1","eu-west-1","eu-west-2","us-gov-west-1");

		if (in_array($selected_region, $neural_supported_regions)) {
			return true;
		} else {
			return false;
		}
	}

	public function is_news_style_for_voice($voice) {
		$supported_voices = array("Joanna","Matthew","Lupe","Amy");

		if (in_array($voice, $supported_voices)) {
			return true;
		} else {
			return false;
		}
	}

	public function is_conversational_style_for_voice($voice) {
		$supported_voices = array("Joanna","Matthew");

		if (in_array($voice, $supported_voices)) {
			return true;
		} else {
			return false;
		}
	}

	public function is_neural_supported_for_voice($voice) {
		$voice_data = $this->get_polly_voice( $voice );
		if ( is_array( $voice_data ) ) {
			return in_array( 'neural', $this->get_supported_synthesis_engines( $voice_data ), true );
		}

		$neural_supported_voices = array("Olivia","Amy","Emma","Brian","Ivy","Joanna","Kendra","Kimberly","Salli","Joey","Justin","Kevin","Matthew","Camila","Lupe", "Seoyeon", "Gabrielle");

		if (in_array($voice, $neural_supported_voices)) {
			return true;
		} else {
			return false;
		}

	}

	public function is_neural_only_voice( $voice = null ) {
		if ( null === $voice ) {
			$voice = $this->get_voice_id();
		}

		$neural_only_voices = array("Olivia","Kevin", "Gabrielle");
		$logger = new AmazonAI_Logger();
		$voice_data = $this->get_polly_voice( $voice );

		$logger->log("Checking for neural: ".$voice);

		if ( is_array( $voice_data ) ) {
			$supported_engines = $this->get_supported_synthesis_engines( $voice_data );
			$is_neural_only = in_array( 'neural', $supported_engines, true ) && ! in_array( 'standard', $supported_engines, true );

			$logger->log("Neural only: " . ( $is_neural_only ? "TRUE" : "FALSE" ));

			return $is_neural_only;
		}

		if (in_array($voice, $neural_only_voices)) {
			$logger->log("Neural only: TRUE");
			return true;
		} else {
			$logger->log("Neural only: FALSE");
			return false;
		}

	}

	public function get_polly_engine($voice) {
		if (!$this->is_neural_supported_in_region()) {
			return 'standard';
		}

		if (!$this->is_neural_supported_for_voice($voice)) {
			return 'standard';
		}


		if ( $this->is_polly_neural_requested() ) {
			return 'neural';
		}

		return 'standard';

	}


	/**
	 * Checks if Media Libary support is enabled.
	 *
	 * @since  2.5.0
	 */
	public function is_medialibrary_enabled()
	{
		$value = get_option('amazon_ai_medialibrary_enabled');
		if (empty($value)) {
			$result = false;
		}
		else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Checks if S3 storage is enabled.
	 *
	 * @since  1.0.7
	 */
	public function is_s3_enabled()
	{
		$value = get_option('amazon_polly_s3', 'on');
		if (empty($value)) {
			$result = false;
		}
		else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Check  connectivity with AWS can be eastablished.
	 *
	 * @since    2.5.0
	 */
	private function check_aws_access( $persist_state = true )
	{
		try {
			$voice_list = $this->get_polly_voices( true );
			if ( $persist_state ) {
				update_option('amazon_polly_valid_keys', '1');
			}
			return true;
		}

		catch(Exception $e) {
			if ( $persist_state ) {
				update_option('amazon_polly_valid_keys', '0');
			}
			throw new CredsException('Could not connect to AWS. Check your AWS credentials.');
		}
	}

	/**
	 * Returns AWS SDK configuration to allow connection with AWS account.
	 *
	 * @since    2.5.0
	 */
	private function get_aws_sdk_config($region = null)
	{
		$aws_sdk_config = [
			'region' => AmazonAI_GeneralConfiguration::get_aws_region(),
			'version' => 'latest',
			'ua_append' => ['request-source/aws-for-wordpress']
		];
		$credentials = false;
		$aws_access_key = AmazonAI_GeneralConfiguration::get_aws_access_key();
		$aws_secret_key = AmazonAI_GeneralConfiguration::get_aws_secret_key();

		if ($aws_access_key && $aws_secret_key) {
			$credentials = [
				'key' => $aws_access_key,
				'secret' => $aws_secret_key,
			];
		}

		if ($credentials = apply_filters('amazon_polly_aws_sdk_credentials', $credentials)) {
			$aws_sdk_config['credentials'] = $credentials;
		}

		if ($region) {
			$aws_sdk_config['region'] = $region;
		}

		return $aws_sdk_config;
	}

	/**
	 * Calculate the total price of converting all posts into audio.
	 *
	 * @since  1.0.0
	 */
	public function get_price_message_for_update_all()
	{
		$post_types_supported = $this->get_posttypes_array();
		$number_of_characters = 0;
		$posts_per_page = apply_filters('amazon_polly_posts_per_page', 5);
		$count_posts = wp_count_posts()->publish;
		$max_count_posts = 100;

		// Retrieving the number of characters in all posts.

		$paged = 0;
		$post_count = 0;
		do {
			$paged++;
			$wp_query = new WP_Query(array(
				'posts_per_page' => $posts_per_page,
				'post_type' => $post_types_supported,
				'fields' => 'ids',
				'paged' => $paged,
			));
			$number_of_posts = $wp_query->max_num_pages;
			while ($wp_query->have_posts()) {
				$post_count++;
				$wp_query->the_post();
				$post_id = get_the_ID();
				$clean_text = $this->clean_text($post_id, true, false);
				$post_sentences = $this->break_text($clean_text);
				if (!empty($post_sentences)) {
					foreach($post_sentences as $sentence) {
						$sentence = str_replace('**AMAZONPOLLY*SSML*BREAK*time=***1s***SSML**', '', $sentence);
						$sentence = str_replace('**AMAZONPOLLY*SSML*BREAK*time=***500ms***SSML**', '', $sentence);
						$number_of_characters+= strlen($sentence);
					}
				}
			}

			// If we reached the number of posts which we wanted to read, we stop
			// reading next posts.

			if ($post_count >= $max_count_posts) {
				break;
			}
		}

		while ($paged < $number_of_posts);

		// Price for converting single character according to Amazon Polly pricing.

		$amazon_polly_price = 0.000004;

		// Estimating average number of characters per post.

		if (0 !== $post_count) {
			$post_chars_count_avg = $number_of_characters / $post_count;
		}
		else {
			$post_chars_count_avg = 0;
		}

		// Estimating the total price of convertion of all posts.

		$total_price = 2 * $amazon_polly_price * $count_posts * $post_chars_count_avg;
		$message = 'You are about to convert ' . number_format($count_posts, 0, '.', ',') . ' pieces of text-based content, which totals approximately ' . number_format($number_of_characters, 0, '.', ',') . ' characters. Based on the Amazon Polly pricing ($4 dollars per 1 million characters) it will cost you about $' . $total_price . ' to convert all of your content into to speech-based audio. Some or all of your costs might be covered by the Free Tier (conversion of 5 million characters per month for free, for the first 12 months, starting from the first request for speech). For more information, see https://aws.amazon.com/polly/';
		return $message;
	}

	/**
	 * Method prepare WP_Filesystem variable for interacting with local file system.
	 *
	 * @since    1.0.0
	 */
	public function prepare_wp_filesystem() {
		/** Ensure WordPress Administration File API is loaded as REST requests do not load the file API */
		require_once(ABSPATH . 'wp-admin/includes/file.php');

		$url   = wp_nonce_url( admin_url( 'post-new.php' ) );
		$creds = request_filesystem_credentials( $url );

		if ( ! WP_Filesystem( $creds ) ) {
			request_filesystem_credentials( $url );
			return true;
		}

		global $wp_filesystem;

		return $wp_filesystem;
	}

	/**
	 * Method breakes input text into smaller parts.
	 *
	 * @since       1.0.0
	 * @param       string $text     Text which should be broken.
	 */
	public function break_text($text)
	{
		$text = str_replace('-AMAZONPOLLY-ONLYAUDIO-START-', '', $text);
		$text = str_replace('-AMAZONPOLLY-ONLYAUDIO-END-', '', $text);
		$text = preg_replace('/-AMAZONPOLLY-ONLYWORDS-START-[\S\s]*?-AMAZONPOLLY-ONLYWORDS-END-/', '', $text);
		$parts = [];
		if (!empty($text)) {
			$part_id = 0;
			$paragraphs = explode("\n", $text);
			foreach($paragraphs as $paragraph) {
				$paragraph_size = strlen(trim($paragraph));
				if ($paragraph_size > 0) {
					if ($paragraph_size <= 2800) {
						$parts[$part_id] = $paragraph . ' **AMAZONPOLLY*SSML*BREAK*time=***500ms***SSML** ';
						$part_id++;
					}
					else {
						$words = explode(' ', $paragraph);
						$current_part = '';
						$last_part = '';
						foreach($words as $word) {
							$word_length = strlen($word);
							$current_part_length = strlen($current_part);
							if ($word_length + $current_part_length < 2800) {
								$current_part = $current_part . $word . ' ';
								$last_part = $current_part;
							}
							else {
								$current_part = $current_part . $word . ' ';
								$parts[$part_id] = $current_part;
								$part_id++;
								$current_part = '';
								$last_part = '';
							}
						}

						$parts[$part_id] = $last_part . ' **AMAZONPOLLY*SSML*BREAK*time=***500ms***SSML** ';
						$part_id++;
					} //end if
				} //end if
			} //end foreach
		} //end if

		// Modify speed

		$parts = $this->modify_speed($parts);

		$logger = new AmazonAI_Logger();

		foreach($parts as $part) {
			$logger->log(sprintf('%s <<< PART >>> ', __METHOD__));
			$logger->log(sprintf('%s', $part));
		}

		return $parts;
	}

	/**
	 * Method update sentences (input of the method), and modify their speed,
	 * by adding SSML prosody tag for each sentence.
	 *
	 * @param           string $sentences                 Sentences which should be updated.
	 * @since           1.0.5
	 */
	public function modify_speed($sentences)
	{
		$new_sentences = [];
		$new_sentence_id = 0;
		$speed = $this->get_audio_speed();
		if (100 !== $speed) {
			foreach($sentences as $sentence) {
				$new_sentence = '<prosody rate="' . $speed . '%">' . $sentence . '</prosody>';
				$new_sentences[$new_sentence_id] = $new_sentence;
				$new_sentence_id++;
			}
		}

		return $new_sentences;
	}

	public function modify_sentence_speed($sentence)
	{

		$speed = $this->get_audio_speed();
		if (100 !== $speed) {
				$sentence = '<prosody rate="' . $speed . '%">' . $sentence . '</prosody>';
		}


		return $sentence;
	}

	/**
	 * Checks if post title should be added.
	 *
	 * @since  1.0.7
	 */
	public function is_excerpt_adder_enabled()
	{
		$value = get_option('amazon_polly_add_post_excerpt', 'on');
		if (empty($value)) {
			$result = false;
		}
		else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Encode SSML tags.
	 *
	 * @since  1.0.7
	 * @param  string $text text which should be decoded.
	 */
	public function decode_ssml_tags( $text ) {

		$text = preg_replace( '/(\*\*AMAZONPOLLY\*SSML\*BREAK\*)(.*?)(\*\*\*)(.*?)(\*\*\*SSML\*\*)/', '<break $2"$4"/>', $text );

		return $text;
	}

	public function get_audio_hash( $post_id ): string {
		return md5( get_post_field( 'post_modified', $post_id ) );
	}

	/**
	 * Method retrievies post which ID was provided, and clean it.
	 *
	 * @since       1.0.12
	 * @param       string $post_id     ID of the post for which test (content) should be prepapred for conversion.
	 */
	public function clean_text($post_id, $with_title, $only_title)
	{

		#$this->logger->log(sprintf('%s Cleaning text (%s, %s) ', __METHOD__, $with_title, $only_title));

		$clean_text = '';

		// Depending on the plugin configurations, post's title will be added to the audio.
		if ($with_title) {
			if ($this->is_title_adder_enabled()) {
				$clean_text = get_the_title($post_id) . '. **AMAZONPOLLY*SSML*BREAK*time=***1s***SSML** ';
			}
		}


		// Depending on the plugin configurations, post's excerpt will be added to the audio.

		if ($this->is_excerpt_adder_enabled()) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter.
				$my_excerpt = apply_filters('the_excerpt', get_post_field('post_excerpt', $post_id));
			$clean_text = $clean_text . $my_excerpt . ' **AMAZONPOLLY*SSML*BREAK*time=***1s***SSML** ';
		}

		$clean_text = $clean_text . get_post_field('post_content', $post_id);
		$clean_text = apply_filters('amazon_polly_content', $clean_text);

		if ($only_title) {
			$clean_text = get_the_title($post_id);
		}

		$clean_text = str_replace('&nbsp;', ' ', $clean_text);
		$clean_text = do_shortcode($clean_text);

		$clean_text = $this->skip_tags($clean_text);
		$clean_text = $this->add_pauses($clean_text);

		$is_ssml_enabled = $this->is_ssml_enabled();
		if ($is_ssml_enabled) {
			$clean_text = $this->encode_ssml_tags($clean_text);
		}

		// Creating text description for images
		$clean_text = $this->replace_images($clean_text);
		$clean_text = strip_tags($clean_text, '<break>');
		$clean_text = esc_html($clean_text);


		$clean_text = str_replace('&nbsp;', ' ', $clean_text);
		$clean_text = preg_replace("/https:\/\/([^\s]+)/", "", $clean_text);
		$clean_text_temp = '';

		$paragraphs = explode("\n", $clean_text);
		foreach($paragraphs as $paragraph) {
			$paragraph_size = strlen(trim($paragraph));
			if ($paragraph_size > 0) {
				$clean_text_temp = $clean_text_temp . "\n" . $paragraph;
			}
		}

		$clean_text = $clean_text_temp;
		$clean_text = html_entity_decode($clean_text, ENT_QUOTES, 'UTF-8');
		$clean_text = str_replace('&', ' and ', $clean_text);
		$clean_text = str_replace('<', ' ', $clean_text);
		$clean_text = str_replace('>', ' ', $clean_text);


		return $clean_text;
	}

	private function replace_images($clean_text) {

		//$new_clean_text = preg_replace('/<img.*?alt="(.*?)"[^\>]+>/', 'Image: $1.', $clean_text);
		$new_clean_text = preg_replace('/<img.*?alt="(.*?)"[^\>]+>/', '$1', $clean_text);

		return $new_clean_text;

	}

	/**
	 * Run when deleting a post.
	 *
	 * @param      string $post_id   ID of the post which is gonna to be deleted.
	 * @since    1.0.0
	 */
	public function delete_post( $post_id ) {
		// Check if this isn't an auto save.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'attachment' === get_post_type( $post_id ) ) {
			return;
		}

		$post_types_supported = $this->get_posttypes_array();
		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, $post_types_supported, true ) ) {
			return;
		}

		$this->delete_post_audio( $post_id );
	}

	private function get_audio_state_meta_keys() {
		return array(
			'amazon_polly_audio_link_location',
			'amazon_polly_audio_location',
			'amazon_polly_generated_voice_id',
			'amazon_polly_audio_playtime',
			'amazon_polly_audio_hash',
			'amazon_polly_media_library_attachment_id',
			'amazon_polly_settings_hash',
			'amazon_polly_transcript_source_lan',
		);
	}

	private function get_file_handler_for_audio_location( $audio_location ) {
		if ( 's3' === $audio_location ) {
			return $this->s3_handler;
		}

		if ( 'local' === $audio_location ) {
			return $this->local_file_handler;
		}

		return $this->get_file_handler();
	}

	public function clear_post_audio_state_meta( int $post_id ): void {
		foreach ( $this->get_audio_state_meta_keys() as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}

		foreach ( $this->get_all_polly_languages() as $language_code ) {
			delete_post_meta( $post_id, 'amazon_polly_translation_' . $language_code );
			delete_post_meta( $post_id, 'amazon_polly_transcript_' . $language_code );
		}
	}

	public function clear_post_audio_runtime_cache( int $post_id ): void {
		do_action( 'amazon_polly_clear_post_audio_runtime_cache', $post_id );
	}

	public function clear_post_audio_state( int $post_id ): void {
		$media_library_att_id = (int) get_post_meta( $post_id, 'amazon_polly_media_library_attachment_id', true );
		if ( $media_library_att_id > 0 ) {
			wp_delete_attachment( $media_library_att_id, true );
		}

		$this->clear_post_audio_state_meta( $post_id );
		$this->set_post_audio_state( $post_id, self::AUDIO_STATE_NONE );
		$this->clear_post_audio_runtime_cache( $post_id );
	}

	/**
	 * Delets audio for specific post.
	 *
	 * @param string $post_id ID of the post for which audio should be deleted.
	 * @since 1.0.0
	 */
	public function delete_post_audio( $post_id ) {
		$deletion_error = null;

		try {
			// Deleting audio file.
			$this->init();

			$audio_location = get_post_meta( $post_id, 'amazon_polly_audio_location', true );
			$file           = 'amazon_polly_' . $post_id . '.mp3';
			$wp_filesystem  = $this->prepare_wp_filesystem();
			$file_handler   = $this->get_file_handler_for_audio_location( $audio_location );
			$file_handler->delete($wp_filesystem, $file, $post_id);
		} catch(Exception $e) {
			$deletion_error = $e;
		}

		$this->clear_post_audio_state( (int) $post_id );

		if ( $deletion_error ) {
			$this->show_error_notice("notice-error", "Encountered an error while deleting the file.");
			$logger = new AmazonAI_Logger();
			$logger->log( sprintf( '%s Delete post audio failed: %s', __METHOD__, $deletion_error->getMessage() ) );
		}

	}

	private function skip_tags($text) {

		$skip_tags_array = $this->get_skiptags_array();

		foreach ($skip_tags_array as $value) {
			$text = preg_replace('/<' . $value . '>(\s*?)(.*?)(\s*?)<\/' . $value . '>/', '', $text);
		}

		return $text;
	}

	private function add_pauses($text) {

		#Creates a little pause after closes the tag <li>
		$text = str_replace ('</li>',' **AMAZONPOLLY*SSML*BREAK*time=***300ms***SSML** </li>',$text);

		#Create a support to the tag <sub> (helpful to 'read' abreviations, for example)
		$text = preg_replace('/<sub\b((?:(?:\s+alias="(.*?)")|[^\s>]+|\s+))*>([\s\S]*?)<\/sub>/', '$2', $text);

		return $text;
	}

	/**
	 * Encode SSML tags.
	 *
	 * @since  1.0.7
	 * @param  string $text text which should be encoded.
	 */
	private function encode_ssml_tags($text)
	{
		$text = preg_replace('/<ssml><break ([\S\s]*?)["\'](.*?)["\'](.*?)<\/ssml>/', '**AMAZONPOLLY*SSML*BREAK*$1***$2***SSML**', $text);
		return $text;
	}

	/**
	 * Checks if post title should be added.
	 *
	 * @since  1.0.7
	 */
	public function is_title_adder_enabled()
	{
		$value = get_option('amazon_polly_add_post_title', 'on');
		if (empty($value)) {
			$result = false;
		}
		else {
			$result = true;
		}

		return $result;
	}


	/**
	 * Add an SSML QuickTag button for the classic editor.
	 *
	 * @since    1.0.7
	 */
	public function add_quicktags() {
		if ( $this->is_ssml_enabled() && wp_script_is( 'quicktags' ) ) {
			wp_add_inline_script(
				'quicktags',
				"QTags.addButton('itron_aws_polly_ssml_break', 'SSML Break', '<ssml><break time=\"1s\"/></ssml>', '', '', 'AWS Polly SSML Break Tag', 111);"
			);
		}
	}

  /**
	 * Configure supported HTML tags.
	 *
	 * @since  1.0.7
	 * @param  string $tags supported tags.
	 */
	public function allowed_tags_tinymce( $tags ) {
		$ssml_tags                       = array(
			'ssml',
			'speak',
			'break[time|whatever]',
			'emphasis[level]',
			'lang',
			'mark',
			'paragraph',
			'phoneme',
			'prosody',
			's',
			'say-as',
			'sub',
			'w',
			'amazon:breath',
			'amazon:auto-breaths',
			'amazon:effect[name]',
			'amazon:effect[phonation]',
			'amazon:effect[vocal-tract-length]',
			'amazon:effect[name]',
		);
		$tags['extended_valid_elements'] = implode( ',', $ssml_tags );
		return $tags;
	}

  /**
	 * Configure supported HTML tags.
	 *
	 * @since  1.0.7
	 * @param  string $tags supported tags.
	 */
	public function allowed_tags_kses( $tags ) {
		$tags['ssml']  = true;
		$tags['speak'] = true;
		$tags['break'] = array(
			'time' => true,
		);
		return $tags;
	}

	/**
	 * Return skip tags array.
	 *
	 * @since  1.0.7
	 */
	public function get_skiptags_array() {
		$array = get_option( 'amazon_ai_skip_tags' );
		$array = explode( ' ', $array );

		return $array;

	}

	/**
	 * Return post type value array.
	 *
	 * @since  1.0.7
	 */
	public function get_posttypes_array() {
		$posttypes_array = explode( ' ', $this->get_posttypes() );
		$posttypes_array = array_values( array_filter( $posttypes_array ) );
		$posttypes_array = apply_filters( 'amazon_polly_post_types', $posttypes_array );

		return $posttypes_array;

	}

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
	private function get_asset_version( string $relative_path ): string {
		$asset_path = plugin_dir_path( __FILE__ ) . ltrim( $relative_path, '/' );

		if ( file_exists( $asset_path ) ) {
			return (string) filemtime( $asset_path );
		}

		return '1.0.1';
	}

    public function enqueue_styles() {
        wp_enqueue_style( 'itron-aws-polly-admin', plugin_dir_url( __FILE__ ) . 'css/amazonpolly-admin.css', array(), $this->get_asset_version( 'css/amazonpolly-admin.css' ), 'all' );
        wp_enqueue_style( 'itron-aws-polly-font-awesome', plugin_dir_url( __FILE__ ) . 'css/all.min.css', array(), $this->get_asset_version( 'css/all.min.css' ), 'all' );
        wp_enqueue_style( 'jquery-ui-core' );
        wp_enqueue_style( 'jquery-ui-progressbar' );
    }

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'itron-aws-polly-admin', plugin_dir_url( __FILE__ ) . 'js/amazonpolly-admin.js', array( 'jquery' ), $this->get_asset_version( 'js/amazonpolly-admin.js' ), false );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-progressbar' );
		wp_localize_script(
			'itron-aws-polly-admin',
			'itronAwsPollyAdmin',
			array(
				'ajaxAction' => 'itron_aws_polly_transcribe',
				'ajaxNonce'  => wp_create_nonce( 'pollyajaxnonce' ),
			)
		);

    }

	/**
	 * Register meta box for 'Enable Amazon Polly' on post creation form.
	 *
	 * @since    1.0.0
	 */
	public function field_checkbox() {

		$post_types_supported = $this->get_posttypes_array();

		$meta_box = new AmazonAI_PostMetaBox($this);

		add_meta_box(
			'amazon_polly_box_id',
			// This is HTML id of the box on edit screen.
			'Amazon Polly',
			// Title of the box.
			[ $meta_box, 'display_box_content'],
			// Function to be called to display the checkboxes, see the function below.
			$post_types_supported,
			// On which edit screen the box should appear.
			'normal',
			// Part of page where the box should appear.
			'high'
			// Priority of the box.
		);
	}

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=amazon_ai">Settings</a>';
        array_push( $links, $settings_link );
        return $links;
    }
}
