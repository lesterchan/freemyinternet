<?php
/**
 * Options: defaults, merging and sanitizing.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Options.
 */
class Test_FreeMyInternet_Options extends WP_UnitTestCase {

	/**
	 * Start every test from an unconfigured install.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( FreeMyInternet_Options::OPTION_NAME );
		delete_option( FreeMyInternet_Options::VERSION_OPTION_NAME );
	}

	/**
	 * A fresh install must not show anything. Version 0.01 blacked the site out
	 * the moment it was activated; that must not survive the upgrade.
	 */
	public function test_disabled_by_default() {
		$this->assertFalse( FreeMyInternet_Options::get( 'enabled' ) );
	}

	/**
	 * Everything lives in one row.
	 */
	public function test_settings_live_in_a_single_option_row() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );

		$this->assertIsArray( get_option( FreeMyInternet_Options::OPTION_NAME ) );
		$this->assertSame( 'freemyinternet', FreeMyInternet_Options::OPTION_NAME );
	}

	/**
	 * Keys missing from a stored row fall back to the defaults, which is what lets
	 * an upgraded install pick up settings added in a later release.
	 */
	public function test_stored_values_merge_over_defaults() {
		update_option( FreeMyInternet_Options::OPTION_NAME, array( 'heading' => 'Stored' ) );

		$this->assertSame( 'Stored', FreeMyInternet_Options::get( 'heading' ) );
		$this->assertSame( 'blackout', FreeMyInternet_Options::get( 'mode' ) );
		$this->assertFalse( FreeMyInternet_Options::get( 'enabled' ) );
	}

	/**
	 * An unknown key is null rather than a notice.
	 */
	public function test_unknown_key_returns_null() {
		$this->assertNull( FreeMyInternet_Options::get( 'no_such_key' ) );
	}

	/**
	 * A corrupt row does not take the site down.
	 */
	public function test_non_array_row_falls_back_to_defaults() {
		update_option( FreeMyInternet_Options::OPTION_NAME, 'not an array' );

		$this->assertSame( 'blackout', FreeMyInternet_Options::get( 'mode' ) );
	}

	/**
	 * Only the two known presentations are accepted.
	 */
	public function test_sanitize_rejects_an_unknown_mode() {
		$clean = FreeMyInternet_Options::sanitize( array( 'mode' => 'wharrgarbl' ) );

		$this->assertSame( 'blackout', $clean['mode'] );

		$clean = FreeMyInternet_Options::sanitize( array( 'mode' => 'banner' ) );

		$this->assertSame( 'banner', $clean['mode'] );
	}

	/**
	 * An unsafe protocol must not survive being saved.
	 */
	public function test_sanitize_strips_unsafe_link_protocols() {
		$clean = FreeMyInternet_Options::sanitize(
			array(
				'link_url'  => 'javascript:alert(1)',
				'image_url' => 'javascript:alert(1)',
			)
		);

		$this->assertSame( '', $clean['link_url'] );
		$this->assertSame( '', $clean['image_url'] );
	}

	/**
	 * A normal URL is kept.
	 */
	public function test_sanitize_keeps_a_safe_url() {
		$clean = FreeMyInternet_Options::sanitize( array( 'link_url' => 'https://example.org/protest' ) );

		$this->assertSame( 'https://example.org/protest', $clean['link_url'] );
	}

	/**
	 * Script tags are removed from the message, which is the one field allowed
	 * to carry markup.
	 */
	public function test_sanitize_strips_scripts_from_the_message() {
		$clean = FreeMyInternet_Options::sanitize( array( 'message' => 'Hi <script>alert(1)</script><strong>there</strong>' ) );

		$this->assertStringNotContainsString( '<script', $clean['message'] );
		$this->assertStringContainsString( '<strong>there</strong>', $clean['message'] );
	}

	/**
	 * Colours must be real hex colours, or the default.
	 */
	public function test_sanitize_rejects_a_bad_colour() {
		$clean = FreeMyInternet_Options::sanitize(
			array(
				'background' => 'red; background-image:url(evil)',
				'text_color' => '#abcdef',
			)
		);

		$this->assertSame( '#000000', $clean['background'] );
		$this->assertSame( '#abcdef', $clean['text_color'] );
	}

	/**
	 * Checkboxes come back as real booleans.
	 */
	public function test_sanitize_casts_checkboxes() {
		$clean = FreeMyInternet_Options::sanitize( array( 'enabled' => '1' ) );

		$this->assertTrue( $clean['enabled'] );
		$this->assertFalse( $clean['dismissible'] );
	}

	/**
	 * The datetime-local input posts `Y-m-d\TH:i`; it is stored with a space.
	 */
	public function test_sanitize_normalises_a_datetime() {
		$clean = FreeMyInternet_Options::sanitize( array( 'start' => '2026-06-01T09:30' ) );

		$this->assertSame( '2026-06-01 09:30', $clean['start'] );
	}

	/**
	 * An unparseable bound is discarded, meaning "unbounded", rather than
	 * silently becoming now.
	 */
	public function test_sanitize_discards_an_unparseable_datetime() {
		$clean = FreeMyInternet_Options::sanitize( array( 'start' => 'not a date' ) );

		$this->assertSame( '', $clean['start'] );
	}

	/**
	 * Sanitizing something that is not an array yields the defaults.
	 */
	public function test_sanitize_handles_a_non_array() {
		$this->assertSame( FreeMyInternet_Options::get_defaults(), FreeMyInternet_Options::sanitize( 'nope' ) );
	}

	/**
	 * The schema version is stamped, and stamping twice changes nothing.
	 */
	public function test_maybe_upgrade_is_idempotent() {
		FreeMyInternet_Options::maybe_upgrade();
		$this->assertSame( FREEMYINTERNET_VERSION, get_option( FreeMyInternet_Options::VERSION_OPTION_NAME ) );

		FreeMyInternet_Options::update( array( 'heading' => 'Set by the user' ) );
		FreeMyInternet_Options::maybe_upgrade();

		// Re-running must not reset settings -- the classic consolidation bug.
		$this->assertSame( 'Set by the user', FreeMyInternet_Options::get( 'heading' ) );
	}

	/**
	 * The schema version lives outside the row it gates, or it could not be read
	 * to decide whether that row needs migrating.
	 */
	public function test_version_is_stored_in_its_own_row() {
		FreeMyInternet_Options::maybe_upgrade();

		$this->assertNotSame( FreeMyInternet_Options::OPTION_NAME, FreeMyInternet_Options::VERSION_OPTION_NAME );
		$this->assertArrayNotHasKey( 'version', FreeMyInternet_Options::get() );
	}
}
