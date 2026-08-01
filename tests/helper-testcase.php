<?php
/**
 * The base class every test in this plugin extends.
 *
 * @package FreeMyInternet
 */

/**
 * Puts every test back on an unconfigured install.
 *
 * The suite runs in one process, so a row written by one test would otherwise
 * still be there for the next one, and an enqueue would still be registered.
 */
class FreeMyInternet_TestCase extends WP_UnitTestCase {

	/**
	 * Start from a plugin that has never been configured.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( FreeMyInternet_Options::OPTION );
		delete_option( FreeMyInternet_Options::VERSION );
		delete_option( FreeMyInternet_Options::LEGACY_OPTION );

		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	/**
	 * Absolute path to a file in the plugin directory.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	protected function plugin_path( $relative = '' ) {
		return rtrim( dirname( __DIR__ ) . '/' . ltrim( $relative, '/' ), '/' );
	}

	/**
	 * The contents of a file in the plugin directory.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	protected function plugin_file_contents( $relative ) {
		return (string) file_get_contents( $this->plugin_path( $relative ) );
	}

	/**
	 * Run the uninstaller, however many times a suite asks for it.
	 *
	 * The uninstaller declares a global function, so a second require would
	 * fatal on redeclare and a require_once that has already fired proves
	 * nothing. Calling the function directly once it exists is the repeatable
	 * form. Nothing here touches schema, so including the file is safe for the
	 * first caller.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'freemyinternet/freemyinternet.php' );
		}

		if ( function_exists( 'freemyinternet_uninstall_site' ) ) {
			freemyinternet_uninstall_site();

			return;
		}

		require $this->plugin_path( 'uninstall.php' );
	}
}
