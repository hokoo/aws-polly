/**
 * Admin-facing JavaScript for AWS Text-to-Speech plugin.
 *
 * @package    Amazonpolly
 * @subpackage Amazonpolly/admin/js
 */

	(function( $ ) {
		'use strict';
		var adminConfig = window.itronAwsPollyAdmin || {};

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
					action: adminConfig.ajaxAction || 'itron_aws_polly_transcribe',
					nonce: adminConfig.ajaxNonce || '',
				},
				dataType: "json",
				beforeSend: function() {
					$('.amazon-polly-progress-label').show();
				},
				success: function( response ) {
					if( 'done' != response.step ) {
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
		}

		function injectFindPostsWithoutAudioPanel() {
			var form = document.querySelector( '.wrap form' );
			var targetUrl = adminConfig.findPostsWithoutAudioUrl || '';

			if ( ! form || ! targetUrl || document.getElementById( 'itron-aws-polly-find-posts-panel' ) ) {
				return;
			}

			var panel = document.createElement( 'div' );
			panel.id = 'itron-aws-polly-find-posts-panel';
			panel.style.marginTop = '20px';
			panel.style.padding = '15px';
			panel.style.background = '#fff';
			panel.style.border = '1px solid #ccd0d4';
			panel.style.borderLeft = '4px solid #0073aa';

			panel.innerHTML = '<h3 style="margin-top:0;">Posts without audio</h3>'
				+ '<p>Find and select posts that do not have generated audio, then use bulk actions to generate it.</p>'
				+ '<a href="' + targetUrl + '" class="button button-primary">Find posts without audio &rarr;</a>';

			form.parentNode.insertBefore( panel, form.nextSibling );
		}

	$( document ).ready(
		function(){
			injectFindPostsWithoutAudioPanel();

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

			$( '#amazon_polly_s3' ).change(
				function() {
					if ($( "#amazon_polly_s3" ).is( ':checked' )) {
						$( "#amazon_polly_s3_bucket_name_box" ).show();
					} else {
						$( "#amazon_polly_s3_bucket_name_box" ).hide();
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
