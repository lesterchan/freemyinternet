<?php
/**
 * The release invariants, asserted from the source.
 *
 * These are house rules for every lesterchan plugin, and every one of them has
 * been broken by an ordinary edit at some point. Catching them here is cheaper
 * than catching them in the release pre-flight.
 *
 * @package FreeMyInternet
 */

/**
 * Covers the invariants shared by all nineteen plugins.
 */
class FreeMyInternet_Metadata_Test extends FreeMyInternet_TestCase {

	/**
	 * A field from the main plugin file's header docblock.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function header( $field ) {
		$data = get_file_data( $this->plugin_path( 'freemyinternet.php' ), array( $field => $field ) );

		return $data[ $field ];
	}

	/**
	 * A field from the readme's header block.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function readme( $field ) {
		preg_match(
			'/^' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/mi',
			$this->plugin_file_contents( 'README.md' ),
			$matches
		);

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * Every directory in the plugin, relative to its root.
	 *
	 * @param string $relative Directory to walk, relative to the plugin root.
	 * @return array
	 */
	protected function directories( $relative = '' ) {
		$skip  = array( '.', '..', '.git', '.github', 'vendor', 'node_modules' );
		$found = array();

		foreach ( (array) scandir( $this->plugin_path( $relative ) ) as $entry ) {
			if ( in_array( $entry, $skip, true ) ) {
				continue;
			}

			$path = ltrim( $relative . '/' . $entry, '/' );

			if ( ! is_dir( $this->plugin_path( $path ) ) ) {
				continue;
			}

			$found[] = $path;
			$found   = array_merge( $found, $this->directories( $path ) );
		}

		return $found;
	}

	public function test_every_readme_header_line_keeps_its_line_break() {
		$lines  = explode( "\n", $this->plugin_file_contents( 'README.md' ) );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}

