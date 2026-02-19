<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin-Optionen und Transients löschen.
delete_option( 'wptomedium_model_preference' );
delete_transient( 'wptomedium_models_cache' );

// Post Meta von allen Posts entfernen.
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translation' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translated_title' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_status' ) );
