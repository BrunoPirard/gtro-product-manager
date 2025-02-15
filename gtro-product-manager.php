<?php
/**
 * Plugin Name:          GTRO Product Manager
 * Description:          Gestion des produits GTRO pour WooCommerce
 * Version:              1.1.0
 * Author:               BulgaWeb
 * Author URI:           https://bulgaweb.com
 * License:              GPL-2.0+
 * License URI:          http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:          gtro-product-manager
 * Domain Path:          /languages
 * Requires PHP:         8.0
 * WC requires at least: 8.0.0
 * WC tested up to:      9.4.3
 * Requires Plugins:     woocommerce
 */
if (!defined('WPINC')) {
    die;
}

if (!defined('GTRO_VERSION')) {
    define('GTRO_VERSION', '1.1.0');
}

if (!defined('GTRO_PLUGIN_DIR')) {
    define('GTRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('GTRO_PLUGIN_URL')) {
    define('GTRO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});


/**
 * Called when the plugin is activated.
 *
 * Calls the static method GTRO_Plugin\GTRO_Activator::activate()
 * to perform any necessary setup operations.
 *
 * @since 1.0.0
 */
function activate_gtro() {
    require_once GTRO_PLUGIN_DIR . 'includes/class-gtro-activator.php';
    GTRO_Plugin\GTRO_Activator::activate();
}

/**
 * Called when the plugin is deactivated.
 *
 * This function requires the GTRO_Deactivator class and calls its
 * deactivate method to perform any necessary cleanup operations.
 *
 * @since 1.0.0
 */
function deactivate_gtro() {
    require_once GTRO_PLUGIN_DIR . 'includes/class-gtro-deactivator.php';
    GTRO_Plugin\GTRO_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_gtro');
register_deactivation_hook(__FILE__, 'deactivate_gtro');

require GTRO_PLUGIN_DIR . 'includes/class-gtro.php';

/**
 * Initializes and returns the GTRO_Main plugin instance.
 *
 * This function ensures that the GTRO_Main plugin is only instantiated once
 * and returns the instance. It is hooked to the 'plugins_loaded' action to 
 * ensure that the plugin is fully loaded and ready to use.
 *
 * @since 1.0.0
 * @return GTRO_Plugin\GTRO_Main The instance of the GTRO_Main plugin.
 */
function run_gtro() {
    static $plugin = null;
    if ($plugin === null) {
        $plugin = new GTRO_Plugin\GTRO_Main();
    }
    return $plugin;
}

add_action('plugins_loaded', 'run_gtro');
