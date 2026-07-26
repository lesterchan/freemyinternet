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
class Test_FreeMyInternet_Schedule extends WP_UnitTestCase {

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

	/**
	 * No bounds means always on.
	 */
	public function test_unbounded_window_is_open() {
		$this->assertTrue( FreeMyInternet_Frontend::within_schedule( $this->window( '', '' ) ) );
	}

	/**
	 * The 2013 case: the protest is over.
	 */
	public function test_past_window_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Frontend::within_schedule( $this->window( '2013-06-01 00:00', '2013-06-30 23:59' ) )
		);
	}

	/**
	 * A window that has not started yet.
	 */
	public function test_future_window_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Frontend::within_schedule( $this->window( '2099-01-01 00:00', '2099-12-31 23:59' ) )
		);
	}

	/**
	 * A window bracketing now.
	 */
	public function test_current_window_is_open() {
		$this->assertTrue(
			FreeMyInternet_Frontend::within_schedule( $this->window( $this->offset( -HOUR_IN_SECONDS ), $this->offset( HOUR_IN_SECONDS ) ) )
		);
	}

	/**
	 * Started, with no end.
	 */
	public function test_started_with_no_end_is_open() {
		$this->assertTrue(
			FreeMyInternet_Frontend::within_schedule( $this->window( $this->offset( -HOUR_IN_SECONDS ), '' ) )
		);
	}

	/**
	 * No start, end already passed.
	 */
	public function test_no_start_with_passed_end_is_closed() {
		$this->assertFalse(
			FreeMyInternet_Frontend::within_schedule( $this->window( '', $this->offset( -HOUR_IN_SECONDS ) ) )
		);
	}

	/**
	 * An unparseable bound is ignored rather than closing the window.
	 */
	public function test_unparseable_bounds_are_ignored() {
		$this->assertTrue( FreeMyInternet_Frontend::within_schedule( $this->window( 'rubbish', 'nonsense' ) ) );
	}

	/**
	 * Missing keys must not raise a notice.
	 */
	public function test_missing_keys_are_tolerated() {
		$this->assertTrue( FreeMyInternet_Frontend::within_schedule( array() ) );
	}

	/**
	 * Bounds are read in the site's timezone, not UTC. With the site well away
	 * from UTC, a window built from the site's own clock still reads as open.
	 */
	public function test_bounds_are_read_in_the_site_timezone() {
		update_option( 'timezone_string', 'Asia/Singapore' );

		$this->assertTrue(
			FreeMyInternet_Frontend::within_schedule(
				$this->window( $this->offset( -HOUR_IN_SECONDS ), $this->offset( HOUR_IN_SECONDS ) )
			)
		);

		update_option( 'timezone_string', '' );
	}

	/**
	 * A closed window stops the overlay rendering, not merely the schedule check.
	 */
	public function test_closed_window_renders_nothing() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
				'start'   => '2013-06-01 00:00',
				'end'     => '2013-06-30 23:59',
			)
		);

		$this->assertFalse( FreeMyInternet_Frontend::should_display() );

		ob_start();
		FreeMyInternet_Frontend::render();

		$this->assertSame( '', trim( ob_get_clean() ) );
	}
}
