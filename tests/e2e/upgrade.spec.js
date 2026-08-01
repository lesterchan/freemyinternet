/**
 * The upgrade path, from the rows a real install already has.
 *
 * FreeMyInternet spent thirteen years as a plugin that blacked a site out the
 * moment it was activated, storing its settings in a row named after the slug
 * alone. 1.0.0 renamed that row, added a version row beside it, and made the
 * whole thing opt-in. Everything in that sentence is a chance to lose an
 * owner's settings, and the sites it happens to are the ones that never look.
 *
 * The migration runs from two entry points and they are genuinely different.
 * Reactivating fires the activation hook; updating through the Plugins screen
 * never does, and leaves `admin_init` to run `maybe_upgrade()` on its own --
 * which is the usual reason a migration silently never runs at all. Both are
 * exercised here, and the browser is what makes the second one real: nothing but
 * an actual admin request fires `admin_init`.
 *
 * Fixtures are written straight into the option rows, because the shape being
 * tested is one the current code cannot produce. That is the point of them.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	LEGACY_OPTION,
	OPTION,
	PLUGIN_FILE,
	SETTINGS_URL,
	VERSION_OPTION,
	ensurePluginActive,
	openSettings,
	pluginVersion,
	uniqueText,
	wpEval,
} = require( './helpers.js' );

/**
 * Put the three rows into an exact state, in one container round trip.
 *
 * `false` means "no row at all", which is a different state from an empty array
 * and the one a pre-1.0.0 install is in for two of the three.
 *
 * @param {Object}       rows          The rows to write.
 * @param {Object|false} rows.legacy   The pre-1.0.0 settings row.
 * @param {Object|false} rows.options  The current settings row.
 * @param {Object|false} rows.versions The version marker row.
 * @return {void}
 */
function setRows( { legacy = false, options = false, versions = false } ) {
	const data = Buffer.from(
		JSON.stringify( { legacy, options, versions } ),
		'utf8',
	).toString( 'base64' );

	wpEval(
		`$rows = json_decode( base64_decode( '${ data }' ), true );
		foreach ( array( 'legacy' => '${ LEGACY_OPTION }', 'options' => '${ OPTION }', 'versions' => '${ VERSION_OPTION }' ) as $key => $name ) {
			if ( false === $rows[ $key ] ) {
				delete_option( $name );
			} else {
				update_option( $name, $rows[ $key ] );
			}
		}
		echo '<<<done>>>';`,
	);
}

/**
 * All three rows as the database holds them, in one container round trip.
 *
 * @return {Object} Keys `legacy`, `options` and `versions`, each false when unset.
 */
function getRows() {
	return JSON.parse(
		wpEval(
			`echo '<<<' . wp_json_encode(
				array(
					'legacy'   => get_option( '${ LEGACY_OPTION }' ),
					'options'  => get_option( '${ OPTION }' ),
					'versions' => get_option( '${ VERSION_OPTION }' ),
				)
			) . '>>>';`,
		),
	);
}

/**
 * A settings row as an older, laxer release would have written it.
 *
 * Every value here is one the current sanitizer has an opinion about, so a
 * migration that skipped the sanitizer would be visible rather than plausible.
 *
 * @param {string} heading The one value that is meant to survive intact.
 * @return {Object} The row.
 */
function legacyRow( heading ) {
	return {
		enabled: '1',
		mode: 'not a mode at all',
		heading,
		background: 'red;background-image:url(https://example.org/x.png)',
		link_url: 'javascript:window.__pwned = 1',
	};
}

