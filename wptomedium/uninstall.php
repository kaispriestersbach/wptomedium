<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin-Option löschen.
delete_option( 'wptomedium_model_preference' );

// Post Meta von allen Posts entfernen.
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translation' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_translated_title' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wptomedium_status' ) );
