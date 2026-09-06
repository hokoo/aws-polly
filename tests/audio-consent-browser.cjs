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
		postStatus: 'publish',
		canPublish: true,
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

async function submitClassicPost( browser, options ) {
	const page = await browser.newPage();
	await page.setContent( `
		<form id="post">
			<input id="itron_polly_tts_enable" type="checkbox" checked>
			<input id="post_password" name="post_password" value="">
			<input type="radio" name="visibility" value="public" checked>
			<input type="radio" name="visibility" value="password">
			<input type="radio" name="visibility" value="private">
			<input id="post_status" name="post_status" type="hidden" value="publish">
			<input name="aa" value="2026">
			<input name="mm" value="09">
			<input name="jj" value="08">
			<input name="hh" value="12">
			<input name="mn" value="00">
			<button id="publish" name="publish" type="submit" value="Publish">Publish</button>
			<button id="save-post" name="saveasdraft" type="submit" value="Save Draft">Save Draft</button>
			<button id="save-pending" name="pending" type="submit" value="Save as Pending">Save as Pending</button>
			<input type="hidden" name="itron_polly_tts_audio_consent_choice" value="">
		</form>
	` );
	await page.evaluate( ( values ) => {
		document.getElementById( 'post_password' ).value = values.password;
		document.getElementById( 'post_status' ).value = values.status;
		document.querySelector( 'input[name="visibility"][value="' + values.visibility + '"]' ).checked = true;
		document.getElementById( values.submitterId ).value = values.submitterValue;
		if ( values.submitterName ) {
			document.getElementById( values.submitterId ).name = values.submitterName;
		}
	}, options );
	await installScript( page, baseConfig( options.config ), options.confirmResult );

	const result = await page.evaluate( ( submitterId ) => {
		const form = document.getElementById( 'post' );
		const event = new SubmitEvent( 'submit', {
			bubbles: true,
			cancelable: true,
			submitter: document.getElementById( submitterId )
		} );
		form.dispatchEvent( event );
		return {
			prevented: event.defaultPrevented,
			choice: form.querySelector( 'input[name="itron_polly_tts_audio_consent_choice"]' ).value,
			confirmCalls: window.confirmCalls
		};
	}, options.submitterId );

	await page.close();
	return result;
}

async function createBlockEditorPage( browser, config, editorState, confirmResult ) {
	const page = await browser.newPage();
	await page.setContent( '<input id="itron_polly_tts_enable" type="checkbox" checked>' );
	await page.evaluate( ( state ) => {
		window.editorState = state;
		window.wp = {
			apiFetch: {
				use: ( middleware ) => {
					window.audioConsentMiddleware = middleware;
				}
			},
			data: {
				select: () => ( {
					getEditedPostAttribute: ( attribute ) => {
						if ( 'status' === attribute ) {
							return window.editorState.status;
						}
						if ( 'password' === attribute ) {
							return window.editorState.password;
						}
						return undefined;
					}
				} )
			}
		};
	}, editorState );
	await installScript( page, baseConfig( Object.assign( { isBlockEditor: true }, config ) ), confirmResult );
	return page;
}

