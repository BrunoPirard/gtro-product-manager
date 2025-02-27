<?php
/**
 * Fichier de la classe Deactivator
 *
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */

namespace GTRO_Plugin;

/**
 * Class Deactivator
 *
 * Handles deactivation for the plugin.
 *
 * @package GTRO_Product_Manager
 * @since 1.0.0
 */
class GTRO_Deactivator {

	/**
	 * Actions à effectuer lors de la désactivation du plugin
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Sauvegarder l'état actuel si nécessaire.
		$current_state = array(
			'version'           => GTRO_VERSION,
			'last_deactivation' => current_time( 'mysql' ),
			'settings'          => get_option( 'gtro_options' ),
		);
		update_option( 'gtro_deactivation_state', $current_state );

		// Nettoyer les tâches programmées si elles existent.
		wp_clear_scheduled_hook( 'gtro_daily_cleanup' );

		// Vider les caches transitoires.
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

		// Si un état sauvegardé existe, restaurer les paramètres.
		if ( ! empty( $saved_state ) && is_array( $saved_state ) ) {
			// Restaurer les paramètres sauvegardés.
			if ( isset( $saved_state['settings'] ) ) {
				update_option( 'gtro_options', $saved_state['settings'] );
			}

			// Nettoyer l'état sauvegardé après restauration.
			delete_option( 'gtro_deactivation_state' );

			// Réinitialiser les caches si nécessaire.
			delete_transient( 'gtro_cache_calendar' );
			delete_transient( 'gtro_cache_dates' );
		}
	}
}
