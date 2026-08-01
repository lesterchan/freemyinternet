/**
 * Settings -> FreeMyInternet, driven the way an owner drives it.
 *
 * The whole plugin is one option row, so this file is about the two failures a
 * screenshot cannot tell apart: a field that saves and does nothing, and a field
 * that does something and will not save. Every assertion here therefore reads
 * the stored row back rather than trusting the notice -- what the front end acts
 * on is the row, not the form.
 *
 * The plugin has no list table, no AJAX and no destructive action, so there is
 * nothing here to confirm or to delete. What there is instead is a sanitizer
 * with real opinions -- a protocol allow-list, a hex colour check, a datetime
 * parser -- and each of those is only reachable through a real request.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	PLUGINS_URL,
	SETTINGS_URL,
	deleteOptions,
	field,
	getStoredOptions,
	openSettings,
	saveSettings,
	setOptions,
	siteDatetimes,
	uniqueText,
	wpEval,
} = require( './helpers.js' );

/** The lesser user both capability tests are argued from. */
const SUBSCRIBER = {
	username: 'fmi_subscriber',
	password: 'correct-horse-battery-staple',
};

/**
 * A second browser, logged in as a subscriber.
 *
 * A fresh context rather than the shared one, because the suite's stored session
 * is the administrator's and swapping users inside it would leave whichever test
 * ran next signed in as the wrong person.
 *
 * @param {import('@playwright/test').Page} page         The administrator's page, for its browser.
 * @param {Object}                          requestUtils The e2e-test-utils request helper.
 * @return {Promise<Object>} The new `context` and the logged-in `subscriber` page.
 */
async function loginAsSubscriber( page, requestUtils ) {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username: SUBSCRIBER.username,
			email: `${ SUBSCRIBER.username }@example.com`,
			password: SUBSCRIBER.password,
			roles: [ 'subscriber' ],
		},
	} ).catch( () => {} ); // Already there from an earlier run.

	const context = await page.context().browser().newContext( { storageState: undefined } );
	const subscriber = await context.newPage();

	await subscriber.goto( '/wp-login.php' );

	// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
	// visitor can start typing. Filling across that moment puts the password
	// into the username box: Playwright focuses #user_pass, the timer takes
	// focus back and selects what is there, and the typed text replaces the
	// selection. Waiting for the timer's own effect is the signal that it has
	// already fired.
	await expect( subscriber.locator( '#user_login' ) ).toBeFocused();

	await subscriber.locator( '#user_login' ).fill( SUBSCRIBER.username );
	await subscriber.locator( '#user_pass' ).fill( SUBSCRIBER.password );
	await subscriber.locator( '#wp-submit' ).click();
	await expect( subscriber.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, subscriber };
}

