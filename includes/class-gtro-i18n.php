<?php
/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */

 namespace GTRO_Plugin;

if (!class_exists('GTRO_Plugin\GTRO_i18n')) {
    class GTRO_i18n {
        
        /**
         * Load the plugin text domain for translation.
         *
         * @since 1.0.0
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain(
                'gtro-product-manager',
                false,
                dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
            );
        }
    }
}
