<?php
namespace GTRO_Plugin;

class GTRO_WooCommerce {

	/**
	 * Check if Product Addons for WooCommerce is active
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public static function is_product_addons_active() {
		return class_exists( 'Product_Extras_For_WooCommerce' ) || class_exists( 'PEWC_Product_Extra' );
	}
	// Ainsi, vous pourriez y accéder depuis n'importe où avec : if (GTRO_Plugin\GTRO_WooCommerce::is_product_addons_active()) {faire quelque chose...}

	/**
	 * Register all actions and filters for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_filter( 'pewc_show_totals', '__return_false', 999 ); // Priorité élevée pour s'assurer qu'il s'applique
		add_action( 'woocommerce_product_data_tabs', array( $this, 'add_gtro_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_gtro_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_gtro_product_options' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_gtro_options' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Ajuster la priorité des hooks en fonction de Product Add-ons
		if ( $this->is_product_addons_active() ) {
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_price_details' ), 5 );
		} else {
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_price_details' ), 10 );
		}

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_gtro_options_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_gtro_options' ), 10, 3 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'modify_price_display' ), 10, 2 );
		add_filter( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 10, 1 );

		// add_shortcode('gtro_product_options', [$this, 'display_gtro_options_shortcode']);
		// Debug hook
		// add_action('admin_init', [$this, 'debug_meta_box_data']);
	}

	/**
	 * Shortcode pour afficher les options GTRO dans le frontend.
	 *
	 * @return string Le HTML des options GTRO.
	 */
	public function display_gtro_options_shortcode() {
		ob_start();
		$this->display_gtro_options();
		return ob_get_clean();
	}

	/**
	 * Affiche les groupes de dates et leurs dates associées dans les journaux d'erreurs.
	 *
	 * Utile pour déboguer les métadonnées des groupes de dates.
	 */
	public function debug_meta_box_data() {
		error_log( '=== Début Debug GTRO ===' );

		// Debug des groupes de dates
		$groupes = get_option( 'gtro_groupes_dates', array() );
		error_log( 'Groupes de dates : ' . print_r( $groupes, true ) );

		// Pour chaque groupe, afficher ses dates
		foreach ( $groupes as $groupe ) {
			$dates = rwmb_meta( 'dates_' . sanitize_title( $groupe ), array( 'object_type' => 'setting' ), 'gtro' );
			error_log( 'Dates pour ' . $groupe . ' : ' . print_r( $dates, true ) );
		}

		error_log( '=== Fin Debug GTRO ===' );
	}

	/**
	 * Ajoute l'onglet "GTRO Options" à la page de produit.
	 *
	 * @param  array $tabs Les onglets de la page de produit.
	 * @return array Les onglets de la page de produit, avec l'onglet "GTRO Options" ajouté.
	 */
	public function add_gtro_product_tab( $tabs ) {
		$tabs['gtro_options'] = array(
			'label'    => __( 'GTRO Options', 'gtro-product-manager' ),
			'target'   => 'gtro_options_product_data',
			'class'    => array( 'hide_if_grouped', 'hide_if_external' ),
			'priority' => 90,
		);
		return $tabs;
	}

	/**
	 * Ajoute l'onglet "GTRO Options" à la page de produit,
	 * avec les voitures et les options disponibles.
	 *
	 * @since 1.0.0
	 */
	public function add_gtro_product_panel() {
		global $post;
		?>
		<div id="gtro_options_product_data" class="panel woocommerce_options_panel">
			<?php
			// Section des voitures disponibles
			echo '<div class="options-group">';
			echo '<h4>' . __( 'Voitures disponibles', 'gtro-product-manager' ) . '</h4>';

			// Récupérer les voitures
			$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );

			if ( ! empty( $available_voitures ) ) {
				foreach ( $available_voitures as $voiture ) {
					if ( isset( $voiture['modeles'] ) ) {
						$voiture_id = '_gtro_voiture_' . sanitize_title( $voiture['modeles'] );
						woocommerce_wp_checkbox(
							array(
								'id'          => $voiture_id,
								'label'       => $voiture['modeles'],
								'description' => sprintf( __( 'Catégorie: %s', 'gtro-product-manager' ), $voiture['categorie'] ),
								'value'       => get_post_meta( $post->ID, $voiture_id, true ),
							)
						);
					}
				}
			}
			echo '</div>';

			// Sélection du groupe de dates
			woocommerce_wp_select(
				array(
					'id'      => '_gtro_date_group',
					'label'   => __( 'Groupe de dates', 'gtro-product-manager' ),
					'options' => $this->get_gtro_date_groups(),
				)
			);

			// Nombre maximum de tours
			woocommerce_wp_text_input(
				array(
					'id'                => '_gtro_max_tours',
					'label'             => __( 'Nombre maximum de tours', 'gtro-product-manager' ),
					'type'              => 'number',
					'desc_tip'          => true,
					'description'       => __( 'Nombre maximum de tours autorisés', 'gtro-product-manager' ),
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				)
			);

			// Sélection de la formule (visible uniquement si max_tours = 0)
			$formules = rwmb_meta('formules_list', array('object_type' => 'setting'), 'gtro_options');
			$formules_options = array('' => __('Sélectionner une formule', 'gtro-product-manager'));

			if (!empty($formules)) {
				foreach ($formules as $formule) {
					if (isset($formule['nom_formule'])) {
						$formules_options[sanitize_title($formule['nom_formule'])] = $formule['nom_formule'];
					}
				}
			}

			woocommerce_wp_select(
				array(
					'id'      => '_gtro_formule',
					'label'   => __('Formule', 'gtro-product-manager'),
					'options' => $formules_options,
					'description' => __('Sélectionnez une formule (uniquement si le nombre maximum de tours est 0)', 'gtro-product-manager'),
					'desc_tip' => true,
				)
			);

			// Section des options disponibles
			echo '<div class="options-group">';
			echo '<h4>' . __( 'Options disponibles', 'gtro-product-manager' ) . '</h4>';

