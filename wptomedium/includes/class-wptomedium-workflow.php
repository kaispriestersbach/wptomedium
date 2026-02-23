<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table laden falls nötig.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Handles workflow, admin pages, and AJAX for WPtoMedium.
 */
class WPtoMedium_Workflow {

	/**
	 * Register admin menu pages.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'WPtoMedium', 'wptomedium' ),
			__( 'WPtoMedium', 'wptomedium' ),
			'manage_options',
			'wptomedium-articles',
			array( __CLASS__, 'render_articles_page' ),
			'dashicons-translation',
			30
		);

		add_submenu_page(
			'wptomedium-articles',
			__( 'Articles', 'wptomedium' ),
			__( 'Articles', 'wptomedium' ),
			'manage_options',
			'wptomedium-articles',
			array( __CLASS__, 'render_articles_page' )
		);

		add_submenu_page(
			'wptomedium-articles',
			__( 'Settings', 'wptomedium' ),
			__( 'Settings', 'wptomedium' ),
			'manage_options',
			'wptomedium-settings',
			array( 'WPtoMedium_Settings', 'render_page' )
		);

		// Review-Seite (verstecktes Submenu — kein Navigations-Eintrag).
		add_submenu_page(
			'',
			__( 'Review Translation', 'wptomedium' ),
			'',
			'manage_options',
			'wptomedium-review',
			array( __CLASS__, 'render_review_page' )
		);
	}

	/**
	 * Register AJAX handlers.
	 */
	public static function register_ajax_handlers() {
		add_action( 'wp_ajax_wptomedium_translate', array( __CLASS__, 'ajax_translate' ) );
		add_action( 'wp_ajax_wptomedium_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_wptomedium_copy_markdown', array( __CLASS__, 'ajax_copy_markdown' ) );
		add_action( 'wp_ajax_wptomedium_mark_copied', array( __CLASS__, 'ajax_mark_copied' ) );
	}

