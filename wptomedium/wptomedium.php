<?php
/**
 * Plugin Name: WPtoMedium
 * Plugin URI:  https://github.com/kaispriestersbach/wptomedium
 * Description: Translate German WordPress posts to English via AI and copy to clipboard for Medium.
 * Version:     1.2.3
 * Author:      Kai
 * Text Domain: wptomedium
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPTOMEDIUM_VERSION', '1.2.3' );
define( 'WPTOMEDIUM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTOMEDIUM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTOMEDIUM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Textdomain laden.
add_action( 'plugins_loaded', 'wptomedium_load_textdomain' );

/**
 * Load plugin textdomain for translations.
 */
function wptomedium_load_textdomain() {
	load_plugin_textdomain( 'wptomedium', false, dirname( WPTOMEDIUM_PLUGIN_BASENAME ) . '/languages' );
}

// Vendor Autoloader (Anthropic SDK) — mit Guard für WP 7.0-Kompatibilität.
if ( ! class_exists( 'Anthropic\\Client' ) ) {
	$autoloader = WPTOMEDIUM_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}
}

// Plugin-Klassen laden.
require_once WPTOMEDIUM_PLUGIN_DIR . 'includes/class-wptomedium-settings.php';
require_once WPTOMEDIUM_PLUGIN_DIR . 'includes/class-wptomedium-translator.php';
require_once WPTOMEDIUM_PLUGIN_DIR . 'includes/class-wptomedium-workflow.php';

// Settings registrieren.
add_action( 'admin_init', array( 'WPtoMedium_Settings', 'register_settings' ) );

// Admin-Menü.
add_action( 'admin_menu', array( 'WPtoMedium_Workflow', 'register_menu' ) );

// Admin-Assets.
add_action( 'admin_enqueue_scripts', 'wptomedium_enqueue_admin_assets' );

/**
 * Enqueue admin CSS und JS nur auf Plugin-Seiten.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function wptomedium_enqueue_admin_assets( $hook_suffix ) {
	$plugin_pages = array(
		'toplevel_page_wptomedium-articles',
		'wptomedium_page_wptomedium-settings',
		'wptomedium_page_wptomedium-review',
	);

	if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
		return;
	}

	wp_enqueue_style(
		'wptomedium-admin',
		WPTOMEDIUM_PLUGIN_URL . 'admin/css/wptomedium-admin.css',
		array(),
		WPTOMEDIUM_VERSION
	);

	wp_enqueue_script(
		'wptomedium-admin',
		WPTOMEDIUM_PLUGIN_URL . 'admin/js/wptomedium-admin.js',
		array( 'jquery' ),
		WPTOMEDIUM_VERSION,
		true
	);

	wp_localize_script( 'wptomedium-admin', 'wptomediumData', array(
		'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'wptomedium_nonce' ),
		'translating'      => __( 'Translating...', 'wptomedium' ),
		'translate'        => __( 'Translate', 'wptomedium' ),
		'retranslate'      => __( 'Retranslate', 'wptomedium' ),
		'requestFailed'    => __( 'Translation request failed.', 'wptomedium' ),
		'htmlCopied'       => __( 'HTML copied!', 'wptomedium' ),
		'markdownCopied'   => __( 'Markdown copied!', 'wptomedium' ),
		'validating'       => __( 'Validating...', 'wptomedium' ),
		'validateKey'      => __( 'Validate Key', 'wptomedium' ),
		'refreshing'       => __( 'Refreshing...', 'wptomedium' ),
		'refreshModels'    => __( 'Refresh Models', 'wptomedium' ),
		'defaultPrompt'    => WPtoMedium_Settings::DEFAULT_SYSTEM_PROMPT,
		'restoreDefault'   => __( 'Restore Default', 'wptomedium' ),
	) );
}

// Redirect zur Settings-Seite nach Aktivierung.
register_activation_hook( __FILE__, function() {
	add_option( 'wptomedium_activation_redirect', true );
} );

add_action( 'admin_init', function() {
	if ( ! get_option( 'wptomedium_activation_redirect', false ) ) {
		return;
	}
	delete_option( 'wptomedium_activation_redirect' );

	// Kein Redirect bei Bulk-Aktivierung oder Netzwerk-Aktivierung.
	if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wptomedium-settings' ) );
	exit;
} );

// Settings Action Link in Plugin-Zeile.
add_filter( 'plugin_action_links_' . WPTOMEDIUM_PLUGIN_BASENAME, function( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wptomedium-settings' ) ) . '">'
		. esc_html__( 'Settings', 'wptomedium' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

// AJAX-Handler registrieren.
WPtoMedium_Workflow::register_ajax_handlers();
add_action( 'wp_ajax_wptomedium_validate_key', array( 'WPtoMedium_Settings', 'ajax_validate_key' ) );
add_action( 'wp_ajax_wptomedium_refresh_models', array( 'WPtoMedium_Settings', 'ajax_refresh_models' ) );
