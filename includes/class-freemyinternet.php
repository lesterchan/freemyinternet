<?php
/**
 * Plugin bootstrap.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin's components and hands each of them their hooks.
 *
 * Everything the plugin does hangs off this one entry point, so the main file
 * carries nothing but the header, the constants and a single call.
 */
class FreeMyInternet {

	/**
	 * Load the components and wire them up.
	 *
	 * @return void
	 */
	public static function init() {
		require_once FREEMYINTERNET_DIR . 'includes/class-freemyinternet-options.php';
		require_once FREEMYINTERNET_DIR . 'includes/class-freemyinternet-display.php';

		// Must be registered at file-load time, which is when this runs.
		register_activation_hook( FREEMYINTERNET_MAIN_FILE, array( __CLASS__, 'activate' ) );

		FreeMyInternet_Display::init();

		if ( is_admin() ) {
			require_once FREEMYINTERNET_DIR . 'includes/class-freemyinternet-settings.php';

			FreeMyInternet_Settings::init();
		}
	}

	/**
	 * Activation: run the upgrade routine so the version row is stamped.
	 *
	 * @return void
	 */
	public static function activate() {
		FreeMyInternet_Options::maybe_upgrade();
	}
}
