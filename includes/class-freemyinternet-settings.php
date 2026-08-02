<?php
/**
 * The settings screen.
 *
 * @package FreeMyInternet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds Settings -> FreeMyInternet with the WordPress Settings API.
 *
 * The plugin's only admin surface is its settings, so it takes a single page
 * under Settings rather than a top-level menu, and every field is registered
 * rather than hand-written into a form table.
 */
class FreeMyInternet_Settings {

	/**
	 * Settings group passed to register_setting() and settings_fields().
	 *
	 * @var string
	 */
	const GROUP = 'freemyinternet_options';

	/**
	 * The settings page slug.
	 *
	 * @var string
	 */
	const PAGE = 'freemyinternet';

	/**
	 * The capability required to see and save the settings.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The section holding what visitors read.
	 *
	 * @var string
	 */
	const SECTION_NOTICE = 'freemyinternet_notice';

	/**
	 * The section holding how the notice looks.
	 *
	 * @var string
	 */
	const SECTION_APPEARANCE = 'freemyinternet_appearance';

	/**
	 * The section holding when the notice runs.
	 *
	 * @var string
	 */
	const SECTION_SCHEDULE = 'freemyinternet_schedule';

	/**
	 * Hook the admin screen into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Activation hooks do not fire when a plugin is updated, so the upgrade
		// routine is also run on every admin load.
		add_action( 'admin_init', array( 'FreeMyInternet_Options', 'maybe_upgrade' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( FREEMYINTERNET_MAIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * The capability required for a given context.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string
	 */
	public static function capability( $context = 'settings' ) {
		/**
		 * Filters the capability required to manage the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What the capability is being checked for.
		 */
		return (string) apply_filters( 'freemyinternet_capability', self::CAPABILITY, $context );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		$hook = add_options_page(
			__( 'FreeMyInternet Settings', 'freemyinternet' ),
			__( 'FreeMyInternet', 'freemyinternet' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);

		if ( $hook ) {
			// The screen does not print its own notices -- see render_page() --
			// so anything it wants to say has to be queued before the printer
			// runs. load-{$hook} fires in admin.php immediately before
			// admin-header.php, which is what pulls in options-head.php.
			add_action( 'load-' . $hook, array( __CLASS__, 'queue_schedule_warning' ) );
		}
	}

	/**
	 * Warn on the screen when the notice is switched on but its window has closed.
	 *
	 * Queued from load-{$hook} rather than while rendering, because by the time
	 * the page renders, options-head.php has already printed the queue.
	 *
	 * @return void
	 */
	public static function queue_schedule_warning() {
		if ( ! self::schedule_has_closed() ) {
			return;
		}

		add_settings_error(
			self::GROUP,
			'freemyinternet_schedule_closed',
			__( 'The notice is switched on, but the current date is outside its window, so visitors are not seeing it.', 'freemyinternet' ),
			'warning'
		);
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'freemyinternet' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			FreeMyInternet_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'FreeMyInternet_Options', 'sanitize' ),
			)
		);

		add_settings_section(
			self::SECTION_NOTICE,
			__( 'Protest Notice', 'freemyinternet' ),
			array( __CLASS__, 'section_notice' ),
			self::PAGE
		);

		add_settings_section(
			self::SECTION_APPEARANCE,
			__( 'Appearance', 'freemyinternet' ),
			'__return_false',
			self::PAGE
		);

		add_settings_section(
			self::SECTION_SCHEDULE,
			__( 'Schedule', 'freemyinternet' ),
			array( __CLASS__, 'section_schedule' ),
			self::PAGE
		);

		foreach ( self::fields() as $name => $field ) {
			add_settings_field(
				$name,
				$field['title'],
				array( __CLASS__, 'field_' . $name ),
				self::PAGE,
				$field['section'],
				array( 'label_for' => self::PAGE . '-' . $name )
			);
		}
	}

	/**
	 * The field definitions: a title and a section for each registered field.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'enabled'     => array(
				'title'   => __( 'Show the notice', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'mode'        => array(
				'title'   => __( 'Presentation', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'heading'     => array(
				'title'   => __( 'Heading', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'message'     => array(
				'title'   => __( 'Message', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'link_url'    => array(
				'title'   => __( 'Link URL', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'link_text'   => array(
				'title'   => __( 'Link text', 'freemyinternet' ),
				'section' => self::SECTION_NOTICE,
			),
			'image_url'   => array(
				'title'   => __( 'Image URL', 'freemyinternet' ),
				'section' => self::SECTION_APPEARANCE,
			),
			'background'  => array(
				'title'   => __( 'Background colour', 'freemyinternet' ),
				'section' => self::SECTION_APPEARANCE,
			),
			'text_color'  => array(
				'title'   => __( 'Text colour', 'freemyinternet' ),
				'section' => self::SECTION_APPEARANCE,
			),
			'dismissible' => array(
				'title'   => __( 'Let visitors dismiss it', 'freemyinternet' ),
				'section' => self::SECTION_APPEARANCE,
			),
			'start'       => array(
				'title'   => __( 'Start', 'freemyinternet' ),
				'section' => self::SECTION_SCHEDULE,
			),
			'end'         => array(
				'title'   => __( 'End', 'freemyinternet' ),
				'section' => self::SECTION_SCHEDULE,
			),
		);
	}

	/**
	 * Intro copy for the notice section.
	 *
	 * @return void
	 */
	public static function section_notice() {
		echo '<p>' . esc_html__( 'What visitors see. A heading or a message is required — an empty notice is never shown.', 'freemyinternet' ) . '</p>';
	}

	/**
	 * Intro copy for the schedule section.
	 *
	 * @return void
	 */
	public static function section_schedule() {
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the site's configured timezone, e.g. Asia/Singapore or UTC+8. */
					__( 'Times are in your site timezone (%s). Outside the window nothing is shown.', 'freemyinternet' ),
					wp_timezone_string()
				)
			)
		);
	}

	/**
	 * Whether the notice is switched on but its window has already closed.
	 *
	 * This is the state the plugin spent thirteen years in, so the screen says so
	 * rather than leaving the owner to wonder why nothing appears.
	 *
	 * @return bool
	 */
	public static function schedule_has_closed() {
		$options = FreeMyInternet_Options::get();

		return ! empty( $options['enabled'] ) && ! FreeMyInternet_Display::within_schedule( $options );
	}

	/**
	 * The "show the notice" checkbox.
	 *
	 * @return void
	 */
	public static function field_enabled() {
		self::checkbox( 'enabled' );
		self::description( __( 'Off by default. Nothing is shown to visitors until you turn this on.', 'freemyinternet' ) );
	}

	/**
	 * The presentation radio buttons.
	 *
	 * @return void
	 */
	public static function field_mode() {
		$choices = array(
			'blackout' => __( 'Full-screen blackout — covers the whole site', 'freemyinternet' ),
			'banner'   => __( 'Top banner — leaves the site readable', 'freemyinternet' ),
		);

		$value = (string) FreeMyInternet_Options::get( 'mode' );

		echo '<fieldset>';

		// The first radio carries the id the field was registered with, so the
		// label do_settings_fields() prints has something to point at.
		$first = true;

		foreach ( $choices as $choice => $label ) {
			printf(
				'<label><input type="radio" id="%1$s" name="%2$s" value="%3$s" %4$s /> %5$s</label><br />',
				esc_attr( $first ? self::id( 'mode' ) : self::id( 'mode' ) . '-' . $choice ),
				esc_attr( self::name( 'mode' ) ),
				esc_attr( $choice ),
				checked( $value, $choice, false ),
				esc_html( $label )
			);

			$first = false;
		}

		echo '</fieldset>';
	}

	/**
	 * The heading text box.
	 *
	 * @return void
	 */
	public static function field_heading() {
		self::text( 'heading' );
	}

	/**
	 * The message box.
	 *
	 * @return void
	 */
	public static function field_message() {
		printf(
			'<textarea id="%1$s" name="%2$s" rows="5" class="large-text code">%3$s</textarea>',
			esc_attr( self::id( 'message' ) ),
			esc_attr( self::name( 'message' ) ),
			esc_textarea( (string) FreeMyInternet_Options::get( 'message' ) )
		);

		self::description( __( 'Basic HTML such as links and emphasis is allowed.', 'freemyinternet' ) );
	}

	/**
	 * The call-to-action link URL.
	 *
	 * @return void
	 */
	public static function field_link_url() {
		self::url( 'link_url' );
	}

	/**
	 * The call-to-action link text.
	 *
	 * @return void
	 */
	public static function field_link_text() {
		self::text( 'link_text' );
		self::description( __( 'The link is only shown when both the URL and the text are filled in.', 'freemyinternet' ) );
	}

	/**
	 * The optional image URL.
	 *
	 * @return void
	 */
	public static function field_image_url() {
		self::url( 'image_url' );
		self::description( __( 'Optional. Leave empty for a text-only notice.', 'freemyinternet' ) );
	}

	/**
	 * The background colour picker.
	 *
	 * @return void
	 */
	public static function field_background() {
		self::color( 'background' );
	}

	/**
	 * The text colour picker.
	 *
	 * @return void
	 */
	public static function field_text_color() {
		self::color( 'text_color' );
	}

	/**
	 * The dismissable checkbox.
	 *
	 * @return void
	 */
	public static function field_dismissible() {
		self::checkbox( 'dismissible' );
		self::description( __( 'Adds a close button. The dismissal is remembered until you change the notice.', 'freemyinternet' ) );
	}

	/**
	 * The start of the window.
	 *
	 * @return void
	 */
	public static function field_start() {
		self::datetime( 'start' );
		self::description( __( 'Leave empty to start immediately.', 'freemyinternet' ) );
	}

	/**
	 * The end of the window.
	 *
	 * @return void
	 */
	public static function field_end() {
		self::datetime( 'end' );
		self::description( __( 'Leave empty to keep showing it indefinitely.', 'freemyinternet' ) );
	}

	/**
	 * The id attribute for a field, matching the label_for it was registered with.
	 *
	 * @param string $name Option key.
	 * @return string
	 */
	protected static function id( $name ) {
		return self::PAGE . '-' . $name;
	}

	/**
	 * The name attribute for a field, which posts into the settings array.
	 *
	 * @param string $name Option key.
	 * @return string
	 */
	protected static function name( $name ) {
		return FreeMyInternet_Options::OPTION . '[' . $name . ']';
	}

	/**
	 * Print a field's description.
	 *
	 * @param string $text Description text.
	 * @return void
	 */
	protected static function description( $text ) {
		printf( '<p class="description">%s</p>', esc_html( $text ) );
	}

	/**
	 * Print a checkbox.
	 *
	 * @param string $name Option key.
	 * @return void
	 */
	protected static function checkbox( $name ) {
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
			esc_attr( self::id( $name ) ),
			esc_attr( self::name( $name ) ),
			checked( ! empty( FreeMyInternet_Options::get( $name ) ), true, false )
		);
	}

	/**
	 * Print a single-line text box.
	 *
	 * @param string $name Option key.
	 * @return void
	 */
	protected static function text( $name ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
			esc_attr( self::id( $name ) ),
			esc_attr( self::name( $name ) ),
			esc_attr( (string) FreeMyInternet_Options::get( $name ) )
		);
	}

	/**
	 * Print a URL box.
	 *
	 * @param string $name Option key.
	 * @return void
	 */
	protected static function url( $name ) {
		printf(
			'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="https://" />',
			esc_attr( self::id( $name ) ),
			esc_attr( self::name( $name ) ),
			esc_attr( (string) FreeMyInternet_Options::get( $name ) )
		);
	}

	/**
	 * Print a colour picker.
	 *
	 * @param string $name Option key.
	 * @return void
	 */
	protected static function color( $name ) {
		printf(
			'<input type="color" id="%1$s" name="%2$s" value="%3$s" />',
			esc_attr( self::id( $name ) ),
			esc_attr( self::name( $name ) ),
			esc_attr( (string) FreeMyInternet_Options::get( $name ) )
		);
	}

	/**
	 * Print a datetime-local box.
	 *
	 * The stored value carries a space where the input wants a T.
	 *
	 * @param string $name Option key.
	 * @return void
	 */
	protected static function datetime( $name ) {
		printf(
			'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s" />',
			esc_attr( self::id( $name ) ),
			esc_attr( self::name( $name ) ),
			esc_attr( str_replace( ' ', 'T', (string) FreeMyInternet_Options::get( $name ) ) )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FreeMyInternet Settings', 'freemyinternet' ); ?></h1>
			<?php
			/*
			 * No settings_errors() call here on purpose.
			 *
			 * This screen is registered with add_options_page(), so its parent
			 * is options-general.php, and admin-header.php requires
			 * options-head.php -- which calls settings_errors() -- for exactly
			 * that parent. The notices are therefore already printed above
			 * .wrap by the time this runs, and common.js relocates them into
			 * it. Calling it again rendered every queued notice a second time,
			 * "Settings saved." included, the two stacked one under the other
			 * inside what looks like the plugin's own markup.
			 *
			 * wp-ban carries the same comment for the same reason. A screen on
			 * a top-level menu needs the opposite -- admin.php includes no
			 * options-head.php, so nothing prints unless the screen does -- and
			 * that is the half of the rule this file must not be "fixed" back
			 * to. It is also why the closed-window warning is queued from
			 * load-{$hook} in add_page(): queueing it here would be too late.
			 */
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
