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
class FreeMyInternet_Uninstall_Test extends FreeMyInternet_TestCase {

	/**
	 * The uninstaller's source.
	 *
	 * @return string
	 */
	private function source() {
		return $this->plugin_file_contents( 'uninstall.php' );
	}

	/**
	 * The uninstaller's source with comments stripped.
	 *
	 * Every assertion below must run against this rather than the raw source. The
	 * comments in uninstall.php explain the very arguments being asserted on --
	 * "'number' => 0 is required" sits directly above the code -- so matching the
	 * raw file passes even when the argument itself has been changed. That is not
	 * hypothetical: it was caught by changing 'number' => 0 to 100 and watching
	 * this file stay green.
	 *
	 * @return string
	 */
	private function code() {
		$code = '';

		foreach ( token_get_all( $this->source() ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}

				$code .= $token[1];
			} else {
				$code .= $token;
			}
		}

		return $code;
	}

	public function test_the_comment_stripping_helper_really_strips_comments() {
		$this->assertStringContainsString( 'WP_Site_Query', $this->source(), 'the guard only means something if the comment is there.' );
		$this->assertStringNotContainsString( 'WP_Site_Query', $this->code(), 'the helper must strip comments before anything is asserted.' );
	}

	public function test_running_the_per_site_routine_removes_both_rows() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );
		FreeMyInternet_Options::maybe_upgrade();

		$this->assertNotFalse( get_option( FreeMyInternet_Options::OPTION ), 'the settings row must exist before it can be removed.' );
		$this->assertNotFalse( get_option( FreeMyInternet_Options::VERSION ), 'the version row must exist before it can be removed.' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'freemyinternet/freemyinternet.php' );
		}

		// require_once: the suite runs in one process, and a second require would
		// redeclare freemyinternet_uninstall_site() and fatal.
		require_once $this->plugin_path( 'uninstall.php' );

		freemyinternet_uninstall_site();

		$this->assertFalse( get_option( FreeMyInternet_Options::OPTION ), 'the settings row must be deleted.' );
		$this->assertFalse( get_option( FreeMyInternet_Options::VERSION ), 'the version row must be deleted.' );
	}

	public function test_every_row_the_plugin_writes_is_named_in_the_uninstaller() {
		$code = $this->code();

		$this->assertStringContainsString( "delete_option( 'freemyinternet_options' )", $code, 'the settings row is not deleted.' );
		$this->assertStringContainsString( "delete_option( 'freemyinternet_version' )", $code, 'the version row is not deleted.' );
		$this->assertStringContainsString( "delete_option( 'freemyinternet' )", $code, 'the pre-release settings row is not deleted.' );
	}

	public function test_the_site_query_is_uncapped() {
		$this->assertMatchesRegularExpression(
			"/'number'\s*=>\s*0/",
			$this->code(),
			"WP_Site_Query defaults 'number' to 100, so it has to be lifted explicitly."
		);
	}

	public function test_the_site_query_selects_ids_only() {
		$this->assertMatchesRegularExpression(
			"/'fields'\s*=>\s*'ids'/",
			$this->code(),
			'hydrating WP_Site objects for a large network is wasted work.'
		);
	}

	public function test_the_blog_is_restored_inside_the_loop() {
		$this->assertMatchesRegularExpression(
			'/foreach\s*\(.*?\)\s*\{[^}]*switch_to_blog[^}]*restore_current_blog[^}]*\}/s',
			$this->code(),
			'switch_to_blog() pushes onto a stack, so restoring once after the loop leaves it unwound by one.'
		);
	}

	public function test_the_removed_wp_get_sites_is_never_called() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->code(), 'wp_get_sites() went in WP 5.1 and fatals.' );
	}

	public function test_direct_access_to_the_uninstaller_is_blocked() {
		$this->assertStringContainsString(
			"defined( 'WP_UNINSTALL_PLUGIN' ) || exit;",
			$this->code(),
			'the uninstaller must not run outside an uninstall.'
		);
	}
}
