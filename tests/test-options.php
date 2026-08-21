<?php
/**
 * Options: defaults, merging and sanitizing.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Options.
 */
class FreeMyInternet_Options_Test extends FreeMyInternet_TestCase {

	public function test_a_fresh_install_shows_nothing_to_visitors() {
		$this->assertFalse(
			FreeMyInternet_Options::get( 'enabled' ),
			'a fresh install must not black the site out the way 0.01 did.'
		);
	}

	public function test_every_setting_lives_in_one_option_row_named_after_the_slug() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );

		$this->assertSame(
			'freemyinternet_options',
			FreeMyInternet_Options::OPTION,
			'the settings row must be named {slug}_options.'
		);
		$this->assertIsArray(
			get_option( FreeMyInternet_Options::OPTION ),
			'the settings row must hold a nested array of every setting.'
		);
	}

	public function test_the_version_row_is_separate_from_the_settings_row() {
		$this->assertSame(
			'freemyinternet_version',
			FreeMyInternet_Options::VERSION,
			'the version markers must live in {slug}_version.'
		);
		$this->assertNotSame(
			FreeMyInternet_Options::OPTION,
			FreeMyInternet_Options::VERSION,
			'the markers must not share a row with the settings they gate.'
		);
	}

	public function test_keys_missing_from_a_stored_row_fall_back_to_the_defaults() {
		update_option( FreeMyInternet_Options::OPTION, array( 'heading' => 'Stored' ) );

		$this->assertSame( 'Stored', FreeMyInternet_Options::get( 'heading' ), 'a stored value must be returned as stored.' );
		$this->assertSame( 'blackout', FreeMyInternet_Options::get( 'mode' ), 'a missing key must fall back to its default.' );
		$this->assertFalse( FreeMyInternet_Options::get( 'enabled' ), 'a missing enabled key must not switch the notice on.' );
	}

	public function test_an_unknown_key_returns_null_rather_than_a_notice() {
		$this->assertNull( FreeMyInternet_Options::get( 'no_such_key' ), 'an unknown key must return null.' );
	}

	public function test_a_corrupt_option_row_falls_back_to_the_defaults() {
		update_option( FreeMyInternet_Options::OPTION, 'not an array' );

		$this->assertSame( 'blackout', FreeMyInternet_Options::get( 'mode' ), 'a corrupt row must not take the site down.' );
	}

	public function test_sanitizing_rejects_a_presentation_that_is_not_one_of_the_two() {
		$clean = FreeMyInternet_Options::sanitize( array( 'mode' => 'wharrgarbl' ) );

		$this->assertSame( 'blackout', $clean['mode'], 'an unknown presentation must fall back to blackout.' );

		$clean = FreeMyInternet_Options::sanitize( array( 'mode' => 'banner' ) );

		$this->assertSame( 'banner', $clean['mode'], 'a known presentation must be kept.' );
	}

	public function test_sanitizing_strips_an_unsafe_protocol_from_both_urls() {
		$clean = FreeMyInternet_Options::sanitize(
			array(
				'link_url'  => 'javascript:alert(1)',
				'image_url' => 'javascript:alert(1)',
			)
		);

		$this->assertSame( '', $clean['link_url'], 'a javascript: link must not be stored.' );
		$this->assertSame( '', $clean['image_url'], 'a javascript: image URL must not be stored.' );
	}

	public function test_sanitizing_keeps_an_ordinary_url() {
		$clean = FreeMyInternet_Options::sanitize( array( 'link_url' => 'https://example.org/protest' ) );

		$this->assertSame( 'https://example.org/protest', $clean['link_url'], 'a safe URL must survive being saved.' );
	}

	public function test_sanitizing_strips_scripts_from_the_message_but_keeps_its_markup() {
		$clean = FreeMyInternet_Options::sanitize( array( 'message' => 'Hi <script>alert(1)</script><strong>there</strong>' ) );

		$this->assertStringNotContainsString( '<script', $clean['message'], 'a script tag must not survive wp_kses_post().' );
		$this->assertStringContainsString( '<strong>there</strong>', $clean['message'], 'basic markup must survive in the message.' );
	}

	public function test_sanitizing_rejects_a_colour_that_is_not_a_hex_colour() {
		$clean = FreeMyInternet_Options::sanitize(
			array(
				'background' => 'red; background-image:url(evil)',
				'text_color' => '#abcdef',
			)
		);

		$this->assertSame( '#000000', $clean['background'], 'a bad colour must fall back to its default.' );
		$this->assertSame( '#abcdef', $clean['text_color'], 'a real hex colour must be kept.' );
	}

	public function test_sanitizing_casts_the_checkboxes_to_booleans() {
		$clean = FreeMyInternet_Options::sanitize( array( 'enabled' => '1' ) );

		$this->assertTrue( $clean['enabled'], 'a ticked checkbox must be stored as true.' );
		$this->assertFalse( $clean['dismissible'], 'an unposted checkbox must be stored as false.' );
	}

	public function test_sanitizing_normalises_the_datetime_local_format() {
		$clean = FreeMyInternet_Options::sanitize( array( 'start' => '2026-06-01T09:30' ) );

		$this->assertSame( '2026-06-01 09:30', $clean['start'], 'the posted T separator must be stored as a space.' );
	}

	public function test_sanitizing_discards_an_unparseable_bound_rather_than_defaulting_it() {
		$clean = FreeMyInternet_Options::sanitize( array( 'start' => 'not a date' ) );

		$this->assertSame( '', $clean['start'], 'an unparseable bound means unbounded, not now.' );
	}

	public function test_sanitizing_something_that_is_not_an_array_yields_the_defaults() {
		$this->assertSame(
			FreeMyInternet_Options::get_defaults(),
			FreeMyInternet_Options::sanitize( 'nope' ),
			'a non-array input must sanitize to the defaults.'
		);
	}

	public function test_the_two_presentations_are_the_only_ones_offered() {
		$this->assertSame(
			array( 'blackout', 'banner' ),
			FreeMyInternet_Options::modes(),
			'the plugin offers exactly two presentations.'
		);
	}

	public function test_a_datetime_is_converted_to_a_timestamp_and_rubbish_is_not() {
		$this->assertIsInt(
			FreeMyInternet_Options::to_timestamp( '2026-06-01 09:30' ),
			'a parseable datetime must become a timestamp.'
		);
		$this->assertFalse( FreeMyInternet_Options::to_timestamp( '' ), 'an empty bound has no timestamp.' );
		$this->assertFalse( FreeMyInternet_Options::to_timestamp( 'rubbish' ), 'an unparseable bound has no timestamp.' );
	}

	public function test_a_non_scalar_datetime_is_discarded() {
		$this->assertSame(
			'',
			FreeMyInternet_Options::sanitize_datetime( array( 'not', 'scalar' ) ),
			'an array posted into a datetime field must be discarded.'
		);
	}
}
