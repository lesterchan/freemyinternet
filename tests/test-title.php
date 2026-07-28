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
class FreeMyInternet_Title_Test extends FreeMyInternet_TestCase {

	/**
	 * Switch the overlay on, so the title is proven safe while the plugin is
	 * actively rendering.
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

	public function test_the_plugin_hooks_nothing_onto_the_title_filter() {
		$this->assertFalse( has_filter( 'the_title', 'freemyinternet' ), 'the plugin must not hook the_title at all.' );
	}

	public function test_a_title_passed_through_the_filter_chain_comes_back_intact() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Hello World' ) );

		ob_start();
		$filtered = apply_filters( 'the_title', 'Hello World', $post_id );
		$echoed   = ob_get_clean();

		$this->assertSame( 'Hello World', $filtered, 'the title must survive the filter chain unchanged.' );
		$this->assertSame( '', $echoed, 'filtering a title must not echo anything.' );
	}

	public function test_no_markup_is_emitted_while_a_title_is_being_filtered() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Cost: 5 & up' ) );

		ob_start();
		$filtered = apply_filters( 'the_title', 'Cost: 5 & up', $post_id );
		$echoed   = ob_get_clean();

		// Core hooks wptexturize() onto the_title, so `&` legitimately becomes
		// `&#038;`. Assert the title survives, not that it is byte-identical.
		$this->assertNotSame( '', trim( $filtered ), 'the title must not filter down to an empty string.' );
		$this->assertStringContainsString( 'Cost: 5', $filtered, 'the title text must survive.' );
		$this->assertSame( '', $echoed, 'filtering a title must not echo the overlay.' );
		$this->assertStringNotContainsString( 'freemyinternet', $filtered, 'the overlay must not be appended to a title.' );
	}

	public function test_titles_read_back_with_get_the_title_are_unaffected() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A Real Post' ) );

		$this->assertSame( 'A Real Post', get_the_title( $post_id ), 'get_the_title() must return the stored title.' );
	}
}
