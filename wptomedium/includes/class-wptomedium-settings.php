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
	 * Option name for system prompt.
	 *
	 * @var string
	 */
	const OPTION_SYSTEM_PROMPT = 'wptomedium_system_prompt';

	/**
	 * Option name for max tokens.
	 *
	 * @var string
	 */
	const OPTION_MAX_TOKENS = 'wptomedium_max_tokens';

	/**
	 * Option name for temperature.
	 *
	 * @var string
	 */
	const OPTION_TEMPERATURE = 'wptomedium_temperature';

	/**
	 * Default system prompt (editable part, without format instructions).
	 *
	 * @var string
	 */
	const DEFAULT_SYSTEM_PROMPT = 'You are a professional translator specializing in German to English blog post translation. Keep all HTML tags exactly as they are. Do not add or remove any HTML tags. Translate only the text content within the tags. Maintain the original tone, style, and formatting.';

	/**
	 * Default max tokens.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_TOKENS = 4096;

	/**
	 * Default temperature.
	 *
	 * @var float
	 */
	const DEFAULT_TEMPERATURE = 0.3;

	/**
	 * Transient name for cached models list.
	 *
	 * @var string
	 */
	const TRANSIENT_MODELS = 'wptomedium_models_cache';

	/**
	 * Cache TTL for models list in seconds (12 hours).
	 *
	 * @var int
	 */
	const MODELS_CACHE_TTL = 43200;

	/**
	 * Fallback models when API is not available.
	 *
	 * @var array<string, string>
	 */
	const FALLBACK_MODELS = array(
		'claude-sonnet-4-20250514'  => 'Claude Sonnet 4',
		'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
	);

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

		// Translation Settings.
		register_setting(
			'wptomedium_settings',
			self::OPTION_SYSTEM_PROMPT,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => self::DEFAULT_SYSTEM_PROMPT,
			)
		);

		register_setting(
			'wptomedium_settings',
			self::OPTION_MAX_TOKENS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_max_tokens' ),
				'default'           => self::DEFAULT_MAX_TOKENS,
			)
		);

		register_setting(
			'wptomedium_settings',
			self::OPTION_TEMPERATURE,
			array(
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_temperature' ),
				'default'           => self::DEFAULT_TEMPERATURE,
			)
		);

		add_settings_section(
			'wptomedium_translation_section',
			__( 'Translation Settings', 'wptomedium' ),
			array( __CLASS__, 'render_translation_section' ),
			'wptomedium-settings'
		);

		add_settings_field(
			'wptomedium_system_prompt_field',
			__( 'System Prompt', 'wptomedium' ),
			array( __CLASS__, 'render_system_prompt_field' ),
			'wptomedium-settings',
			'wptomedium_translation_section'
		);

		add_settings_field(
			'wptomedium_max_tokens_field',
			__( 'Max Tokens', 'wptomedium' ),
			array( __CLASS__, 'render_max_tokens_field' ),
			'wptomedium-settings',
			'wptomedium_translation_section'
		);

		add_settings_field(
			'wptomedium_temperature_field',
			__( 'Temperature', 'wptomedium' ),
			array( __CLASS__, 'render_temperature_field' ),
			'wptomedium-settings',
			'wptomedium_translation_section'
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
		echo '<select id="wptomedium-model-select" name="' . esc_attr( self::OPTION_MODEL ) . '">';
		foreach ( $models as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		?>
		<button type="button" class="button wptomedium-refresh-models">
			<?php esc_html_e( 'Refresh Models', 'wptomedium' ); ?>
		</button>
		<span class="wptomedium-refresh-result" style="display:none; margin-left:10px;"></span>
		<?php
	}

	/**
	 * Get available Claude models from cache or fallback.
	 *
	 * @return array<string, string> Model ID => Display name.
	 */
	public static function get_available_models() {
		$cached = get_transient( self::TRANSIENT_MODELS );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		return self::FALLBACK_MODELS;
	}

	/**
	 * Fetch available Claude models from the Anthropic API.
	 *
	 * @param string $api_key Anthropic API key.
	 * @return array<string, string>|WP_Error Model ID => Display name, or WP_Error on failure.
	 */
	public static function fetch_models_from_api( $api_key ) {
		if ( ! class_exists( 'Anthropic\\Client' ) ) {
			return new \WP_Error( 'sdk_missing', __( 'Anthropic SDK not available.', 'wptomedium' ) );
		}

		try {
			$client = new \Anthropic\Client( apiKey: $api_key );
			$page   = $client->models->list( limit: 1000 );
			$items  = $page->getItems();

			$models = array();
			foreach ( $items as $item ) {
				if ( 0 === strpos( $item->id, 'claude-' ) ) {
					$models[ $item->id ] = $item->displayName;
				}
			}

			if ( empty( $models ) ) {
				return new \WP_Error( 'no_models', __( 'No Claude models found.', 'wptomedium' ) );
			}

			set_transient( self::TRANSIENT_MODELS, $models, self::MODELS_CACHE_TTL );

			return $models;
		} catch ( \Anthropic\Core\Exceptions\AuthenticationException $e ) {
			return new \WP_Error( 'auth_error', __( 'Invalid API key.', 'wptomedium' ) );
		} catch ( \Anthropic\Core\Exceptions\RateLimitException $e ) {
			return new \WP_Error( 'rate_limit', __( 'Rate limit exceeded. Key may be valid — try again later.', 'wptomedium' ) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'api_error', sprintf(
				/* translators: %s: error message from the API */
				__( 'Could not fetch models: %s', 'wptomedium' ),
				$e->getMessage()
			) );
		}
	}

	/**
	 * Render the translation section description.
	 */
	public static function render_translation_section() {
		echo '<p>' . esc_html__( 'Configure how the AI translates your posts.', 'wptomedium' ) . '</p>';
	}

	/**
	 * Render the system prompt textarea.
	 */
	public static function render_system_prompt_field() {
		$value = get_option( self::OPTION_SYSTEM_PROMPT, self::DEFAULT_SYSTEM_PROMPT );
		?>
		<textarea
			name="<?php echo esc_attr( self::OPTION_SYSTEM_PROMPT ); ?>"
			id="wptomedium-system-prompt"
			class="wptomedium-prompt-textarea"
			rows="10"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'This prompt tells the AI how to translate. The output format instructions are added automatically.', 'wptomedium' ); ?>
		</p>
		<button type="button" class="button wptomedium-restore-prompt">
			<?php esc_html_e( 'Restore Default', 'wptomedium' ); ?>
		</button>
		<?php
	}

	/**
	 * Render the max tokens input field.
	 */
	public static function render_max_tokens_field() {
		$value = get_option( self::OPTION_MAX_TOKENS, self::DEFAULT_MAX_TOKENS );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_MAX_TOKENS ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			min="1024"
			max="128000"
			step="1"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Maximum number of tokens in the AI response (1024–128000).', 'wptomedium' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the temperature input field.
	 */
	public static function render_temperature_field() {
		$value = get_option( self::OPTION_TEMPERATURE, self::DEFAULT_TEMPERATURE );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_TEMPERATURE ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			min="0"
			max="1"
			step="0.1"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Controls randomness of the translation (0 = deterministic, 1 = creative).', 'wptomedium' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize max tokens value, clamping to valid range.
	 *
	 * @param mixed $value Input value.
	 * @return int Clamped integer.
	 */
	public static function sanitize_max_tokens( $value ) {
		$value = absint( $value );
		return max( 1024, min( 128000, $value ) );
	}

	/**
	 * Sanitize temperature value, clamping to valid range.
	 *
	 * @param mixed $value Input value.
	 * @return float Clamped float.
	 */
	public static function sanitize_temperature( $value ) {
		$value = (float) $value;
		return max( 0.0, min( 1.0, round( $value, 1 ) ) );
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
	 * Get the configured system prompt (editable part only).
	 *
	 * @return string System prompt.
	 */
	public static function get_system_prompt() {
		return get_option( self::OPTION_SYSTEM_PROMPT, self::DEFAULT_SYSTEM_PROMPT );
	}

	/**
	 * Get the configured max tokens.
	 *
	 * @return int Max tokens.
	 */
	public static function get_max_tokens() {
		return (int) get_option( self::OPTION_MAX_TOKENS, self::DEFAULT_MAX_TOKENS );
	}

	/**
	 * Get the configured temperature.
	 *
	 * @return float Temperature.
	 */
	public static function get_temperature() {
		return (float) get_option( self::OPTION_TEMPERATURE, self::DEFAULT_TEMPERATURE );
	}

	/**
	 * AJAX handler to validate the Anthropic API key.
	 *
	 * Uses the models endpoint to validate the key and fetch available models in one call.
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

		$models = self::fetch_models_from_api( $api_key );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message() );
		}

		wp_send_json_success( array(
			'message' => __( 'API key is valid!', 'wptomedium' ),
			'models'  => $models,
		) );
	}

	/**
	 * AJAX handler to refresh the models list.
	 */
	public static function ajax_refresh_models() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wptomedium' ) );
		}

		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'No API key configured.', 'wptomedium' ) );
		}

		delete_transient( self::TRANSIENT_MODELS );

		$models = self::fetch_models_from_api( $api_key );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message() );
		}

		wp_send_json_success( array(
			'message' => __( 'Models refreshed!', 'wptomedium' ),
			'models'  => $models,
		) );
	}
}
