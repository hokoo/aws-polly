const { chromium } = require( 'playwright' );

const scriptPath = require( 'path' ).resolve( __dirname, '../plugin-dir/admin/js/audio-consent.js' );

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

function baseConfig( overrides = {} ) {
	return Object.assign( {
		globalEnabled: true,
		postEnabled: true,
		postPasswordProtected: true,
		isBlockEditor: false,
		classicChoiceField: 'itron_polly_tts_audio_consent_choice',
		bulkNonceField: 'itron_polly_tts_bulk_audio_consent_nonce',
		bulkNonce: 'bulk-nonce',
		bulkChoiceField: 'itron_polly_tts_bulk_audio_consent_choice',
		restField: 'itron_polly_tts_public_audio_consent',
		restRoutes: [ '/wp/v2/posts', '/library/v1/volumes' ],
		confirmMessage: 'Allow public audio?',
		bulkConfirmMessage: 'Allow selected protected audio?'
	}, overrides );
}

async function installScript( page, config, confirmResult ) {
	await page.evaluate( ( values ) => {
		window.itronPollyAudioConsent = values.config;
		window.confirmCalls = 0;
		window.confirm = () => {
			window.confirmCalls += 1;
			return values.confirmResult;
		};
	}, { config, confirmResult } );
	await page.addScriptTag( { path: scriptPath } );
}

async function testNoJsSaveContinues( browser ) {
	const page = await browser.newPage();
	await page.setContent( `
		<form id="post">
			<input id="itron_polly_tts_enable" type="checkbox" checked>
			<input id="post_password" name="post_password" value="secret">
		</form>
	` );
	const result = await page.evaluate( () => {
		const form = document.getElementById( 'post' );
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );
		form.dispatchEvent( event );
		return {
			prevented: event.defaultPrevented,
			choice: form.querySelector( 'input[name="itron_polly_tts_audio_consent_choice"]' )
		};
	} );
	assert( ! result.prevented, 'A no-JS classic save must continue.' );
	assert( null === result.choice, 'A no-JS save must not manufacture a consent grant.' );
	await page.close();
}

async function testClassicRefusal( browser ) {
	const page = await browser.newPage();
	await page.setContent( `
		<form id="post">
			<input id="itron_polly_tts_enable" type="checkbox" checked>
			<input id="post_password" name="post_password" value="secret">
			<input type="hidden" name="itron_polly_tts_audio_consent_choice" value="">
		</form>
	` );
	await installScript( page, baseConfig(), false );
	const result = await page.evaluate( () => {
		const form = document.getElementById( 'post' );
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );
		form.dispatchEvent( event );
		return {
			prevented: event.defaultPrevented,
			choice: form.querySelector( 'input[name="itron_polly_tts_audio_consent_choice"]' ).value,
			confirmCalls: window.confirmCalls
		};
	} );
	assert( ! result.prevented, 'Classic refusal must not cancel the post save.' );
	assert( '0' === result.choice, 'Classic refusal must submit an explicit false choice.' );
	assert( 1 === result.confirmCalls, 'A classic save must show one confirmation.' );
	await page.close();
}

async function testBulkSinglePrompt( browser ) {
	const page = await browser.newPage();
	await page.setContent( `
		<form id="posts-filter">
			<select name="action"><option value="polly_generate_audio" selected>Generate</option></select>
			<select name="action2"><option value="-1" selected>Bulk actions</option></select>
			<button id="doaction" type="submit">Apply</button>
			<input type="checkbox" name="post[]" value="11" checked>
			<input type="checkbox" name="post[]" value="12" checked>
			<input type="checkbox" name="post[]" value="13" checked>
		</form>
		<span class="itron-polly-tts-protected-audio" data-post-id="11" hidden></span>
		<span class="itron-polly-tts-protected-audio" data-post-id="12" hidden></span>
	` );
	await installScript( page, baseConfig(), false );
	const result = await page.evaluate( () => {
		const form = document.getElementById( 'posts-filter' );
		const event = new SubmitEvent( 'submit', {
			bubbles: true,
			cancelable: true,
			submitter: document.getElementById( 'doaction' )
		} );
		form.dispatchEvent( event );
		return {
			prevented: event.defaultPrevented,
			choice: form.querySelector( 'input[name="itron_polly_tts_bulk_audio_consent_choice"]' ).value,
			nonce: form.querySelector( 'input[name="itron_polly_tts_bulk_audio_consent_nonce"]' ).value,
			confirmCalls: window.confirmCalls
		};
	} );
	assert( ! result.prevented, 'Bulk refusal must not cancel the bulk action.' );
	assert( '0' === result.choice, 'Bulk refusal must submit one explicit false choice for the protected group.' );
	assert( 'bulk-nonce' === result.nonce, 'Bulk confirmation must include its dedicated nonce.' );
	assert( 1 === result.confirmCalls, 'Multiple protected rows must produce exactly one bulk popup.' );
	await page.close();
}

