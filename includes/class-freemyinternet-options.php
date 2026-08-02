<?php
/**
 * Plugin options.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's two option rows.
 *
 * Settings live in one row and the version markers in another, so the settings
 * screen and the upgrade routine can never overwrite each other's work.
 */
class FreeMyInternet_Options {

	/**
	 * The option row holding every setting, as a nested array.
	 *
	 * @var string
	 */
	const OPTION = 'freemyinternet_options';

	/**
	 * The option row holding the 'plugin' and 'db' version markers.
	 *
	 * @var string
	 */
	const VERSION = 'freemyinternet_version';

	/**
	 * The settings row this plugin used before 1.0.0 shipped.
	 *
	 * The rename happened inside the unreleased major, so this is only ever seen
	 * on an install that ran a development build.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'freemyinternet';

	/**
	 * The two supported presentations.
	 *
	 * @return array
	 */
	public static function modes() {
		return array( 'blackout', 'banner' );
	}

	/**
	 * The default option values.
	 *
	 * `enabled` defaults to false on purpose. Versions up to 0.01 blacked out the
	 * site unconditionally the moment they were activated, against a campaign that
	 * ended in 2013 and an image host that no longer resolves. An upgrade must not
	 * silently keep doing that, and a fresh activation must not start doing it.
	 *
	 * Translated on demand rather than at load time, so this must not be called
	 * before `init` fires.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'     => false,
			'mode'        => 'blackout',
			'heading'     => '',
			'message'     => '',
			'link_url'    => '',
			'link_text'   => __( 'Find out more', 'freemyinternet' ),
			'image_url'   => '',
			'background'  => '#000000',
			'text_color'  => '#ffffff',
			'dismissible' => true,
			'start'       => '',
			'end'         => '',
		);
	}

	/**
	 * Get the stored options, merged over the defaults.
	 *
	 * Merging on read is what lets an install upgraded from an older version pick
	 * up keys that did not exist when its row was written.
	 *
	 * @param string|null $key Optional single key to return.
	 * @return mixed The full option array, or one value, or null for an unknown key.
	 */
	public static function get( $key = null ) {
		$stored  = get_option( self::OPTION, array() );
		$options = wp_parse_args( is_array( $stored ) ? $stored : array(), self::get_defaults() );

		if ( null === $key ) {
			return $options;
		}

		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/**
	 * Replace the stored options.
	 *
	 * @param array $options Option values.
	 * @return bool Whether the option row changed.
	 */
	public static function update( array $options ) {
		return update_option( self::OPTION, $options );
	}

	/**
	 * Get the version markers.
	 *
	 * @return array The 'plugin' and 'db' markers, each an empty string when unset.
	 */
	public static function get_versions() {
		$stored = get_option( self::VERSION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Clean a full set of submitted options.
	 *
	 * Also used as register_setting()'s sanitize_callback, which receives the whole
	 * nested array in one go. It reads nothing back out of the database: the version
	 * markers live in their own row, so there is nothing here to rescue and nothing
	 * this can corrupt.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$clean = array();

		$clean['enabled']     = ! empty( $input['enabled'] );
		$clean['dismissible'] = ! empty( $input['dismissible'] );

		$mode          = isset( $input['mode'] ) ? (string) $input['mode'] : '';
		$clean['mode'] = in_array( $mode, self::modes(), true ) ? $mode : $defaults['mode'];

		$clean['heading']   = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : '';
		$clean['link_text'] = isset( $input['link_text'] ) ? sanitize_text_field( $input['link_text'] ) : '';

		// The message is the one field allowed inline markup, so a protest notice can
		// carry a link or some emphasis. wp_kses_post() drops scripts and handlers.
		$clean['message'] = isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : '';

		// esc_url_raw() drops any protocol outside the safe list, so javascript: and
		// data: URLs become an empty string rather than a stored payload.
		$clean['link_url']  = isset( $input['link_url'] ) ? esc_url_raw( trim( (string) $input['link_url'] ) ) : '';
		$clean['image_url'] = isset( $input['image_url'] ) ? esc_url_raw( trim( (string) $input['image_url'] ) ) : '';

		// sanitize_hex_color() returns null for anything that is not #rgb or #rrggbb.
		$background          = isset( $input['background'] ) ? sanitize_hex_color( $input['background'] ) : null;
		$text_color          = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : null;
		$clean['background'] = $background ? $background : $defaults['background'];
		$clean['text_color'] = $text_color ? $text_color : $defaults['text_color'];

		$clean['start'] = isset( $input['start'] ) ? self::sanitize_datetime( $input['start'] ) : '';
		$clean['end']   = isset( $input['end'] ) ? self::sanitize_datetime( $input['end'] ) : '';

		return $clean;
	}

	/**
	 * Normalise a datetime-local value to `Y-m-d H:i`, or an empty string.
	 *
	 * An empty bound means "unbounded in that direction", which is why an
	 * unparseable value is discarded rather than defaulted to now.
	 *
	 * @param mixed $value Raw value, expected as `Y-m-d\TH:i` from datetime-local.
	 * @return string
	 */
	public static function sanitize_datetime( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( str_replace( 'T', ' ', (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		$parsed = self::to_timestamp( $value );

		return false === $parsed ? '' : wp_date( 'Y-m-d H:i', $parsed );
	}

	/**
	 * Convert a stored datetime string to a Unix timestamp in the site's timezone.
	 *
	 * @param string $value Datetime string.
	 * @return int|false Timestamp, or false if it could not be parsed.
	 */
	public static function to_timestamp( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return false;
		}

		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return false;
		}

		return $date->getTimestamp();
	}

	/**
	 * Bring the stored rows up to date with the running code.
	 *
	 * Runs on activation and on every admin load, because activation hooks do not
	 * fire when a plugin is updated -- which is the usual reason a migration never
	 * runs. Idempotent.
	 *
	 * Both markers are written together in one update_option() at the very end, so
	 * a half-finished upgrade never records itself as complete.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$versions = self::get_versions();

		if ( FREEMYINTERNET_VERSION === $versions['plugin'] && FREEMYINTERNET_DB_VERSION === $versions['db'] ) {
			return;
		}

		self::migrate();

		update_option(
			self::VERSION,
			array(
				'plugin' => FREEMYINTERNET_VERSION,
				'db'     => FREEMYINTERNET_DB_VERSION,
			)
		);
	}

	/**
	 * Fold the pre-1.0.0 rows into the current ones.
	 *
	 * The old settings row was named after the slug alone, and the old version row
	 * held a bare version string rather than the two markers. Both are read once,
	 * folded in, and deleted; re-running finds nothing left to do.
	 *
	 * Settings are re-sanitised on the way through, so an upgrade cleans a row that
	 * an older, laxer version wrote just as thoroughly as a save would.
	 *
	 * @return void
	 */
	protected static function migrate() {
		$legacy = get_option( self::LEGACY_OPTION );

		if ( false !== $legacy ) {
			/*
			 * The raw row, never one register_setting() can synthesise. A plugin
			 * that passes a `default` installs a `default_option_*` filter with it,
			 * and a bare get_option() then answers with that defaults array instead
			 * of false for a row which does not exist -- so this branch is skipped
			 * and the legacy row is deleted a few lines below regardless, taking the
			 * settings with it. Passing an explicit default defeats the registered
			 * one: filter_default_option() returns early when a default was passed.
			 *
			 * It bites only on an admin request. Activation and WP-CLI never run
			 * register_setting(), which is why reactivating repairs it and why every
			 * test that goes through activation passes.
			 */
			if ( false === get_option( self::OPTION, false ) ) {
				update_option( self::OPTION, self::sanitize( $legacy ) );
			}

			delete_option( self::LEGACY_OPTION );
		}

		$stored = get_option( self::OPTION, false );

		if ( false !== $stored ) {
			update_option( self::OPTION, self::sanitize( $stored ) );
		}
	}
}
