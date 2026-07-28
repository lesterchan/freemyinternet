<?php
/**
 * Front-end rendering and escaping.
 *
 * @package FreeMyInternet
 */

/**
 * Covers FreeMyInternet_Display's output.
 *
 * Options are written with FreeMyInternet_Options::update() rather than through
 * the sanitizer on purpose: escaping at the sink has to hold on its own, for a
 * row written by an older version or by another plugin.
 */
class FreeMyInternet_Render_Test extends FreeMyInternet_TestCase {

	/**
	 * Render the overlay and return its markup.
	 *
	 * @return string
	 */
	private function render() {
		ob_start();
		FreeMyInternet_Display::render();
		return ob_get_clean();
	}

	public function test_a_fresh_activation_renders_nothing_at_all() {
		$this->assertSame( '', trim( $this->render() ), 'an unconfigured install must render nothing.' );
	}

	public function test_an_enabled_but_empty_notice_renders_nothing() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );

		$this->assertSame( '', trim( $this->render() ), 'an empty black screen helps nobody.' );
	}

	public function test_a_heading_on_its_own_is_enough_to_render() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Free My Internet',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'class="freemyinternet ', $output, 'the outermost element carries the root class.' );
		$this->assertStringContainsString( 'Free My Internet', $output, 'the heading must reach the markup.' );
	}

	public function test_exactly_one_overlay_is_printed() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		$this->assertSame(
			1,
			substr_count( $this->render(), 'data-freemyinternet-key="' ),
			'0.01 printed a copy of the banner at every title; there must be exactly one.'
		);
	}

	public function test_the_chosen_presentation_reaches_the_class_attribute() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => 'banner',
			)
		);

		$this->assertStringContainsString( 'freemyinternet--banner', $this->render(), 'the banner modifier must be printed.' );

		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => 'blackout',
			)
		);

		$this->assertStringContainsString( 'freemyinternet--blackout', $this->render(), 'the blackout modifier must be printed.' );
	}

	public function test_a_presentation_stored_by_some_other_means_falls_back_rather_than_escaping() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'mode'    => '" onmouseover="alert(1)',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'freemyinternet--blackout', $output, 'an unknown presentation must fall back to blackout.' );
		$this->assertStringNotContainsString( 'onmouseover', $output, 'an unknown presentation must not reach the class attribute.' );
	}

	public function test_the_heading_is_escaped_at_the_sink() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => '<script>alert(1)</script>',
			)
		);

		$output = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output, 'a stored script tag must not be printed raw.' );
		$this->assertStringContainsString( '&lt;script&gt;', $output, 'the heading must be escaped rather than dropped.' );
	}

	public function test_the_message_allows_markup_but_never_a_script() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'message' => '<strong>Act now</strong><script>alert(1)</script>',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( '<strong>Act now</strong>', $output, 'the message is the one field allowed markup.' );
		$this->assertStringNotContainsString( '<script', $output, 'a script must not survive wp_kses_post() at the sink.' );
	}

	public function test_a_javascript_link_stored_directly_still_cannot_reach_the_page() {
		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'javascript:alert(1)',
				'link_text' => 'Click',
			)
		);

		$this->assertStringNotContainsString( 'javascript:', $this->render(), 'esc_url() must strip an unsafe protocol at the sink.' );
	}

	public function test_the_link_is_shown_only_when_it_has_both_a_url_and_its_text() {
		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'https://example.org',
				'link_text' => '',
			)
		);

		$this->assertStringNotContainsString( 'freemyinternet__link', $this->render(), 'a link with no text must not be printed.' );

		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'link_url'  => 'https://example.org',
				'link_text' => 'Learn more',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( 'freemyinternet__link', $output, 'a complete link must be printed.' );
		$this->assertStringContainsString( 'https://example.org', $output, 'the link target must reach the markup.' );
	}

	public function test_the_image_is_shown_only_when_one_is_configured() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		$this->assertStringNotContainsString( 'freemyinternet__image', $this->render(), 'no image is printed when none is set.' );

		FreeMyInternet_Options::update(
			array(
				'enabled'   => true,
				'heading'   => 'Protest',
				'image_url' => 'https://example.org/protest.png',
			)
		);

		$this->assertStringContainsString( 'freemyinternet__image', $this->render(), 'a configured image must be printed.' );
	}

	public function test_the_dismiss_button_follows_the_setting() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		$this->assertStringContainsString( 'data-freemyinternet-dismiss', $this->render(), 'the close button must be printed when asked for.' );

		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => false,
			)
		);

		$this->assertStringNotContainsString( 'data-freemyinternet-dismiss', $this->render(), 'no close button when the setting is off.' );
	}

	public function test_editing_the_notice_changes_the_dismissal_key() {
		$first  = FreeMyInternet_Display::dismiss_key( array( 'heading' => 'One' ) );
		$second = FreeMyInternet_Display::dismiss_key( array( 'heading' => 'Two' ) );

		$this->assertNotSame( $first, $second, 'a different notice must produce a different dismissal key.' );
		$this->assertSame(
			$first,
			FreeMyInternet_Display::dismiss_key( array( 'heading' => 'One' ) ),
			'the same notice must produce the same dismissal key.'
		);
	}

	public function test_the_colours_reach_the_markup_as_custom_properties_and_rubbish_does_not() {
		FreeMyInternet_Options::update(
			array(
				'enabled'    => true,
				'heading'    => 'Protest',
				'background' => '#123456',
				'text_color' => 'url(javascript:alert(1))',
			)
		);

		$output = $this->render();

		$this->assertStringContainsString( '--freemyinternet-bg:#123456', $output, 'the chosen background must reach the markup.' );
		$this->assertStringContainsString( '--freemyinternet-fg:#ffffff', $output, 'a bad colour must fall back to its default.' );
		$this->assertStringNotContainsString( 'javascript:', $output, 'a bad colour must not reach the style attribute.' );
	}

	public function test_the_overlay_never_renders_on_an_admin_request() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		set_current_screen( 'dashboard' );

		$this->assertFalse( FreeMyInternet_Display::should_display(), 'a misconfigured overlay must not lock the owner out.' );
		$this->assertSame( '', trim( $this->render() ), 'nothing is rendered in wp-admin.' );

		set_current_screen( 'front' );
	}

	public function test_the_should_display_filter_can_suppress_the_overlay() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		add_filter( 'freemyinternet_should_display', '__return_false' );

		$this->assertFalse( FreeMyInternet_Display::should_display(), 'the filter must be able to suppress the overlay.' );
		$this->assertSame( '', trim( $this->render() ), 'a suppressed overlay renders nothing.' );

		remove_filter( 'freemyinternet_should_display', '__return_false' );
	}

	public function test_the_should_display_filter_is_handed_the_options() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		$seen = null;

		add_filter(
			'freemyinternet_should_display',
			function ( $display, $options ) use ( &$seen ) {
				$seen = $options;

				return $display;
			},
			10,
			2
		);

		FreeMyInternet_Display::should_display();

		remove_all_filters( 'freemyinternet_should_display' );

		$this->assertIsArray( $seen, 'the filter must receive the plugin options.' );
		$this->assertSame( 'Protest', $seen['heading'], 'the filter must receive the options actually in force.' );
	}

	public function test_the_styles_are_inlined_and_only_while_the_overlay_is_showing() {
		FreeMyInternet_Display::enqueue();

		$this->assertFalse( wp_style_is( FREEMYINTERNET_SLUG, 'enqueued' ), 'nothing is enqueued while there is nothing to show.' );

		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		FreeMyInternet_Display::enqueue();

		$this->assertTrue( wp_style_is( FREEMYINTERNET_SLUG, 'enqueued' ), 'the stylesheet must be enqueued while a protest is running.' );
		$this->assertTrue( wp_script_is( FREEMYINTERNET_SLUG, 'enqueued' ), 'the script must be enqueued when there is a button to wire up.' );

		$this->assertFalse(
			wp_styles()->registered[ FREEMYINTERNET_SLUG ]->src,
			'the handle is registered without a src, so nothing is fetched over HTTP.'
		);

		$inline = wp_styles()->get_data( FREEMYINTERNET_SLUG, 'after' );

		$this->assertNotEmpty( $inline, 'the stylesheet must be attached as inline CSS.' );
		$this->assertStringContainsString( '.freemyinternet', implode( '', (array) $inline ), 'the inline CSS must be the overlay stylesheet.' );
	}

	public function test_the_script_is_skipped_when_there_is_no_button_for_it() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => false,
			)
		);

		FreeMyInternet_Display::enqueue();

		$this->assertTrue( wp_style_is( FREEMYINTERNET_SLUG, 'enqueued' ), 'the stylesheet is still needed without a button.' );
		$this->assertFalse( wp_script_is( FREEMYINTERNET_SLUG, 'enqueued' ), 'no button means no script.' );
	}

	public function test_the_stylesheet_uses_logical_properties_rather_than_physical_ones() {
		$css = FreeMyInternet_Display::css();

		foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left', 'border-right', 'float:left', 'float:right' ) as $physical ) {
			$this->assertStringNotContainsString(
				$physical,
				$css,
				$physical . ' is a physical property; one direction-neutral sheet must serve both writing directions.'
			);
		}

		$this->assertStringNotContainsString( '!important', $css, 'a theme must be able to override the overlay.' );
		$this->assertStringNotContainsString( 'font-family', $css, 'the overlay inherits its typeface from the theme.' );
	}

	public function test_the_dismissal_script_declares_nothing_in_the_global_scope() {
		$js = FreeMyInternet_Display::js();

		$this->assertStringStartsWith( '( function () {', $js, 'the script must live inside an IIFE.' );
		$this->assertStringContainsString(
			'data-freemyinternet-dismiss',
			$js,
			'behaviour must attach through a data attribute rather than an inline handler.'
		);
		$this->assertStringNotContainsString( 'onclick', $js, 'no inline event handlers.' );
	}

	public function test_every_rule_in_the_stylesheet_is_scoped_to_the_root_class() {
		foreach ( explode( "\n", FreeMyInternet_Display::css() ) as $line ) {
			if ( '' === trim( $line ) || 0 === strpos( $line, '@media' ) ) {
				continue;
			}

			$this->assertStringStartsWith( '.freemyinternet', $line, 'every rule must be scoped under the plugin root class.' );
		}
	}
}
