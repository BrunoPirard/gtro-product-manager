<?php
/**
 * Plugin Name:       GTRO Product Manager
 * Plugin URI:        https://example.com/gtro-product-manager
 * Description:       Gestion des produits GTRO pour WooCommerce
 * Version:          1.0.0
 * Author:           Votre Nom
 * Author URI:       https://example.com
 * License:          GPL-2.0+
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:      gtro-product-manager
 * Domain Path:      /languages
 */
if (!defined('WPINC')) {
    die;
}

if (!defined('GTRO_VERSION')) {
    define('GTRO_VERSION', '1.0.0');
}

if (!defined('GTRO_PLUGIN_DIR')) {
    define('GTRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('GTRO_PLUGIN_URL')) {
    define('GTRO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

function activate_gtro() {
    require_once GTRO_PLUGIN_DIR . 'includes/class-gtro-activator.php';
    GTRO_Plugin\GTRO_Activator::activate();
}

function deactivate_gtro() {
    require_once GTRO_PLUGIN_DIR . 'includes/class-gtro-deactivator.php';
    GTRO_Plugin\GTRO_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_gtro');
register_deactivation_hook(__FILE__, 'deactivate_gtro');

require GTRO_PLUGIN_DIR . 'includes/class-gtro.php';


function run_gtro() {
    static $plugin = null;
    if ($plugin === null) {
        $plugin = new GTRO_Plugin\GTRO_Main();
    }
    return $plugin;
}
add_action('plugins_loaded', 'run_gtro');
