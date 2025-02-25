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
	/**
	 * Class Main
	 *
	 * Handles main functionality for the plugin.
	 *
	 * @package GTRO_Product_Manager
	 * @since 1.0.0
	 */
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

		/**
		 * The current instance of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    object
		 */
		private static $instance = null;

		/**
		 * The current settigs of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    object
		 */
		private $settings;

		/**
		 * The current calendar of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    object
		 */
		private $calendar;

		/**
		 * The current dates manager of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    object
		 */
		public $dates_manager;

		/**
		 * Define the core functionality of the plugin.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// Éviter l'initialisation multiple.
			if ( null !== self::$instance ) {
				return self::$instance;
			}

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
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-I18n.php';
			include_once GTRO_PLUGIN_DIR . 'admin/class-gtro-admin.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-settings.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-woocommerce.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-calendar.php';
			include_once GTRO_PLUGIN_DIR . 'includes/class-gtro-dates-manager.php';

			$this->loader        = new GTRO_Loader();
			$this->settings      = new GTRO_Settings();
			$this->dates_manager = new GTRO_Dates_Manager();
		}

		/**
		 * Get the singleton instance of this class
		 *
		 * @return GTRO_Main
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 *
		 * @since  1.0.0
		 * @access private
		 */
		private function set_locale() {
			$plugin_i18n = new GTRO_I18n();
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
			$plugin_admin = new GTRO_Admin( $this->get_plugin_name(), $this->get_version() );

			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

			// Désactiver les produits variables.
			$this->loader->add_filter( 'product_type_selector', $this, 'remove_variable_product_type' );
			$this->loader->add_action( 'admin_head', $this, 'hide_variable_product_options' );
			$this->loader->add_filter( 'woocommerce_ajax_variation_threshold', $this, 'disable_variations' );

			// Initialiser l'intégration WooCommerce si WooCommerce est actif.
			if ( class_exists( 'WooCommerce' ) ) {
				new GTRO_WooCommerce();
			}
		}

		/**
		 * Remove variable product type from product type selector
		 *
		 * @param  array $types Product types.
		 * @return array Modified product types
		 */
		public function remove_variable_product_type( $types ) {
			unset( $types['variable'] );
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

		/**
		 * Register all of the hooks related to the public-facing functionality
		 * of the plugin.
		 *
		 * @since  1.0.0
		 * @access private
		 */
		private function define_public_hooks() {
			// Initialiser le calendrier.
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

		/**
		 * Helper method to get promo dates
		 *
		 * @param  string|array|null $group_slugs Le(s) slug(s) du/des groupe(s).
		 * @return array Liste des dates en promotion
		 */
		public function get_promo_dates( $group_slugs = null ) {
			return $this->dates_manager->get_promo_dates( $group_slugs );
		}

		/**
		 * Get available groups
		 *
		 * @return array Liste des slugs de groupes
		 */
		public function get_available_groups() {
			return $this->dates_manager->get_available_groups();
		}

		/**
		 * Méthode appelée lors de l'activation du plugin
		 *
		 * @since 1.0.0
		 */
		public static function activate() {
			// Initialiser les options par défaut si nécessaire.
			$default_options = array(
				'promo_color' => '#FFD700',
				'delete_data' => false,
			);

			$current_options = get_option( 'gtro_options', array() );
			$merged_options  = wp_parse_args( $current_options, $default_options );

			update_option( 'gtro_options', $merged_options );
		}
	}
}
