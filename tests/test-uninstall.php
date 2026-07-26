<?php
/**
 * The uninstaller.
 *
 * @package FreeMyInternet
 */

/**
 * Covers uninstall.php.
 *
 * The multisite branch is asserted at source level rather than by running it: a
 * single-site test suite cannot build the 101-site network that would be needed
 * to observe WP_Site_Query's default cap of 100 actually truncating the loop.
 * The bug is silent when it happens -- uninstall still reports success while
 * leaving options behind on every site past the hundredth -- so guarding the
 * source is what stops the argument being dropped again.
 */
class Test_FreeMyInternet_Uninstall extends WP_UnitTestCase {

	/**
	 * The uninstaller's source.
	 *
	 * @return string
	 */
	private function source() {
		return file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
	}

	/**
	 * Running the per-site routine removes both rows.
	 */
	public function test_removes_every_option() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );
		FreeMyInternet_Options::maybe_upgrade();

		$this->assertNotFalse( get_option( FreeMyInternet_Options::OPTION_NAME ) );
		$this->assertNotFalse( get_option( FreeMyInternet_Options::VERSION_OPTION_NAME ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'freemyinternet/freemyinternet.php' );
		}

		// require_once: the suite runs in one process, and a second require would
		// redeclare freemyinternet_uninstall_site() and fatal.
		require_once dirname( __DIR__ ) . '/uninstall.php';

		freemyinternet_uninstall_site();

		$this->assertFalse( get_option( FreeMyInternet_Options::OPTION_NAME ) );
		$this->assertFalse( get_option( FreeMyInternet_Options::VERSION_OPTION_NAME ) );
	}

	/**
	 * Every option the plugin writes is deleted. A row added later without a
	 * matching delete_option() would orphan itself forever.
	 */
	public function test_deletes_all_known_rows() {
		$source = $this->source();

		$this->assertStringContainsString( "delete_option( 'freemyinternet' )", $source );
		$this->assertStringContainsString( "delete_option( 'freemyinternet_version' )", $source );
	}

	/**
	 * WP_Site_Query defaults 'number' to 100, so it has to be lifted explicitly.
	 */
	public function test_site_query_is_uncapped() {
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $this->source() );
	}

	/**
	 * Only the IDs are needed; hydrating WP_Site objects for a large network is
	 * wasted work.
	 */
	public function test_site_query_selects_ids_only() {
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $this->source() );
	}

	/**
	 * The restore belongs inside the loop: switch_to_blog() pushes onto a stack,
	 * so restoring once after the loop leaves it unwound by exactly one.
	 */
	public function test_restore_current_blog_is_inside_the_loop() {
		$source = $this->source();

		$this->assertMatchesRegularExpression(
			'/foreach\s*\(.*?\)\s*\{[^}]*switch_to_blog[^}]*restore_current_blog[^}]*\}/s',
			$source
		);
	}

	/**
	 * The removed wp_get_sites() is never called; it went in WP 5.1 and fatals.
	 */
	public function test_does_not_call_the_removed_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->source() );
	}

	/**
	 * Direct access is blocked.
	 */
	public function test_guards_direct_access() {
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;", $this->source() );
	}
}