test.describe( 'The upgrade from a pre-1.0.0 install', () => {
	test.afterEach( async () => {
		// The rows decide what every other file in the suite sees, and this one
		// is the only file that ever switches the plugin off. Both are put back
		// whether the test passed or not.
		setRows( {} );
		ensurePluginActive();
	} );

	test( 'the fixture really is a pre-1.0.0 install, and one admin request migrates it', async ( {
		page,
	} ) => {
		const heading = uniqueText( 'Old protest' );

		setRows( { legacy: legacyRow( heading ) } );

		// The precondition the rest of this file leans on. Without it a run in
		// which setRows() quietly did nothing would still go green, because
		// "the legacy row is gone afterwards" is true of a row that was never
		// there.
		const before = getRows();

		expect( before.legacy ).toMatchObject( { heading } );
		expect( before.options ).toBe( false );
		expect( before.versions ).toBe( false );

		// No reactivation anywhere in this test: this is the update-through-the-
		// Plugins-screen path, where the activation hook never fires and the
		// admin_init callback is the only thing that runs. Any admin page will
		// do -- the migration is not the settings screen's own doing.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

		const after = getRows();

		// Folded in, not copied: a legacy row left behind would be picked up
		// again by the next release that went looking for one.
		expect( after.legacy ).toBe( false );

		// And re-sanitised on the way through, so an upgrade cleans a row that
		// an older release wrote just as thoroughly as pressing Save would. The
		// heading survives; the mode that is not a mode, the colour that is not
		// a colour and the javascript: URL do not.
		expect( after.options.heading ).toBe( heading );
		expect( after.options.enabled ).toBe( true );
		expect( after.options.mode ).toBe( 'blackout' );
		expect( after.options.background ).toBe( '#000000' );
		expect( after.options.link_url ).toBe( '' );

		// Both markers, written together, so a half-finished upgrade could not
		// have recorded itself as complete.
		expect( after.versions ).toEqual( { plugin: pluginVersion(), db: '1' } );
	} );

	test( 'the settings the upgrade rescued are the settings the screen shows', async ( { page } ) => {
		const heading = uniqueText( 'Rescued' );

		setRows( { legacy: legacyRow( heading ) } );

		await openSettings( page );

		// Present is not the same as alive. The row can survive the migration
		// and still be the wrong shape for the screen that has to edit it, and
		// the owner would find out by opening a form with their protest missing
		// from it.
		await expect( page.locator( '#freemyinternet-heading' ) ).toHaveValue( heading );
		await expect( page.locator( '#freemyinternet-enabled' ) ).toBeChecked();
		await expect( page.locator( '#freemyinternet-mode' ) ).toBeChecked();
	} );

	test( 'a legacy row never overwrites settings the owner has already saved', async ( { page } ) => {
		const current = uniqueText( 'Already saved' );

		// The shape an install lands in when it ran a development build, saved
		// something through the new screen, and then met the migration. The
		// newer row is the one the owner has actually seen.
		setRows( {
			legacy: legacyRow( uniqueText( 'Stale' ) ),
			options: { heading: current, enabled: false },
		} );

		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

		const after = getRows();

		expect( after.options.heading ).toBe( current );
		expect( after.legacy ).toBe( false );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A row the sanitizer would rewrite if it ran, and version markers
		// saying it has already run. maybe_upgrade() returning early is what
		// keeps an admin request from being twelve option writes, so the proof
		// that it returned early is that this deliberately dirty row survives.
		const dirty = { heading: uniqueText( 'Untouched' ), mode: 'not a mode at all' };

		setRows( { options: dirty, versions: { plugin: pluginVersion(), db: '1' } } );

		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

		expect( getRows().options ).toEqual( dirty );
	} );

	test( 'reactivating stamps the version row, and the plugin still works afterwards', async ( {
		page,
	} ) => {
		const heading = uniqueText( 'Reactivated' );

		// The other entry point. deactivate/activate is what an owner does to
		// "fix" a plugin, and it is the only path that fires the activation
		// hook -- so it has to reach the same migration the admin_init callback
		// does, from the same starting rows.
		setRows( { legacy: legacyRow( heading ) } );

		wpEval(
			`require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( '${ PLUGIN_FILE }' );
			activate_plugin( '${ PLUGIN_FILE }' );
			echo '<<<done>>>';`,
		);

		const after = getRows();

		expect( after.legacy ).toBe( false );
		expect( after.options.heading ).toBe( heading );
		expect( after.versions ).toEqual( { plugin: pluginVersion(), db: '1' } );

		// And the install that came through it is a working one, not just a
		// tidy set of rows: the screen loads and holds what the upgrade wrote.
		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ) ).toBeVisible();
		await expect( page.locator( '#freemyinternet-heading' ) ).toHaveValue( heading );
	} );

	test( 'a second activation changes nothing', async ( { page } ) => {
		setRows( { legacy: legacyRow( uniqueText( 'Idempotent' ) ) } );

		// Owners deactivate and reactivate to fix things, sometimes twice. The
		// second pass has to be a bystander: the rows it finds are the rows it
		// leaves.
		wpEval(
			`require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( '${ PLUGIN_FILE }' );
			activate_plugin( '${ PLUGIN_FILE }' );
			echo '<<<done>>>';`,
		);

		const once = getRows();

		wpEval(
			`require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( '${ PLUGIN_FILE }' );
			activate_plugin( '${ PLUGIN_FILE }' );
			echo '<<<done>>>';`,
		);

		expect( getRows() ).toEqual( once );

		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

		// And the admin_init pass after it is a bystander too, which is the
		// combination a real update actually produces.
		expect( getRows() ).toEqual( once );
	} );
} );
