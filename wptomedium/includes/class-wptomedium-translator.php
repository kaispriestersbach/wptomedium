<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		// AI-Prompt bauen.
		$prompt_text = $this->build_prompt( $post->post_title, $content );

		// AI Client prüfen.
		if ( ! class_exists( 'WordPress\\AI_Client\\AI_Client' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WP AI Client SDK not available.', 'wptomedium' ),
			);
		}

		$prompt = \WordPress\AI_Client\AI_Client::prompt( $prompt_text )
			->using_temperature( 0.3 );

		if ( ! $prompt->is_supported_for_text_generation() ) {
			return array(
				'success' => false,
				'message' => __( 'No AI provider configured. Please set up AI Credentials in Settings.', 'wptomedium' ),
			);
		}

		$response = $prompt->generate_text();
		if ( empty( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'AI returned an empty response.', 'wptomedium' ),
			);
		}

		// Response parsen und sanitizen.
		$parsed             = $this->parse_response( $response );
		$translated_content = $this->sanitize_for_medium( $parsed['content'] );
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

		// Gutenberg rendern.
		$content = apply_filters( 'the_content', $post->post_content );

		// Block-Kommentare entfernen.
		$content = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );

		// h3-h6 → h2.
		$content = preg_replace( '/<h[3-6]([^>]*)>/', '<h2>', $content );
		$content = preg_replace( '/<\/h[3-6]>/', '</h2>', $content );

		// CSS-Klassen und style-Attribute entfernen.
		$content = preg_replace( '/\s+class="[^"]*"/', '', $content );
		$content = preg_replace( '/\s+style="[^"]*"/', '', $content );

		// Tabellen → Text-Absätze.
		$content = $this->convert_tables( $content );

		// Galerien → einzelne figure-Elemente.
		$content = $this->convert_galleries( $content );

		// Finaler Sanitizer.
		$content = wp_kses( $content, self::$medium_tags );

		return $content;
	}

	/**
	 * Build the AI translation prompt.
	 *
	 * @param string $title   The post title.
	 * @param string $content The HTML content.
	 * @return string The translation prompt.
	 */
	private function build_prompt( $title, $content ) {
		return sprintf(
			'Translate the following German blog post to English. Keep all HTML tags exactly as they are. '
			. 'Do not add or remove any HTML tags. Translate only the text content within the tags. '
			. 'Return ONLY the translated content in this exact format:'
			. "\n\nTITLE: [translated title]\n\nCONTENT:\n[translated HTML content]"
			. "\n\n---\n\nOriginal Title: %s\n\nOriginal Content:\n%s",
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

		if ( preg_match( '/TITLE:\s*(.+?)(?:\n|CONTENT:)/s', $response, $title_match ) ) {
			$title = trim( $title_match[1] );
		}

		if ( preg_match( '/CONTENT:\s*(.+)/s', $response, $content_match ) ) {
			$content = trim( $content_match[1] );
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
		return wp_kses( $html, self::$medium_tags );
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
		return preg_replace_callback(
			'/<figure[^>]*class="[^"]*wp-block-gallery[^"]*"[^>]*>(.*?)<\/figure>/s',
			function( $matches ) {
				preg_match_all( '/<img[^>]+>/s', $matches[1], $images );
				$result = '';
				foreach ( $images[0] as $img ) {
					$result .= '<figure>' . $img . '</figure>' . "\n";
				}
				return $result;
			},
			$html
		);
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
