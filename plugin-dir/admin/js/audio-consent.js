( function( window, document ) {
	'use strict';

	var config = window.itronPollyAudioConsent || {};

	function setHiddenField( form, name, value ) {
		var field = form.querySelector( 'input[name="' + name + '"]' );

		if ( ! field ) {
			field = document.createElement( 'input' );
			field.type = 'hidden';
			field.name = name;
			form.appendChild( field );
		}

		field.value = value;
	}

	function isPostAudioEnabled() {
		var checkbox = document.getElementById( 'itron_polly_tts_enable' );

		if ( ! config.globalEnabled ) {
			return false;
		}

		return checkbox ? checkbox.checked : Boolean( config.postEnabled );
	}

	function captureClassicChoice() {
		var form = document.getElementById( 'post' );

		if ( ! form || config.isBlockEditor ) {
			return;
		}

		form.addEventListener( 'submit', function() {
			var password = form.querySelector( '#post_password, input[name="post_password"]' );

			setHiddenField( form, config.classicChoiceField, '' );
			if ( ! isPostAudioEnabled() || ! password || '' === String( password.value ) ) {
				return;
			}

			setHiddenField(
				form,
				config.classicChoiceField,
				window.confirm( config.confirmMessage ) ? '1' : '0'
			);
		} );
	}

	function selectedBulkAction( form, submitter ) {
		var top = form.querySelector( 'select[name="action"]' );
		var bottom = form.querySelector( 'select[name="action2"]' );

		if ( submitter && 'doaction2' === submitter.id ) {
			return bottom ? bottom.value : '';
		}

		if ( submitter && 'doaction' === submitter.id ) {
			return top ? top.value : '';
		}

		return top && '-1' !== top.value ? top.value : ( bottom ? bottom.value : '' );
	}

	function hasSelectedProtectedPost( form ) {
		var protectedIds = {};

		document.querySelectorAll( '.itron-polly-tts-protected-audio[data-post-id]' ).forEach(
			function( marker ) {
				protectedIds[ String( marker.getAttribute( 'data-post-id' ) ) ] = true;
			}
		);

		return Array.prototype.some.call(
			form.querySelectorAll( 'input[name="post[]"]:checked' ),
			function( checkbox ) {
				return Boolean( protectedIds[ String( checkbox.value ) ] );
			}
		);
	}

	function captureBulkChoice() {
		var form = document.getElementById( 'posts-filter' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function( event ) {
			if ( ! config.globalEnabled || 'polly_generate_audio' !== selectedBulkAction( form, event.submitter ) || ! hasSelectedProtectedPost( form ) ) {
				return;
			}

			setHiddenField( form, config.bulkNonceField, config.bulkNonce );
			setHiddenField(
				form,
				config.bulkChoiceField,
				window.confirm( config.bulkConfirmMessage || config.confirmMessage ) ? '1' : '0'
			);
		} );
	}

	function getRequestPath( options ) {
		var value = options.path || options.url || '';

		try {
			value = new window.URL( value, window.location.href ).pathname;
		} catch ( error ) {
			value = String( value ).split( '?' )[ 0 ].split( '#' )[ 0 ];
		}

		return value;
	}

	function isCorePostWrite( options ) {
		var method = String( options.method || 'GET' ).toUpperCase();
		var path = getRequestPath( options );

		if ( [ 'POST', 'PUT', 'PATCH' ].indexOf( method ) === -1 ) {
			return false;
		}

		return ( config.restRoutes || [] ).some( function( route ) {
			var index = path.indexOf( route );
			var itemPath;

			if ( -1 === index ) {
				return false;
			}

			itemPath = path.slice( index );
			return itemPath === route || new RegExp( '^' + route.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '/[1-9][0-9]*$' ).test( itemPath );
		} );
	}

	function getEditedPassword( options ) {
		if ( options.data && Object.prototype.hasOwnProperty.call( options.data, 'password' ) ) {
			return String( options.data.password || '' );
		}

		if ( window.wp && window.wp.data && window.wp.data.select ) {
			var editor = window.wp.data.select( 'core/editor' );
			if ( editor && editor.getEditedPostAttribute ) {
				return String( editor.getEditedPostAttribute( 'password' ) || '' );
			}
		}

		return config.postPasswordProtected ? '__protected__' : '';
	}

	function registerBlockEditorMiddleware() {
		if ( ! config.isBlockEditor || ! window.wp || ! window.wp.apiFetch || ! window.wp.apiFetch.use ) {
			return;
		}

		window.wp.apiFetch.use( function( options, next ) {
			var choice;
			var nextOptions;

			if ( ! isCorePostWrite( options ) || ! isPostAudioEnabled() || '' === getEditedPassword( options ) ) {
				return next( options );
			}

			choice = window.confirm( config.confirmMessage );
			nextOptions = Object.assign( {}, options, {
				data: Object.assign( {}, options.data || {} )
			} );
			nextOptions.data[ config.restField ] = choice;

			// Refusal controls audio consent only; the core post request always continues.
			return next( nextOptions );
		} );
	}

	captureClassicChoice();
	captureBulkChoice();
	registerBlockEditorMiddleware();
}( window, document ) );
