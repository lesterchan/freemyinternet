<?php
/**
 * Regression guard for the bug that emptied every title on the site.
 *
 * @package FreeMyInternet
 */

/**
 * Up to 0.01 the banner callback was registered on the `the_title` filter but
 * returned nothing, so every title filtered to an empty string and a copy of the
 * banner was echoed at each one. This is the test that must never go green again
 * by accident.
 */
class Test_FreeMyInternet_Title extends WP_UnitTestCase {

	/**
	 * Enable the overlay, so the test proves the title is safe even when the
	 * plugin is actively rendering.
	 */
	public function set_up() {
		parent::set_up();

		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'message' => 'Body copy',
			)
		);
	}

	/**
	 * The plugin must not hook the_title at all.
	 */
	public function test_plugin_does_not_hook_the_title() {
		$this->assertFalse( has_filter( 'the_title', 'freemyinternet' ) );
	}

	/**
	 * A title passed through the filter chain comes back intact.
	 */
	public function test_the_title_is_not_emptied() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Hello World' ) );

		ob_start();
		$filtered = apply_filters( 'the_title', 'Hello World', $post_id );
		$echoed   = ob_get_clean();

		$this->assertSame( 'Hello World', $filtered );
		$this->assertSame( '', $echoed );
	}

	/**
	 * No markup is emitted while a title is being filtered.
	 *
	 * Core hooks wptexturize() onto the_title, so `&` legitimately becomes
	 * `&#038;`. Assert the title survives, not that it is byte-identical.
	 */
	public function test_the_title_emits_no_markup() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Cost: 5 & up' ) );

		ob_start();
		$filtered = apply_filters( 'the_title', 'Cost: 5 & up', $post_id );
		$echoed   = ob_get_clean();

		$this->assertNotSame( '', trim( $filtered ) );
		$this->assertStringContainsString( 'Cost: 5', $filtered );
		$this->assertSame( '', $echoed );
		$this->assertStringNotContainsString( 'freemyinternet-overlay', $filtered );
	}

	/**
	 * Titles read back with get_the_title() are unaffected.
	 */
	public function test_get_the_title_is_unaffected() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A Real Post' ) );

		$this->assertSame( 'A Real Post', get_the_title( $post_id ) );
	}
}
