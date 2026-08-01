/**
 * Shared steps for the FreeMyInternet end-to-end suite.
 *
 * The plugin owns exactly one settings row and prints one overlay into
 * wp_footer, so the fixtures come from one of two places. Anything an owner
 * would type goes through Settings -> FreeMyInternet, so the screen that writes
 * the row is exercised by the tests that depend on it. Anything an owner could
 * not type -- a heading holding a script tag, a colour that is not a colour --
 * goes straight into the option row through WP-CLI, because the point of those
 * tests is what the front end does with a row it did not sanitise itself.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const SETTINGS_URL = '/wp-admin/options-general.php?page=freemyinternet';
const PLUGINS_URL = '/wp-admin/plugins.php';

/** The option row every setting lives in. */
const OPTION = 'freemyinternet_options';

/** The option row holding the 'plugin' and 'db' version markers. */
const VERSION_OPTION = 'freemyinternet_version';

/** The settings row this plugin wrote before 1.0.0 renamed it. */
const LEGACY_OPTION = 'freemyinternet';

/** The plugin file, as WordPress names it on the Plugins screen. */
const PLUGIN_FILE = 'freemyinternet/freemyinternet.php';

/** The root element the overlay renders as. */
const OVERLAY = '.freemyinternet';

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a heading holding quotes,
 * angle brackets and a script tag is exactly the sort of string that arrives at
 * the other end subtly different, and a fixture that is not the payload byte for
 * byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Write the settings row exactly as given, without going near the sanitizer.
 *
 * Only the keys handed in are stored. That is deliberate and it is also
 * realistic: FreeMyInternet_Options::get() merges the defaults on read, which is
 * what lets a row written by an older version pick up keys that did not exist
 * then, so a partial row is a shape real installs have.
 *
 * @param {Object} options Option keys to store.
 * @return {void}
 */
function setOptions( options ) {
	const data = Buffer.from( JSON.stringify( options ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * The settings row as the database holds it, before any defaults are merged in.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function getStoredOptions() {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( get_option( '${ OPTION }' ) ) . '>>>';` ) );
}

/**
 * Remove the settings row entirely, which is the state a fresh install is in.
 *
 * @return {void}
 */
function deleteOptions() {
	wpEval( `delete_option( '${ OPTION }' ); echo '<<<done>>>';` );
}

/**
 * Several moments on the site's clock, formatted the way the plugin stores it.
 *
 * The schedule is read in the site's timezone, so a bound built from this
 * machine's clock is wrong by the offset between the two -- and on a developer
 * machine at UTC+8 that is enough to put a window that should be open eight
 * hours in the future. Asking the site what time it thinks it is removes the
 * question rather than assuming an answer.
 *
 * Every bound a test needs comes back from one call because each one costs a
 * container round trip of several seconds, and a test that asked four times ran
 * out of Playwright's minute while doing nothing but waiting for `npx`.
 *
 * @param {...number} offsetSeconds How far from now, negative for the past.
 * @return {string[]} `Y-m-d H:i` datetimes in the site's timezone, in the order asked for.
 */
function siteDatetimes( ...offsetSeconds ) {
	const offsets = offsetSeconds.map( ( offset ) => parseInt( offset, 10 ) ).join( ',' );

	return JSON.parse(
		wpEval(
			`$out = array();
			foreach ( array( ${ offsets } ) as $offset ) {
				$out[] = wp_date( 'Y-m-d H:i', time() + $offset );
			}
			echo '<<<' . wp_json_encode( $out ) . '>>>';`,
		),
	);
}

/**
 * One moment on the site's clock.
 *
 * @param {number} offsetSeconds How far from now, negative for the past.
 * @return {string} A `Y-m-d H:i` datetime in the site's timezone.
 */
function siteDatetime( offsetSeconds ) {
	return siteDatetimes( offsetSeconds )[ 0 ];
}

/**
 * The plugin's own version constant, so the upgrade tests do not hard-code it.
 *
 * A test asserting the literal '1.0.0' would start failing on the version bump
 * that changed nothing about the migration, which teaches the next reader to
 * edit the test rather than to read it.
 *
 * @return {string} The value of FREEMYINTERNET_VERSION.
 */
function pluginVersion() {
	return wpEval( "echo '<<<' . FREEMYINTERNET_VERSION . '>>>';" );
}

/**
 * Put the plugin back on, whatever a test left behind.
 *
 * Only the upgrade suite ever switches it off, and only for as long as it takes
 * to prove the activation hook runs -- but a failure in the middle of that would
 * otherwise hand every later test a site with no plugin, and the whole run would
 * report a hundred symptoms of one cause.
 *
 * @return {void}
 */
function ensurePluginActive() {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( '${ PLUGIN_FILE }' ) ) {
			activate_plugin( '${ PLUGIN_FILE }' );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Open the settings screen.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page ) {
	await page.goto( SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'FreeMyInternet Settings' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice rather than the redirect: options.php sends the browser back
	// whether or not anything was written, so arriving here again says nothing.
	//
	// .first() because this screen currently prints the notice twice -- see the
	// dedicated test for that in settings.spec.js. Waiting on "a notice saying
	// Settings saved." is what every other test here actually needs, and letting
	// one cosmetic defect fail nine unrelated tests would hide the nine.
	await expect( page.locator( '#setting-error-settings_updated' ).first() ).toContainText(
		'Settings saved.',
	);
}

/**
 * The id of a field, which is also the id its label points at.
 *
 * @param {string} name Option key.
 * @return {string} A CSS id selector.
 */
function field( name ) {
	return `#freemyinternet-${ name }`;
}

/**
 * The overlay, in whichever of its two presentations.
 *
 * @param {import('@playwright/test').Page} page Page that may be showing it.
 * @return {import('@playwright/test').Locator} The overlay root.
 */
function overlay( page ) {
	return page.locator( OVERLAY );
}

/**
 * Text no earlier run can have used.
 *
 * The dismissal is remembered in localStorage against a signature of the notice,
 * and the browser context outlives a single test, so two tests sharing a heading
 * would share a dismissal too.
 *
 * @param {string} base What the text should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueText( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

module.exports = {
	LEGACY_OPTION,
	OPTION,
	OVERLAY,
	PLUGINS_URL,
	PLUGIN_FILE,
	SETTINGS_URL,
	VERSION_OPTION,
	deleteOptions,
	ensurePluginActive,
	field,
	getStoredOptions,
	openSettings,
	overlay,
	pluginVersion,
	saveSettings,
	setOptions,
	siteDatetime,
	siteDatetimes,
	uniqueText,
	wpEval,
};
