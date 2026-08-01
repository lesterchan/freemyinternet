/**
 * The stored XSS regressions, in a real browser.
 *
 * The settings screen sanitises on the way in, but that is the assumption under
 * test rather than a step to reproduce: these fixtures go straight into the
 * option row, which is the row a compromised install, an older laxer release or
 * a WP-CLI one-liner already left behind. Everything the plugin then renders
 * from that row is checked here -- the overlay a visitor gets, and the form the
 * owner opens to fix it.
 *
 * The assertion is the same everywhere and it has two halves: the sentinel the
 * payload would set must never become defined, and the payload's text must still
 * be on the page. Escaping that ate the value entirely passes the first half and
 * is its own bug -- a protest heading that silently vanishes is one.
 *
 * The message field is the exception worth reading carefully. It is the one
 * field allowed inline markup, so it goes through wp_kses_post(), which keeps a
 * bare <img> and strips only its onerror. So the check there is not "no image"
 * -- an image is the feature -- but "nothing that runs".
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { deleteOptions, field, overlay, setOptions, uniqueText } = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

/**
 * A settings row in which every field is an attack.
 *
 * @return {Object} The stored options, so a test can assert on the same strings.
 */
function setHostileOptions() {
	const options = {
		enabled: true,
		mode: 'blackout',
		heading: `${ uniqueText( 'Hostile' ) } ${ SCRIPT_PAYLOAD }`,
		message: `Message ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD }`,
		link_url: 'https://example.org/protest',
		link_text: `Read on ${ ATTR_PAYLOAD }`,
		image_url: `https://example.org/x.png${ ATTR_PAYLOAD }`,
		dismissible: true,
	};

	setOptions( options );

	return options;
}

test.describe( 'A hostile option row stays inert', () => {
	test.afterEach( async () => {
		deleteOptions();
	} );

	test( 'the overlay renders every payload as text and none of them run', async ( { page } ) => {
		setHostileOptions();

		await page.goto( '/' );

		await expect( overlay( page ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );

		// Nothing that runs, anywhere in the overlay: no script element the
		// browser would have executed, and no event handler attribute.
		await expect( overlay( page ).locator( 'script' ) ).toHaveCount( 0 );
		await expect( overlay( page ).locator( '[onerror]' ) ).toHaveCount( 0 );
		await expect( overlay( page ).locator( '[onmouseover]' ) ).toHaveCount( 0 );

		// And the values survived. The heading is escaped whole; the message
		// keeps what wp_kses_post() allows, which means the script tag's text
		// remains after its tags are stripped.
		await expect( overlay( page ).locator( '.freemyinternet__heading' ) ).toContainText(
			'window.__pwned',
		);
		await expect( overlay( page ).locator( '.freemyinternet__message' ) ).toContainText( 'Message' );
		await expect( overlay( page ).locator( '.freemyinternet__link' ) ).toContainText( 'onmouseover' );
	} );

	test( 'an attribute-breaking heading cannot escape the attributes it is printed into', async ( {
		page,
	} ) => {
		// The heading is printed twice, and the second time is the dangerous one:
		// once as text, and once into the aria-label of the overlay's own root
		// element. An unescaped quote there ends the attribute and everything
		// after it becomes markup on the container itself.
		const heading = `${ uniqueText( 'Breakout' ) } ${ ATTR_PAYLOAD }`;

		setOptions( { enabled: true, heading } );

		await page.goto( '/' );

		await expect( overlay( page ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );

		await expect( overlay( page ) ).toHaveAttribute( 'aria-label', heading );
		await expect( overlay( page ) ).toHaveAttribute( 'role', 'region' );
		await expect( page.locator( '[onmouseover]' ) ).toHaveCount( 0 );
	} );

	test( 'a javascript: URL in the row never becomes an href or a src', async ( { page } ) => {
		// esc_url_raw() keeps this out of the row on the way in, so the only way
		// a row holds it is the way this fixture does. render() escapes again on
		// the way out, which is what this proves is not redundant.
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Bad protocols' ),
			link_url: 'javascript:window.__pwned = 1',
			link_text: 'Read on',
			image_url: 'javascript:window.__pwned = 1',
		} );

		await page.goto( '/' );

		await expect( overlay( page ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );

		// esc_url() empties an unsafe protocol, and both the link and the image
		// are only printed when their URL survives it -- so neither is here.
		await expect( overlay( page ).locator( 'a[href^="javascript:"]' ) ).toHaveCount( 0 );
		await expect( overlay( page ).locator( 'img[src^="javascript:"]' ) ).toHaveCount( 0 );
		await expect( overlay( page ).locator( 'a.freemyinternet__link' ) ).toHaveCount( 0 );
		await expect( overlay( page ).locator( 'img.freemyinternet__image' ) ).toHaveCount( 0 );
	} );

	test( 'the settings screen round-trips a hostile row without running or losing it', async ( {
		page,
	} ) => {
		const options = setHostileOptions();

		await page.goto( '/wp-admin/options-general.php?page=freemyinternet' );

		await expect( page.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#wpbody [onerror]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpbody [onmouseover]' ) ).toHaveCount( 0 );

		// The fields must hold the stored values themselves. A screen that
		// escaped a value into the attribute and decoded it on save is fine; one
		// that dropped or double-escaped it corrupts the row the moment the owner
		// presses Save Changes -- which is the first thing an owner does after
		// finding a payload in their notice.
		await expect( page.locator( field( 'heading' ) ) ).toHaveValue( options.heading );
		await expect( page.locator( field( 'message' ) ) ).toHaveValue( options.message );
		await expect( page.locator( field( 'link_text' ) ) ).toHaveValue( options.link_text );
	} );
} );
