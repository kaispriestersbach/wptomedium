<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin-Optionen und Transients löschen.
$option_keys = array(
	'wptomedium_api_key',
	'wptomedium_model',
	'wptomedium_system_prompt',
	'wptomedium_max_tokens',
	'wptomedium_temperature',
	'wptomedium_activation_redirect',
);

foreach ( $option_keys as $option_key ) {
	delete_option( $option_key );
}

delete_transient( 'wptomedium_models_cache' );

// Post Meta von allen Posts entfernen.
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translation' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translated_title' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_status' ) );
