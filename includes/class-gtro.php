<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */
namespace GTRO_Plugin;

if ( ! class_exists( 'GTRO_Plugin\GTRO_Main' ) ) {
	class GTRO_Main {



		/**
		 * The loader that's responsible for maintaining and registering all hooks that power
		 * the plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 * @var    GTRO_Loader    $loader    Maintains and registers all hooks for the plugin.
		 */
		protected $loader;

		/**
		 * The unique identifier of this plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 * @var    string    $plugin_name    The string used to uniquely identify this plugin.
		 */
		protected $plugin_name;

		/**
		 * The current version of the plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 * @var    string    $version    The current version of the plugin.
		 */
		protected $version;

		private static $instance = null;
		private $settings;
		private $calendar;

		/**
		 * Define the core functionality of the plugin.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			if ( self::$instance === null ) {
				self::$instance = $this;

				if ( defined( 'GTRO_VERSION' ) ) {
					$this->version = GTRO_VERSION;
				} else {
					$this->version = '1.0.0';
				}
				$this->plugin_name = 'gtro-product-manager';

				$this->load_dependencies();
				$this->set_locale();
				$this->define_admin_hooks();
				$this->define_public_hooks();
			}
			return self::$instance;
		}

		/**
		 * Load the required dependencies for this plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 */
		private function load_dependencies() {
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-loader.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-i18n.php';
			include_once GTRO_PLUGIN_DIR . 'admin/class-gtro-admin.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-settings.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-woocommerce.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-calendar.php';

			$this->loader   = new GTRO_Loader();
			$this->settings = new GTRO_Settings(); // Gardez uniquement cette initialisation
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 *
		 * @since  1.0.0
		 * @access private
		 */
		private function set_locale() {
			$plugin_i18n = new GTRO_i18n();
			$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
		}

		/**
		 * Register all of the hooks related to the admin area functionality
		 * of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 */
		private function define_admin_hooks() {
			$plugin_admin = new GTRO_Admin($this->get_plugin_name(), $this->get_version());

			$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
			$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

			// Désactiver les produits variables
			$this->loader->add_filter('product_type_selector', $this, 'remove_variable_product_type');
			$this->loader->add_action('admin_head', $this, 'hide_variable_product_options');
			$this->loader->add_filter('woocommerce_ajax_variation_threshold', $this, 'disable_variations');

			// Initialiser l'intégration WooCommerce si WooCommerce est actif
			if (class_exists('WooCommerce')) {
				new GTRO_WooCommerce();
			}
		}

		/**
		 * Remove variable product type from product type selector
		 *
		 * @param array $types Product types
		 * @return array Modified product types
		 */
		public function remove_variable_product_type($types) {
			unset($types['variable']);
			return $types;
		}

		/**
		 * Hide variable product options in admin
		 */
		public function hide_variable_product_options() {
			echo '<style>
				.show_if_variable,
				.variable_product_options {
					display: none !important;
				}
			</style>';
		}

		/**
		 * Disable variations completely
		 *
		 * @return int
		 */
		public function disable_variations() {
			return 0;
		}

		private function define_public_hooks() {
			// Initialiser le calendrier
			$this->calendar = new GTRO_Calendar( $this->get_plugin_name(), $this->get_version() );
		}

		/**
		 * Run the loader to execute all of the hooks with WordPress.
		 *
		 * @since 1.0.0
		 */
		public function run() {
			$this->loader->run();
		}

		/**
		 * The name of the plugin used to uniquely identify it within the context of
		 * WordPress and to define internationalization functionality.
		 *
		 * @since  1.0.0
		 * @return string    The name of the plugin.
		 */
		public function get_plugin_name() {
			return $this->plugin_name;
		}

		/**
		 * The reference to the class that orchestrates the hooks with the plugin.
		 *
		 * @since  1.0.0
		 * @return GTRO_Loader    Orchestrates the hooks of the plugin.
		 */
		public function get_loader() {
			return $this->loader;
		}

		/**
		 * Retrieve the version number of the plugin.
		 *
		 * @since  1.0.0
		 * @return string    The version number of the plugin.
		 */
		public function get_version() {
			return $this->version;
		}
	}
}
