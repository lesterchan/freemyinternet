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
	 * Enqueue the overlay's styling, and its script only when there is a button.
	 *
	 * Both are inlined rather than shipped as files. Together they are a few
	 * kilobytes, and they are only ever printed while a protest is actually
	 * running, so two extra HTTP requests would be poor value. Inlining the CSS
	 * also removes any flash of an unstyled full-screen overlay.
	 *
	 * These register srcless handles rather than echoing tags directly, so the
	 * output still goes through WordPress's own printers -- which is what lets a
	 * CSP plugin filtering script_loader_tag attach its nonce.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::should_display() ) {
			return;
		}

		$options = FreeMyInternet_Options::get();

		wp_register_style( self::HANDLE, false, array(), FREEMYINTERNET_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );

		if ( empty( $options['dismissible'] ) ) {
			return;
		}

		// The $args array form of the last parameter is WP 6.3+; the floor is 6.0,
		// so this passes the boolean $in_footer instead. The script must run after
		// the markup it operates on, which wp_footer has already printed.
		wp_register_script( self::HANDLE, false, array(), FREEMYINTERNET_VERSION, true );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, self::js() );
	}

	/**
	 * The overlay stylesheet.
	 *
	 * Colours come from the two custom properties, which render() sets inline from
	 * the saved options.
	 *
	 * @return string
	 */
	public static function css() {
		return <<<'CSS'
.freemyinternet-overlay{--freemyinternet-bg:#000;--freemyinternet-fg:#fff;box-sizing:border-box;background-color:var(--freemyinternet-bg);color:var(--freemyinternet-fg);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.5;z-index:99999}
.freemyinternet-overlay *,.freemyinternet-overlay *::before,.freemyinternet-overlay *::after{box-sizing:inherit}
.freemyinternet-overlay--blackout{position:fixed;top:0;right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:center;padding:3rem 1.5rem;overflow-y:auto;text-align:center}
.freemyinternet-overlay--blackout .freemyinternet-overlay__inner{max-width:40rem}
.freemyinternet-overlay--banner{position:fixed;top:0;right:0;left:0;display:flex;align-items:center;justify-content:center;padding:.75rem 3rem;text-align:center}
.freemyinternet-overlay--banner .freemyinternet-overlay__inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.25rem 1rem}
.freemyinternet-overlay__image{display:block;max-width:100%;height:auto;margin:0 auto 1.5rem}
.freemyinternet-overlay--banner .freemyinternet-overlay__image{max-height:1.75rem;margin:0}
.freemyinternet-overlay__heading{margin:0 0 .75rem;font-size:1.75rem;font-weight:700;color:inherit}
.freemyinternet-overlay--banner .freemyinternet-overlay__heading{margin:0;font-size:1rem}
.freemyinternet-overlay__message{margin:0 0 1.25rem}
.freemyinternet-overlay--banner .freemyinternet-overlay__message{margin:0;font-size:.9375rem}
.freemyinternet-overlay__message>*:last-child{margin-bottom:0}
.freemyinternet-overlay__message a,.freemyinternet-overlay__link{color:inherit}
.freemyinternet-overlay__link{display:inline-block;padding:.5rem 1.25rem;border:2px solid currentColor;border-radius:3px;font-weight:600;text-decoration:none}
.freemyinternet-overlay--banner .freemyinternet-overlay__link{padding:.125rem .75rem;border-width:1px}
.freemyinternet-overlay__link:hover,.freemyinternet-overlay__link:focus{color:var(--freemyinternet-bg);background-color:var(--freemyinternet-fg)}
.freemyinternet-overlay__dismiss{position:absolute;top:.5rem;right:.75rem;padding:.25rem .5rem;border:0;background:none;color:inherit;font-size:1.75rem;line-height:1;cursor:pointer}
.freemyinternet-overlay__dismiss:focus-visible{outline:2px solid currentColor;outline-offset:2px}
@media screen and (max-width:600px){.freemyinternet-overlay__heading{font-size:1.375rem}.freemyinternet-overlay--banner{padding:.75rem 2.5rem}}
CSS;
	}

	/**
	 * The dismissal script.
	 *
	 * Vanilla, no jQuery. The dismissal is remembered against a signature of the
	 * current notice, so editing the protest text shows it again to everyone.
	 *
	 * @return string
	 */
	public static function js() {
		return <<<'JS'
( function () {
	var SELECTOR = '.freemyinternet-overlay';
	var PREFIX = 'freemyinternet-dismissed:';

	function storageKey( overlay ) {
		return PREFIX + ( overlay.getAttribute( 'data-freemyinternet-key' ) || '' );
	}

	function wasDismissed( overlay ) {
		try {
			return null !== window.localStorage.getItem( storageKey( overlay ) );
		} catch ( e ) {
			return false;
		}
	}

	function remember( overlay ) {
		try {
			window.localStorage.setItem( storageKey( overlay ), '1' );
		} catch ( e ) {}
	}

	function remove( overlay ) {
		if ( overlay && overlay.parentNode ) {
			overlay.parentNode.removeChild( overlay );
		}
	}

	function init() {
		var overlay = document.querySelector( SELECTOR );

		if ( ! overlay ) {
			return;
		}

		if ( wasDismissed( overlay ) ) {
			remove( overlay );
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			if ( ! ( event.target instanceof Element ) ) {
				return;
			}

			var button = event.target.closest( '[data-freemyinternet-dismiss]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();
			remember( button.closest( SELECTOR ) );
			remove( button.closest( SELECTOR ) );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
JS;
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
