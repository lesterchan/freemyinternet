<?php
/**
 * The settings screen.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Admin.
 */
class Test_FreeMyInternet_Admin extends WP_UnitTestCase {

	/**
	 * Run as an administrator, since every screen here is capability gated.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( FreeMyInternet_Options::OPTION_NAME );
	}

	/**
	 * The setting is registered against the group the form posts, with the
	 * sanitizer wired up. A mismatch here is why a Settings API save silently
	 * does nothing.
	 */
	public function test_setting_is_registered_with_a_sanitizer() {
		global $wp_registered_settings;

		FreeMyInternet_Admin::register_settings();

		$this->assertArrayHasKey( FreeMyInternet_Options::OPTION_NAME, $wp_registered_settings );

		$setting = $wp_registered_settings[ FreeMyInternet_Options::OPTION_NAME ];

		$this->assertSame( FreeMyInternet_Admin::OPTION_GROUP, $setting['group'] );
		$this->assertSame(
			array( 'FreeMyInternet_Options', 'sanitize' ),
			$setting['sanitize_callback']
		);
	}

	/**
	 * Every field belongs to a section that is actually registered on the page,
	 * otherwise it silently never renders.
	 */
	public function test_every_field_has_a_registered_section() {
		global $wp_settings_sections, $wp_settings_fields;

		FreeMyInternet_Admin::register_settings();

		$page     = FreeMyInternet_Admin::PAGE_SLUG;
		$sections = array_keys( $wp_settings_sections[ $page ] );

		$this->assertNotEmpty( $wp_settings_fields[ $page ] );

		foreach ( FreeMyInternet_Admin::fields() as $name => $field ) {
			$this->assertContains( $field['section'], $sections, "Field {$name} has an unregistered section." );
			$this->assertArrayHasKey( $name, $wp_settings_fields[ $page ][ $field['section'] ] );
		}
	}

	/**
	 * Saving through the sanitizer round-trips.
	 */
	public function test_settings_round_trip() {
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

		$this->assertTrue( FreeMyInternet_Options::get( 'enabled' ) );
		$this->assertSame( 'banner', FreeMyInternet_Options::get( 'mode' ) );
		$this->assertSame( 'Free My Internet', FreeMyInternet_Options::get( 'heading' ) );
		$this->assertSame( '2026-06-01 00:00', FreeMyInternet_Options::get( 'start' ) );
	}

	/**
	 * A Settings link is added to the plugin's row.
	 */
	public function test_action_links() {
		$links = FreeMyInternet_Admin::action_links( array( '<a href="#">Deactivate</a>' ) );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'page=' . FreeMyInternet_Admin::PAGE_SLUG, $links[0] );
	}

	/**
	 * Handles being passed something that is not an array.
	 */
	public function test_action_links_tolerates_a_non_array() {
		$this->assertIsArray( FreeMyInternet_Admin::action_links( null ) );
	}

	/**
	 * The page renders the form pointing at options.php with the right group.
	 */
	public function test_page_renders_the_form() {
		FreeMyInternet_Admin::register_settings();

		ob_start();
		FreeMyInternet_Admin::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'action="options.php"', $output );
		$this->assertStringContainsString( FreeMyInternet_Admin::OPTION_GROUP, $output );
		$this->assertStringContainsString( 'freemyinternet-heading', $output );
	}

	/**
	 * A user without the capability gets nothing.
	 */
	public function test_page_is_capability_gated() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		FreeMyInternet_Admin::render_page();

		$this->assertSame( '', trim( ob_get_clean() ) );
	}

	/**
	 * The screen warns when the notice is on but the window has closed, which is
	 * exactly the state this plugin spent thirteen years in.
	 */
	public function test_warns_when_the_window_has_closed() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'start'   => '2013-06-01 00:00',
				'end'     => '2013-06-30 23:59',
			)
		);

		ob_start();
		FreeMyInternet_Admin::schedule_section_intro();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
	}

	/**
	 * No warning while the window is open.
	 */
	public function test_no_warning_while_the_window_is_open() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		ob_start();
		FreeMyInternet_Admin::schedule_section_intro();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'notice-warning', $output );
	}
}