async function runBlockRequest( page, request ) {
	return page.evaluate( async ( options ) => {
		let forwarded;
		const saveResult = await window.audioConsentMiddleware( options, ( nextOptions ) => {
			forwarded = nextOptions;
			return Promise.resolve( 'saved' );
		} );
		return {
			confirmCalls: window.confirmCalls,
			forwarded,
			saveResult
		};
	}, request );
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

async function testClassicStatusConsent( browser ) {
	const privateResult = await submitClassicPost( browser, {
		config: { postPasswordProtected: false },
		confirmResult: false,
		password: '',
		status: 'publish',
		submitterId: 'publish',
		submitterValue: 'Publish',
		visibility: 'private'
	} );
	assert( ! privateResult.prevented, 'Private-post refusal must not cancel the classic save.' );
	assert( '0' === privateResult.choice, 'A passwordless private post must submit an explicit false choice after refusal.' );
	assert( 1 === privateResult.confirmCalls, 'Selected private visibility must prompt despite post_status=publish.' );

	const draftResult = await submitClassicPost( browser, {
		config: { postPasswordProtected: false },
		confirmResult: false,
		password: '',
		status: 'publish',
		submitterId: 'save-post',
		submitterValue: 'Save Draft',
		visibility: 'public'
	} );
	assert( ! draftResult.prevented, 'Draft refusal must not cancel the classic save.' );
	assert( '0' === draftResult.choice, 'A passwordless draft must submit an explicit false choice after refusal.' );
	assert( 1 === draftResult.confirmCalls, 'The save-draft control must prompt once even when post_status is still publish.' );

	const pendingResult = await submitClassicPost( browser, {
		config: { canPublish: false, postPasswordProtected: false },
		confirmResult: true,
		password: '',
		status: 'draft',
		submitterId: 'publish',
		submitterValue: 'Submit for Review',
		visibility: 'public'
	} );
	assert( '1' === pendingResult.choice, 'A contributor submitting for review must submit an explicit true choice after confirmation.' );
	assert( 1 === pendingResult.confirmCalls, 'The pending-status publish control must prompt once when canPublish is false.' );

	const savePendingResult = await submitClassicPost( browser, {
		config: { postPasswordProtected: false },
		confirmResult: true,
		password: '',
		status: 'publish',
		submitterId: 'save-pending',
		submitterValue: 'Save as Pending',
		visibility: 'public'
	} );
	assert( '1' === savePendingResult.choice, 'The save-pending control must request and carry explicit consent.' );
	assert( 1 === savePendingResult.confirmCalls, 'The save-pending control must prompt despite post_status=publish.' );

	const futureResult = await submitClassicPost( browser, {
		config: { currentDate: '2026-09-07 12:00:00', postPasswordProtected: false },
		confirmResult: true,
		password: '',
		status: 'publish',
		submitterId: 'publish',
		submitterValue: 'Schedule',
		visibility: 'public'
	} );
	assert( '1' === futureResult.choice, 'Scheduling a future passwordless post must carry explicit consent.' );
	assert( 1 === futureResult.confirmCalls, 'A future classic publication must prompt once.' );

	const rescheduleResult = await submitClassicPost( browser, {
		config: { currentDate: '2026-09-07 12:00:00', postPasswordProtected: false },
		confirmResult: false,
		password: '',
		status: 'publish',
		submitterId: 'publish',
		submitterName: 'save',
		submitterValue: 'Update',
		visibility: 'public'
	} );
	assert( 1 === rescheduleResult.confirmCalls, 'Existing-post Update must prompt when its date moves publication into the future.' );
	assert( '0' === rescheduleResult.choice && ! rescheduleResult.prevented, 'Rescheduling refusal must preserve the save and explicitly decline audio.' );

	const scheduledPublishResult = await submitClassicPost( browser, {
		config: { currentDate: '2026-09-08 12:00:00', postPasswordProtected: false, postStatus: 'future' },
		confirmResult: false,
		password: '',
		status: 'future',
		submitterId: 'publish',
		submitterName: 'save',
		submitterValue: 'Update',
		visibility: 'public'
	} );
	assert( 0 === scheduledPublishResult.confirmCalls && '' === scheduledPublishResult.choice, 'Existing scheduled-post Update must not prompt when the date makes it immediately public.' );

	const draftPublishResult = await submitClassicPost( browser, {
		config: { postPasswordProtected: false },
		confirmResult: false,
		password: '',
		status: 'draft',
		submitterId: 'publish',
		submitterValue: 'Publish',
		visibility: 'public'
	} );
	assert( '' === draftPublishResult.choice, 'Immediate Publish from a passwordless public draft must not submit a consent choice.' );
	assert( 0 === draftPublishResult.confirmCalls, 'Immediate Publish from a passwordless public draft must not prompt.' );

	const publishResult = await submitClassicPost( browser, {
		config: { postPasswordProtected: false },
		confirmResult: false,
		password: '',
		status: 'publish',
		submitterId: 'publish',
		submitterValue: 'Publish',
		visibility: 'public'
	} );
	assert( '' === publishResult.choice, 'A passwordless public publish must not submit a consent choice.' );
	assert( 0 === publishResult.confirmCalls, 'A passwordless public publish must not prompt.' );
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
	assert( '0' === result.choice, 'Bulk refusal must submit one explicit false choice for mixed consent-required and public rows.' );
	assert( 'bulk-nonce' === result.nonce, 'Bulk confirmation must include its dedicated nonce.' );
	assert( 1 === result.confirmCalls, 'Mixed bulk rows with multiple consent-required posts must produce exactly one popup.' );
	await page.close();
}

async function testBlockEditorPrecedence( browser ) {
	const page = await createBlockEditorPage(
		browser,
		{ postPasswordProtected: false },
		{ password: '', status: 'publish' },
		false
	);

	const privateResult = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { status: 'private', title: 'Private update' }
	} );
	assert( 'saved' === privateResult.saveResult, 'Gutenberg refusal must preserve the core save result.' );
	assert( false === privateResult.forwarded.data.itron_polly_tts_public_audio_consent, 'Payload private status must override the published editor state and carry refusal.' );

	await page.evaluate( () => {
		window.confirm = () => {
			window.confirmCalls += 1;
			return true;
		};
	} );
	const draftResult = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PATCH',
		data: { status: 'draft' }
	} );
	assert( true === draftResult.forwarded.data.itron_polly_tts_public_audio_consent, 'Payload draft status must override the published editor state and carry confirmation.' );

	await page.evaluate( () => {
		window.editorState = { password: '', status: 'private' };
	} );
	const publishResult = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { password: '', status: 'publish' }
	} );
	assert( 2 === publishResult.confirmCalls, 'Payload publish status and empty password must override private editor state without prompting.' );
	assert(
		! Object.prototype.hasOwnProperty.call( publishResult.forwarded.data, 'itron_polly_tts_public_audio_consent' ),
		'A payload overriding editor state to a passwordless publish must not add consent.'
	);

	await page.evaluate( () => {
		window.editorState = { password: 'old-password', status: 'private' };
	} );
	const privatePasswordRemoval = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { password: '' }
	} );
	assert( true === privatePasswordRemoval.forwarded.data.itron_polly_tts_public_audio_consent, 'Removing a password from a private post must still request consent.' );
	assert( 3 === privatePasswordRemoval.confirmCalls, 'Private status must remain consent-required after password removal.' );

	await page.evaluate( () => {
		window.editorState = { password: 'old-password', status: 'publish' };
	} );
	const publicPasswordRemoval = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { password: '' }
	} );
	assert( 3 === publicPasswordRemoval.confirmCalls, 'Removing a password from a public published post must not prompt.' );
	assert(
		! Object.prototype.hasOwnProperty.call( publicPasswordRemoval.forwarded.data, 'itron_polly_tts_public_audio_consent' ),
		'Public password removal must not add a consent choice.'
	);

	const custom = await runBlockRequest( page, {
		url: 'https://example.test/wp-json/library/v1/volumes',
		method: 'POST',
		data: { password: 'new-book-password', status: 'publish' }
	} );
	assert( true === custom.forwarded.data.itron_polly_tts_public_audio_consent, 'A matching custom REST base with a password must carry the boolean grant.' );
	await page.close();
}

