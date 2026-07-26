<?php
/**
 * Front-end rendering and escaping.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Frontend's output.
 *
 * Options are written with FreeMyInternet_Options::update() rather than through
 * the sanitizer on purpose: escaping at the sink has to hold on its own, for a
 * row written by an older version or by another plugin.
 */
class Test_FreeMyInternet_Render extends WP_UnitTestCase {

	/**
	 * Start from an unconfigured install.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( FreeMyInternet_Options::OPTION_NAME );

		// The suite runs in one process and these globals are not reset between
		// test methods, so an enqueue in one test would otherwise still be
		// registered in the next.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	/**
	 * Render and return the markup.
	 *
	 * @return string
	 */
	private function render() {
		ob_start();
		FreeMyInternet_Frontend::render();
		return ob_get_clean();
	}

	/**
	 * A fresh activation shows nothing at all.
	 */
	public function test_renders_nothing_by_default() {
		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * Enabled but empty is still nothing -- an empty black screen helps nobody.
	 */
	public function test_renders_nothing_without_a_heading_or_message() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );

		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * A heading alone is enough.
	 */
	public function test_renders_with_a_heading() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Free My Internet',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'freemyinternet-overlay', $output );
		$this->assertStringContainsString( 'Free My Internet', $output );
	}

	/**
	 * The overlay is printed once, not once per title as in 0.01.
	 */
	public function test_renders_exactly_one_overlay() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		// Count the container's own attribute: the class prefix would also match
		// the __inner, __heading and __dismiss elements inside it.
		$this->assertSame( 1, substr_count( $this->render(), 'data-freemyinternet-key="' ) );
	}

	/**
	 * The chosen presentation reaches the markup.
	 */
	public function test_mode_modifier_class() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => 'banner',
			)
		);

		$this->assertStringContainsString( 'freemyinternet-overlay--banner', $this->render() );

		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => 'blackout',
			)
		);

		$this->assertStringContainsString( 'freemyinternet-overlay--blackout', $this->render() );
	}

	/**
	 * An unknown mode stored by some other means falls back rather than reaching
	 * the class attribute.
	 */
	public function test_unknown_mode_falls_back() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => '" onmouseover="alert(1)',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'freemyinternet-overlay--blackout', $output );
		$this->assertStringNotContainsString( 'onmouseover', $output );
	}

	/**
	 * The heading is escaped at the sink.
	 */
	public function test_heading_is_escaped() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => '<script>alert(1)</script>',
			)
		);

		$output = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * The message allows basic markup but never a script.
	 */
	public function test_message_allows_markup_but_not_scripts() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'message' => '<strong>Act now</strong><script>alert(1)</script>',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( '<strong>Act now</strong>', $output );
		$this->assertStringNotContainsString( '<script', $output );
	}

	/**
	 * A javascript: target stored directly still cannot reach the page.
	 */
	public function test_unsafe_link_is_neutralised() {
		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'javascript:alert(1)',
				'link_text' => 'Click',
			)
		);

		$output = $this->render();

		$this->assertStringNotContainsString( 'javascript:', $output );
	}

	/**
	 * The link needs both halves before it is shown.
	 */
	public function test_link_needs_both_url_and_text() {
		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'https://example.org',
				'link_text' => '',
			)
		);

		$this->assertStringNotContainsString( 'freemyinternet-overlay__link', $this->render() );

		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'https://example.org',
				'link_text' => 'Learn more',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'freemyinternet-overlay__link', $output );
		$this->assertStringContainsString( 'https://example.org', $output );
	}

	/**
	 * The dismiss button follows the setting.
	 */
	public function test_dismiss_button_follows_the_setting() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		$this->assertStringContainsString( 'data-freemyinternet-dismiss', $this->render() );

		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => false,
			)
		);

		$this->assertStringNotContainsString( 'data-freemyinternet-dismiss', $this->render() );
	}

	/**
	 * Editing the notice changes the dismissal key, so a visitor who dismissed the
	 * previous notice is shown the new one.
	 */
	public function test_dismiss_key_changes_with_the_notice() {
		$first  = FreeMyInternet_Frontend::dismiss_key( array( 'heading' => 'One' ) );
		$second = FreeMyInternet_Frontend::dismiss_key( array( 'heading' => 'Two' ) );

		$this->assertNotSame( $first, $second );
		$this->assertSame( $first, FreeMyInternet_Frontend::dismiss_key( array( 'heading' => 'One' ) ) );
	}

	/**
	 * The colours reach the markup as custom properties, and a bad value does not.
	 */
	public function test_colours_are_sanitised_at_the_sink() {
		FreeMyInternet_Options::update(
			array(
				'enabled'    => true,
				'heading'    => 'Protest',
				'background' => '#123456',
				'text_color' => 'url(javascript:alert(1))',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( '--freemyinternet-bg:#123456', $output );
		$this->assertStringContainsString( '--freemyinternet-fg:#ffffff', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
	}

	/**
	 * The overlay never renders on an admin request.
	 */
	public function test_never_renders_in_admin() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		set_current_screen( 'dashboard' );

		$this->assertFalse( FreeMyInternet_Frontend::should_display() );
		$this->assertSame( '', trim( $this->render() ) );

		set_current_screen( 'front' );
	}

	/**
	 * The filter can suppress the overlay.
	 */
	public function test_should_display_filter_can_suppress() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		add_filter( 'freemyinternet_should_display', '__return_false' );

		$this->assertFalse( FreeMyInternet_Frontend::should_display() );
		$this->assertSame( '', trim( $this->render() ) );

		remove_filter( 'freemyinternet_should_display', '__return_false' );
	}

	/**
	 * The CSS and JS are inlined rather than enqueued as files, and only while the
	 * overlay is actually being shown.
	 */
	public function test_assets_are_inlined_only_when_displaying() {
		FreeMyInternet_Frontend::enqueue();

		$this->assertFalse( wp_style_is( FreeMyInternet_Frontend::HANDLE, 'enqueued' ) );

		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		FreeMyInternet_Frontend::enqueue();

		$this->assertTrue( wp_style_is( FreeMyInternet_Frontend::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( FreeMyInternet_Frontend::HANDLE, 'enqueued' ) );

		// Registered without a src, so nothing is fetched over HTTP.
		$style = wp_styles()->registered[ FreeMyInternet_Frontend::HANDLE ];
		$this->assertFalse( $style->src );

		$inline = wp_styles()->get_data( FreeMyInternet_Frontend::HANDLE, 'after' );
		$this->assertNotEmpty( $inline );
		$this->assertStringContainsString( '.freemyinternet-overlay', implode( '', (array) $inline ) );
	}

	/**
	 * The script is not enqueued when there is no button for it to wire up.
	 */
	public function test_script_is_skipped_when_not_dismissible() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => false,
			)
		);

		FreeMyInternet_Frontend::enqueue();

		$this->assertTrue( wp_style_is( FreeMyInternet_Frontend::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_script_is( FreeMyInternet_Frontend::HANDLE, 'enqueued' ) );
	}
}
