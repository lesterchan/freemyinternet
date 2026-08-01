<?php
/**
 * The release invariants, asserted from the source.
 *
 * Everything §7.2 asks of all nineteen plugins now lives in
 * Plugin_Metadata_TestCase. This file holds what only FreeMyInternet can say:
 * the version it ships, its class prefix, the breaks its Upgrade Notice has to
 * cover, and the handful of hooks the shared base leaves to the plugin.
 *
 * @package FreeMyInternet
 */

/**
 * FreeMyInternet's half of the shared metadata contract.
 */
class FreeMyInternet_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '1.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * The one plugin in the collection that is not WP_-prefixed: the campaign
	 * name is the plugin name, so FreeMyInternet_Display rather than
	 * WP_FreeMyInternet_Display (§2.4, and the decision table in RESUME.md).
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'FreeMyInternet';
	}

	/**
	 * What a site owner updating from the released 0.01 would notice.
	 *
	 * 0.01 stored no option rows and had no settings screen, so there is no
	 * rename to announce. What it did have was a global freemyinternet()
	 * function hung off the_title and get_header, which blacked the site out
	 * unconditionally against a 2013 campaign whose image host no longer
	 * resolves. All of that is gone, and the raised floors mean an old site is
	 * never offered the update at all.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'0.01',
			'off by default',
			'Settings -> FreeMyInternet',
			'freemyinternet(',
		);
	}

	/**
	 * Seed the rows uninstall has to remove, the pre-release one included.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		FreeMyInternet_Options::update( array( 'enabled' => true ) );
		FreeMyInternet_Options::maybe_upgrade();
		update_option( FreeMyInternet_Options::LEGACY_OPTION, array( 'enabled' => true ) );
	}

	/**
	 * Write the freemyinternet_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		FreeMyInternet_Options::maybe_upgrade();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) FreeMyInternet_Options::sanitize( $input );
	}

	/**
	 * Real settings beside the poison, so the sanitiser actually runs.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array(
			'enabled' => '1',
			'mode'    => 'banner',
			'heading' => 'Protest',
		);
	}

	/**
	 * Register the overlay's assets.
	 *
	 * Nothing is enqueued unless a protest is actually running and there is
	 * something to say, and the script handle only appears when the notice is
	 * dismissible -- so all three have to be set before the shared asset tests
	 * have anything to look at.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		FreeMyInternet_Display::enqueue();
	}

	/**
	 * Both handles are registered srcless, and carry inline code instead.
	 *
	 * This plugin ships no js/ and no css/ directory at all: the few kilobytes
	 * are inlined, and only while a protest is running. The shared jQuery and
	 * RTL tests therefore have no file to walk, and everything they assert
	 * hangs on these two handles existing -- so this asserts the shape they
	 * depend on rather than leaving it implied.
	 */
	public function test_the_overlay_registers_srcless_handles_carrying_inline_code() {
		$this->register_plugin_assets();

		$this->assertTrue( wp_style_is( FREEMYINTERNET_SLUG, 'registered' ), 'The overlay stylesheet handle is not registered.' );
		$this->assertTrue( wp_script_is( FREEMYINTERNET_SLUG, 'registered' ), 'The dismissal script handle is not registered.' );

		$style  = wp_styles()->registered[ FREEMYINTERNET_SLUG ];
		$script = wp_scripts()->registered[ FREEMYINTERNET_SLUG ];

		$this->assertFalse( $style->src, 'The stylesheet is inlined, so the handle carries no src.' );
		$this->assertFalse( $script->src, 'The script is inlined, so the handle carries no src.' );

		$this->assertNotEmpty( $style->extra['after'], 'The stylesheet handle carries no inline CSS.' );
		$this->assertNotEmpty( $script->extra['after'], 'The script handle carries no inline JavaScript.' );

		$this->assertSame( array(), (array) glob( $this->metadata_root() . '/js/*.js' ), 'This plugin ships no scripts.' );
		$this->assertSame( array(), (array) glob( $this->metadata_root() . '/css/*.css' ), 'This plugin ships no stylesheets.' );
	}
}
