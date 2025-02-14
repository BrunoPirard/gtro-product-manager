<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/admin
 */
namespace GTRO_Plugin;

if (!class_exists('GTRO_Plugin\GTRO_Admin')) {
    class GTRO_Admin {

        /**
         * The ID of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $plugin_name    The ID of this plugin.
         */
        private $plugin_name;

        /**
         * The version of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $version    The current version of this plugin.
         */
        private $version;

        /**
         * Initialize the class and set its properties.
         *
         * @since    1.0.0
         * @param    string    $plugin_name       The name of this plugin.
         * @param    string    $version    The version of this plugin.
         */
        public function __construct($plugin_name, $version) {
            $this->plugin_name = $plugin_name;
            $this->version = $version;

            // Add menu
            add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
            
            // Add Settings link to the plugin
            add_filter('plugin_action_links_' . plugin_basename(GTRO_PLUGIN_DIR . $this->plugin_name . '.php'),
                array($this, 'add_action_links')
            );

            // Check if WooCommerce is active
            add_action('admin_init', array($this, 'check_woocommerce_dependency'));
        }

        /**
         * Register the stylesheets for the admin area.
         *
         * @since    1.0.0
         */
        public function enqueue_styles() {
            wp_enqueue_style(
                $this->plugin_name,
                GTRO_PLUGIN_URL . 'admin/css/gtro-admin.css',
                array(),
                $this->version,
                'all'
            );
        }

        /**
         * Register the JavaScript for the admin area.
         *
         * @since    1.0.0
         */
        public function enqueue_scripts() {
            wp_enqueue_script(
                'gtro-admin-script',
                GTRO_PLUGIN_URL . 'admin/js/gtro-admin.js',
                array('jquery'),
                $this->version,
                true
            );
        }

        /**
         * Add settings action link to the plugins page.
         *
         * @since    1.0.0
         */
        public function add_action_links($links) {
            $settings_link = array(
                '<a href="' . admin_url('admin.php?page=' . $this->plugin_name) . '">' . 
                __('Settings', 'gtro-product-manager') . '</a>',
            );
            return array_merge($settings_link, $links);
        }

        /**
         * Check if WooCommerce is active
         *
         * @since    1.0.0
         */
        public function check_woocommerce_dependency() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="error">
                        <p><?php _e('GTRO Product Manager requires WooCommerce to be installed and active.', 'gtro-product-manager'); ?></p>
                    </div>
                    <?php
                });
            }
        }

        /**
         * Add options page to admin menu
         *
         * @since    1.0.0
         */
        public function add_plugin_admin_menu() {
            // Nous n'avons plus besoin d'ajouter un menu car Meta Box gère déjà cela
            // via la classe GTRO_Settings
            return;
            
            /* Si vous voulez quand même ajouter un menu personnalisé, décommentez ceci :
            add_menu_page(
                __('GTRO Settings', 'gtro-product-manager'),
                __('GTRO', 'gtro-product-manager'),
                'manage_options',
                $this->plugin_name,
                array($this, 'display_plugin_setup_page'),
                'dashicons-calendar-alt',
                6
            );
            */
        }

        /**
         * Render the settings page for this plugin.
         *
         * @since    1.0.0
         */
        public function display_plugin_setup_page() {
            include_once('partials/gtro-admin-display.php');
        }

        /**
         * Initialize WooCommerce custom product type
         *
         * @since    1.0.0
         */
        public function init_gtro_product_type() {
            // Cette méthode sera implémentée pour créer le type de produit personnalisé
        }
    }
}