			$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );

			if ( ! empty( $available_options ) ) {
				foreach ( $available_options as $option ) {
					if ( isset( $option['options'] ) && isset( $option['prix_options'] ) ) {
						$option_id = '_gtro_option_' . sanitize_title( $option['options'] );
						woocommerce_wp_checkbox(
							array(
								'id'          => $option_id,
								'label'       => $option['options'],
								'description' => sprintf( __( 'Prix: %s€', 'gtro-product-manager' ), $option['prix_options'] ),
								'value'       => get_post_meta( $post->ID, $option_id, true ),
							)
						);
					}
				}
			}
			echo '</div>';
			?>
		</div>
		<?php
	}

	/**
	 * Sauvegarde les options GTRO du produit.
	 *
	 * Marque d'abord toutes les options existantes comme "no", puis
	 * sauvegarde les valeurs cochées.
	 *
	 * @param int $post_id L'ID du produit.
	 */
	public function save_gtro_product_options( $post_id ) {
		// 1. D'abord, marquer toutes les options existantes comme "no"
		$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		$available_options  = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );

		// Réinitialiser les voitures
		if ( ! empty( $available_voitures ) ) {
			foreach ( $available_voitures as $voiture ) {
				if ( isset( $voiture['modeles'] ) ) {
					$voiture_id = '_gtro_voiture_' . sanitize_title( $voiture['modeles'] );
					update_post_meta( $post_id, $voiture_id, 'no' );
				}
			}
		}

		// Réinitialiser les options
		if ( ! empty( $available_options ) ) {
			foreach ( $available_options as $option ) {
				if ( isset( $option['options'] ) ) {
					$option_id = '_gtro_option_' . sanitize_title( $option['options'] );
					update_post_meta( $post_id, $option_id, 'no' );
				}
			}
		}

		// 2. Ensuite sauvegarder les valeurs cochées
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, '_gtro_' ) === 0 ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $value ) );
			}
		}
	}

	/**
	 * Retrieve GTRO date groups from the options.
	 *
	 * This function fetches the date groups stored in the WordPress options
	 * and prepares them for use in a WooCommerce product setting. Each group
	 * is sanitized and mapped to its title.
	 *
	 * @since 1.0.0
	 *
	 * @return array An associative array of date groups with sanitized titles
	 *               as keys and the original group names as values. Includes
	 *               a default option for selecting a group.
	 */
	private function get_gtro_date_groups() {
		$groupes = get_option( 'gtro_groupes_dates', array() );
		$options = array( '' => __( 'Sélectionner un groupe', 'gtro-product-manager' ) );

		foreach ( $groupes as $groupe ) {
			$options[ sanitize_title( $groupe ) ] = $groupe;
		}

		return $options;
	}

	/**
	 * Display GTRO product options in the WooCommerce product page.
	 *
	 * This function retrieves and displays available vehicle models, extra laps,
	 * date selections, and additional options for a GTRO product. The options
	 * are dynamically generated based on the product's meta data and settings.
	 *
	 * - Vehicle Selection: Displays a dropdown of activated vehicle models.
	 * - Extra Laps: Provides an input field for specifying extra laps.
	 * - Date Selection: Shows a date selector based on the selected group.
	 * - Additional Options: Lists available add-ons with checkboxes.
	 *
	 * @since 1.0.0
	 */
	public function display_gtro_options() {
		global $product;

		// Récupérer les voitures activées
		$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		$voitures_activees  = array();

		if ( ! empty( $available_voitures ) ) {
			foreach ( $available_voitures as $voiture ) {
				if ( isset( $voiture['modeles'] ) ) {
					$voiture_id = '_gtro_voiture_' . sanitize_title( $voiture['modeles'] );
					if ( get_post_meta( $product->get_id(), $voiture_id, true ) === 'yes' ) {
						$voitures_activees[] = $voiture;
					}
				}
			}
		}

		// Afficher le sélecteur de voitures
		echo '<div class="gtro-vehicle-selection">';
		echo '<h3>' . __( 'Sélection du véhicule', 'gtro-product-manager' ) . '</h3>';

		// Grille de véhicules
		echo '<div class="vehicles-grid">';
		foreach ( $voitures_activees as $voiture ) {
			$vehicle_id = sanitize_title( $voiture['modeles'] );
			echo '<div class="vehicle-card" data-value="' . esc_attr($vehicle_id) . '" data-category="' . esc_attr( $voiture['categorie'] ) . '">';
			
			// Récupérer l'image avec wp_get_attachment_image_url
			if (isset($voiture['image_voiture']) && !empty($voiture['image_voiture'])) {
				$image_url = wp_get_attachment_image_url($voiture['image_voiture'], 'full');
				if ($image_url) {
					echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($voiture['modeles']) . '">';
				}
			}
			
			echo '<h4>' . esc_html($voiture['modeles']) . '</h4>';
			echo '</div>';
		}
		echo '</div>';

		// Select caché pour maintenir la compatibilité avec le JS existant
		echo '<select name="gtro_vehicle" required style="display: none;">';
		echo '<option value="">' . __( 'Choisissez votre véhicule', 'gtro-product-manager' ) . '</option>';
		// Debug
		//error_log('Voitures activées : ' . print_r($voitures_activees, true));
		foreach ( $voitures_activees as $voiture ) {
			echo '<option value="' . esc_attr( sanitize_title( $voiture['modeles'] ) ) . '" 
				data-category="' . esc_attr( $voiture['categorie'] ) . '">'
				. esc_html( $voiture['modeles'] )
				. '</option>';
		}
		echo '</select>';
		echo '</div>';

		// 3. Tours supplémentaires
		$max_tours = intval(get_post_meta($product->get_id(), '_gtro_max_tours', true));

		if ($max_tours > 0) {
			// Code existant pour les tours supplémentaires
			echo '<div class="gtro-extra-laps">';
			echo '<h3>' . __('Tours supplémentaires', 'gtro-product-manager') . '</h3>';
			echo '<input type="number" name="gtro_extra_laps" value="0" min="0" max="' . esc_attr($max_tours) . '">';
			echo '</div>';
		} else {
			// Récupérer la formule sélectionnée pour ce produit
			$selected_formule = get_post_meta($product->get_id(), '_gtro_formule', true);
			
			if (!empty($selected_formule)) {
				// Récupérer les options de la formule
				$formules = rwmb_meta('formules_list', array('object_type' => 'setting'), 'gtro_options');
				foreach ($formules as $formule) {
					if (sanitize_title($formule['nom_formule']) === $selected_formule) {
						if (isset($formule['options_formule']) && !empty($formule['options_formule'])) {
							echo '<div class="gtro-formule-options">';
							echo '<h3>' . __('Options disponibles', 'gtro-product-manager') . '</h3>';
							echo '<select name="gtro_formule_option" required>';
							echo '<option value="">' . __('Choisissez votre option', 'gtro-product-manager') . '</option>';
							
							foreach ($formule['options_formule'] as $option) {
								if (isset($option['nom_option_formule']) && isset($option['prix_formule'])) {
									echo '<option value="' . esc_attr(sanitize_title($option['nom_option_formule'])) . '" 
										data-price="' . esc_attr($option['prix_formule']) . '">'
										. esc_html($option['nom_option_formule'])
										. ' - ' . wc_price($option['prix_formule'])
										. '</option>';
								}
							}
							
							echo '</select>';
							echo '</div>';
						}
						break;
					}
				}
			}
		}

		// 4. Sélecteur de dates
		// Récupérer le groupe de dates sélectionné pour ce produit
		$selected_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		// error_log('Groupe de dates sélectionné : ' . $selected_group);

		if ( ! empty( $selected_group ) ) {
			// Récupérer les dates du groupe depuis les settings
			$dates = rwmb_meta( 'dates_' . sanitize_title( $selected_group ), array( 'object_type' => 'setting' ), 'gtro_options' );
			// error_log('Dates du groupe ' . $selected_group . ' : ' . print_r($dates, true));

			echo '<div class="gtro-date-selection">';
			echo '<h3>' . __( 'Choisir une date', 'gtro-product-manager' ) . '</h3>';
			echo '<select name="gtro_date" required>';
			echo '<option value="">' . __( 'Sélectionnez une date', 'gtro-product-manager' ) . '</option>';

			if ( ! empty( $dates ) ) {
				foreach ( $dates as $date ) {
					if ( isset( $date['date'] ) ) {
						echo '<option value="' . esc_attr( $date['date'] ) . '">'
							. esc_html( $date['date'] )
							. '</option>';
					}
				}
			}

			echo '</select>';
			echo '</div>';
		}

		// 5. Options supplémentaires
		$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );
		$options_activees  = array();

		if ( ! empty( $available_options ) ) {
			foreach ( $available_options as $option ) {
				if ( isset( $option['options'] ) ) {
					$option_id = '_gtro_option_' . sanitize_title( $option['options'] );
					if ( get_post_meta( $product->get_id(), $option_id, true ) === 'yes' ) {
						$options_activees[] = $option;
					}
				}
			}
		}

		if ( ! empty( $options_activees ) ) {
			echo '<div class="gtro-options">';
			echo '<h3>' . __( 'Options supplémentaires', 'gtro-product-manager' ) . '</h3>';

			foreach ( $options_activees as $option ) {
				$option_id = sanitize_title( $option['options'] );
				echo '<div class="gtro-option">';
				echo '<label>';
				echo '<input type="checkbox" name="gtro_options[]" value="' . esc_attr( $option_id ) . '">';
				echo esc_html( $option['options'] );
				if ( isset( $option['prix_options'] ) ) {
					echo ' (+' . wc_price( $option['prix_options'] ) . ')';
				}
				echo '</label>';
				echo '</div>';
			}

			echo '</div>';
		}
	}

	/**
	 * Affiche les options supplémentaires dans le panier
	 *
	 * @param array $item_data Les métadonnées du produit
	 * @param array $cart_item Les données de l'élément du panier
	 *
	 * @return array Les métadonnées du produit
	 */
	public function display_gtro_options_in_cart( $item_data, $cart_item ) {
		if ( isset( $cart_item['gtro_options'] ) ) {
			foreach ( $cart_item['gtro_options'] as $option_id => $price ) {
				$item_data[] = array(
					'key'   => __( 'Option', 'gtro-product-manager' ),
					'value' => $option_id . ' (+' . wc_price( $price ) . ')',
				);
			}
		}
		return $item_data;
	}

	/**
	 * Affiche les détails du prix avant le formulaire d'ajout au panier.
	 *
	 * Cette méthode est appelée par le hook 'woocommerce_before_add_to_cart_form'
	 * et affiche une zone pour les détails du prix qui sera mise à jour
	 * dynamiquement via JavaScript.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_price_details() {
		global $product;

		// Vérifier si c'est un produit GTRO
		$date_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		if ( empty( $date_group ) ) {
			return;
		}

		// Afficher le conteneur pour les détails du prix
		echo '<div id="gtro-price-details" class="gtro-price-details">';
		echo '<h3>' . esc_html__( 'Détails du prix', 'gtro-product-manager' ) . '</h3>';
		echo '<div class="price-breakdown">';
		echo '<p class="base-price-line">' . esc_html__( 'Prix de base:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '<p class="vehicle-supplement-line" style="display:none;">' . esc_html__( 'Supplément véhicule:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '<p class="extra-laps-line" style="display:none;">' . esc_html__( 'Tours supplémentaires:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '<p class="promo-line" style="display:none;">' . esc_html__( 'Promotion:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '<p class="options-line" style="display:none;">' . esc_html__( 'Options:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '<p class="total-price-line">' . esc_html__( 'Prix total:', 'gtro-product-manager' ) . ' <span></span></p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Calcule le prix total d'un stage
	 *
	 * 1. Ajuste le prix en fonction de la catégorie du véhicule
	 * 2. Calcule le prix des tours supplémentaires
	 * 3. Applique la promotion de la date si elle existe
	 * 4. Ajoute le prix des options (après la promo)
	 *
	 * @param int    $base_price       Le prix de base du stage
	 * @param string $vehicle          La catégorie du
	 *                                 véhicule
	 * @param int    $extra_laps       Le nombre de tours
	 *                                 supplémentaires
	 * @param string $selected_date    La date
	 *                                 sélectionnée
	 * @param array  $selected_options Les options supplémentaires
	 *                                 sélectionnées
	 *
	 * @return int Le prix total du stage
	 */
	private function calculate_total_price($base_price, $vehicle = '', $extra_laps = 0, $selected_date = '', $selected_options = array(), $formule_option = '') {
		$total = $base_price;

		// 1. Ajuster le prix en fonction de la catégorie du véhicule
		if (!empty($vehicle)) {
			$available_voitures = rwmb_meta('voitures_gtro', array('object_type' => 'setting'), 'gtro_options');
			foreach ($available_voitures as $voiture) {
				if (sanitize_title($voiture['modeles']) === $vehicle) {
					switch ($voiture['categorie']) {
						case '2':
							$total += 50;
							break;
						case '3':
							$total += 100;
							break;
					}
					break;
				}
			}
		}

		// 2. Ajouter soit le prix des tours supplémentaires, soit le prix de la formule
		if (!empty($formule_option)) {
			// Si une formule est sélectionnée, ajouter son prix
			$formules = rwmb_meta('formules_list', array('object_type' => 'setting'), 'gtro_options');
			foreach ($formules as $formule) {
				if (isset($formule['options_formule'])) {
					foreach ($formule['options_formule'] as $option) {
						if (sanitize_title($option['nom_option_formule']) === $formule_option) {
							$total += floatval($option['prix_formule']);
							break 2;
						}
					}
				}
			}
		} elseif ($extra_laps > 0) {
			// Sinon calculer le prix des tours supplémentaires
			$price_per_lap = get_option('gtro_price_per_lap', 50);
			$total += ($extra_laps * $price_per_lap);
		}

		// 3. Appliquer la promotion de la date si elle existe (sur le total incluant la formule)
		if (!empty($selected_date)) {
			global $product;
			$selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
			$dates = rwmb_meta('dates_' . sanitize_title($selected_group), array('object_type' => 'setting'), 'gtro_options');

			foreach ($dates as $date) {
				if ($date['date'] === $selected_date && isset($date['promo']) && $date['promo'] > 0) {
					$discount = $total * ($date['promo'] / 100);
					$total -= $discount;
					break;
				}
			}
		}

		// 4. Ajouter le prix des options supplémentaires (après la promo)
		if (!empty($selected_options)) {
			$available_options = rwmb_meta('options_supplementaires', array('object_type' => 'setting'), 'gtro_options');
			foreach ($selected_options as $option_slug) {
				foreach ($available_options as $option) {
					if (sanitize_title($option['options']) === $option_slug) {
						$total += floatval($option['prix_options']);
						break;
					}
				}
			}
		}

		return $total;
	}

	/**
	 * Modifie l'affichage du prix d'un produit GTRO.
	 *
	 * Affiche le prix de base du produit, suivi d'une note indiquant que
	 * ce prix ne comprend pas les options.
	 *
	 * @param  string     $price_html Le prix HTML du produit.
	 * @param  WC_Product $product    Le produit.
	 * @return string Le prix modifié.
	 */
	public function modify_price_display( $price_html, $product ) {
		// Ne modifier le prix que sur la page du produit
		if ( ! is_product() ) {
			return $price_html;
		}

		// Vérifier si c'est un produit GTRO
		$date_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		if ( empty( $date_group ) ) {
			return $price_html;
		}

		$base_price = $product->get_price();

		// Ajouter une note sur les options
		$price_html  = '<span class="base-price">' . wc_price( $base_price ) . '</span>';
		$price_html .= '<br><small class="price-note">' . __( 'Prix de base hors options', 'gtro-product-manager' ) . '</small>';

		return $price_html;
	}

	/**
	 * Modifie les totaux du panier pour les produits GTRO.
	 *
	 * Pour chaque produit GTRO dans le panier, remplace le prix
	 * par le prix total calculé en prenant en compte la catégorie
	 * du véhicule, les tours supplémentaires, la promotion de la date
	 * et les options.
	 *
	 * @param WC_Cart $cart Le panier.
	 */
	public function calculate_totals( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['gtro_total_price'] ) ) {
				$cart_item['data']->set_price( $cart_item['gtro_total_price'] );
			}
		}
	}

	/**
	 * Ajoute les options GTRO au panier.
	 *
	 * Récupère les données du formulaire et les ajoute au panier.
	 * Calcule également le prix total du produit en fonction
	 * de ces options.
	 *
	 * @param  array $cart_item_data Les données du produit dans le panier.
	 * @param  int   $product_id     L'ID du produit.
	 * @param  int   $variation_id   L'ID de la variation du produit.
	 * @return array Les données du produit mises à jour.
	 */
	public function add_gtro_options_to_cart($cart_item_data, $product_id, $variation_id) {
		if (isset($_POST['gtro_vehicle'])) {
			$cart_item_data['gtro_vehicle'] = sanitize_text_field($_POST['gtro_vehicle']);
		}

		if (isset($_POST['gtro_date'])) {
			$cart_item_data['gtro_date'] = sanitize_text_field($_POST['gtro_date']);
		}

		// Vérifier si c'est un produit avec tours supplémentaires ou formule
		$max_tours = intval(get_post_meta($product_id, '_gtro_max_tours', true));
		
		if ($max_tours > 0) {
			if (isset($_POST['gtro_extra_laps'])) {
				$cart_item_data['gtro_extra_laps'] = intval($_POST['gtro_extra_laps']);
			}
		} else {
			if (isset($_POST['gtro_formule_option'])) {
				$cart_item_data['gtro_formule_option'] = sanitize_text_field($_POST['gtro_formule_option']);
			}
		}

		// Calculer le nouveau prix
		$product = wc_get_product($product_id);
		$base_price = $product->get_price();

		$new_price = $this->calculate_total_price(
			$base_price,
			$cart_item_data['gtro_vehicle'] ?? '',
			$cart_item_data['gtro_extra_laps'] ?? 0,
			$cart_item_data['gtro_date'] ?? '',
			isset($_POST['gtro_options']) ? array_map('sanitize_text_field', $_POST['gtro_options']) : array(),
			$cart_item_data['gtro_formule_option'] ?? ''
		);

		$cart_item_data['gtro_total_price'] = $new_price;

		return $cart_item_data;
	}

	public function validate_gtro_options($passed, $product_id, $quantity) {
		if (!isset($_POST['gtro_vehicle']) || empty($_POST['gtro_vehicle'])) {
			wc_add_notice(__('Veuillez sélectionner un véhicule', 'gtro-product-manager'), 'error');
			$passed = false;
		}

		if (!isset($_POST['gtro_date']) || empty($_POST['gtro_date'])) {
			wc_add_notice(__('Veuillez sélectionner une date', 'gtro-product-manager'), 'error');
			$passed = false;
		}

		// Vérifier si c'est un produit avec formule
		$max_tours = intval(get_post_meta($product_id, '_gtro_max_tours', true));
		if ($max_tours === 0) {
			if (!isset($_POST['gtro_formule_option']) || empty($_POST['gtro_formule_option'])) {
				wc_add_notice(__('Veuillez sélectionner une option de formule', 'gtro-product-manager'), 'error');
				$passed = false;
			}
		}

		return $passed;
	}

	/**
	 * Récupère les données GTRO du produit en session
	 *
	 * @param array $cart_item Les données du produit dans le panier.
	 * @param array $values    Les données du produit en
	 *                         session.
	 *
	 * @return array Les données du produit dans le panier avec les données GTRO.
	 */
	public function get_cart_item_from_session($cart_item, $values) {
		if (isset($values['gtro_vehicle'])) {
			$cart_item['gtro_vehicle'] = $values['gtro_vehicle'];
		}

		if (isset($values['gtro_extra_laps'])) {
			$cart_item['gtro_extra_laps'] = $values['gtro_extra_laps'];
		}

		if (isset($values['gtro_date'])) {
			$cart_item['gtro_date'] = $values['gtro_date'];
		}

		if (isset($values['gtro_formule_option'])) {
			$cart_item['gtro_formule_option'] = $values['gtro_formule_option'];
		}

		if (isset($values['gtro_total_price'])) {
			$cart_item['gtro_total_price'] = $values['gtro_total_price'];
		}

		return $cart_item;
	}

	/**
	 * Récupère les dates et leurs promotions associées pour un produit
	 *
	 * @param  int $product_id ID du produit
	 * @return array Tableau des dates avec leurs promotions
	 */
	private function get_dates_with_promos( $product_id ) {
		$dates_with_promos = array();

		// Récupérer les dates et promos depuis la metabox
		$dates = get_post_meta( $product_id, '_gtro_dates', true );

		if ( ! empty( $dates ) && is_array( $dates ) ) {
			foreach ( $dates as $date ) {
				if ( ! empty( $date['date'] ) && isset( $date['promo'] ) ) {
					$dates_with_promos[] = array(
						'date'  => $date['date'],
						'promo' => floatval( $date['promo'] ),
					);
				}
			}
		}

		return $dates_with_promos;
	}

	/**
	 * Enqueue scripts and styles for the GTRO product page.
	 *
	 * This function checks if the current page is a product page and
	 * then enqueues the necessary JavaScript and CSS files for the GTRO plugin.
	 * It also prepares data related to the product, including base price, category
	 * supplements, promotional dates, and available options, and localizes it for
	 * use in the frontend script.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		$plugin_dir = plugin_dir_url( __DIR__ );
		wp_enqueue_script( 'gtro-public', $plugin_dir . 'public/js/gtro-public.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_style( 'gtro-public', $plugin_dir . 'public/css/gtro-public.css', array(), '1.0.0', 'all' );

		$product_id = get_the_ID();
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		// Récupérer les voitures avec leurs suppléments de base et catégories
		$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		$vehicles_data      = array();
		foreach ( $available_voitures as $voiture ) {
			if ( isset( $voiture['modeles'] ) && isset( $voiture['categorie'] ) ) {
				$vehicle_key = sanitize_title( $voiture['modeles'] );
				$vehicles_data[ $vehicle_key ] = array(
					'supplement_base' => isset( $voiture['supplement_base'] ) ? floatval( $voiture['supplement_base'] ) : 0,
					'categorie'      => $voiture['categorie'],
				);
				//error_log("Added vehicle: {$vehicle_key} with category: {$voiture['categorie']}");
			} else {
				//error_log('Missing required fields for vehicle: ' . print_r($voiture, true));
			}
		}

		// Récupérer les prix par tours pour chaque catégorie
		$prix_categories = rwmb_meta( 'prix_categories', array( 'object_type' => 'setting' ), 'gtro_options' );
		$category_prices = array();
		foreach ( $prix_categories as $cat ) {
			if ( isset( $cat['categorie'] ) && isset( $cat['prix_tour_sup'] ) ) {
				$category_prices[ $cat['categorie'] ] = floatval( $cat['prix_tour_sup'] );
			}
		}

		// Récupérer les dates et promos
		$selected_group    = get_post_meta( $product_id, '_gtro_date_group', true );
		$dates_with_promos = array();
		if ( ! empty( $selected_group ) ) {
			$dates = rwmb_meta( 'dates_' . sanitize_title( $selected_group ), array( 'object_type' => 'setting' ), 'gtro_options' );
			if ( ! empty( $dates ) ) {
				foreach ( $dates as $date ) {
					if ( isset( $date['date'] ) && isset( $date['promo'] ) ) {
						$dates_with_promos[] = array(
							'date'  => $date['date'],
							'promo' => floatval( $date['promo'] ),
						);
					}
				}
			}
		}

		// Récupérer les options disponibles
		$available_options = array();
		$options           = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );
		if ( ! empty( $options ) ) {
			foreach ( $options as $option ) {
				if ( isset( $option['options'] ) && isset( $option['prix_options'] ) ) {
					$option_id                       = sanitize_title( $option['options'] );
					$available_options[ $option_id ] = floatval( $option['prix_options'] );
				}
			}
		}

		// Récupérer les formules et leurs prix
		$formules_data = array();
		$formules = rwmb_meta('formules_gtro', array('object_type' => 'setting'), 'gtro_options');
		if (!empty($formules)) {
			foreach ($formules as $formule) {
				if (isset($formule['nom_formule']) && isset($formule['prix_formule'])) {
					$formule_id = sanitize_title($formule['nom_formule']);
					$formules_data[$formule_id] = floatval($formule['prix_formule']);
				}
			}
		}
		//error_log('Available vehicles: ' . print_r($available_voitures, true));
		//error_log('Processed vehicles data: ' . print_r($vehicles_data, true));

		wp_localize_script(
			'gtro-public',
			'gtroData',
			array(
				'basePrice'        => floatval($product->get_price()),
				'vehiclesData'     => $vehicles_data,
				'categoryPrices'   => $category_prices,
				'datesPromo'       => empty($dates_with_promos) ? array() : $dates_with_promos,
				'availableOptions' => empty($available_options) ? array() : $available_options,
				'showPriceDetails' => true,
				'maxTours'         => intval(get_post_meta($product_id, '_gtro_max_tours', true)),
				'formulesData'     => $formules_data  // Ajout des données des formules
			)
		);
	}
}
