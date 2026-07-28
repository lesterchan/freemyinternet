<?php
/**
 * The settings screen.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Settings.
 */
class FreeMyInternet_Settings_Test extends FreeMyInternet_TestCase {

	/**
	 * Run as an administrator, since every screen here is capability gated.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// add_settings_error() writes to a global the test suite does not reset, so
		// a notice raised by one test would still be queued for the next one.
		$GLOBALS['wp_settings_errors'] = array();
	}

	public function test_the_setting_is_registered_against_the_group_the_form_posts() {
		global $wp_registered_settings;

		FreeMyInternet_Settings::register_settings();

		$this->assertArrayHasKey(
			FreeMyInternet_Options::OPTION,
			$wp_registered_settings,
			'the settings row is not registered, so a save would be discarded.'
		);

		$setting = $wp_registered_settings[ FreeMyInternet_Options::OPTION ];

		$this->assertSame(
			FreeMyInternet_Settings::GROUP,
			$setting['group'],
			'the registered group must be the one settings_fields() prints.'
		);
		$this->assertSame(
			array( 'FreeMyInternet_Options', 'sanitize' ),
			$setting['sanitize_callback'],
			'the setting must be sanitized by the options class.'
		);
	}

	public function test_the_settings_group_is_the_settings_row_name() {
		$this->assertSame(
			FreeMyInternet_Options::OPTION,
			FreeMyInternet_Settings::GROUP,
			'the settings group and the settings row must share one name.'
		);
	}

	public function test_every_field_has_a_registered_section_and_a_callback_of_its_own() {
		global $wp_settings_sections, $wp_settings_fields;

		FreeMyInternet_Settings::register_settings();

		$page     = FreeMyInternet_Settings::PAGE;
		$sections = array_keys( $wp_settings_sections[ $page ] );

		$this->assertNotEmpty( $wp_settings_fields[ $page ], 'no fields were registered on the settings page.' );

		foreach ( FreeMyInternet_Settings::fields() as $name => $field ) {
			$this->assertContains( $field['section'], $sections, "field {$name} belongs to a section that is never registered." );
			$this->assertArrayHasKey( $name, $wp_settings_fields[ $page ][ $field['section'] ], "field {$name} was not registered." );
			$this->assertTrue(
				method_exists( 'FreeMyInternet_Settings', 'field_' . $name ),
				"field {$name} has no field_{$name}() callback."
			);
		}
	}

	public function test_the_page_renders_the_form_and_every_field() {
		FreeMyInternet_Settings::register_settings();

		ob_start();
		FreeMyInternet_Settings::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'action="options.php"', $output, 'the form must post to options.php.' );
		$this->assertStringContainsString( FreeMyInternet_Settings::GROUP, $output, 'the form must carry the settings group.' );

		foreach ( array_keys( FreeMyInternet_Settings::fields() ) as $name ) {
			$this->assertStringContainsString(
				FreeMyInternet_Options::OPTION . '[' . $name . ']',
				$output,
				"field {$name} does not post into the settings array."
			);
		}
	}

	public function test_the_page_hand_writes_no_form_table_and_no_inline_styles() {
		FreeMyInternet_Settings::register_settings();

		ob_start();
		FreeMyInternet_Settings::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="form-table"', $output, 'do_settings_sections() must emit the form table.' );
		$this->assertStringNotContainsString(
			'<table',
			$this->plugin_file_contents( 'includes/class-freemyinternet-settings.php' ),
			'the screen must not hand-write a form table.'
		);
		$this->assertStringNotContainsString( ' style="', $output, 'admin markup carries no inline style attributes.' );
		$this->assertSame( 1, substr_count( $output, '<h1>' ), 'a screen has exactly one h1.' );
	}

	public function test_a_user_without_the_capability_gets_nothing() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		FreeMyInternet_Settings::render_page();

		$this->assertSame( '', trim( ob_get_clean() ), 'the settings screen must be capability gated.' );
	}

	public function test_the_required_capability_goes_through_the_filter() {
		$this->assertSame(
			'manage_options',
			FreeMyInternet_Settings::capability(),
			'a settings screen requires manage_options.'
		);

		add_filter( 'freemyinternet_capability', '__return_empty_string' );

		$this->assertSame( '', FreeMyInternet_Settings::capability(), 'the capability filter must be honoured.' );

		remove_filter( 'freemyinternet_capability', '__return_empty_string' );
	}

	public function test_the_filter_is_told_which_context_is_being_checked() {
		$seen = null;

		add_filter(
			'freemyinternet_capability',
			function ( $capability, $context ) use ( &$seen ) {
				$seen = $context;

				return $capability;
			},
			10,
			2
		);

		FreeMyInternet_Settings::capability( 'settings' );

		remove_all_filters( 'freemyinternet_capability' );

		$this->assertSame( 'settings', $seen, 'the capability filter must receive the context it is being asked about.' );
	}

	public function test_a_settings_link_is_added_to_the_plugins_screen_row() {
		$links = FreeMyInternet_Settings::action_links( array( '<a href="#">Deactivate</a>' ) );

		$this->assertCount( 2, $links, 'the Settings link must be added, not replace the existing ones.' );
		$this->assertStringContainsString(
			'page=' . FreeMyInternet_Settings::PAGE,
			$links[0],
			'the Settings link must point at the plugin page.'
		);
	}

	public function test_the_action_links_filter_tolerates_something_that_is_not_an_array() {
		$this->assertIsArray(
			FreeMyInternet_Settings::action_links( null ),
			'a filter handed a non-array must still return an array.'
		);
	}

	public function test_the_screen_warns_when_the_notice_is_on_but_its_window_has_closed() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'start'   => '2013-06-01 00:00',
				'end'     => '2013-06-30 23:59',
			)
		);

		$this->assertTrue( FreeMyInternet_Settings::schedule_has_closed(), 'a window that ended in 2013 has closed.' );

		FreeMyInternet_Settings::register_settings();

		ob_start();
		FreeMyInternet_Settings::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output, 'the closed window must be reported on the screen.' );
	}

	public function test_the_screen_stays_quiet_while_the_window_is_open() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		$this->assertFalse( FreeMyInternet_Settings::schedule_has_closed(), 'an unbounded window is open.' );

		FreeMyInternet_Settings::register_settings();

		ob_start();
		FreeMyInternet_Settings::render_page();

		$this->assertStringNotContainsString( 'notice-warning', ob_get_clean(), 'there is nothing to warn about while the notice is showing.' );
	}

	public function test_the_settings_page_hangs_under_the_settings_menu() {
		global $submenu;

		set_current_screen( 'dashboard' );

		FreeMyInternet_Settings::add_page();

		$slugs = wp_list_pluck( isset( $submenu['options-general.php'] ) ? $submenu['options-general.php'] : array(), 2 );

		$this->assertContains(
			FreeMyInternet_Settings::PAGE,
			$slugs,
			'a plugin whose only admin surface is settings belongs under Settings.'
		);

		set_current_screen( 'front' );
	}

	public function test_saving_through_the_sanitizer_round_trips() {
		$saved = FreeMyInternet_Options::sanitize(
			array(
				'enabled'   => '1',
				'mode'      => 'banner',
				'heading'   => 'Free My Internet',
				'message'   => 'Something is wrong.',
				'link_url'  => 'https://example.org',
				'link_text' => 'Read more',
				'start'     => '2026-06-01T00:00',
			)
		);

		FreeMyInternet_Options::update( $saved );

		$this->assertTrue( FreeMyInternet_Options::get( 'enabled' ), 'the notice must come back switched on.' );
		$this->assertSame( 'banner', FreeMyInternet_Options::get( 'mode' ), 'the presentation must survive the round trip.' );
		$this->assertSame( 'Free My Internet', FreeMyInternet_Options::get( 'heading' ), 'the heading must survive the round trip.' );
		$this->assertSame( '2026-06-01 00:00', FreeMyInternet_Options::get( 'start' ), 'the start bound must survive the round trip.' );
	}
}