	/**
	 * Render the articles list page.
	 */
	public static function render_articles_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$table = new WPtoMedium_Articles_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPtoMedium — Articles', 'wptomedium' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="wptomedium-articles" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the review page with side-by-side comparison.
	 */
	public static function render_review_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			wp_die( esc_html__( 'Post not found.', 'wptomedium' ) );
		}

		$translation = get_post_meta( $post_id, '_wptomedium_translation', true );
		$title       = get_post_meta( $post_id, '_wptomedium_translated_title', true );

		// Original-Content rendern.
		$original_content = apply_filters( 'the_content', $post->post_content );

		// TinyMCE-Toolbar auf Medium-kompatible Tags einschränken.
		$editor_content_style = implode(
			'',
			array(
				'html,body{margin:0;padding:0;}',
				'body.wptomedium-editor-body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;font-size:16px;line-height:1.65;color:#1d2327;overflow-y:hidden;padding:16px 18px;}',
				'body.wptomedium-editor-body > :first-child{margin-top:0;}',
				'body.wptomedium-editor-body > :last-child{margin-bottom:0;}',
				'body.wptomedium-editor-body p,body.wptomedium-editor-body ul,body.wptomedium-editor-body ol,body.wptomedium-editor-body blockquote,body.wptomedium-editor-body figure,body.wptomedium-editor-body pre{margin:0 0 1.2em;}',
				'body.wptomedium-editor-body ul,body.wptomedium-editor-body ol{padding-left:1.5em;}',
				'body.wptomedium-editor-body li{margin:0 0 .45em;}',
				'body.wptomedium-editor-body h1,body.wptomedium-editor-body h2,body.wptomedium-editor-body h3{font-family:inherit;font-size:1.85em;line-height:1.3;font-weight:700;margin:1.35em 0 .55em;}',
				'body.wptomedium-editor-body img{display:block;max-width:100%;height:auto;margin:0 auto;}',
				'body.wptomedium-editor-body figcaption{font-size:.9em;line-height:1.45;color:#50575e;margin-top:.4em;}',
			)
		);

		$editor_settings = array(
			'textarea_name' => 'wptomedium_translation',
			'textarea_rows' => 20,
			'media_buttons' => false,
			'teeny'         => false,
			'tinymce'       => array(
				'toolbar1'      => 'bold,italic,link,blockquote,formatselect,bullist,numlist,code,hr,undo,redo',
				'toolbar2'      => '',
				'block_formats' => 'Paragraph=p;Heading 2=h2',
				'content_css'   => false,
				'body_class'    => 'wptomedium-editor-body',
				'wp_autoresize_on' => true,
				'content_style' => $editor_content_style,
			),
			'quicktags'     => array(
				'buttons' => 'strong,em,link,block,ul,ol,li,code,close',
			),
		);
		?>
		<div class="wrap wptomedium-review-wrap">
			<h1><?php esc_html_e( 'Review Translation', 'wptomedium' ); ?></h1>

			<?php if ( empty( $translation ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No translation available. Please translate the post first.', 'wptomedium' ); ?></p>
				</div>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wptomedium-articles' ) ); ?>">&larr; <?php esc_html_e( 'Back to Articles', 'wptomedium' ); ?></a></p>
				<?php return; ?>
			<?php endif; ?>

			<div class="wptomedium-title-row">
				<div class="wptomedium-title-original">
					<label><?php esc_html_e( 'Original Title', 'wptomedium' ); ?></label>
					<h2><?php echo esc_html( $post->post_title ); ?></h2>
				</div>
				<div class="wptomedium-title-translation">
					<label for="wptomedium-translated-title"><?php esc_html_e( 'Translated Title', 'wptomedium' ); ?></label>
					<input type="text"
						id="wptomedium-translated-title"
						class="widefat"
						value="<?php echo esc_attr( $title ); ?>"
						data-post-id="<?php echo esc_attr( $post_id ); ?>" />
				</div>
			</div>

			<div class="wptomedium-review-container">
				<div class="wptomedium-review-panel wptomedium-panel-original">
					<h3><?php esc_html_e( 'Original (German)', 'wptomedium' ); ?></h3>
					<div class="wptomedium-content-preview">
						<?php echo wp_kses_post( $original_content ); ?>
					</div>
				</div>

				<div class="wptomedium-review-panel wptomedium-panel-translation">
					<h3><?php esc_html_e( 'Translation (English)', 'wptomedium' ); ?></h3>
					<?php wp_editor( $translation, 'wptomedium_translation_editor', $editor_settings ); ?>
				</div>
			</div>

			<div class="wptomedium-actions">
				<button type="button" class="button button-primary wptomedium-save" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Save Translation', 'wptomedium' ); ?>
				</button>
				<button type="button" class="button wptomedium-retranslate" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Retranslate', 'wptomedium' ); ?>
				</button>
				<button type="button" class="button wptomedium-copy-html" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Copy as HTML', 'wptomedium' ); ?>
				</button>
				<button type="button" class="button wptomedium-copy-markdown" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Copy as Markdown', 'wptomedium' ); ?>
				</button>
			</div>

			<div class="wptomedium-toast wptomedium-is-hidden">
				<?php esc_html_e( 'Copied!', 'wptomedium' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for translating a post.
	 */
	public static function ajax_translate() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wptomedium' ) );
		}

		$post_id = self::get_valid_post_id_from_request();
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( __( 'Invalid post ID.', 'wptomedium' ) );
		}

		// Status auf pending setzen.
		update_post_meta( $post_id, '_wptomedium_status', 'pending' );

		$translator = new WPtoMedium_Translator();
		$result     = $translator->translate( $post_id );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'    => $result['message'],
				'review_url' => admin_url( 'admin.php?page=wptomedium-review&post_id=' . $post_id ),
			) );
		} else {
			// Status zurücksetzen bei Fehler.
			delete_post_meta( $post_id, '_wptomedium_status' );
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX handler for saving an edited translation.
	 */
	public static function ajax_save() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wptomedium' ) );
		}

		$post_id = self::get_valid_post_id_from_request();
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( __( 'Invalid post ID.', 'wptomedium' ) );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] )
			? WPtoMedium_Translator::sanitize_medium_html( wp_unslash( $_POST['content'] ) )
			: '';

		update_post_meta( $post_id, '_wptomedium_translated_title', $title );
		update_post_meta( $post_id, '_wptomedium_translation', $content );

		wp_send_json_success( __( 'Translation saved.', 'wptomedium' ) );
	}

	/**
	 * AJAX handler for converting translation to Markdown.
	 */
	public static function ajax_copy_markdown() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wptomedium' ) );
		}

		$post_id = self::get_valid_post_id_from_request();
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( __( 'Invalid post ID.', 'wptomedium' ) );
		}

		$title   = get_post_meta( $post_id, '_wptomedium_translated_title', true );
		$content = get_post_meta( $post_id, '_wptomedium_translation', true );

		$translator = new WPtoMedium_Translator();
		$markdown   = '# ' . $title . "\n\n" . $translator->to_markdown( $content );

		wp_send_json_success( array(
			'markdown' => $markdown,
		) );
	}

	/**
	 * AJAX handler to mark translation as copied.
	 */
	public static function ajax_mark_copied() {
		check_ajax_referer( 'wptomedium_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wptomedium' ) );
		}

		$post_id = self::get_valid_post_id_from_request();
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( __( 'Invalid post ID.', 'wptomedium' ) );
		}

		update_post_meta( $post_id, '_wptomedium_status', 'copied' );

		wp_send_json_success();
	}

	/**
	 * Get and validate post ID from AJAX request payload.
	 *
	 * @return int|WP_Error Valid post ID or WP_Error.
	 */
	private static function get_valid_post_id_from_request() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( 0 === $post_id ) {
			return new WP_Error( 'invalid_post_id' );
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || 'post' !== $post->post_type ) {
			return new WP_Error( 'invalid_post_id' );
		}

		return $post_id;
	}
}

