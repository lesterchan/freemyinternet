<?php
/**
 * Migration and version-marker tests.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Options::maybe_upgrade() and the markers it stamps.
 */
class FreeMyInternet_Upgrade_Test extends FreeMyInternet_TestCase {

	public function test_the_upgrade_routine_stamps_both_markers_in_one_row() {
		FreeMyInternet_Options::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => FREEMYINTERNET_VERSION,
				'db'     => FREEMYINTERNET_DB_VERSION,
			),
			get_option( FreeMyInternet_Options::VERSION ),
			'both markers must be written together, in the version row.'
		);
	}

	public function test_the_upgrade_routine_can_run_twice_without_resetting_the_settings() {
		FreeMyInternet_Options::maybe_upgrade();
		FreeMyInternet_Options::update( array( 'heading' => 'Set by the user' ) );
		FreeMyInternet_Options::maybe_upgrade();

		$this->assertSame(
			'Set by the user',
			FreeMyInternet_Options::get( 'heading' ),
			're-running the upgrade must not overwrite what the owner saved.'
		);
	}

	public function test_the_upgrade_routine_moves_the_pre_release_settings_row_across() {
		update_option(
			FreeMyInternet_Options::LEGACY_OPTION,
			array(
				'enabled' => true,
				'heading' => 'From the old row',
			)
		);

		FreeMyInternet_Options::maybe_upgrade();

		$this->assertSame(
			'From the old row',
			FreeMyInternet_Options::get( 'heading' ),
			'the old settings row must be folded into the new one.'
		);
		$this->assertFalse(
			get_option( FreeMyInternet_Options::LEGACY_OPTION ),
			'the old settings row must be deleted once it has been folded in.'
		);
	}

	public function test_the_upgrade_routine_reshapes_a_bare_version_string_into_the_two_markers() {
		update_option( FreeMyInternet_Options::VERSION, '0.9.0' );

		FreeMyInternet_Options::maybe_upgrade();

		$stored = get_option( FreeMyInternet_Options::VERSION );

		$this->assertIsArray( $stored, 'a bare version string must be reshaped into an array.' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $stored ), 'the reshaped row must hold exactly plugin and db.' );
	}

	public function test_the_upgrade_routine_re_sanitises_a_row_written_by_an_older_version() {
		update_option(
			FreeMyInternet_Options::OPTION,
			array(
				'heading'  => 'Protest<script>alert(1)</script>',
				'link_url' => 'javascript:alert(1)',
			)
		);

		FreeMyInternet_Options::maybe_upgrade();

		$this->assertStringNotContainsString(
			'<script',
			FreeMyInternet_Options::get( 'heading' ),
			'the upgrade must re-sanitise the stored settings, not just stamp a version.'
		);
		$this->assertSame( '', FreeMyInternet_Options::get( 'link_url' ), 'an unsafe stored URL must not survive the upgrade.' );
	}

	public function test_activating_the_plugin_stamps_the_version_row() {
		FreeMyInternet::activate();

		$this->assertSame(
			array(
				'plugin' => FREEMYINTERNET_VERSION,
				'db'     => FREEMYINTERNET_DB_VERSION,
			),
			get_option( FreeMyInternet_Options::VERSION ),
			'activation must leave the version row stamped.'
		);
	}

	public function test_the_version_markers_are_read_back_as_strings_even_when_unset() {
		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			FreeMyInternet_Options::get_versions(),
			'an unstamped install must report both markers as empty strings.'
		);
	}
}
