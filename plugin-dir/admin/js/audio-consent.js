( function( window, document ) {
	'use strict';

	var config = window.itronPollyAudioConsent || {};
	var loadedAt = Date.now();

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

	function getClassicStatus( form, submitter ) {
		var statusField = form.querySelector( '[name="post_status"]' );
		var visibility = form.querySelector( 'input[name="visibility"]:checked' );
		var status = statusField ? statusField.value : config.postStatus;
		var action = submitter ? ( submitter.name || submitter.id ) : '';
		var dateFields;
		var scheduledDate;
		var currentDate;

		if ( visibility && 'private' === visibility.value ) {
			return 'private';
		}
		if ( [ 'saveasdraft', 'advanced', 'save-post' ].indexOf( action ) !== -1 ) {
			return 'draft';
		}
		if ( 'pending' === action ) {
			return 'pending';
		}
		if ( 'saveasprivate' === action ) {
			return 'private';
		}
		if ( 'publish' === action && 'private' !== status ) {
			if ( false === config.canPublish ) {
				return 'pending';
			}
			status = 'publish';
		}

		// Core resolves publish/future dates for both Publish and existing-post Update.
		if ( 'publish' === status || 'future' === status ) {
			dateFields = [ 'aa', 'mm', 'jj', 'hh', 'mn' ].map( function( name ) {
				return form.querySelector( '[name="' + name + '"]' );
			} );
			if ( config.currentDate && dateFields.every( Boolean ) ) {
				scheduledDate = new Date( Number( dateFields[0].value ), Number( dateFields[1].value ) - 1, Number( dateFields[2].value ), Number( dateFields[3].value ), Number( dateFields[4].value ) );
				currentDate = new Date( config.currentDate.replace( ' ', 'T' ) ).getTime() + Date.now() - loadedAt;
				if ( scheduledDate.getTime() - currentDate >= 60000 ) {
					return 'future';
				}
				return 'publish';
			}
		}

		return status;
	}

	function captureClassicChoice() {
		var form = document.getElementById( 'post' );

		if ( ! form || config.isBlockEditor ) {
			return;
		}

		form.addEventListener( 'submit', function( event ) {
			var password = form.querySelector( '#post_password, input[name="post_password"]' );
			var visibility = form.querySelector( 'input[name="visibility"]:checked' );
			var hasPassword = password ? '' !== String( password.value ) : Boolean( config.postPasswordProtected );
			var status = getClassicStatus( form, event.submitter );

			if ( visibility && 'password' !== visibility.value ) {
				hasPassword = false;
			}

			setHiddenField( form, config.classicChoiceField, '' );
			if ( ! isPostAudioEnabled() || ( ! hasPassword && 'publish' === status ) ) {
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

	function getEditedAttribute( options, attribute, fallback ) {
		if ( options.data && Object.prototype.hasOwnProperty.call( options.data, attribute ) ) {
			return String( options.data[ attribute ] || '' );
		}

		if ( window.wp && window.wp.data && window.wp.data.select ) {
			var editor = window.wp.data.select( 'core/editor' );
			if ( editor && editor.getEditedPostAttribute ) {
				var value = editor.getEditedPostAttribute( attribute );
				if ( undefined !== value && null !== value ) {
					return String( value );
				}
			}
		}

		return fallback;
	}

	function registerBlockEditorMiddleware() {
		if ( ! config.isBlockEditor || ! window.wp || ! window.wp.apiFetch || ! window.wp.apiFetch.use ) {
			return;
		}

		window.wp.apiFetch.use( function( options, next ) {
			var choice;
			var nextOptions;

			if ( ! isCorePostWrite( options ) || ! isPostAudioEnabled() ) {
				return next( options );
			}
			if ( '' === getEditedAttribute( options, 'password', config.postPasswordProtected ? '__protected__' : '' )
				&& 'publish' === getEditedAttribute( options, 'status', config.postStatus ) ) {
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