			$header[] = $line;
		}

		$this->assertCount( 9, $header, 'the readme header should hold nine fields' );

		$last = array_pop( $header );

		foreach ( $header as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				'"' . trim( $line ) . '" needs two trailing spaces or it runs into the next field.'
			);
		}

		$this->assertSame( rtrim( $last ), $last, 'the last header field needs no trailing spaces.' );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame(
			'https://lesterchan.net/portfolio/programming/php/',
			$this->header( 'Plugin URI' ),
			'the Plugin URI is not the canonical portfolio URL.'
		);
		$this->assertSame( 'https://lesterchan.net', $this->header( 'Author URI' ), 'the Author URI is not canonical.' );
		$this->assertSame(
			'https://lesterchan.net/site/donation/',
			$this->readme( 'Donate link' ),
			'the readme Donate link is not canonical.'
		);
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->header( 'License URI' ),
			'the License URI is not the canonical GPLv2 URL.'
		);
	}

	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme( 'Contributors' ), 'Contributors must be GamerZ and nothing else.' );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'freemyinternet', $this->header( 'Text Domain' ), 'the text domain must be the plugin slug.' );
		$this->assertSame( '/languages', $this->header( 'Domain Path' ), 'the domain path must be /languages.' );
		$this->assertSame( 'freemyinternet', FREEMYINTERNET_SLUG, 'FREEMYINTERNET_SLUG must be the plugin slug.' );
	}

	public function test_version_matches_everywhere() {
		$this->assertSame(
			$this->header( 'Version' ),
			$this->readme( 'Stable tag' ),
			'the readme Stable tag disagrees with the plugin header Version.'
		);
		$this->assertSame(
			$this->header( 'Version' ),
			FREEMYINTERNET_VERSION,
			'FREEMYINTERNET_VERSION disagrees with the plugin header Version.'
		);
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame(
			$this->header( 'Requires at least' ),
			$this->readme( 'Requires at least' ),
			'the WordPress floor disagrees between the plugin header and the readme.'
		);
		$this->assertSame(
			$this->header( 'Requires PHP' ),
			$this->readme( 'Requires PHP' ),
			'the PHP floor disagrees between the plugin header and the readme.'
		);
	}

	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## (.+)$/m', $this->plugin_file_contents( 'README.md' ), $matches );

		$this->assertSame(
			array(
				'Description',
				'Usage',
				'Frequently Asked Questions',
				'Screenshots',
				'Changelog',
				'Upgrade Notice',
			),
			$matches[1],
			'the readme sections are not the canonical set, in the canonical order.'
		);
	}

	public function test_changelog_prefixes_are_canonical() {
		preg_match(
			'/^## Changelog\s*$(.+?)^## /ms',
			$this->plugin_file_contents( 'README.md' ),
			$matches
		);

		$this->assertNotEmpty( $matches, 'the readme has no Changelog section.' );

		preg_match_all( '/^\* (.+)$/m', $matches[1], $entries );

		$this->assertNotEmpty( $entries[1], 'the Changelog section has no entries.' );

		foreach ( $entries[1] as $entry ) {
			$this->assertMatchesRegularExpression(
				'/^(BREAKING|NEW|CHANGED|FIXED|NOTE): /',
				$entry,
				'"' . $entry . '" does not start with one of the five allowed changelog prefixes.'
			);
		}
	}

	public function test_no_jquery_is_enqueued() {
		FreeMyInternet_Options::update(
			array(
				'enabled'     => true,
				'heading'     => 'Protest',
				'dismissible' => true,
			)
		);

		FreeMyInternet_Display::enqueue();

		$this->assertTrue( wp_script_is( FREEMYINTERNET_SLUG, 'registered' ), 'the plugin registered no script to check.' );

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, FREEMYINTERNET_SLUG ) ) {
				continue;
			}

			$this->assertNotContains( 'jquery', (array) $script->deps, $handle . ' declares a jquery dependency.' );
		}

		foreach ( (array) glob( $this->plugin_path( 'includes' ) . '/*.php' ) as $file ) {
			$this->assertStringNotContainsStringIgnoringCase(
				'jquery',
				(string) file_get_contents( $file ),
				basename( $file ) . ' mentions jQuery.'
			);
		}
	}

	public function test_every_directory_has_an_index_php() {
		foreach ( $this->directories() as $directory ) {
			$this->assertFileExists(
				$this->plugin_path( $directory ) . '/index.php',
				$directory . '/ has no silence-is-golden index.php.'
			);
		}
	}

	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'freemyinternet/freemyinternet.php' );
		}

		// require_once: the suite runs in one process, and a second require would
		// redeclare freemyinternet_uninstall_site() and fatal.
		require_once $this->plugin_path( 'uninstall.php' );

		$like = $wpdb->esc_like( FREEMYINTERNET_SLUG ) . '%';

		FreeMyInternet_Options::update( array( 'enabled' => true ) );
		FreeMyInternet_Options::maybe_upgrade();
		update_option( FreeMyInternet_Options::LEGACY_OPTION, array( 'enabled' => true ) );

		freemyinternet_uninstall_site();

		$this->assertSame(
			array(),
			$wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ),
			'uninstalling left option rows behind on a single site.'
		);

		if ( ! is_multisite() ) {
			return;
		}

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		FreeMyInternet_Options::update( array( 'enabled' => true ) );
		FreeMyInternet_Options::maybe_upgrade();

		freemyinternet_uninstall_site();

		$survivors = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		restore_current_blog();

		$this->assertSame( array(), $survivors, 'uninstalling left option rows behind on a second site of the network.' );
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		FreeMyInternet_Options::maybe_upgrade();

		$stored = get_option( FreeMyInternet_Options::VERSION );

		$this->assertIsArray( $stored, 'the version row must hold an array of markers.' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $stored ), 'the version row must hold exactly plugin and db.' );
		$this->assertSame( FREEMYINTERNET_VERSION, $stored['plugin'], 'the plugin marker is not the running version.' );
		$this->assertSame( FREEMYINTERNET_DB_VERSION, $stored['db'], 'the db marker is not the running schema version.' );
	}

	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = FreeMyInternet_Options::sanitize(
			array(
				'enabled'    => '1',
				'heading'    => 'Protest',
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey(
				$key,
				$clean,
				'the sanitizer put a ' . $key . ' marker into the settings row; markers belong in their own row.'
			);
		}

		FreeMyInternet_Options::update( $clean );

		$this->assertSame(
			array(),
			array_intersect( array( 'version', 'db_version', 'versions' ), array_keys( FreeMyInternet_Options::get() ) ),
			'a version marker reached the stored settings row.'
		);
	}

	public function test_no_rtl_stylesheet_is_registered() {
		FreeMyInternet_Options::update(
			array(
				'enabled' => true,
				'heading' => 'Protest',
			)
		);

		FreeMyInternet_Display::enqueue();

		$this->assertFalse(
			wp_styles()->get_data( FREEMYINTERNET_SLUG, 'rtl' ),
			'the plugin registers rtl style data; write direction-neutral CSS instead.'
		);

		foreach ( array_merge( array( '' ), $this->directories() ) as $directory ) {
			$this->assertSame(
				array(),
				(array) glob( $this->plugin_path( $directory ) . '/*-rtl.css' ),
				'no plugin ships a separate RTL stylesheet.'
			);
		}
	}
}
