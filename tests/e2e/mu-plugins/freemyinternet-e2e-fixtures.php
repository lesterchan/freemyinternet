<?php
/**
 * Plugin Name: FreeMyInternet E2E fixtures
 * Description: Attaches the plugin's two public filters, so the browser suite can reach the paths a theme or a site plugin owns. Loaded only in the wp-env tests environment.
 *
 * The plugin documents two filters as public API, and both of them decide
 * something a browser can see and nothing else can. `freemyinternet_should_display`
 * is the escape hatch a site uses to keep the overlay off the checkout page or to
 * show it to logged-out visitors only; `freemyinternet_capability` is how a site
 * hands the settings screen to somebody who is not an administrator. Neither has
 * a caller in the plugin itself, so without a file playing the part of the theme
 * or site plugin that would attach them, both would ship untested.
 *
 * Both read their answer out of an option rather than deciding it here. A filter
 * that always suppressed the overlay would quietly turn every other test in the
 * display suite green for the wrong reason, so this file is inert until a test
 * asks it for something and puts the option back afterwards.
 *
 * It is a fixture, not a shipped file: it lives under tests/ and is mapped into
 * wp-content/mu-plugins for the tests environment only, by .wp-env.json.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/*
 * Web requests only.
 *
 * wp-env maps this directory into the tests environment, and PHPUnit runs in
 * that same environment -- so without this guard the unit suite would load a
 * fixture that has no business being visible to a test that is not driving a
 * browser.
 */
if ( 'cli' === PHP_SAPI ) {
	return;
}

/**
 * Answer the display filter with whatever the test asked for.
 *
 * Absent option means "say nothing", which is not the same as saying true: the
 * plugin's own decision has to remain the one under test everywhere else.
 *
 * @param bool $display Whether the plugin decided to show the overlay.
 * @return bool
 */
function freemyinternet_e2e_should_display( $display ) {
	$answer = get_option( 'freemyinternet_e2e_should_display', null );

	if ( null === $answer ) {
		return $display;
	}

	return (bool) $answer;
}

add_filter( 'freemyinternet_should_display', 'freemyinternet_e2e_should_display' );

/**
 * Hand the settings screen to a lesser capability when the test asks.
 *
 * The plugin filters its capability for exactly this: a site that wants an
 * editor to be able to switch the protest on without giving them the keys to
 * everything else. `read` is the capability every logged-in user has, so a
 * subscriber reaching the screen is unambiguous evidence the filter was honoured
 * rather than that some other gate happened to let them by.
 *
 * @param string $capability The capability the plugin requires.
 * @return string
 */
function freemyinternet_e2e_capability( $capability ) {
	$answer = get_option( 'freemyinternet_e2e_capability', '' );

	return '' === $answer ? $capability : (string) $answer;
}

add_filter( 'freemyinternet_capability', 'freemyinternet_e2e_capability' );
