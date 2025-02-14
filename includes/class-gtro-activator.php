<?php
namespace GTRO_Plugin;

class GTRO_Activator {
    public static function activate() {
        if (!class_exists('RWMB_Loader')) {
            deactivate_plugins(plugin_basename(GTRO_PLUGIN_DIR . 'gtro-product-manager.php'));
            wp_die('Ce plugin nécessite le plugin Meta Box pour fonctionner. Veuillez l\'installer et l\'activer.');
        }
    }
}
