<?php
/**
 * Fichier de la classe Activator
 *
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */

namespace GTRO_Plugin;

/**
 * Classe gérant l'activation du plugin
 *
 * Cette classe contient toute la logique exécutée lors de l'activation du plugin.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */
class GTRO_Activator {

	/**
	 * Méthode exécutée lors de l'activation du plugin
	 *
	 * Vérifie si le plugin Meta Box est installé et activé.
	 * Si ce n'est pas le cas, désactive le plugin et affiche un message d'erreur.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		if ( ! class_exists( 'RWMB_Loader' ) ) {
			deactivate_plugins( plugin_basename( GTRO_PLUGIN_DIR . 'gtro-product-manager.php' ) );
			wp_die( 'Ce plugin nécessite le plugin Meta Box pour fonctionner. Veuillez l\'installer et l\'activer.' );
		}
	}
}
