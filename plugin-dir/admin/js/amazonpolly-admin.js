/**
 * Additional JS if needed. All of the code for your admin-facing JavaScript source should reside in this file.
 *
 * @link       amazon.com
 * @since      1.0.0
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin/js
 */

	(function( $ ) {
		'use strict';

		function getSelectedPollyVoiceOption() {
			var voiceSelect = $( '#amazon_polly_voice_id' );
			if ( ! voiceSelect.length ) {
				return $();
			}

			return voiceSelect.find( 'option:selected' );
		}

		function getSelectedPollySpeakingStyleInput() {
			return $( 'input[name="amazon_polly_speaking_style"]:checked' );
		}

		function getFirstPollyVoiceValue( predicate ) {
			var voiceSelect = $( '#amazon_polly_voice_id' );
			var fallbackValue = null;
			if ( ! voiceSelect.length ) {
				return fallbackValue;
			}

			voiceSelect.find( 'option' ).each(
				function() {
					var option = $( this );
					if ( ! option.val() ) {
						return;
					}

					if ( predicate( option ) ) {
						fallbackValue = option.val();
						return false;
					}
				}
			);

			return fallbackValue;
		}

		function syncPollyVoiceSelectWithNeural( triggerSource ) {
			var neuralCheckbox = $( '#amazon_polly_neural' );
			var voiceSelect = $( '#amazon_polly_voice_id' );
			var selectedOption;
			var isNeuralOnly;
			var supportsNeural;
			var supportsStandard;
			var standardFallbackValue;

			if ( ! neuralCheckbox.length || ! voiceSelect.length ) {
				return;
			}

			selectedOption = getSelectedPollyVoiceOption();
			if ( ! selectedOption.length ) {
				return;
			}

			isNeuralOnly = '1' === String( selectedOption.data( 'neural-only' ) );
			supportsNeural = '1' === String( selectedOption.data( 'supports-neural' ) );
			supportsStandard = '1' === String( selectedOption.data( 'standard-supported' ) );

			if ( 'voice' === triggerSource || 'init' === triggerSource ) {
				if ( isNeuralOnly && ! neuralCheckbox.is( ':checked' ) ) {
					neuralCheckbox.prop( 'checked', true );
				} else if ( supportsStandard && ! supportsNeural && neuralCheckbox.is( ':checked' ) ) {
					neuralCheckbox.prop( 'checked', false );
					$( '#amazon_polly_speaking_style_default' ).prop( 'checked', true );
				}
			}

			if ( 'neural' === triggerSource ) {
				if ( ! neuralCheckbox.is( ':checked' ) && isNeuralOnly ) {
					standardFallbackValue = getFirstPollyVoiceValue(
						function( option ) {
							return '1' === String( option.data( 'standard-supported' ) );
						}
					);

					if ( standardFallbackValue && standardFallbackValue !== voiceSelect.val() ) {
						voiceSelect.val( standardFallbackValue );
						voiceSelect.trigger( 'change.select2' );
					} else if ( ! standardFallbackValue ) {
						neuralCheckbox.prop( 'checked', true );
						return;
					}
				}

				if ( ! neuralCheckbox.is( ':checked' ) ) {
					$( '#amazon_polly_speaking_style_default' ).prop( 'checked', true );
				}
			}
		}

		function syncPollyDynamicOption( optionId, isAvailable, message ) {
			var container = $( '#' + optionId + '_ui' );
			var checkbox = $( '#' + optionId );
			if ( ! container.length || ! checkbox.length ) {
				return;
			}

			var input = container.find( '.amazon-polly-dynamic-option-input' );
			var description = container.find( '.amazon-polly-dynamic-option-description' );
			var statusMessage = container.find( '.amazon-polly-dynamic-option-message' );

			if ( isAvailable ) {
				input.show();
				description.show();
				statusMessage.hide();
				checkbox.prop( 'disabled', false );
				return;
			}

			checkbox.prop( 'checked', false );
			input.hide();
			description.hide();
			statusMessage.text( message || '' ).show();
			checkbox.prop( 'disabled', true );
		}

		function syncPollySpeakingStyleState() {
			var selectedOption = getSelectedPollyVoiceOption();
			if ( ! selectedOption.length ) {
				return;
			}

			var supportsNews = '1' === String( selectedOption.data( 'supports-news' ) );
			var supportsConversational = '1' === String( selectedOption.data( 'supports-conversational' ) );
			var neuralCheckbox = $( '#amazon_polly_neural' );
			var neuralRequested = neuralCheckbox.length && ! neuralCheckbox.prop( 'disabled' ) && neuralCheckbox.is( ':checked' );
			var neuralContainer = $( '#amazon_polly_neural_ui' );
			var speakingStyleContainer = $( '#amazon_polly_speaking_style_ui' );
			var isNeuralRegionSupported = '1' === String( neuralContainer.data( 'region-supported' ) );
			var styleInput = speakingStyleContainer.find( '.amazon-polly-dynamic-option-input' );
			var styleDescription = speakingStyleContainer.find( '.amazon-polly-dynamic-option-description' );
			var styleMessage = speakingStyleContainer.find( '.amazon-polly-dynamic-option-message' );
			var newsChoice = speakingStyleContainer.find( '.amazon-polly-style-choice-news' );
			var conversationalChoice = speakingStyleContainer.find( '.amazon-polly-style-choice-conversational' );
			var defaultChoice = speakingStyleContainer.find( '.amazon-polly-style-choice-default input' );
			var selectedStyle = getSelectedPollySpeakingStyleInput().val() || '';
			var hasSupportedStyle = supportsNews || supportsConversational;
			var canShowStyles = isNeuralRegionSupported && neuralRequested && hasSupportedStyle;
			var unavailableStyleSelected = ( 'news' === selectedStyle && ! supportsNews ) || ( 'conversational' === selectedStyle && ! supportsConversational );
			var styleUnavailableMessage = '';

			if ( neuralContainer.length ) {
				var supportsNeural = '1' === String( selectedOption.data( 'supports-neural' ) );
				syncPollyDynamicOption(
					'amazon_polly_neural',
					isNeuralRegionSupported && supportsNeural,
					isNeuralRegionSupported ? neuralContainer.data( 'message-voice' ) : neuralContainer.data( 'message-region' )
				);
			}

			if ( ! speakingStyleContainer.length ) {
				return;
			}

			newsChoice.toggle( supportsNews );
			newsChoice.find( 'input' ).prop( 'disabled', ! supportsNews );
			conversationalChoice.toggle( supportsConversational );
			conversationalChoice.find( 'input' ).prop( 'disabled', ! supportsConversational );

			if ( unavailableStyleSelected || ! canShowStyles ) {
				defaultChoice.prop( 'checked', true );
			}

			if ( ! isNeuralRegionSupported ) {
				styleUnavailableMessage = speakingStyleContainer.data( 'message-region' );
			} else if ( ! neuralRequested ) {
				styleUnavailableMessage = speakingStyleContainer.data( 'message-neural' );
			} else if ( ! hasSupportedStyle ) {
				styleUnavailableMessage = speakingStyleContainer.data( 'message-voice' );
			}

			if ( canShowStyles ) {
				styleInput.show();
				styleDescription.show();
				styleMessage.hide();
				defaultChoice.prop( 'disabled', false );
				newsChoice.find( 'input' ).prop( 'disabled', ! supportsNews );
				conversationalChoice.find( 'input' ).prop( 'disabled', ! supportsConversational );
				return;
			}

			styleInput.hide();
			styleDescription.hide();
			styleMessage.text( styleUnavailableMessage || '' ).show();
			defaultChoice.prop( 'disabled', false );
			speakingStyleContainer.find( 'input[name="amazon_polly_speaking_style"]' ).not( defaultChoice ).prop( 'disabled', true );
		}

		function refreshPollySettingsUi( triggerSource ) {
			syncPollyVoiceSelectWithNeural( triggerSource );
			syncPollySpeakingStyleState();
		}

		function amazonPollyProcessStep() {

		var amazonPollyProgressbar = $( "#amazon-polly-progressbar" );

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'polly_transcribe',
				nonce: pollyajax.nonce,
			},
			dataType: "json",
			beforeSend: function() {
				$('.amazon-polly-progress-label').show();
			},
			complete: function() {
			},
			success: function( response ) {
				if( 'done' == response.step ) {

				} else {
					amazonPollyProcessStep();
				}

				$( "#amazon-polly-progressbar" ).progressbar({
					value: response.percentage
				});

				amazonPollyProgressbar.progressbar( "value", response.percentage);
			}
		}).fail(function (response) {
			if ( window.console && window.console.log ) {
				console.log( response );
			}
		});
	};



	function amazonPollyTransProcessStep(phase, langs) {

		var amazonPollyTransProgressbar = $( "#amazon_polly_trans-progressbar" );
		var amazonPollyTransProgressLabel = $( ".amazon_polly_trans-label" );

		var post_id = $( "#post_ID" ).val();


		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'polly_translate',
				phase: phase,
				langs: langs,
				post_id: post_id,
				nonce: pollyajax.nonce,
			},
			dataType: "json",
			beforeSend: function() {
				$('.amazon_polly_trans-label').show();
			},
			complete: function() {
			},
			success: function( response ) {
				if( 'done' == response.step ) {

				} else {
					amazonPollyTransProcessStep('continue',response.langs);
				}

				$( "#amazon_polly_trans-progressbar" ).progressbar({
					value: response.percentage
				});

				amazonPollyTransProgressbar.progressbar( "value", response.percentage);

				amazonPollyTransProgressLabel.text( response.message );
			}
		}).fail(function (response) {
			if ( window.console && window.console.log ) {
				console.log( response );
			}
		});
	};



	$( document ).ready(
		function(){

			var amazonPollyProgressbar = $( "#amazon-polly-progressbar" );
			var amazonPollyProgressLabel = $( ".amazon-polly-progress-label" );

			$( '#amazon_polly_batch_transcribe' ).click(
				function(){
					$('#amazon_polly_batch_transcribe').hide();

					amazonPollyProgressbar.progressbar({
				      value: false,
				      change: function() {
				        amazonPollyProgressLabel.text( "Starting" );
				      },
				      complete: function() {
				        amazonPollyProgressLabel.text( "Complete!" );
				      }
				    });
					amazonPollyProcessStep();
				}
			);


			var amazonPollyTraProgressbar = $( "#amazon_polly_trans-progressbar" );
			var amazonPollyTraProgressLabel = $( ".amazon_polly_trans-label" );

			$( '#amazon_polly_trans_button' ).click(
				function(){
					$('#amazon_polly_trans_button').hide();
					$('#amazon-polly-trans-info').hide();

					amazonPollyTraProgressbar.progressbar({
							value: false,
							change: function() {
								amazonPollyTraProgressLabel.text( amazonPollyTraProgressbar.progressbar( "value" ) + "%" );
							},
							complete: function() {
								amazonPollyTraProgressLabel.text( "Translation completed!" );
							}
						});
					amazonPollyTransProcessStep('start','');
				}
			);

			$( '#amazon_polly_s3' ).change(
				function() {
					if ($( "#amazon_polly_s3" ).is( ':checked' )) {
						$( "#amazon_polly_s3_bucket_name_box" ).show();
						$( "#amazon_polly_cloudfront" ).prop( "disabled", false );
						$( "#amazon_polly_cloudfront_learnmore" ).prop( "disabled", false );
					} else {
						$( "#amazon_polly_s3_bucket_name_box" ).hide();
						$( "#amazon_polly_cloudfront" ).prop( "disabled", true );
						$( "#amazon_polly_cloudfront_learnmore" ).prop( "disabled", true );
					}
				}
			);

			$( '#amazon_polly_bulk_update_div' ).hide();
			$( '#amazon_ai_plugin_cost_info' ).hide();

			$( '#amazon_polly_enable' ).change(
				function() {
					if ($( "#amazon_polly_enable" ).is( ':checked' )) {
						$( "#amazon_polly_post_options" ).show();
					} else {
						$( "#amazon_polly_post_options" ).hide();
					}
				}
			);

			$( '.wrap input, .wrap select' ).not('#amazon_polly_update_all').change(
				function() {
					$( '#amazon_polly_update_all' ).prop("disabled", true);
					$( '#amazon_polly_update_all' ).show();
					$( '#label_amazon_polly_update_all' ).show();
					$( '#amazon_polly_bulk_update_div' ).hide();
					$( '#amazon_polly_update_all_pricing_message' ).hide();
				}
			);

			$( '#amazon_polly_update_all' ).click(
				function(e) {
					e.stopPropagation();
					e.preventDefault();

					$( '#amazon_polly_update_all' ).hide();
					$( "#amazon_polly_bulk_update_div" ).show();
					$( '#amazon_polly_update_all_pricing_message' ).show();
				}
			);

			$( '#amazon_polly_price_checker_button' ).click(
				function(){

					if ( $('#amazon_ai_plugin_cost_info').is(":hidden") ) {
						$( '#amazon_ai_plugin_cost_info' ).show();
					} else {
						$( '#amazon_ai_plugin_cost_info' ).hide();
					}
				}
			);

			$( '#amazon_polly_s3_learnmore' ).click(
				function(){
					alert( 'With this option selected, audio files will not be saved to or streamed from the local WordPress server, but instead, from Amazon S3. For more information and pricing, see https://aws.amazon.com/s3 ' );
				}
			);

			$( '#amazon_polly_cloudfront_learnmore' ).click(
				function(){
					alert( 'If you have a CloudFront distribution for your S3 bucket, enter its name here. For more information and pricing, see https://aws.amazon.com/cloudfront ' );
				}
			);

			if( $('#amazon_polly_trans_button').length ) {
				if( $('#major-publishing-actions').length ) {
				     $( '#major-publishing-actions' ).append("<div id='amazon-polly-translate-reminder'>This content will be published in one language. To translate to other languages, choose <b>Translate</b> after publishing or updating.</div>");
				}
			}

					refreshPollySettingsUi( 'init' );

					$( '#amazon_polly_neural' ).change(
						function() {
							refreshPollySettingsUi( 'neural' );
						}
					);

					$( '#amazon_polly_voice_id' ).change(
						function() {
							refreshPollySettingsUi( 'voice' );
						}
					);

					$( 'input[name="amazon_polly_speaking_style"]' ).change(
						function() {
							refreshPollySettingsUi( 'style' );
						}
					);

		}
	);

})( jQuery );
