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
 * Before 1.0.0 the plugin had no settings at all, so there is no hand-rolled form
 * to preserve compatibility with.
 */
class FreeMyInternet_Admin {

	/**
	 * Settings group passed to register_setting() and settings_fields().
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'freemyinternet_group';

	/**
	 * The settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'freemyinternet';

	/**
	 * Hook the admin screen into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Activation hooks do not fire when a plugin is updated, so the version
		// stamp is also refreshed on every admin load.
		add_action( 'admin_init', array( 'FreeMyInternet_Options', 'maybe_upgrade' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( FREEMYINTERNET_MAIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'FreeMyInternet Settings', 'freemyinternet' ),
			__( 'FreeMyInternet', 'freemyinternet' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
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
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'freemyinternet' ) . '</a>'
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
			self::OPTION_GROUP,
			FreeMyInternet_Options::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'FreeMyInternet_Options', 'sanitize' ),
			)
		);

		add_settings_section(
			'freemyinternet_notice',
			__( 'Protest Notice', 'freemyinternet' ),
			array( __CLASS__, 'notice_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'freemyinternet_appearance',
			__( 'Appearance', 'freemyinternet' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_section(
			'freemyinternet_schedule',
			__( 'Schedule', 'freemyinternet' ),
			array( __CLASS__, 'schedule_section_intro' ),
			self::PAGE_SLUG
		);

		foreach ( self::fields() as $name => $field ) {
			add_settings_field(
				$name,
				$field['title'],
				array( __CLASS__, 'render_field' ),
				self::PAGE_SLUG,
				$field['section'],
				array(
					'label_for'   => 'freemyinternet-' . $name,
					'name'        => $name,
					'type'        => $field['type'],
					'description' => isset( $field['description'] ) ? $field['description'] : '',
					'choices'     => isset( $field['choices'] ) ? $field['choices'] : array(),
				)
			);
		}
	}

	/**
	 * The field definitions.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'enabled'     => array(
				'title'       => __( 'Show the notice', 'freemyinternet' ),
				'type'        => 'checkbox',
				'section'     => 'freemyinternet_notice',
				'description' => __( 'Off by default. Nothing is shown to visitors until you turn this on.', 'freemyinternet' ),
			),
			'mode'        => array(
				'title'   => __( 'Presentation', 'freemyinternet' ),
				'type'    => 'radio',
				'section' => 'freemyinternet_notice',
				'choices' => array(
					'blackout' => __( 'Full-screen blackout — covers the whole site', 'freemyinternet' ),
					'banner'   => __( 'Top banner — leaves the site readable', 'freemyinternet' ),
				),
			),
			'heading'     => array(
				'title'   => __( 'Heading', 'freemyinternet' ),
				'type'    => 'text',
				'section' => 'freemyinternet_notice',
			),
			'message'     => array(
				'title'       => __( 'Message', 'freemyinternet' ),
				'type'        => 'textarea',
				'section'     => 'freemyinternet_notice',
				'description' => __( 'Basic HTML such as links and emphasis is allowed.', 'freemyinternet' ),
			),
			'link_url'    => array(
				'title'   => __( 'Link URL', 'freemyinternet' ),
				'type'    => 'url',
				'section' => 'freemyinternet_notice',
			),
			'link_text'   => array(
				'title'       => __( 'Link text', 'freemyinternet' ),
				'type'        => 'text',
				'section'     => 'freemyinternet_notice',
				'description' => __( 'The link is only shown when both the URL and the text are filled in.', 'freemyinternet' ),
			),
			'image_url'   => array(
				'title'       => __( 'Image URL', 'freemyinternet' ),
				'type'        => 'url',
				'section'     => 'freemyinternet_appearance',
				'description' => __( 'Optional. Leave empty for a text-only notice.', 'freemyinternet' ),
			),
			'background'  => array(
				'title'   => __( 'Background colour', 'freemyinternet' ),
				'type'    => 'color',
				'section' => 'freemyinternet_appearance',
			),
			'text_color'  => array(
				'title'   => __( 'Text colour', 'freemyinternet' ),
				'type'    => 'color',
				'section' => 'freemyinternet_appearance',
			),
			'dismissible' => array(
				'title'       => __( 'Let visitors dismiss it', 'freemyinternet' ),
				'type'        => 'checkbox',
				'section'     => 'freemyinternet_appearance',
				'description' => __( 'Adds a close button. The dismissal is remembered until you change the notice.', 'freemyinternet' ),
			),
			'start'       => array(
				'title'       => __( 'Start', 'freemyinternet' ),
				'type'        => 'datetime',
				'section'     => 'freemyinternet_schedule',
				'description' => __( 'Leave empty to start immediately.', 'freemyinternet' ),
			),
			'end'         => array(
				'title'       => __( 'End', 'freemyinternet' ),
				'type'        => 'datetime',
				'section'     => 'freemyinternet_schedule',
				'description' => __( 'Leave empty to keep showing it indefinitely.', 'freemyinternet' ),
			),
		);
	}

	/**
	 * Intro copy for the notice section.
	 *
	 * @return void
	 */
	public static function notice_section_intro() {
		echo '<p>' . esc_html__( 'What visitors see. A heading or a message is required — an empty notice is never shown.', 'freemyinternet' ) . '</p>';
	}

	/**
	 * Intro copy for the schedule section, plus a warning when the window has closed.
	 *
	 * @return void
	 */
	public static function schedule_section_intro() {
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

		$options = FreeMyInternet_Options::get();

		if ( empty( $options['enabled'] ) || FreeMyInternet_Frontend::within_schedule( $options ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning inline"><p>%s</p></div>',
			esc_html__( 'The notice is switched on, but the current date is outside this window, so visitors are not seeing it.', 'freemyinternet' )
		);
	}

	/**
	 * Render one settings field.
	 *
	 * @param array $args Field arguments from add_settings_field().
	 * @return void
	 */
	public static function render_field( $args ) {
		$name  = $args['name'];
		$id    = 'freemyinternet-' . $name;
		$key   = FreeMyInternet_Options::OPTION_NAME . '[' . $name . ']';
		$value = FreeMyInternet_Options::get( $name );

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
					esc_attr( $id ),
					esc_attr( $key ),
					checked( ! empty( $value ), true, false )
				);
				break;

			case 'radio':
				echo '<fieldset>';
				foreach ( $args['choices'] as $choice => $label ) {
					printf(
						'<label style="display:block;margin-bottom:.35em"><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label>',
						esc_attr( $key ),
						esc_attr( $choice ),
						checked( $value, $choice, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="5" class="large-text code">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_textarea( (string) $value )
				);
				break;

			case 'color':
				printf(
					'<input type="color" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
				break;

			case 'datetime':
				printf(
					'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( str_replace( ' ', 'T', (string) $value ) )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="https://" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
				break;
		}

		if ( '' !== $args['description'] ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'FreeMyInternet Settings', 'freemyinternet' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
