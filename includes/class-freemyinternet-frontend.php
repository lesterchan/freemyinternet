<?php
/**
 * Front-end rendering.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the overlay should appear, and renders it.
 *
 * Everything is hung off `wp_footer`, which fires on the front end only -- not in
 * wp-admin and not on wp-login.php -- so a misconfigured overlay cannot lock an
 * owner out of the dashboard.
 */
class FreeMyInternet_Frontend {

	/**
	 * Shared handle for the stylesheet and the script.
	 *
	 * @var string
	 */
	const HANDLE = 'freemyinternet';

	/**
	 * Hook the front end into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		// Must be registered at file-load time, which is when this runs.
		register_activation_hook( FREEMYINTERNET_MAIN_FILE, array( __CLASS__, 'activate' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 5 );
	}

	/**
	 * Activation: stamp the schema version.
	 *
	 * @return void
	 */
	public static function activate() {
		FreeMyInternet_Options::maybe_upgrade();
	}

	/**
	 * Whether the overlay should be shown on this request.
	 *
	 * @return bool
	 */
	public static function should_display() {
		if ( is_admin() ) {
			return false;
		}

		$options = FreeMyInternet_Options::get();

		if ( empty( $options['enabled'] ) ) {
			return false;
		}

		// Nothing to say means nothing to show, rather than an empty black screen.
		if ( '' === trim( (string) $options['heading'] ) && '' === trim( (string) $options['message'] ) ) {
			return false;
		}

		if ( ! self::within_schedule( $options ) ) {
			return false;
		}

		/**
		 * Filters whether the protest overlay is shown on this request.
		 *
		 * Useful for excluding particular templates, or for showing the overlay to
		 * logged-out visitors only.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $display Whether to display the overlay.
		 * @param array $options The plugin's options.
		 */
		return (bool) apply_filters( 'freemyinternet_should_display', true, $options );
	}

	/**
	 * Whether the current moment falls inside the configured window.
	 *
	 * An empty bound means unbounded in that direction, so an unconfigured schedule
	 * always shows. Both stored bounds are read in the site's timezone and compared
	 * as absolute timestamps.
	 *
	 * @param array $options The plugin's options.
	 * @return bool
	 */
	public static function within_schedule( array $options ) {
		$now   = time();
		$start = FreeMyInternet_Options::to_timestamp( isset( $options['start'] ) ? $options['start'] : '' );
		$end   = FreeMyInternet_Options::to_timestamp( isset( $options['end'] ) ? $options['end'] : '' );

		if ( false !== $start && $now < $start ) {
			return false;
		}

		if ( false !== $end && $now > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * A short signature of the current notice.
	 *
	 * The dismissal is stored against this, so editing the protest text or its
	 * schedule shows the overlay again to visitors who dismissed the previous one.
	 *
	 * @param array $options The plugin's options.
	 * @return string
	 */
	public static function dismiss_key( array $options ) {
		$signature = wp_json_encode(
			array(
				isset( $options['heading'] ) ? $options['heading'] : '',
				isset( $options['message'] ) ? $options['message'] : '',
				isset( $options['start'] ) ? $options['start'] : '',
				isset( $options['end'] ) ? $options['end'] : '',
				isset( $options['mode'] ) ? $options['mode'] : '',
			)
		);

		return substr( md5( (string) $signature ), 0, 12 );
	}

	/**
	 * Enqueue the stylesheet, and the script only when there is a button for it.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::should_display() ) {
			return;
		}

		$options = FreeMyInternet_Options::get();

		wp_enqueue_style(
			self::HANDLE,
			plugins_url( 'assets/freemyinternet.css', FREEMYINTERNET_MAIN_FILE ),
			array(),
			FREEMYINTERNET_VERSION
		);

		if ( empty( $options['dismissible'] ) ) {
			return;
		}

		// The $args array form of the last parameter is WP 6.3+; the floor is 6.0,
		// so this passes the boolean $in_footer instead. The script must load after
		// the markup it operates on, which wp_footer has already printed.
		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/freemyinternet.js', FREEMYINTERNET_MAIN_FILE ),
			array(),
			FREEMYINTERNET_VERSION,
			true
		);
	}

	/**
	 * Print the overlay.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::should_display() ) {
			return;
		}

		$options = FreeMyInternet_Options::get();

		$mode = in_array( $options['mode'], FreeMyInternet_Options::modes(), true )
			? $options['mode']
			: 'blackout';

		$background = sanitize_hex_color( (string) $options['background'] );
		$foreground = sanitize_hex_color( (string) $options['text_color'] );

		$style = sprintf(
			'--freemyinternet-bg:%1$s;--freemyinternet-fg:%2$s',
			$background ? $background : '#000000',
			$foreground ? $foreground : '#ffffff'
		);

		// esc_url() strips any protocol outside the safe list, so a javascript: or
		// data: target stored by some other means still cannot reach the page.
		$link_url  = esc_url( (string) $options['link_url'] );
		$image_url = esc_url( (string) $options['image_url'] );
		$link_text = trim( (string) $options['link_text'] );
		$heading   = trim( (string) $options['heading'] );

		$label = '' !== $heading ? $heading : __( 'Site notice', 'freemyinternet' );
		?>
		<div class="freemyinternet-overlay freemyinternet-overlay--<?php echo esc_attr( $mode ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			data-freemyinternet-key="<?php echo esc_attr( self::dismiss_key( $options ) ); ?>"
			role="region"
			aria-label="<?php echo esc_attr( $label ); ?>">
			<div class="freemyinternet-overlay__inner">
				<?php if ( '' !== $image_url ) : ?>
					<img class="freemyinternet-overlay__image" src="<?php echo esc_url( $image_url ); ?>" alt="" />
				<?php endif; ?>

				<?php if ( '' !== $heading ) : ?>
					<p class="freemyinternet-overlay__heading"><?php echo esc_html( $heading ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $options['message'] ) ) : ?>
					<div class="freemyinternet-overlay__message"><?php echo wp_kses_post( $options['message'] ); ?></div>
				<?php endif; ?>

				<?php if ( '' !== $link_url && '' !== $link_text ) : ?>
					<a class="freemyinternet-overlay__link" href="<?php echo esc_url( $link_url ); ?>"><?php echo esc_html( $link_text ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $options['dismissible'] ) ) : ?>
				<button type="button"
					class="freemyinternet-overlay__dismiss"
					data-freemyinternet-dismiss
					aria-label="<?php esc_attr_e( 'Dismiss this notice', 'freemyinternet' ); ?>">&times;</button>
			<?php endif; ?>
		</div>
		<?php
	}
}
