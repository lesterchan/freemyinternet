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
	 * Creates a user who may actually reach the plugin's screens.
	 *
	 * The settings screen takes `manage_options`, which core's map_meta_cap()
	 * does not touch under multisite, so no grant_super_admin() here: a site
	 * administrator holds it on a network exactly as on a single site. Granting
	 * anyway would make the fixture stop representing the operator this plugin
	 * actually has and hide the very class of bug §7.2.2 is about.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

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
	 * The uninstaller does its work in the file body, and PHP will not run a
	 * file body twice -- so the first caller in a process gets the real thing
	 * and any later one would silently get nothing at all. The require is
	 * therefore only there to guarantee the function exists, and the fan-out is
	 * driven from here: the same loop the file itself runs, with the same
	 * arguments.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'freemyinternet/freemyinternet.php' );
		}

		require_once dirname( __DIR__ ) . '/uninstall.php';

		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				freemyinternet_uninstall_site();
				restore_current_blog();
			}

			return;
		}

		freemyinternet_uninstall_site();
	}
}
