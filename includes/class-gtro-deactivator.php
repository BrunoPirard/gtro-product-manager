<?php
/**
 * Fichier de la classe Deactivator
 *
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */

namespace GTRO_Plugin;

class GTRO_Deactivator {

	/**
	 * Actions à effectuer lors de la désactivation du plugin
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Sauvegarder l'état actuel si nécessaire
		$current_state = array(
			'version'           => GTRO_VERSION,
			'last_deactivation' => current_time( 'mysql' ),
			'settings'          => get_option( 'gtro_options' ),
		);
		update_option( 'gtro_deactivation_state', $current_state );

		// Nettoyer les tâches programmées si elles existent
		wp_clear_scheduled_hook( 'gtro_daily_cleanup' );

		// Vider les caches transitoires
		delete_transient( 'gtro_cache_calendar' );
		delete_transient( 'gtro_cache_dates' );
	}

	/**
	 * Restaurer l'état après réactivation si nécessaire
	 *
	 * @since 1.0.0
	 */
	public static function restore_state() {
		$saved_state = get_option( 'gtro_deactivation_state' );
		if ( $saved_state ) {
			// Restaurer les paramètres si nécessaire
			// Code de restauration ici
		}
	}
}
