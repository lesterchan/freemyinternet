<?php
/**
 * The schedule window.
 *
 * @package FreeMyInternet
 */

/**
 * The window is the direct fix for this plugin's real failure mode: the protest
 * ended in 2013 and the overlay did not.
 */
class FreeMyInternet_Schedule_Test extends FreeMyInternet_TestCase {

	/**
	 * Build an options array with the given bounds.
	 *
	 * @param string $start Start bound.
	 * @param string $end   End bound.
	 * @return array
	 */
	private function window( $start, $end ) {
		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * A datetime offset from now, in the site's timezone.
	 *
	 * @param int $seconds Offset in seconds.
	 * @return string
	 */
	private function offset( $seconds ) {
		return wp_date( 'Y-m-d H:i', time() + $seconds );
	}

	public function test_a_window_with_no_bounds_is_always_open() {
		$this->assertTrue(
			FreeMyInternet_Display::within_schedule( $this->window( '', '' ) ),
			'an unconfigured schedule means always on.'
		);
	}

	public function test_a_window_that_has_already_ended_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Display::within_schedule( $this->window( '2013-06-01 00:00', '2013-06-30 23:59' ) ),
			'the 2013 campaign is over and the overlay must know it.'
		);
	}

	public function test_a_window_that_has_not_started_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Display::within_schedule( $this->window( '2099-01-01 00:00', '2099-12-31 23:59' ) ),
			'a window in the future must not be open yet.'
		);
	}

	public function test_a_window_bracketing_now_is_open() {
		$this->assertTrue(
			FreeMyInternet_Display::within_schedule( $this->window( $this->offset( -HOUR_IN_SECONDS ), $this->offset( HOUR_IN_SECONDS ) ) ),
			'a window around now must be open.'
		);
	}

	public function test_a_window_that_has_started_with_no_end_is_open() {
		$this->assertTrue(
			FreeMyInternet_Display::within_schedule( $this->window( $this->offset( -HOUR_IN_SECONDS ), '' ) ),
			'an open-ended window that has started must be open.'
		);
	}

	public function test_a_window_with_no_start_and_a_passed_end_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Display::within_schedule( $this->window( '', $this->offset( -HOUR_IN_SECONDS ) ) ),
			'an end in the past closes the window whether or not a start was set.'
		);
	}

	public function test_an_unparseable_bound_is_ignored_rather_than_closing_the_window() {
		$this->assertTrue(
			FreeMyInternet_Display::within_schedule( $this->window( 'rubbish', 'nonsense' ) ),
			'rubbish in a bound means unbounded, not closed.'
		);
	}

	public function test_missing_bounds_do_not_raise_a_notice() {
		$this->assertTrue( FreeMyInternet_Display::within_schedule( array() ), 'an options array with no bounds must be tolerated.' );
	}

	public function test_the_bounds_are_read_in_the_site_timezone_rather_than_utc() {
		update_option( 'timezone_string', 'Asia/Singapore' );

		$open = FreeMyInternet_Display::within_schedule(
			$this->window( $this->offset( -HOUR_IN_SECONDS ), $this->offset( HOUR_IN_SECONDS ) )
		);

		update_option( 'timezone_string', '' );

		$this->assertTrue( $open, 'a window built from the site clock must read as open on a site well away from UTC.' );
	}

	public function test_a_closed_window_stops_the_overlay_rendering() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'start'   => '2013-06-01 00:00',
				'end'     => '2013-06-30 23:59',
			)
		);

		$this->assertFalse( FreeMyInternet_Display::should_display(), 'a closed window must stop the overlay.' );

		ob_start();
		FreeMyInternet_Display::render();

		$this->assertSame( '', trim( ob_get_clean() ), 'a closed window must render nothing at all.' );
	}
}
