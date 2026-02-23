<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Anthropic\Client;

/**
 * Handles translation and content conversion for WPtoMedium.
 */
class WPtoMedium_Translator {

	/**
	 * Allowed HTML tags for Medium content.
	 *
	 * @var array
	 */
	private static $medium_tags = array(
		'h1'         => array(),
		'h2'         => array(),
		'p'          => array(),
		'a'          => array( 'href' => array() ),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'blockquote' => array(),
		'figure'     => array(),
		'figcaption' => array(),
		'img'        => array(
			'src' => array(),
			'alt' => array(),
		),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'pre'        => array(),
		'code'       => array(),
		'hr'         => array(),
		'br'         => array(),
	);

	/**
	 * Get the allowed HTML tags for Medium content.
	 *
	 * @return array Allowed HTML tags.
	 */
	public static function get_medium_tags() {
		return self::$medium_tags;
	}

	/**
	 * Sanitize raw HTML to the Medium-compatible safe subset.
	 *
	 * @param string $html Raw HTML content.
	 * @return string Sanitized HTML.
	 */
	public static function sanitize_medium_html( $html ) {
		$html = (string) $html;
		$html = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html );

		return wp_kses( $html, self::get_medium_tags() );
	}

	/**
	 * Translate a post from German to English.
	 *
	 * @param int $post_id The post ID to translate.
	 * @return array{success: bool, message: string} Result with success status and message.
	 */
	public function translate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'wptomedium' ),
			);
		}

		// Content vorbereiten.
		$content = $this->prepare_content( $post_id );
		if ( empty( $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post has no content.', 'wptomedium' ),
			);
		}

		// API Key prüfen.
		$api_key = WPtoMedium_Settings::get_api_key();
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Anthropic API key not configured. Please add your API key in Settings.', 'wptomedium' ),
			);
		}

		// Anthropic SDK prüfen.
		if ( ! class_exists( 'Anthropic\\Client' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Anthropic SDK not available.', 'wptomedium' ),
			);
		}

		try {
			$client = new Client( apiKey: $api_key );
			$model  = WPtoMedium_Settings::resolve_model_for_translation( $api_key );
			$prompt = $this->build_prompt( $post->post_title, $content );

			try {
				$message = $this->create_translation_message(
					$client,
					$model,
					$prompt,
					WPtoMedium_Settings::get_max_tokens(),
					WPtoMedium_Settings::get_temperature()
				);
			} catch ( \Anthropic\Core\Exceptions\BadRequestException $e ) {
				// Retry once with known-safe defaults in case saved settings are incompatible.
				$this->log_exception( 'Anthropic bad request, retrying with safe defaults', $e );
				$message = $this->create_translation_message(
					$client,
					WPtoMedium_Settings::resolve_model_for_translation( $api_key ),
					$prompt,
					WPtoMedium_Settings::DEFAULT_MAX_TOKENS,
					WPtoMedium_Settings::DEFAULT_TEMPERATURE
				);
			}

			$response_text = '';
			if ( isset( $message->content[0] ) && isset( $message->content[0]->text ) ) {
				$response_text = (string) $message->content[0]->text;
			}
		} catch ( \Anthropic\Core\Exceptions\APIException $e ) {
			$this->log_exception( 'Anthropic API error', $e );
			return array(
				'success' => false,
				'message' => $this->get_api_error_message( $e ),
			);
		} catch ( \Throwable $e ) {
			$this->log_exception( 'Translation request failed', $e );
			return array(
				'success' => false,
				'message' => __( 'Translation failed. Please try again.', 'wptomedium' ),
			);
		}

		if ( empty( $response_text ) ) {
			return array(
				'success' => false,
				'message' => __( 'AI returned an empty response.', 'wptomedium' ),
			);
		}

		// Response parsen und sanitizen.
		$parsed             = $this->parse_response( $response_text );
		if ( empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'AI returned an empty response.', 'wptomedium' ),
			);
		}

		$translated_content = $this->sanitize_for_medium( $parsed['content'] );
		if ( empty( trim( wp_strip_all_tags( $translated_content ) ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'AI returned an empty response.', 'wptomedium' ),
			);
		}

		$translated_title   = sanitize_text_field( $parsed['title'] );

		// Post Meta speichern.
		update_post_meta( $post_id, '_wptomedium_translation', $translated_content );
		update_post_meta( $post_id, '_wptomedium_translated_title', $translated_title );
		update_post_meta( $post_id, '_wptomedium_status', 'translated' );

		return array(
			'success' => true,
			'message' => __( 'Translation complete.', 'wptomedium' ),
		);
	}

	/**
	 * Convert Gutenberg HTML to Medium-compatible HTML.
	 *
	 * @param int $post_id The post ID.
	 * @return string Medium-compatible HTML content.
	 */
	public function prepare_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Content für Übersetzung rendern ohne the_content-Filterkette.
		$content = $this->render_content_for_translation( $post );

		// Block-Kommentare entfernen.
		$content = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );

		// h3-h6 → h2.
		$content = preg_replace( '/<h[3-6]([^>]*)>/', '<h2>', $content );
		$content = preg_replace( '/<\/h[3-6]>/', '</h2>', $content );

		// Tabellen → Text-Absätze.
		$content = $this->convert_tables( $content );

		// Galerien → einzelne figure-Elemente.
		$content = $this->convert_galleries( $content );

		// CSS-Klassen und style-Attribute entfernen.
		$content = preg_replace( '/\s+class="[^"]*"/', '', $content );
		$content = preg_replace( '/\s+style="[^"]*"/', '', $content );

		// Finaler Sanitizer.
		$content = wp_kses( $content, self::get_medium_tags() );

		return $content;
	}

	/**
	 * Get the system prompt for translation.
	 *
	 * @return string The system prompt.
	 */
	private function get_system_prompt() {
		$editable = WPtoMedium_Settings::get_system_prompt();

		$format = "\n\n"
			. 'Return ONLY the translated content in this exact format:' . "\n\n"
			. 'TITLE: [translated title]' . "\n\n"
			. 'CONTENT:' . "\n"
			. '[translated HTML content]';

		return $editable . $format;
	}

	/**
	 * Send translation request to Anthropic Messages API.
	 *
	 * @param Client $client      Anthropic client instance.
	 * @param string $model       Model ID.
	 * @param string $prompt      User prompt text.
	 * @param int    $max_tokens  Max output tokens.
	 * @param float  $temperature Temperature value.
	 * @return mixed Message response object from SDK.
	 */
	private function create_translation_message( Client $client, $model, $prompt, $max_tokens, $temperature ) {
		return $client->messages->create(
			model: $model,
			maxTokens: $max_tokens,
			temperature: $temperature,
			system: $this->get_system_prompt(),
			messages: array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);
	}

	/**
	 * Build the user prompt with the content to translate.
	 *
	 * @param string $title   The post title.
	 * @param string $content The HTML content.
	 * @return string The user prompt.
	 */
	private function build_prompt( $title, $content ) {
		return sprintf(
			"Original Title: %s\n\nOriginal Content:\n%s",
			$title,
			$content
		);
	}

	/**
	 * Parse the AI response to extract title and content.
	 *
	 * @param string $response The AI response text.
	 * @return array{title: string, content: string} Parsed title and content.
	 */
	private function parse_response( $response ) {
		$title   = '';
		$content = '';

		if ( preg_match( '/^\s*CONTENT:\s*/im', $response, $content_match, PREG_OFFSET_CAPTURE ) ) {
			$content_marker = $content_match[0];
			$content_offset = $content_marker[1] + strlen( $content_marker[0] );
			$content        = trim( substr( $response, $content_offset ) );

			$before_content = substr( $response, 0, $content_marker[1] );
			if ( preg_match( '/TITLE:\s*(.+)$/im', $before_content, $title_match ) ) {
				$title = trim( $title_match[1] );
			}
		}

		return array(
			'title'   => $title,
			'content' => $content,
		);
	}

	/**
	 * Sanitize HTML for Medium compatibility.
	 *
	 * @param string $html Raw HTML content.
	 * @return string Sanitized HTML.
	 */
	private function sanitize_for_medium( $html ) {
		return self::sanitize_medium_html( $html );
	}

	/**
	 * Render post content for translation via standard content pipeline.
	 *
	 * This includes shortcode expansion and dynamic block rendering to ensure
	 * the translated output reflects the final front-end content.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Rendered content.
	 */
	private function render_content_for_translation( WP_Post $post ) {
		$previous_post = ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post )
			? $GLOBALS['post']
			: null;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$content = apply_filters( 'the_content', (string) $post->post_content );

		if ( $previous_post instanceof WP_Post ) {
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );
		} else {
			unset( $GLOBALS['post'] );
			wp_reset_postdata();
		}

		return (string) $content;
	}

	/**
	 * Convert HTML tables to text paragraphs.
	 *
	 * @param string $html HTML content with tables.
	 * @return string HTML content with tables replaced.
	 */
	private function convert_tables( $html ) {
		return preg_replace_callback(
			'/<table[^>]*>(.*?)<\/table>/s',
			function( $matches ) {
				$text = strip_tags( $matches[1], '<strong><em><a><br>' );
				$text = preg_replace( '/\s+/', ' ', $text );
				return '<p>' . trim( $text ) . '</p>';
			},
			$html
		);
	}

	/**
	 * Convert gallery blocks to individual figure elements.
	 *
	 * @param string $html HTML content with galleries.
	 * @return string HTML content with galleries replaced.
	 */
	private function convert_galleries( $html ) {
		if ( false === strpos( $html, 'wp-block-gallery' ) ) {
			return $html;
		}

		$dom = new \DOMDocument();

		$previous_errors = libxml_use_internal_errors( true );
		$loaded          = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( false === $loaded ) {
			return $html;
		}

		$xpath     = new \DOMXPath( $dom );
		$galleries = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-gallery ")]' );
		if ( ! ( $galleries instanceof \DOMNodeList ) || 0 === $galleries->length ) {
			return $html;
		}

		$gallery_nodes = array();
		foreach ( $galleries as $gallery_node ) {
			$gallery_nodes[] = $gallery_node;
		}

		foreach ( $gallery_nodes as $gallery_node ) {
			if ( ! $gallery_node->parentNode ) {
				continue;
			}

			$images = $gallery_node->getElementsByTagName( 'img' );
			if ( 0 === $images->length ) {
				continue;
			}

			$replacement = $dom->createDocumentFragment();
			foreach ( $images as $image ) {
				$figure = $dom->createElement( 'figure' );
				$img    = $dom->createElement( 'img' );

				if ( $image->hasAttribute( 'src' ) ) {
					$img->setAttribute( 'src', $image->getAttribute( 'src' ) );
				}
				if ( $image->hasAttribute( 'alt' ) ) {
					$img->setAttribute( 'alt', $image->getAttribute( 'alt' ) );
				}

				$figure->appendChild( $img );
				$replacement->appendChild( $figure );
				$replacement->appendChild( $dom->createTextNode( "\n" ) );
			}

			$gallery_node->parentNode->replaceChild( $replacement, $gallery_node );
		}

		$result = $dom->saveHTML();
		if ( false === $result ) {
			return $html;
		}

		return $result;
	}

	/**
	 * Log provider exceptions in debug mode without exposing details to users.
	 *
	 * @param string     $context   Log context.
	 * @param \Throwable $exception Exception object.
	 * @return void
	 */
	private function log_exception( $context, \Throwable $exception ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[WPtoMedium] %s: %s', $context, $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Map Anthropic API exceptions to user-safe, localized messages.
	 *
	 * @param \Throwable $exception Exception object.
	 * @return string User-safe localized message.
	 */
	private function get_api_error_message( \Throwable $exception ) {
		if ( $exception instanceof \Anthropic\Core\Exceptions\BadRequestException ) {
			$message = strtolower( $exception->getMessage() );
			if (
				false !== strpos( $message, 'credit balance is too low' )
				|| false !== strpos( $message, 'plans & billing' )
				|| false !== strpos( $message, 'purchase credits' )
				|| false !== strpos( $message, 'billing' )
			) {
				return __( 'Insufficient Anthropic credits. Please top up your plan in Plans & Billing and try again.', 'wptomedium' );
			}

			return __( 'Invalid request. Please review your settings and try again.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\AuthenticationException ) {
			return __( 'Invalid API key.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\PermissionDeniedException ) {
			return __( 'Permission denied for this API request.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\NotFoundException ) {
			return __( 'Requested API resource was not found.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\ConflictException ) {
			return __( 'Request conflict. Please retry in a moment.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\UnprocessableEntityException ) {
			return __( 'Request could not be processed. Please adjust input and try again.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\RateLimitException ) {
			return __( 'Rate limit exceeded. Please try again later.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\InternalServerException ) {
			return __( 'API service is temporarily unavailable. Please try again later.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\APITimeoutException ) {
			return __( 'API request timed out. Please try again.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\APIConnectionException ) {
			return __( 'Could not connect to API. Please check your network and try again.', 'wptomedium' );
		}

		if ( $exception instanceof \Anthropic\Core\Exceptions\APIStatusException ) {
			return __( 'API request failed. Please try again later.', 'wptomedium' );
		}

		return __( 'Translation failed. Please try again.', 'wptomedium' );
	}

	/**
	 * Convert HTML to Markdown.
	 *
	 * @param string $html HTML content.
	 * @return string Markdown content.
	 */
	public function to_markdown( $html ) {
		$md = $html;

		// Block-Elemente zuerst.
		$md = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/s', "# $1\n\n", $md );
		$md = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/s', "## $1\n\n", $md );
		$md = preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/s', "> $1\n\n", $md );
		$md = preg_replace( '/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/s', "```\n$1\n```\n\n", $md );
		$md = preg_replace( '/<hr[^>]*\/?>/s', "---\n\n", $md );

		// Listen.
		$md = preg_replace( '/<ul[^>]*>(.*?)<\/ul>/s', "$1\n", $md );
		$md = preg_replace( '/<ol[^>]*>(.*?)<\/ol>/s', "$1\n", $md );
		$md = preg_replace( '/<li[^>]*>(.*?)<\/li>/s', "- $1\n", $md );

		// Bilder und Figures.
		$md = preg_replace(
			'/<figure[^>]*>.*?<img[^>]+src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>.*?(?:<figcaption>(.*?)<\/figcaption>)?.*?<\/figure>/s',
			"![$2]($1)\n\n",
			$md
		);
		$md = preg_replace( '/<img[^>]+src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/', '![$2]($1)', $md );

		// Inline-Elemente.
		$md = preg_replace( '/<a[^>]+href="([^"]*)"[^>]*>(.*?)<\/a>/s', '[$2]($1)', $md );
		$md = preg_replace( '/<(strong|b)>(.*?)<\/\1>/s', '**$2**', $md );
		$md = preg_replace( '/<(em|i)>(.*?)<\/\1>/s', '*$2*', $md );
		$md = preg_replace( '/<code>(.*?)<\/code>/s', '`$1`', $md );

		// Absätze.
		$md = preg_replace( '/<p[^>]*>(.*?)<\/p>/s', "$1\n\n", $md );
		$md = preg_replace( '/<br[^>]*\/?>/s', "\n", $md );

		// Übrige Tags entfernen.
		$md = strip_tags( $md );

		// Mehrfache Leerzeilen normalisieren.
		$md = preg_replace( '/\n{3,}/', "\n\n", $md );

		return trim( $md );
	}
}
