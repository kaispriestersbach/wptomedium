<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page for WPtoMedium plugin.
 */
class WPtoMedium_Settings {

	/**
	 * Option name for Anthropic API key.
	 *
	 * @var string
	 */
	const OPTION_API_KEY = 'wptomedium_api_key';

	/**
	 * Option name for Claude model.
	 *
	 * @var string
	 */
	const OPTION_MODEL = 'wptomedium_model';

	/**
	 * Register settings with WordPress Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'wptomedium_settings',
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'wptomedium_settings',
			self::OPTION_MODEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'claude-sonnet-4-20250514',
			)
		);

		add_settings_section(
			'wptomedium_ai_section',
			__( 'AI Configuration', 'wptomedium' ),
			array( __CLASS__, 'render_ai_section' ),
			'wptomedium-settings'
		);

		add_settings_field(
			'wptomedium_api_key_field',
			__( 'Anthropic API Key', 'wptomedium' ),
			array( __CLASS__, 'render_api_key_field' ),
			'wptomedium-settings',
			'wptomedium_ai_section'
		);

		add_settings_field(
			'wptomedium_model_field',
			__( 'Claude Model', 'wptomedium' ),
			array( __CLASS__, 'render_model_field' ),
			'wptomedium-settings',
			'wptomedium_ai_section'
		);
	}

	/**
	 * Render the AI section description.
	 */
	public static function render_ai_section() {
		echo '<p>' . esc_html__( 'Enter your Anthropic API key and select a Claude model for translations.', 'wptomedium' ) . '</p>';
	}

	/**
	 * Render the API key input field.
	 */
	public static function render_api_key_field() {
		$api_key = get_option( self::OPTION_API_KEY, '' );
		$masked  = '';
		if ( ! empty( $api_key ) ) {
			$masked = str_repeat( '*', max( 0, strlen( $api_key ) - 4 ) ) . substr( $api_key, -4 );
		}
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_API_KEY ); ?>"
			value="<?php echo esc_attr( $api_key ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<button type="button" class="button wptomedium-validate-key">
			<?php esc_html_e( 'Validate Key', 'wptomedium' ); ?>
		</button>
		<span class="wptomedium-validate-result" style="display:none; margin-left:10px;"></span>
		<?php if ( ! empty( $masked ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: masked API key showing only last 4 characters */
					esc_html__( 'Current key: %s', 'wptomedium' ),
					'<code>' . esc_html( $masked ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to Anthropic console */
				esc_html__( 'Get your API key from %s', 'wptomedium' ),
				'<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the model selection dropdown.
	 */
	public static function render_model_field() {
		$current = get_option( self::OPTION_MODEL, 'claude-sonnet-4-20250514' );
		$models  = self::get_available_models();
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
	 * Get available Claude models.
	 *
	 * @return array<string, string> Model ID => Display name.
	 */
	public static function get_available_models() {
		return array(
			'claude-sonnet-4-20250514'  => 'Claude Sonnet 4',
			'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
		);
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
	 * Get the configured Anthropic API key.
	 *
	 * @return string API key or empty string.
	 */
	public static function get_api_key() {
		return get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Get the configured Claude model ID.
	 *
	 * @return string Model ID.
	 */
	public static function get_model() {
		return get_option( self::OPTION_MODEL, 'claude-sonnet-4-20250514' );
	}

	/**
	 * AJAX handler to validate the Anthropic API key.
	 */
	public static function ajax_validate_key() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wptomedium' ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'Please enter an API key.', 'wptomedium' ) );
		}

		if ( ! class_exists( 'Anthropic\\Client' ) ) {
			wp_send_json_error( __( 'Anthropic SDK not available.', 'wptomedium' ) );
		}

		try {
			$client  = new \Anthropic\Client( apiKey: $api_key );
			$message = $client->messages->create(
				model: 'claude-haiku-4-5-20251001',
				maxTokens: 1,
				messages: array(
					array(
						'role'    => 'user',
						'content' => 'Hi',
					),
				),
			);
			wp_send_json_success( __( 'API key is valid!', 'wptomedium' ) );
		} catch ( \Anthropic\Core\Exceptions\AuthenticationException $e ) {
			wp_send_json_error( __( 'Invalid API key.', 'wptomedium' ) );
		} catch ( \Anthropic\Core\Exceptions\RateLimitException $e ) {
			wp_send_json_error( __( 'Rate limit exceeded. Key may be valid — try again later.', 'wptomedium' ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( sprintf( __( 'Connection error: %s', 'wptomedium' ), $e->getMessage() ) );
		}
	}
}
