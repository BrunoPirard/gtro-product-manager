<?php
// Si WordPress n'appelle pas ce fichier, sortir
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Vérifier si on doit supprimer les données
$settings = get_option( 'gtro_options' );
if ( ! empty( $settings['delete_data'] ) ) {
	// Supprimer toutes les options
	delete_option( 'gtro_options' );
	delete_option( 'gtro_groupes_dates' );
	delete_option( 'gtro_deactivation_state' );

	// Supprimer les métadonnées
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'gtro_%'" );

	// Nettoyer le cache
	wp_cache_flush();
}