async function testBlockEditorExcludedRequests( browser ) {
	const page = await createBlockEditorPage(
		browser,
		{ postPasswordProtected: false, postStatus: 'private' },
		{ password: '', status: 'private' },
		false
	);
	const requests = [
		{ path: '/wp/v2/posts/21/autosaves', method: 'POST', data: { status: 'private' } },
		{ path: '/wp/v2/posts/21?context=edit', method: 'GET' },
		{ path: '/wp/v2/users/7', method: 'POST', data: { status: 'private' } }
	];

	for ( const request of requests ) {
		const result = await runBlockRequest( page, request );
		assert( 0 === result.confirmCalls, 'Autosave, GET, and unrelated requests must not prompt.' );
		assert(
			! Object.prototype.hasOwnProperty.call( result.forwarded.data || {}, 'itron_polly_tts_public_audio_consent' ),
			'Excluded requests must not add or revoke consent.'
		);
	}
	await page.close();
}

async function testBlockEditorServerFallback( browser ) {
	const page = await createBlockEditorPage(
		browser,
		{ postPasswordProtected: false, postStatus: 'future' },
		{ password: undefined, status: undefined },
		false
	);
	const result = await runBlockRequest( page, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { title: 'Scheduled update' }
	} );

	assert( 1 === result.confirmCalls, 'A future server-config status must prompt when payload and editor values are absent.' );
	assert( false === result.forwarded.data.itron_polly_tts_public_audio_consent, 'Server-config fallback must carry an explicit false choice.' );
	await page.close();
}

async function testGlobalOffNoPrompt( browser ) {
	const classicResult = await submitClassicPost( browser, {
		config: { globalEnabled: false, postPasswordProtected: false, postStatus: 'private' },
		confirmResult: false,
		password: '',
		status: 'private',
		submitterId: 'save-post',
		submitterValue: 'Save Draft',
		visibility: 'private'
	} );
	assert( 0 === classicResult.confirmCalls, 'Global audio OFF must suppress classic status consent.' );
	assert( '' === classicResult.choice, 'Global audio OFF must not submit a classic consent choice.' );

	const blockPage = await createBlockEditorPage(
		browser,
		{ globalEnabled: false, postPasswordProtected: false, postStatus: 'private' },
		{ password: '', status: 'private' },
		false
	);
	const blockResult = await runBlockRequest( blockPage, {
		path: '/wp/v2/posts/21',
		method: 'PUT',
		data: { status: 'private' }
	} );
	assert( 0 === blockResult.confirmCalls, 'Global audio OFF must suppress Gutenberg status consent.' );
	assert(
		! Object.prototype.hasOwnProperty.call( blockResult.forwarded.data, 'itron_polly_tts_public_audio_consent' ),
		'Global audio OFF must not add a Gutenberg consent choice.'
	);
	await blockPage.close();
}

( async () => {
	const browser = await chromium.launch( { headless: true } );

	try {
		await testNoJsSaveContinues( browser );
		await testClassicRefusal( browser );
		await testClassicStatusConsent( browser );
		await testBulkSinglePrompt( browser );
		await testBlockEditorPrecedence( browser );
		await testBlockEditorExcludedRequests( browser );
		await testBlockEditorServerFallback( browser );
		await testGlobalOffNoPrompt( browser );
		console.log( 'audio-consent-browser: PASS' );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
