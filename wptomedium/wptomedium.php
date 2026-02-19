<?php
/**
 * Plugin Name: WPtoMedium
 * Plugin URI:  https://github.com/your-repo/wptomedium
 * Description: Translate German WordPress posts to English via AI and copy to clipboard for Medium.
 * Version:     1.0.0
 * Author:      Kai
 * Text Domain: wptomedium
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPTOMEDIUM_VERSION', '1.0.0' );
define( 'WPTOMEDIUM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTOMEDIUM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTOMEDIUM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Vendor Autoloader (WP AI Client SDK) — mit Guard für WP 7.0-Kompatibilität.
if ( ! class_exists( 'WordPress\\AI_Client\\AI_Client' ) ) {
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

// AI Client initialisieren.
add_action( 'init', function() {
	if ( class_exists( 'WordPress\\AI_Client\\AI_Client' ) ) {
		WordPress\AI_Client\AI_Client::init();
	}
} );

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
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'wptomedium_nonce' ),
	) );
}

// Settings Action Link in Plugin-Zeile.
add_filter( 'plugin_action_links_' . WPTOMEDIUM_PLUGIN_BASENAME, function( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wptomedium-settings' ) ) . '">'
		. esc_html__( 'Settings', 'wptomedium' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

// AJAX-Handler registrieren.
WPtoMedium_Workflow::register_ajax_handlers();