/**
 * Custom list table for WPtoMedium articles.
 */
class WPtoMedium_Articles_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'article',
			'plural'   => 'articles',
			'ajax'     => false,
		) );
	}

	/**
	 * Get table columns.
	 *
	 * @return array Column definitions.
	 */
	public function get_columns() {
		return array(
			'title'  => __( 'Title', 'wptomedium' ),
			'date'   => __( 'Date', 'wptomedium' ),
			'status' => __( 'Translation Status', 'wptomedium' ),
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );

		$this->items = $query->posts;
		$this->set_pagination_args( array(
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Render the title column with row actions.
	 *
	 * @param WP_Post $item The current post.
	 * @return string Column HTML.
	 */
	public function column_title( $item ) {
		$review_url = admin_url( 'admin.php?page=wptomedium-review&post_id=' . $item->ID );

		$actions = array(
			'translate' => sprintf(
				'<a href="#" class="wptomedium-translate" data-post-id="%d">%s</a>',
				$item->ID,
				esc_html__( 'Translate', 'wptomedium' )
			),
		);

		$status = get_post_meta( $item->ID, '_wptomedium_status', true );
		if ( in_array( $status, array( 'translated', 'copied' ), true ) ) {
			$actions['review'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $review_url ),
				esc_html__( 'Review', 'wptomedium' )
			);
		}

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $item->post_title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the date column.
	 *
	 * @param WP_Post $item The current post.
	 * @return string Formatted date.
	 */
	public function column_date( $item ) {
		return esc_html( get_the_date( '', $item ) );
	}

	/**
	 * Render the status column.
	 *
	 * @param WP_Post $item The current post.
	 * @return string Status label.
	 */
	public function column_status( $item ) {
		$status = get_post_meta( $item->ID, '_wptomedium_status', true );
		$labels = array(
			''           => '<span class="wptomedium-status-none">' . esc_html__( 'Not translated', 'wptomedium' ) . '</span>',
			'pending'    => '<span class="wptomedium-status-pending">' . esc_html__( 'Pending', 'wptomedium' ) . '</span>',
			'translated' => '<span class="wptomedium-status-translated">' . esc_html__( 'Translated', 'wptomedium' ) . '</span>',
			'copied'     => '<span class="wptomedium-status-copied">' . esc_html__( 'Copied', 'wptomedium' ) . '</span>',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[''];
	}
}
