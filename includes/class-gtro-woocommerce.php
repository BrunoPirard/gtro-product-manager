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
		// add_shortcode('gtro_product_options', [$this, 'display_gtro_options_shortcode']);
		add_action('woocommerce_product_data_tabs', array($this, 'add_gtro_product_tab'));
		add_action('woocommerce_product_data_panels', array($this, 'add_gtro_product_panel'));
		add_action('woocommerce_process_product_meta', array($this, 'save_gtro_product_options'));
		add_action('woocommerce_before_add_to_cart_button', array($this, 'display_gtro_options'), 5);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		// Ajuster la priorité des hooks en fonction de Product Add-ons
		if ($this->is_product_addons_active()) {
			add_action('woocommerce_before_add_to_cart_form', array($this, 'display_price_details'), 5);
		} else {
			add_action('woocommerce_before_add_to_cart_form', array($this, 'display_price_details'), 10);
		}

		add_filter('woocommerce_add_cart_item_data', array($this, 'add_gtro_options_to_cart'), 10, 3);
		add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
		add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_gtro_options'), 10, 3);
		add_filter('woocommerce_get_price_html', array($this, 'modify_price_display'), 10, 2);
		add_filter('woocommerce_calculate_totals', array($this, 'calculate_totals'), 10, 1);
		add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_price'), 10, 1);

		// Utiliser une seule méthode pour afficher les métadonnées du panier
		add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
		
		// Utiliser une seule méthode pour ajouter les métadonnées à la commande
		add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 10, 4);
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
			// Nouveau sélecteur pour le type de sélection de véhicules
			woocommerce_wp_select(
				array(
					'id'          => '_gtro_vehicle_selection_type',
					'label'       => __('Type de sélection des véhicules', 'gtro-product-manager'),
					'description' => __('Définissez combien de véhicules le client peut sélectionner', 'gtro-product-manager'),
					'desc_tip'    => true,
					'options'     => array(
						'single'   => __('1 véhicule uniquement', 'gtro-product-manager'),
						'double'   => __('2 véhicules', 'gtro-product-manager'),
						'triple'   => __('3 véhicules', 'gtro-product-manager'),
						'quadruple' => __('4 véhicules', 'gtro-product-manager')
					),
					'value'       => get_post_meta($post->ID, '_gtro_vehicle_selection_type', true) ?: 'single'
				)
			);

			// Section des voitures disponibles (code existant)
			echo '<div class="options-group">';
			echo '<h4>' . __('Voitures disponibles', 'gtro-product-manager') . '</h4>';

			// Récupérer les voitures
			$available_voitures = rwmb_meta('voitures_gtro', array('object_type' => 'setting'), 'gtro_options');

			if (!empty($available_voitures)) {
				foreach ($available_voitures as $voiture) {
					if (isset($voiture['modeles'])) {
						$voiture_id = '_gtro_voiture_' . sanitize_title($voiture['modeles']);
						woocommerce_wp_checkbox(
							array(
								'id'          => $voiture_id,
								'label'       => $voiture['modeles'],
								'description' => sprintf(__('Catégorie: %s', 'gtro-product-manager'), $voiture['categorie']),
								'value'       => get_post_meta($post->ID, $voiture_id, true),
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
	public function save_gtro_product_options($post_id) {
		// Sauvegarder le type de sélection des véhicules
		if (isset($_POST['_gtro_vehicle_selection_type'])) {
			update_post_meta(
				$post_id,
				'_gtro_vehicle_selection_type',
				sanitize_text_field($_POST['_gtro_vehicle_selection_type'])
			);
		}

		// 1. D'abord, marquer toutes les options existantes comme "no"
		$available_voitures = rwmb_meta('voitures_gtro', array('object_type' => 'setting'), 'gtro_options');
		$available_options = rwmb_meta('options_supplementaires', array('object_type' => 'setting'), 'gtro_options');

		// Réinitialiser les voitures
		if (!empty($available_voitures)) {
			foreach ($available_voitures as $voiture) {
				if (isset($voiture['modeles'])) {
					$voiture_id = '_gtro_voiture_' . sanitize_title($voiture['modeles']);
					update_post_meta($post_id, $voiture_id, 'no');
				}
			}
		}

		// Réinitialiser les options
		if (!empty($available_options)) {
			foreach ($available_options as $option) {
				if (isset($option['options'])) {
					$option_id = '_gtro_option_' . sanitize_title($option['options']);
					update_post_meta($post_id, $option_id, 'no');
				}
			}
		}

		// 2. Ensuite sauvegarder les valeurs cochées
		foreach ($_POST as $key => $value) {
			if (strpos($key, '_gtro_') === 0) {
				update_post_meta($post_id, $key, sanitize_text_field($value));
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

		// Débuter le formulaire avec le nonce de sécurité
		echo '<div class="gtro-options-form">';
		wp_nonce_field('gtro_add_to_cart', 'gtro_nonce');
		 echo '<input type="hidden" name="gtro_action" value="add_to_cart">';
    
		// Ajouter cette ligne pour empêcher la résoumission
		echo '<input type="hidden" name="form_submitted" value="' . time() . '">';

		// 1. SECTION SÉLECTION DES VÉHICULES
		$selection_type = get_post_meta($product->get_id(), '_gtro_vehicle_selection_type', true) ?: 'single';
		$max_vehicles = array(
			'single' => 1,
			'double' => 2,
			'triple' => 3,
			'quadruple' => 4
		);

		// Récupérer les voitures activées
		$available_voitures = rwmb_meta('voitures_gtro', array('object_type' => 'setting'), 'gtro_options');
		$voitures_activees = array();

		if (!empty($available_voitures)) {
			foreach ($available_voitures as $voiture) {
				if (isset($voiture['modeles'])) {
					$voiture_id = '_gtro_voiture_' . sanitize_title($voiture['modeles']);
					if (get_post_meta($product->get_id(), $voiture_id, true) === 'yes') {
						$voitures_activees[] = $voiture;
					}
				}
			}
		}

		echo '<div class="gtro-vehicle-selection" data-selection-type="' . esc_attr($selection_type) . '">';
		echo '<h3>' . sprintf(
			__('Sélection de véhicule(s) (%d maximum)', 'gtro-product-manager'),
			$max_vehicles[$selection_type]
		) . '</h3>';

		if ($selection_type !== 'single') {
			echo '<div class="vehicle-counter">' . 
				sprintf(__('Véhicules sélectionnés: <span>0</span>/%d', 'gtro-product-manager'), 
				$max_vehicles[$selection_type]) . 
				'</div>';
		}

		// Grille de véhicules
		echo '<div class="vehicles-grid">';
		foreach ($voitures_activees as $voiture) {
			$vehicle_id = sanitize_title($voiture['modeles']);
			echo '<div class="vehicle-card" data-value="' . esc_attr($vehicle_id) . '" 
					data-category="' . esc_attr($voiture['categorie']) . '">';
			
			if (isset($voiture['image_voiture'])) {
				$image_url = wp_get_attachment_image_url($voiture['image_voiture'], 'full');
				if ($image_url) {
					echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($voiture['modeles']) . '">';
				}
			}
			
			echo '<h4>' . esc_html($voiture['modeles']) . '</h4>';
			echo '<div class="selection-indicator"></div>';
			echo '</div>';
		}
		echo '</div>';

		// Zone des véhicules sélectionnés pour les sélections multiples
		if ($selection_type !== 'single') {
			echo '<div class="selected-vehicles-container">';
			echo '<h4>' . __('Véhicules sélectionnés', 'gtro-product-manager') . '</h4>';
			echo '<div class="selected-vehicles-list"></div>';
			echo '</div>';
		}

		// Champ caché pour les véhicules sélectionnés
		echo '<input type="hidden" name="gtro_vehicle" class="gtro-vehicle-input" value="" required>';

		// 2. SECTION TOURS OU FORMULE
		$max_tours = intval(get_post_meta($product->get_id(), '_gtro_max_tours', true));

		if ($max_tours > 0) {
			echo '<div class="gtro-extra-laps">';
			echo '<h3>' . __('Tours supplémentaires', 'gtro-product-manager') . '</h3>';
			echo '<input type="number" name="gtro_extra_laps" value="0" min="0" max="' . esc_attr($max_tours) . '">';
			echo '</div>';
		} else {
			$selected_formule = get_post_meta($product->get_id(), '_gtro_formule', true);
			
			if (!empty($selected_formule)) {
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

		// 3. SECTION SÉLECTION DE DATE
		$selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
		if (!empty($selected_group)) {
			$dates = rwmb_meta('dates_' . sanitize_title($selected_group), array('object_type' => 'setting'), 'gtro_options');

			echo '<div class="gtro-date-selection">';
			echo '<h3>' . __('Choisir une date', 'gtro-product-manager') . '</h3>';
			echo '<select name="gtro_date">';
			echo '<option value="">' . __('Je choisirais plus tard', 'gtro-product-manager') . '</option>';

			if (!empty($dates)) {
				foreach ($dates as $date) {
					if (isset($date['date'])) {
						echo '<option value="' . esc_attr($date['date']) . '">'
							. esc_html($date['date'])
							. '</option>';
					}
				}
			}
			echo '</select>';
			echo '</div>';
		}

		// 4. SECTION OPTIONS SUPPLÉMENTAIRES
		$available_options = rwmb_meta('options_supplementaires', array('object_type' => 'setting'), 'gtro_options');
		$options_activees = array();

		if (!empty($available_options)) {
			foreach ($available_options as $option) {
				if (isset($option['options'])) {
					$option_id = '_gtro_option_' . sanitize_title($option['options']);
					if (get_post_meta($product->get_id(), $option_id, true) === 'yes') {
						$options_activees[] = $option;
					}
				}
			}
		}

		if (!empty($options_activees)) {
			echo '<div class="gtro-options">';
			echo '<h3>' . __('Options supplémentaires', 'gtro-product-manager') . '</h3>';

			foreach ($options_activees as $option) {
				$option_id = sanitize_title($option['options']);
				echo '<div class="gtro-option">';
				echo '<label>';
				echo '<input type="checkbox" name="gtro_options[]" value="' . esc_attr($option_id) . '">';
				echo esc_html($option['options']);
				if (isset($option['prix_options'])) {
					echo ' (+' . wc_price($option['prix_options']) . ')';
				}
				echo '</label>';
				echo '</div>';
			}
			echo '</div>';
		}

		// Fermer le formulaire
		echo '<input type="hidden" name="gtro_action" value="add_to_cart">'; // Ajout d'un champ de contrôle
    	echo '</div>';

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
	private function calculate_total_price($base_price, $vehicle = '', $extra_laps = 0, $selected_date = '', $selected_options = array(), $formule_option = '', $product_id = 0) {
		$total = $base_price;

		// 1. Ajuster le prix en fonction du/des véhicules sélectionnés
		if (!empty($vehicle)) {
			$vehicles = explode(',', $vehicle);
			$available_voitures = rwmb_meta('voitures_gtro', array('object_type' => 'setting'), 'gtro_options');
			
			foreach ($vehicles as $selected_vehicle) {
				foreach ($available_voitures as $voiture) {
					if (sanitize_title($voiture['modeles']) === $selected_vehicle) {
						// Ajouter le supplément de base de la voiture
						if (isset($voiture['supplement_base'])) {
							$total += floatval($voiture['supplement_base']);
						}
						break;
					}
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
			if (!$product) {
				$product = wc_get_product($product_id);
			}
			if ($product) {
				$selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
				$dates = rwmb_meta('dates_' . sanitize_title($selected_group), array('object_type' => 'setting'), 'gtro_options');

				foreach ($dates as $date) {
					if ($date['date'] === $selected_date && isset($date['promo']) && $date['promo'] > 0) {
						$discount = $total * ($date['promo'] / 100);
						$total -= $discount;
						break;
					}
				}
			} else {
				error_log('Product is null in calculate_total_price');
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
		//error_log('=== Début add_gtro_options_to_cart ===');
		//error_log('Product ID: ' . $product_id);
		//error_log('POST Data: ' . print_r($_POST, true));

		// Ajouter la vérification du nonce
		if (!isset($_POST['gtro_nonce']) || !wp_verify_nonce($_POST['gtro_nonce'], 'gtro_add_to_cart')) {
			return $cart_item_data;
		}

		// Vérifier que c'est un produit GTRO
		$date_group = get_post_meta($product_id, '_gtro_date_group', true);
		if (empty($date_group)) {
			return $cart_item_data;
		}

		// Sauvegarder le véhicule sélectionné
		if (isset($_POST['gtro_vehicle'])) {
			$cart_item_data['gtro_vehicle'] = sanitize_text_field($_POST['gtro_vehicle']);
			//error_log('Vehicle: ' . $cart_item_data['gtro_vehicle']);
		}

		// Sauvegarder la date sélectionnée 
		if (isset($_POST['gtro_date'])) {
			$cart_item_data['gtro_date'] = sanitize_text_field($_POST['gtro_date']);
			//error_log('Date: ' . $cart_item_data['gtro_date']);
		}

		// Sauvegarder les options sélectionnées
		if (isset($_POST['gtro_options']) && is_array($_POST['gtro_options'])) {
			$cart_item_data['gtro_options'] = array_map('sanitize_text_field', $_POST['gtro_options']);
			//error_log('Options: ' . print_r($cart_item_data['gtro_options'], true));
		}

		// Vérifier si c'est un produit avec tours supplémentaires ou formule
		$max_tours = intval(get_post_meta($product_id, '_gtro_max_tours', true));
		
		if ($max_tours > 0) {
			if (isset($_POST['gtro_extra_laps'])) {
				$cart_item_data['gtro_extra_laps'] = intval($_POST['gtro_extra_laps']);
				//error_log('Extra Laps: ' . $cart_item_data['gtro_extra_laps']);
			}
		} else {
			if (isset($_POST['gtro_formule_option'])) {
				$cart_item_data['gtro_formule_option'] = sanitize_text_field($_POST['gtro_formule_option']);
				//error_log('Formule Option: ' . $cart_item_data['gtro_formule_option']);
			}
		}

		// Calculer le nouveau prix
		$product = wc_get_product($product_id);
		if (!$product) {
			//error_log('Product is null in add_gtro_options_to_cart');
		} else {
			$base_price = $product->get_price();

			$new_price = $this->calculate_total_price(
				$base_price,
				$cart_item_data['gtro_vehicle'] ?? '',
				$cart_item_data['gtro_extra_laps'] ?? 0,
				$cart_item_data['gtro_date'] ?? '',
				$cart_item_data['gtro_options'] ?? array(), // Utiliser les options sauvegardées
				$cart_item_data['gtro_formule_option'] ?? '',
				$product_id // Passez le product_id ici
			);

			$cart_item_data['gtro_total_price'] = $new_price;
			//error_log('Total Price: ' . $new_price);
		}

		//error_log('=== Fin add_gtro_options_to_cart ===');
		return $cart_item_data;
	}

	/**
	 * Valide les options GTRO avant l'ajout au panier.
	 *
	 * @param bool $passed Si la validation est passée.
	 * @param int $product_id L'ID du produit.
	 * @param int $quantity La quantité du produit.
	 * @return bool Si la validation est passée.
	 */
	public function validate_gtro_options($passed, $product_id, $quantity) {
		// Vérifier si c'est une soumission de formulaire
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return $passed;
		}

		// Vérifier si c'est une action d'ajout au panier intentionnelle
		if (!isset($_POST['gtro_action']) || $_POST['gtro_action'] !== 'add_to_cart' || !isset($_POST['add-to-cart'])) {
			return $passed;
		}

		// Vérifier le nonce
		if (!isset($_POST['gtro_nonce']) || !wp_verify_nonce($_POST['gtro_nonce'], 'gtro_add_to_cart')) {
			wc_add_notice(__('Erreur de sécurité', 'gtro-product-manager'), 'error');
			return false;
		}

		// Vérifier que c'est un produit GTRO
		$date_group = get_post_meta($product_id, '_gtro_date_group', true);
		if (empty($date_group)) {
			return $passed;
		}

		// Vérifier la sélection du véhicule
		if (!isset($_POST['gtro_vehicle']) || empty($_POST['gtro_vehicle'])) {
			wc_add_notice(__('Veuillez sélectionner au moins un véhicule', 'gtro-product-manager'), 'error');
			return false;
		}

		// Vérifier la date
		if (!isset($_POST['gtro_date']) || empty($_POST['gtro_date'])) {
			wc_add_notice(__('Veuillez sélectionner une date', 'gtro-product-manager'), 'error');
			return false;
		}

		// Vérifier si c'est un produit avec formule
		$max_tours = intval(get_post_meta($product_id, '_gtro_max_tours', true));
		if ($max_tours === 0) {
			if (!isset($_POST['gtro_formule_option']) || empty($_POST['gtro_formule_option'])) {
				wc_add_notice(__('Veuillez sélectionner une option de formule', 'gtro-product-manager'), 'error');
				return false;
			}
		}

		return true;
	}

	/**
	 * Récupère les données GTRO du produit en session
	 *
	 * @param array $cart_item Les données du produit dans le panier.
	 * @param array $values    Les données du produit en session.
	 * @return array Les données du produit dans le panier avec les données GTRO.
	 */
	public function get_cart_item_from_session($cart_item, $values) {
		// Récupérer les données sauvegardées
		if (isset($values['gtro_vehicle'])) {
			$cart_item['gtro_vehicle'] = $values['gtro_vehicle'];
			$cart_item['gtro_date'] = $values['gtro_date'] ?? '';
			$cart_item['gtro_options'] = $values['gtro_options'] ?? array();
			$cart_item['gtro_extra_laps'] = $values['gtro_extra_laps'] ?? 0;
			$cart_item['gtro_formule_option'] = $values['gtro_formule_option'] ?? '';
			$cart_item['gtro_total_price'] = $values['gtro_total_price'] ?? 0;

			// Recalculer le prix total
			if ($cart_item['gtro_total_price'] > 0) {
				$cart_item['data']->set_price($cart_item['gtro_total_price']);
			}
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
	 * Met à jour le prix de chaque élément du panier.
	 *
	 * Pour chaque élément du panier qui a un prix total GTRO, recalcule le prix total
	 * en fonction des options sélectionnées (véhicule, date, options, formule, etc.)
	 * et met à jour le prix de l'élément du panier.
	 *
	 * @param WC_Cart $cart Le panier.
	 */
	public function update_cart_item_price($cart) {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			if (isset($cart_item['gtro_total_price'])) {
				// Recalculer le prix total
				$product = $cart_item['data'];
				$base_price = $product->get_regular_price();
				
				$new_price = $this->calculate_total_price(
					$base_price,
					$cart_item['gtro_vehicle'] ?? '',
					$cart_item['gtro_extra_laps'] ?? 0,
					$cart_item['gtro_date'] ?? '',
					$cart_item['gtro_options'] ?? array(),
					$cart_item['gtro_formule_option'] ?? '',
					$cart_item['product_id']
				);
				
				$cart_item['data']->set_price($new_price);
			}
		}
	}

	/**
	 * Affiche les métadonnées personnalisées de l'élément du panier
	 *
	 * @param array $item_data Les métadonnées de l'élément du panier
	 * @param array $cart_item Les données de l'élément du panier
	 * @return array Les métadonnées de l'élément du panier avec les données GTRO
	 */
	public function display_cart_item_custom_meta_data($item_data, $cart_item) {
		if (isset($cart_item['gtro_vehicle'])) {
			$item_data[] = array(
				'key' => __('Véhicules', 'gtro-product-manager'),
				'value' => str_replace(',', ', ', $cart_item['gtro_vehicle'])
			);
		}
		
		if (isset($cart_item['gtro_extra_laps']) && $cart_item['gtro_extra_laps'] > 0) {
			$item_data[] = array(
				'key' => __('Tours supplémentaires', 'gtro-product-manager'),
				'value' => $cart_item['gtro_extra_laps']
			);
		}

		if (isset($cart_item['gtro_formule_option'])) {
			$item_data[] = array(
				'key' => __('Formule', 'gtro-product-manager'),
				'value' => $cart_item['gtro_formule_option']
			);
		}

		if (isset($cart_item['gtro_date']) && !empty($cart_item['gtro_date'])) {
			$item_data[] = array(
				'key' => __('Date', 'gtro-product-manager'),
				'value' => $cart_item['gtro_date']
			);
		}

		if (isset($cart_item['gtro_options']) && !empty($cart_item['gtro_options'])) {
			$item_data[] = array(
				'key' => __('Options', 'gtro-product-manager'),
				'value' => implode(', ', $cart_item['gtro_options'])
			);
		}

		return $item_data;
	}

	/**
	 * Ajoute les métadonnées GTRO à la commande.
	 *
	 * Récupère les données GTRO du produit et les ajoute
	 * en tant que métadonnées à la commande.
	 *
	 * @param WC_Order_Item $item          L'élément de la commande.
	 * @param string        $cart_item_key La clé de l'élément du panier.
	 * @param array         $values        Les valeurs du formulaire GTRO.
	 * @param WC_Order      $order         La commande.
	 */
	public function add_custom_order_line_item_meta($item, $cart_item_key, $values, $order) {
		if (isset($values['gtro_vehicle'])) {
			$item->add_meta_data(__('Véhicules', 'gtro-product-manager'), str_replace(',', ', ', $values['gtro_vehicle']));
		}
		
		if (isset($values['gtro_extra_laps']) && $values['gtro_extra_laps'] > 0) {
			$item->add_meta_data(__('Tours supplémentaires', 'gtro-product-manager'), $values['gtro_extra_laps']);
		}

		if (isset($values['gtro_formule_option'])) {
			$item->add_meta_data(__('Formule', 'gtro-product-manager'), $values['gtro_formule_option']);
		}

		if (isset($values['gtro_date']) && !empty($values['gtro_date'])) {
			$item->add_meta_data(__('Date', 'gtro-product-manager'), $values['gtro_date']);
		}

		if (isset($values['gtro_options']) && !empty($values['gtro_options'])) {
			$item->add_meta_data(__('Options', 'gtro-product-manager'), implode(', ', $values['gtro_options']));
		}
	}

	/**
	 * Retrieves GTRO item data to display in the cart.
	 *
	 * This function adds metadata for vehicles, extra laps, date,
	 * and additional options for GTRO products in the cart.
	 * It formats vehicle names and options with the first letter
	 * capitalized and compiles them into a readable string format.
	 *
	 * @param array $item_data Existing item data in the cart.
	 * @param array $cart_item The cart item containing GTRO data.
	 * @return array Updated item data with GTRO metadata.
	 */
	public function get_item_data($item_data, $cart_item) {
		if (isset($cart_item['gtro_vehicle'])) {
			$vehicles = explode(',', $cart_item['gtro_vehicle']);
			$item_data[] = array(
				'key' => __('Véhicules', 'gtro-product-manager'),
				'value' => implode(', ', array_map('ucfirst', $vehicles))
			);
		}

		// Ajouter les autres données
		if (isset($cart_item['gtro_extra_laps']) && $cart_item['gtro_extra_laps'] > 0) {
			$item_data[] = array(
				'key' => __('Tours supplémentaires', 'gtro-product-manager'),
				'value' => $cart_item['gtro_extra_laps']
			);
		}

		if (isset($cart_item['gtro_date']) && !empty($cart_item['gtro_date'])) {
			$item_data[] = array(
				'key' => __('Date', 'gtro-product-manager'),
				'value' => $cart_item['gtro_date']
			);
		}

		if (isset($cart_item['gtro_options']) && !empty($cart_item['gtro_options'])) {
			$item_data[] = array(
				'key' => __('Options', 'gtro-product-manager'),
				'value' => implode(', ', array_map('ucfirst', $cart_item['gtro_options']))
			);
		}

		return $item_data;
	}

	/**
	 * Ajoute les métadonnées de l'élément du panier (GTRO) à la commande.
	 *
	 * @param WC_Order_Item $item          L'élément de la commande.
	 * @param string        $cart_item_key La clé de l'élément du panier.
	 * @param array         $values        Les valeurs du formulaire GTRO.
	 * @param WC_Order      $order         La commande.
	 */
	public function checkout_create_order_line_item($item, $cart_item_key, $values, $order) {
		if (isset($values['gtro_vehicle'])) {
			$vehicles = explode(',', $values['gtro_vehicle']);
			$item->add_meta_data(__('Véhicules', 'gtro-product-manager'), 
				implode(', ', array_map('ucfirst', $vehicles)));
		}

		if (isset($values['gtro_extra_laps']) && $values['gtro_extra_laps'] > 0) {
			$item->add_meta_data(__('Tours supplémentaires', 'gtro-product-manager'), 
				$values['gtro_extra_laps']);
		}

		if (isset($values['gtro_date']) && !empty($values['gtro_date'])) {
			$item->add_meta_data(__('Date', 'gtro-product-manager'), 
				$values['gtro_date']);
		}

		if (isset($values['gtro_options']) && !empty($values['gtro_options'])) {
			$item->add_meta_data(__('Options', 'gtro-product-manager'), 
				implode(', ', array_map('ucfirst', $values['gtro_options'])));
		}
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
				error_log('Missing required fields for vehicle: ' . print_r($voiture, true));
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

		// Récupérer les combos multi-voitures et leurs remises
		$combos_voitures = array();
		$combos = rwmb_meta('combos_voitures', array('object_type' => 'setting'), 'gtro_options');
		if (!empty($combos)) {
			foreach ($combos as $combo) {
				if (isset($combo['type_combo']) && isset($combo['remise'])) {
					$combos_voitures[] = array(
						'type_combo' => $combo['type_combo'],
						'remise' => floatval($combo['remise'])
					);
				}
			}
		}

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
				'formulesData'     => $formules_data,
				'combosVoitures'   => $combos_voitures  
			)
		);
		wp_add_inline_script('gtro-public', '
			if (window.history.replaceState) {
				window.history.replaceState(null, null, window.location.href);
			}
			
			// Désactiver la résoumission du formulaire
			window.addEventListener("pageshow", function(event) {
				if (event.persisted) {
					window.location.reload();
				}
			});
		');
	}
}