test.describe( 'The settings screen', () => {
	// Once for the file, not once per test. Every test here already puts the
	// row back in afterEach, so a beforeEach would be a second container round
	// trip -- several seconds each -- bought for a state that is already true.
	// The one thing it does buy is that the first test's precondition does not
	// depend on whichever file Playwright happened to run before this one.
	test.beforeAll( async () => {
		deleteOptions();
	} );

	test.afterEach( async () => {
		// The row is global state and the front-end suite reads it, so no test
		// is allowed to leave its own notice switched on behind it.
		deleteOptions();
	} );

	test( 'the fixture really is a fresh install: no row, and the notice off', async ( { page } ) => {
		// The precondition every other test in this file leans on. If the row
		// survived from an earlier run then "the field saved" would be true
		// before the test did anything, and the whole file would pass green.
		expect( getStoredOptions() ).toBe( false );

		await openSettings( page );

		// Off by default is a deliberate decision, not an accident of the empty
		// row: versions up to 0.01 blacked the site out the moment they were
		// activated, and an upgrade must not quietly keep doing that.
		await expect( page.locator( field( 'enabled' ) ) ).not.toBeChecked();
		await expect( page.locator( field( 'mode' ) ) ).toBeChecked();
		await expect( page.locator( field( 'background' ) ) ).toHaveValue( '#000000' );
		await expect( page.locator( field( 'text_color' ) ) ).toHaveValue( '#ffffff' );
		await expect( page.locator( field( 'link_text' ) ) ).toHaveValue( 'Find out more' );
	} );

	test( 'every field lands in the one option row', async ( { page } ) => {
		const heading = uniqueText( 'Save the internet' );
		const [ start, end ] = siteDatetimes( -3600, 3600 );

		await openSettings( page );

		await page.locator( field( 'enabled' ) ).check();
		await page.locator( `${ field( 'mode' ) }-banner` ).check();
		await page.locator( field( 'heading' ) ).fill( heading );
		await page.locator( field( 'message' ) ).fill( '<em>Please</em> write to your MP' );
		await page.locator( field( 'link_url' ) ).fill( 'https://example.org/protest' );
		await page.locator( field( 'link_text' ) ).fill( 'What this is about' );
		await page.locator( field( 'image_url' ) ).fill( 'https://example.org/banner.png' );
		await page.locator( field( 'background' ) ).fill( '#102030' );
		await page.locator( field( 'text_color' ) ).fill( '#f0e0d0' );
		await page.locator( field( 'dismissible' ) ).uncheck();

		// datetime-local wants a T where the plugin stores a space, which is the
		// one place the two representations differ.
		await page.locator( field( 'start' ) ).fill( start.replace( ' ', 'T' ) );
		await page.locator( field( 'end' ) ).fill( end.replace( ' ', 'T' ) );

		await saveSettings( page );

		// The far end, not the notice. saveSettings() has already asserted
		// "Settings saved." -- which is worth having only as the guard that the
		// page did not scope settings_errors() to its own slug and filter the
		// core notice away -- and this is what the front end will actually read.
		expect( getStoredOptions() ).toEqual( {
			enabled: true,
			dismissible: false,
			mode: 'banner',
			heading,
			message: '<em>Please</em> write to your MP',
			link_url: 'https://example.org/protest',
			link_text: 'What this is about',
			image_url: 'https://example.org/banner.png',
			background: '#102030',
			text_color: '#f0e0d0',
			start,
			end,
		} );
	} );

	test( 'the notice can be switched off again once it has been switched on', async ( { page } ) => {
		// A checkbox that saves when ticked and cannot be unticked is a bug this
		// collection has actually shipped: the unchecked box posts nothing, so a
		// sanitizer that only reads the keys it was sent keeps the old value.
		await openSettings( page );
		await page.locator( field( 'enabled' ) ).check();
		await page.locator( field( 'heading' ) ).fill( uniqueText( 'On then off' ) );
		await saveSettings( page );

		expect( getStoredOptions().enabled ).toBe( true );

		await openSettings( page );
		await expect( page.locator( field( 'enabled' ) ) ).toBeChecked();
		await page.locator( field( 'enabled' ) ).uncheck();
		await saveSettings( page );

		expect( getStoredOptions().enabled ).toBe( false );
		await openSettings( page );
		await expect( page.locator( field( 'enabled' ) ) ).not.toBeChecked();
	} );

	test( 'the presentation radio round-trips', async ( { page } ) => {
		await openSettings( page );
		await page.locator( `${ field( 'mode' ) }-banner` ).check();
		await saveSettings( page );

		expect( getStoredOptions().mode ).toBe( 'banner' );

		await openSettings( page );
		await expect( page.locator( `${ field( 'mode' ) }-banner` ) ).toBeChecked();
		await expect( page.locator( field( 'mode' ) ) ).not.toBeChecked();

		await page.locator( field( 'mode' ) ).check();
		await saveSettings( page );

		expect( getStoredOptions().mode ).toBe( 'blackout' );
	} );

	test( 'a javascript: link never reaches the row', async ( { page } ) => {
		// type="url" satisfies the browser here -- javascript: is a perfectly
		// well-formed absolute URL -- so the only thing standing between this and
		// a stored payload is esc_url_raw()'s protocol allow-list.
		await openSettings( page );
		await page.locator( field( 'link_url' ) ).fill( 'javascript:window.__pwned=1' );
		await page.locator( field( 'image_url' ) ).fill( 'javascript:window.__pwned=1' );
		await saveSettings( page );

		const stored = getStoredOptions();

		expect( stored.link_url ).toBe( '' );
		expect( stored.image_url ).toBe( '' );
	} );

	test( 'the success notice is printed once, not twice', async ( { page } ) => {
		await openSettings( page );
		await page.locator( field( 'heading' ) ).fill( uniqueText( 'One notice' ) );
		await saveSettings( page );

		// A page registered with add_options_page() has options-head.php run
		// ahead of it, and that file already calls settings_errors(). A screen
		// that calls it again renders every queued notice a second time, and
		// common.js then relocates both into .wrap so they land one under the
		// other in what looks like the plugin's own markup.
		//
		// wp-ban makes exactly this point in a comment where its own
		// settings_errors() call would have gone, so it is a known shape in this
		// collection rather than a matter of taste.
		await expect( page.locator( '#setting-error-settings_updated' ) ).toHaveCount( 1 );
	} );

	test( 'a datetime the parser cannot read is discarded rather than defaulted', async ( { page } ) => {
		await openSettings( page );

		// The datetime-local widget will not let a person type this, and that is
		// exactly why it is worth posting: the sanitizer is what a copied URL, a
		// stale autofill or another client will meet. An empty bound means
		// "unbounded", so an unreadable one must land there and not on now --
		// defaulting to now would silently start or stop the protest.
		await page.locator( field( 'start' ) ).evaluate( ( input ) => {
			input.type = 'text';
		} );
		await page.locator( field( 'start' ) ).fill( 'not a date at all' );
		await saveSettings( page );

		expect( getStoredOptions().start ).toBe( '' );
	} );

	test( 'the screen says so when the notice is on but its window has closed', async ( { page } ) => {
		const [ closed, open ] = siteDatetimes( -3600, 3600 );

		// The state the plugin spent thirteen years in: switched on, pointed at a
		// campaign that ended, and showing nothing. An owner looking at a ticked
		// box and a blank site deserves to be told which of the two is wrong.
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Closed window' ),
			end: closed,
		} );

		await openSettings( page );

		await expect( page.locator( '#setting-error-freemyinternet_schedule_closed' ) ).toContainText(
			'outside its window',
		);

		// And not when the window is open, or the warning is just decoration.
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Open window' ),
			end: open,
		} );

		await openSettings( page );

		await expect( page.locator( '#setting-error-freemyinternet_schedule_closed' ) ).toHaveCount( 0 );
	} );

	test( 'the Plugins screen carries a Settings link that goes to the screen', async ( { page } ) => {
		await page.goto( PLUGINS_URL );

		const row = page.locator( 'tr[data-slug="freemyinternet"]' );

		await expect( row ).toHaveCount( 1 );
		await row.getByRole( 'link', { name: 'Settings' } ).click();

		await expect( page.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ) ).toBeVisible();
	} );

	test( 'a subscriber gets neither the menu item nor the screen, and an administrator gets both', async ( {
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes with
		// the plugin deactivated, because there is nothing to see either way; the
		// administrator half is what proves the gate is the capability.
		await page.goto( '/wp-admin/options-general.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'FreeMyInternet' );

		await openSettings( page );

		const { context, subscriber } = await loginAsSubscriber( page, requestUtils );

		await subscriber.goto( '/wp-admin/index.php' );
		await expect( subscriber.locator( '#adminmenu' ).getByText( 'FreeMyInternet' ) ).toHaveCount( 0 );

		await subscriber.goto( SETTINGS_URL );
		await expect( subscriber.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await context.close();
	} );

	test( 'the capability filter is what decides, and it decides both the menu and the screen', async ( {
		page,
		requestUtils,
	} ) => {
		// The filter exists so a site can hand the protest switch to somebody
		// who is not an administrator without handing them everything else. It
		// has to move two gates, not one: add_options_page() reads it for the
		// menu and render_page() reads it again before printing anything, and a
		// screen that let the menu through but printed nothing would look
		// exactly like a broken plugin.
		//
		// The fixture mu-plugin answers the filter from this option, so it is
		// inert for the test above -- which is the one that has to keep proving
		// manage_options is the default.
		wpEval( "update_option( 'freemyinternet_e2e_capability', 'read' ); echo '<<<done>>>';" );

		const { context, subscriber } = await loginAsSubscriber( page, requestUtils );

		try {
			await subscriber.goto( '/wp-admin/index.php' );
			await expect( subscriber.locator( '#adminmenu' ) ).toContainText( 'FreeMyInternet' );

			await subscriber.goto( SETTINGS_URL );
			await expect(
				subscriber.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ),
			).toBeVisible();

			// The form itself, not just the wrapper: render_page() returns
			// early on a failed capability check, which would leave the heading
			// printed by nothing and the fields absent.
			await expect( subscriber.locator( field( 'enabled' ) ) ).toBeAttached();
		} finally {
			// Inside a finally, because a filter left answering 'read' would
			// hand the settings screen to a subscriber for the rest of the run
			// and quietly invalidate the test above it.
			wpEval( "delete_option( 'freemyinternet_e2e_capability' ); echo '<<<done>>>';" );
			await context.close();
		}
	} );
} );
