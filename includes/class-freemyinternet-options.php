<?php
/**
 * Plugin options.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's single option row.
 *
 * Before 1.0.0 the plugin stored nothing at all -- the banner was hardcoded -- so
 * there are no legacy rows to migrate, only a schema version to stamp so a future
 * release has something to gate on.
 */
class FreeMyInternet_Options {

	/**
	 * The option key holding every setting, as a nested array.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'freemyinternet';

	/**
	 * Schema version. Kept in its own row because it is read to decide whether the
	 * main row needs migrating, so it cannot live inside the thing being migrated.
	 *
	 * @var string
	 */
	const VERSION_OPTION_NAME = 'freemyinternet_version';

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
		$stored  = get_option( self::OPTION_NAME, array() );
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
		return update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Clean a full set of submitted options.
	 *
	 * Also used as register_setting()'s sanitize_callback, which receives the whole
	 * nested array in one go.
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
	 * Stamp the schema version.
	 *
	 * Runs on activation and on every admin load, because activation hooks do not
	 * fire when a plugin is updated -- which is the usual reason a migration never
	 * runs. Idempotent.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION_NAME ) === FREEMYINTERNET_VERSION ) {
			return;
		}

		// No data migration is needed yet: nothing before 1.0.0 stored any options.
		// Future migrations gate on the stored version, never on whether some key
		// happens to be present, so that re-running cannot overwrite a migrated row.
		update_option( self::VERSION_OPTION_NAME, FREEMYINTERNET_VERSION );
	}
}
