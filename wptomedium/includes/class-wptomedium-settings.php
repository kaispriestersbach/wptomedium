<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page for WPtoMedium plugin.
 */
class WPtoMedium_Settings {

	/**
	 * Option name for AI model preference.
	 *
	 * @var string
	 */
	const OPTION_MODEL = 'wptomedium_model_preference';

	/**
	 * Register settings with WordPress Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'wptomedium_settings',
			self::OPTION_MODEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'auto',
			)
		);

		add_settings_section(
			'wptomedium_ai_section',
			__( 'AI Configuration', 'wptomedium' ),
			array( __CLASS__, 'render_ai_section' ),
			'wptomedium-settings'
		);

		add_settings_field(
			'wptomedium_model_field',
			__( 'Preferred AI Model', 'wptomedium' ),
			array( __CLASS__, 'render_model_field' ),
			'wptomedium-settings',
			'wptomedium_ai_section'
		);
	}

	/**
	 * Render the AI section description with link to AI Credentials.
	 */
	public static function render_ai_section() {
		$credentials_url = admin_url( 'options-general.php' );
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'Configure your AI API key under', 'wptomedium' ),
			esc_url( $credentials_url ),
			esc_html__( 'Settings > AI Credentials', 'wptomedium' )
		);
	}

	/**
	 * Render the model preference dropdown.
	 */
	public static function render_model_field() {
		$current = get_option( self::OPTION_MODEL, 'auto' );
		$models  = array(
			'auto'   => __( 'Automatic (SDK decides)', 'wptomedium' ),
			'claude' => 'Claude',
			'gpt'    => 'GPT',
			'gemini' => 'Gemini',
		);
		echo '<select name="' . esc_attr( self::OPTION_MODEL ) . '">';
		foreach ( $models as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wptomedium_settings' );
				do_settings_sections( 'wptomedium-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the configured model preference.
	 *
	 * @return string Model preference or 'auto'.
	 */
	public static function get_model_preference() {
		return get_option( self::OPTION_MODEL, 'auto' );
	}
}