async function testBlockEditorMiddleware( browser ) {
	const page = await browser.newPage();
	await page.setContent( '<input id="itron_polly_tts_enable" type="checkbox" checked>' );
	await page.evaluate( () => {
		window.wp = {
			apiFetch: {
				use: ( middleware ) => {
					window.audioConsentMiddleware = middleware;
				}
			},
			data: {
				select: () => ( {
					getEditedPostAttribute: () => 'editor-password'
				} )
			}
		};
	} );
	await installScript( page, baseConfig( { isBlockEditor: true } ), false );

	const result = await page.evaluate( async () => {
		const forwarded = [];
		const next = ( options ) => {
			forwarded.push( options );
			return Promise.resolve( 'saved' );
		};
		const saveResult = await window.audioConsentMiddleware( {
			path: '/wp/v2/posts/21?context=edit',
			method: 'PUT',
			data: { title: 'Updated title' }
		}, next );
		await window.audioConsentMiddleware( {
			path: '/wp/v2/posts/21/autosaves',
			method: 'POST',
			data: { content: 'Autosave' }
		}, next );
		await window.audioConsentMiddleware( {
			path: '/wp/v2/posts/21?context=edit',
			method: 'GET'
		}, next );
		await window.audioConsentMiddleware( {
			path: '/wp/v2/users/7',
			method: 'POST',
			data: { name: 'Unrelated metabox request' }
		}, next );
		await window.audioConsentMiddleware( {
			path: '/wp/v2/posts/21',
			method: 'PUT',
			data: { password: '' }
		}, next );

		return {
			saveResult,
			forwarded,
			confirmCalls: window.confirmCalls
		};
	} );

	assert( 'saved' === result.saveResult, 'Gutenberg refusal must preserve the core save result.' );
	assert( false === result.forwarded[0].data.itron_polly_tts_public_audio_consent, 'Gutenberg refusal must add a boolean false REST choice.' );
	assert( 1 === result.confirmCalls, 'Autosave, GET, unrelated, and password-removal requests must not prompt.' );
	for ( let index = 1; index < result.forwarded.length; index += 1 ) {
		assert(
			! Object.prototype.hasOwnProperty.call( result.forwarded[index].data || {}, 'itron_polly_tts_public_audio_consent' ),
			'Non-core-save requests must not add or revoke consent.'
		);
	}

	await page.evaluate( () => {
		window.confirm = () => {
			window.confirmCalls += 1;
			return true;
		};
	} );
	const custom = await page.evaluate( async () => {
		let forwarded;
		await window.audioConsentMiddleware( {
			url: 'https://example.test/wp-json/library/v1/volumes',
			method: 'POST',
			data: { password: 'new-book-password' }
		}, ( options ) => {
			forwarded = options;
			return Promise.resolve( 'created' );
		} );
		return forwarded;
	} );
	assert( true === custom.data.itron_polly_tts_public_audio_consent, 'A matching custom REST base must carry the boolean grant.' );
	await page.close();
}

( async () => {
	const browser = await chromium.launch( { headless: true } );

	try {
		await testNoJsSaveContinues( browser );
		await testClassicRefusal( browser );
		await testBulkSinglePrompt( browser );
		await testBlockEditorMiddleware( browser );
		console.log( 'audio-consent-browser: PASS' );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
