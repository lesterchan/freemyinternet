/**
 * The overlay as a visitor meets it.
 *
 * This is the half PHPUnit cannot see. The plugin's whole output is a div, a
 * stylesheet inlined into the head and a script inlined into the footer, and
 * every interesting thing about it is a browser fact: whether the blackout
 * actually covers the viewport, whether the banner leaves the page readable,
 * whether the colours saved in the row become the colours on the screen, and
 * whether a dismissal survives a reload.
 *
 * Fixtures go straight into the option row rather than through the settings
 * screen. That screen has its own file; going through it here would make every
 * one of these tests depend on it, and would make a form regression look like a
 * rendering regression.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	deleteOptions,
	overlay,
	setOptions,
	siteDatetimes,
	uniqueText,
	wpEval,
} = require( './helpers.js' );

/** The inline stylesheet WordPress prints for the plugin's srcless handle. */
const STYLE = 'style#freemyinternet-inline-css';

/** The inline dismissal script, printed only when there is a button to dismiss. */
const SCRIPT = 'script#freemyinternet-js-after';

/** A post to look at, so the front end has something under the overlay. */
let postLink;

test.describe( 'The front-end overlay', () => {
	// A visitor, not the administrator the rest of the suite runs as. Partly
	// because that is who the overlay is for, and partly for a mechanical
	// reason: the admin bar is fixed across the top of the front end at the same
	// stacking level, directly over the dismiss button, so a logged-in run would
	// have its clicks intercepted by a bar no visitor ever sees.
	test.use( { storageState: { cookies: [], origins: [] } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		const post = await requestUtils.createPost( {
			title: 'Something to read',
			content: 'The site carries on underneath.',
			status: 'publish',
		} );

		postLink = post.link;
	} );

	test.afterEach( async () => {
		// The row decides what every other test sees, so nobody leaves theirs
		// switched on.
		deleteOptions();
	} );

	test( 'the fixture really is switched off by default, and switching it on is what renders it', async ( {
		page,
	} ) => {
		// Both halves in one test on purpose. "Nothing renders" is true with the
		// plugin deactivated as well, so on its own it proves nothing; the second
		// half is what makes this fail when the plugin is gone.
		setOptions( { enabled: false, heading: uniqueText( 'Not yet' ) } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveCount( 0 );

		// And no cost either: the inline CSS and JS are only registered when the
		// notice is actually going to be shown.
		await expect( page.locator( STYLE ) ).toHaveCount( 0 );
		await expect( page.locator( SCRIPT ) ).toHaveCount( 0 );

		setOptions( { enabled: true, heading: uniqueText( 'Now then' ) } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
		await expect( page.locator( STYLE ) ).toHaveCount( 1 );
		await expect( page.locator( SCRIPT ) ).toHaveCount( 1 );
	} );

	test( 'the blackout covers the viewport', async ( { page } ) => {
		const heading = uniqueText( 'Blacked out' );

		setOptions( { enabled: true, mode: 'blackout', heading } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveClass( /freemyinternet--blackout/ );
		await expect( overlay( page ) ).toContainText( heading );

		// The geometry is the point. A blackout that renders correctly and sits
		// in the document flow is not a blackout, and no assertion about its
		// markup would notice.
		const box = await overlay( page ).boundingBox();
		const viewport = page.viewportSize();

		expect( box.x ).toBe( 0 );
		expect( box.y ).toBe( 0 );
		expect( box.width ).toBe( viewport.width );
		expect( box.height ).toBe( viewport.height );

		await expect( overlay( page ) ).toHaveCSS( 'position', 'fixed' );
	} );

	test( 'the banner sits at the top and leaves the page readable', async ( { page } ) => {
		setOptions( { enabled: true, mode: 'banner', heading: uniqueText( 'Banner' ) } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveClass( /freemyinternet--banner/ );

		const box = await overlay( page ).boundingBox();
		const viewport = page.viewportSize();

		expect( box.y ).toBe( 0 );
		expect( box.width ).toBe( viewport.width );

		// The difference between the two presentations is exactly this: the
		// banner is a strip, not a curtain, and the article underneath is still
		// there to be read.
		expect( box.height ).toBeLessThan( viewport.height / 2 );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'The site carries on underneath' );
	} );

	test( 'the saved colours are the colours on the screen', async ( { page } ) => {
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Coloured' ),
			background: '#102030',
			text_color: '#f0e0d0',
		} );

		await page.goto( postLink );

		// The computed style, not the attribute: the plugin sets two custom
		// properties inline and its stylesheet reads them, so an attribute
		// assertion would pass even if the sheet never arrived.
		await expect( overlay( page ) ).toHaveCSS( 'background-color', 'rgb(16, 32, 48)' );
		await expect( overlay( page ) ).toHaveCSS( 'color', 'rgb(240, 224, 208)' );
	} );

	test( 'a colour the row should never have held falls back instead of reaching the style attribute', async ( {
		page,
	} ) => {
		// The row is not always written by the settings screen. This is what a
		// value smuggled past the sanitizer would do: the inline style attribute
		// is a CSS injection point, so render() sanitises again on the way out.
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Bad colour' ),
			background: 'red;background-image:url(https://example.org/x.png)',
			text_color: 'nonsense',
		} );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveCSS( 'background-color', 'rgb(0, 0, 0)' );
		await expect( overlay( page ) ).toHaveCSS( 'color', 'rgb(255, 255, 255)' );
		await expect( overlay( page ) ).toHaveCSS( 'background-image', 'none' );
	} );

	test( 'the image renders only when the row holds a URL', async ( { page } ) => {
		setOptions( { enabled: true, heading: uniqueText( 'No image' ) } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
		await expect( overlay( page ).locator( 'img.freemyinternet__image' ) ).toHaveCount( 0 );

		// A URL on this site, so the browser really loads it and a broken path
		// would show up as a zero-sized element rather than passing quietly.
		setOptions( {
			enabled: true,
			heading: uniqueText( 'With image' ),
			image_url: '/wp-includes/images/w-logo-blue.png',
		} );

		await page.goto( postLink );

		await expect( overlay( page ).locator( 'img.freemyinternet__image' ) ).toBeVisible();
		await expect( overlay( page ).locator( 'img.freemyinternet__image' ) ).toHaveAttribute(
			'src',
			'/wp-includes/images/w-logo-blue.png',
		);
	} );

	test( 'the call-to-action needs both a URL and its text', async ( { page } ) => {
		const link = () => overlay( page ).locator( 'a.freemyinternet__link' );

		setOptions( { enabled: true, heading: uniqueText( 'URL only' ), link_url: 'https://example.org/', link_text: '' } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
		await expect( link() ).toHaveCount( 0 );

		setOptions( { enabled: true, heading: uniqueText( 'Text only' ), link_url: '', link_text: 'Read on' } );

		await page.goto( postLink );

		await expect( link() ).toHaveCount( 0 );

		setOptions( {
			enabled: true,
			heading: uniqueText( 'Both' ),
			link_url: 'https://example.org/',
			link_text: 'Read on',
		} );

		await page.goto( postLink );

		await expect( link() ).toHaveText( 'Read on' );
		await expect( link() ).toHaveAttribute( 'href', 'https://example.org/' );
	} );

	test( 'the message keeps the markup a post is allowed', async ( { page } ) => {
		setOptions( {
			enabled: true,
			heading: uniqueText( 'Marked up' ),
			message: '<em>Please</em> read <a href="https://example.org/bill">the bill</a>',
		} );

		await page.goto( postLink );

		const message = overlay( page ).locator( '.freemyinternet__message' );

		// wp_kses_post() is what the message goes through, so emphasis and a link
		// are meant to survive as elements rather than as escaped text -- this is
		// the one field where markup is the feature.
		await expect( message.locator( 'em' ) ).toHaveText( 'Please' );
		await expect( message.locator( 'a[href="https://example.org/bill"]' ) ).toHaveText( 'the bill' );
	} );

	test( 'a notice with nothing to say is not shown', async ( { page } ) => {
		setOptions( { enabled: true, heading: '', message: '' } );

		await page.goto( postLink );

		// An empty black screen is worse than no protest at all, so the plugin
		// treats "on but blank" as off.
		await expect( overlay( page ) ).toHaveCount( 0 );

		setOptions( { enabled: true, heading: '', message: 'A message and no heading is enough' } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
	} );

	test( 'a notice nobody may dismiss carries neither the button nor the script', async ( { page } ) => {
		setOptions( { enabled: true, heading: uniqueText( 'Undismissable' ), dismissible: false } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
		await expect( overlay( page ).locator( '.freemyinternet__dismiss' ) ).toHaveCount( 0 );

		// The script exists only to answer that button, so a notice without one
		// must not pay for it either.
		await expect( page.locator( SCRIPT ) ).toHaveCount( 0 );
		await expect( page.locator( STYLE ) ).toHaveCount( 1 );
	} );

	test( 'dismissing removes the overlay and is remembered across a reload', async ( { page } ) => {
		setOptions( { enabled: true, heading: uniqueText( 'Dismiss me' ), dismissible: true } );

		await page.goto( postLink );

		await overlay( page ).locator( '.freemyinternet__dismiss' ).click();

		await expect( overlay( page ) ).toHaveCount( 0 );

		// The far end, not the disappearance: the dismissal is only useful if it
		// outlives the page, and localStorage is where the script puts it.
		const remembered = await page.evaluate( () =>
			Object.keys( window.localStorage ).some( ( key ) =>
				key.startsWith( 'freemyinternet-dismissed:' ),
			),
		);

		expect( remembered ).toBe( true );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveCount( 0 );
	} );

	test( 'editing the notice shows it again to a visitor who dismissed the last one', async ( { page } ) => {
		setOptions( { enabled: true, heading: uniqueText( 'First protest' ), dismissible: true } );

		await page.goto( postLink );
		await overlay( page ).locator( '.freemyinternet__dismiss' ).click();
		await expect( overlay( page ) ).toHaveCount( 0 );

		// The dismissal is stored against a signature of the notice's text and
		// schedule, so a new protest is a new notice and everyone sees it --
		// otherwise the first campaign a visitor closed would silence every one
		// after it.
		setOptions( { enabled: true, heading: uniqueText( 'Second protest' ), dismissible: true } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toBeVisible();
	} );

	test( 'the schedule decides whether the overlay renders at all', async ( { page } ) => {
		const [ past, future ] = siteDatetimes( -3600, 3600 );

		// Bounds are read on the site's clock, which is why they are asked of the
		// site rather than built from this machine's -- a developer machine eight
		// hours away from the site would put "an hour ago" in the future.
		setOptions( { enabled: true, heading: uniqueText( 'Not started' ), start: future } );

		await page.goto( postLink );
		await expect( overlay( page ) ).toHaveCount( 0 );

		setOptions( { enabled: true, heading: uniqueText( 'Already over' ), end: past } );

		await page.goto( postLink );
		await expect( overlay( page ) ).toHaveCount( 0 );

		setOptions( { enabled: true, heading: uniqueText( 'Running now' ), start: past, end: future } );

		await page.goto( postLink );
		await expect( overlay( page ) ).toBeVisible();
	} );

	test( 'a notice with no heading still names itself to a screen reader', async ( { page } ) => {
		// The overlay is a landmark, and an unnamed landmark is a region a
		// screen reader announces as nothing at all. The heading is the name
		// when there is one; a message-only notice is the case that needs the
		// fallback, and it is the case nobody looks at.
		setOptions( { enabled: true, heading: '', message: 'A message and no heading' } );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveAttribute( 'role', 'region' );
		await expect( overlay( page ) ).toHaveAttribute( 'aria-label', 'Site notice' );
		await expect( overlay( page ).locator( '.freemyinternet__heading' ) ).toHaveCount( 0 );
	} );

	test( 'a site can veto the overlay through the display filter', async ( { page } ) => {
		const heading = uniqueText( 'Vetoed' );

		setOptions( { enabled: true, heading } );

		// Both directions in one test. The filter is the documented escape
		// hatch -- keep the protest off the checkout page, show it to
		// logged-out visitors only -- and "the overlay is absent" is true with
		// the plugin deactivated too, so the half that proves anything is the
		// one where the filter stops answering and the overlay comes back.
		//
		// The fixture mu-plugin attaches the filter and reads its answer from
		// this option, so it stays inert for every other test in the file.
		wpEval( "update_option( 'freemyinternet_e2e_should_display', 0 ); echo '<<<done>>>';" );

		await page.goto( postLink );

		await expect( overlay( page ) ).toHaveCount( 0 );

		wpEval( "delete_option( 'freemyinternet_e2e_should_display' ); echo '<<<done>>>';" );

		await page.goto( postLink );

		await expect( overlay( page ) ).toContainText( heading );
	} );
} );

test.describe( 'The overlay and wp-admin', () => {
	test.afterEach( async () => {
		deleteOptions();
	} );

	test( 'the overlay reaches the front end and never wp-admin', async ( { page } ) => {
		setOptions( { enabled: true, mode: 'blackout', heading: uniqueText( 'Everywhere but here' ) } );

		// This one stays logged in, because the point of it is the screen only an
		// administrator can reach.
		await page.goto( '/' );
		await expect( overlay( page ) ).toBeVisible();

		// A full-screen blackout in wp-admin would lock the owner out of the very
		// screen that turns it off, which is why everything hangs off wp_footer:
		// it fires on the front end and nowhere else.
		await page.goto( '/wp-admin/options-general.php?page=freemyinternet' );

		await expect( page.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ) ).toBeVisible();
		await expect( overlay( page ) ).toHaveCount( 0 );
	} );
} );
