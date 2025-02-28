<?php
/**
 * Fichier de la classe WooCommerce
 *
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 */

namespace GTRO_Plugin;

/**
 * Class Loader
 *
 * Handles loading for the plugin.
 *
 * @package BGTRO_Product_Manager
 * @since 1.0.0
 */
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
	// Ainsi, vous pourriez y accéder depuis n'importe où avec : if (GTRO_Plugin\GTRO_WooCommerce::is_product_addons_active()) {faire quelque chose...}.

	/**
	 * Register all actions and filters for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_shortcode( 'gtro_product_options', array( $this, 'display_gtro_options_shortcode' ) );
		add_action( 'woocommerce_product_data_tabs', array( $this, 'add_gtro_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_gtro_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_gtro_product_options' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_gtro_options' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Ajuster la priorité des hooks en fonction de Product Add-ons.
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
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_item_price' ), 10, 1 );

		// Utiliser une seule méthode pour afficher les métadonnées du panier.
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Utiliser une seule méthode pour ajouter les métadonnées à la commande.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'checkout_create_order_line_item' ), 10, 4 );
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

		// Ajouter le nonce au début du formulaire.
		wp_nonce_field( 'gtro_save_product_options', 'gtro_product_nonce' );

		global $post;
		?>
		<div id="gtro_options_product_data" class="panel woocommerce_options_panel">
		<?php
		// Nouveau sélecteur pour le type de sélection de véhicules.
		woocommerce_wp_select(
			array(
				'id'          => '_gtro_vehicle_selection_type',
				'label'       => __( 'Type de sélection des véhicules', 'gtro-product-manager' ),
				'description' => __( 'Définissez combien de véhicules le client peut sélectionner', 'gtro-product-manager' ),
				'desc_tip'    => true,
				'options'     => array(
					'single'    => __( '1 véhicule uniquement', 'gtro-product-manager' ),
					'double'    => __( '2 véhicules', 'gtro-product-manager' ),
					'triple'    => __( '3 véhicules', 'gtro-product-manager' ),
					'quadruple' => __( '4 véhicules', 'gtro-product-manager' ),
				),
				'value'       => get_post_meta( $post->ID, '_gtro_vehicle_selection_type', true ) ?? 'single',
			)
		);

			// Section des voitures disponibles (code existant).
			echo '<div class="options-group">';
			echo '<h4>' . esc_html__( 'Voitures disponibles', 'gtro-product-manager' ) . '</h4>';

			// Récupérer les voitures.
			$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );

		if ( ! empty( $available_voitures ) ) {
			foreach ( $available_voitures as $voiture ) {
				if ( isset( $voiture['modeles'] ) ) {
					$voiture_id = '_gtro_voiture_' . sanitize_title( $voiture['modeles'] );
					woocommerce_wp_checkbox(
						array(
							'id'          => $voiture_id,
							'label'       => $voiture['modeles'],
							/* translators: %d: nombre maximum de véhicules */
							'description' => sprintf( __( 'Catégorie: %s', 'gtro-product-manager' ), $voiture['categorie'] ),
							'value'       => get_post_meta( $post->ID, $voiture_id, true ),
						)
					);
				}
			}
		}
			echo '</div>';

			// Sélection du groupe de dates.
			woocommerce_wp_select(
				array(
					'id'      => '_gtro_date_group',
					'label'   => __( 'Groupe de dates', 'gtro-product-manager' ),
					'options' => $this->get_gtro_date_groups(),
				)
			);

			// Nombre maximum de tours.
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

			// Sélection de la formule (visible uniquement si max_tours = 0).
			$formules         = rwmb_meta( 'formules_list', array( 'object_type' => 'setting' ), 'gtro_options' );
			$formules_options = array( '' => __( 'Sélectionner une formule', 'gtro-product-manager' ) );

		if ( ! empty( $formules ) ) {
			foreach ( $formules as $formule ) {
				if ( isset( $formule['nom_formule'] ) ) {
					$formules_options[ sanitize_title( $formule['nom_formule'] ) ] = $formule['nom_formule'];
				}
			}
		}

			woocommerce_wp_select(
				array(
					'id'          => '_gtro_formule',
					'label'       => __( 'Formule', 'gtro-product-manager' ),
					'options'     => $formules_options,
					'description' => __( 'Sélectionnez une formule (uniquement si le nombre maximum de tours est 0)', 'gtro-product-manager' ),
					'desc_tip'    => true,
				)
			);

			// Section des options disponibles.
			echo '<div class="options-group">';
			echo '<h4>' . esc_html__( 'Options disponibles', 'gtro-product-manager' ) . '</h4>';

			$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );

		if ( ! empty( $available_options ) ) {
			foreach ( $available_options as $option ) {
				if ( isset( $option['options'] ) && isset( $option['prix_options'] ) ) {
					$option_id = '_gtro_option_' . sanitize_title( $option['options'] );
					woocommerce_wp_checkbox(
						array(
							'id'          => $option_id,
							'label'       => $option['options'],
							// Translators: %d: Prix de l'option.
							'description' => sprintf( __( 'Prix: %s€', 'gtro-product-manager' ), $option['prix_options'] ),
							'value'       => get_post_meta( $post->ID, $option_id, true ),
						)
					);
				}
			}
		}
			echo '</div>';

			// Après la section des options, ajoutez :.
			echo '<div class="options-group">';
			echo '<h4>' . esc_html__( 'Promotion combo', 'gtro-product-manager' ) . '</h4>';

			// Récupérer les combos.
			$available_combos = rwmb_meta( 'combos_list', array( 'object_type' => 'setting' ), 'gtro_options' );
		if ( ! empty( $available_combos ) ) {
			woocommerce_wp_select(
				array(
					'id'          => '_gtro_combo_promo',
					'label'       => __( 'Promo combo', 'gtro-product-manager' ),
					'description' => __( 'Sélectionnez une promotion combo', 'gtro-product-manager' ),
					'desc_tip'    => true,
					'options'     => $this->get_combos_options(),
				)
			);
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
	 * sauvegarde les valeurs cochées. Vérifie également les nonces
	 * et les autorisations.
	 *
	 * @param int $post_id L'ID du produit.
	 * @return void
	 */
	public function save_gtro_product_options( $post_id ) {
		// error_log( '=== Début save_gtro_product_options ===' );
		// error_log( 'Post ID: ' . $post_id );

		// Vérification de l'autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// error_log( 'Autosave détecté - abandon' );
			return;
		}

		// Vérification du type de post.
		if ( ! isset( $_POST['post_type'] ) || 'product' !== $_POST['post_type'] ) {
			// error_log( 'Post type incorrect - abandon' );
			return;
		}

		// Vérification des permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			// error_log( 'Permissions insuffisantes - abandon' );
			return;
		}

		// Ajout du nonce.
		if ( ! isset( $_POST['gtro_product_nonce'] ) ) {
			// error_log( 'Nonce manquant - abandon' );
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gtro_product_nonce'] ) ), 'gtro_save_product_options' ) ) {
			// error_log('Vérification du nonce échouée - abandon');
			return;
		}

		// error_log( 'POST data: ' . print_r( $_POST, true ) );

		// Sauvegarde du type de sélection des véhicules.
		if ( isset( $_POST['_gtro_vehicle_selection_type'] ) ) {
			$vehicle_type = sanitize_text_field( wp_unslash( $_POST['_gtro_vehicle_selection_type'] ) );
			update_post_meta( $post_id, '_gtro_vehicle_selection_type', $vehicle_type );
			// error_log("Type de sélection des véhicules sauvegardé: $vehicle_type");
		}

		// Sauvegarde des voitures.
		$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		if ( ! empty( $available_voitures ) ) {
			foreach ( $available_voitures as $voiture ) {
				if ( isset( $voiture['modeles'] ) ) {
					$voiture_id = '_gtro_voiture_' . sanitize_title( $voiture['modeles'] );
					$value = isset( $_POST[ $voiture_id ] ) ? 'yes' : 'no';
					update_post_meta( $post_id, $voiture_id, $value );
					// error_log("Voiture $voiture_id sauvegardée: $value");
				}
			}
		}

		// Sauvegarde du groupe de dates.
		if ( isset( $_POST['_gtro_date_group'] ) ) {
			$date_group = sanitize_text_field( wp_unslash( $_POST['_gtro_date_group'] ) );
			update_post_meta( $post_id, '_gtro_date_group', $date_group );
			// error_log("Groupe de dates sauvegardé: $date_group");
		}

		// Sauvegarde du nombre maximum de tours.
		if ( isset( $_POST['_gtro_max_tours'] ) ) {
			$max_tours = absint( wp_unslash( $_POST['_gtro_max_tours'] ) );
			update_post_meta( $post_id, '_gtro_max_tours', $max_tours );
			// error_log("Nombre maximum de tours sauvegardé: $max_tours");
		}

		// Sauvegarde de la formule.
		if ( isset( $_POST['_gtro_formule'] ) ) {
			$formule = sanitize_text_field( wp_unslash( $_POST['_gtro_formule'] ) );
			update_post_meta( $post_id, '_gtro_formule', $formule );
			// error_log("Formule sauvegardée: $formule");
		}

		// Sauvegarde des options supplémentaires.
		$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );
		if ( ! empty( $available_options ) ) {
			foreach ( $available_options as $option ) {
				if ( isset( $option['options'] ) ) {
					$option_id = '_gtro_option_' . sanitize_title( $option['options'] );
					$value = isset( $_POST[ $option_id ] ) ? 'yes' : 'no';
					update_post_meta( $post_id, $option_id, $value );
					// error_log("Option $option_id sauvegardée: $value");
				}
			}
		}

		// Sauvegarde de la promo combo.
		if ( isset( $_POST['_gtro_combo_promo'] ) ) {
			$combo_promo = sanitize_text_field( wp_unslash( $_POST['_gtro_combo_promo'] ) );
			update_post_meta( $post_id, '_gtro_combo_promo', $combo_promo );
			// error_log("Promo combo sauvegardée: $combo_promo");
		}

		// error_log('=== Fin save_gtro_product_options ==='); ?
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
	 * Récupère la liste des combos disponibles pour le select
	 *
	 * @return array
	 */
	private function get_combos_options() {
		$options = array( '' => __( 'Sélectionner un combo', 'gtro-product-manager' ) );

		// Récupérer les combos depuis les options.
		$settings = get_option( 'gtro_options' );

		if ( ! empty( $settings['combos_list'] ) ) {
			foreach ( $settings['combos_list'] as $combo ) {
				if ( ! empty( $combo['nom_combo'] ) ) {
					$slug             = sanitize_title( $combo['nom_combo'] );
					$options[ $slug ] = $combo['nom_combo'];
				}
			}
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

		wp_nonce_field( 'gtro_add_to_cart', 'gtro_nonce' );

		global $product;

		// Débuter le formulaire avec le nonce de sécurité.
		echo '<div class="gtro-options-form">';
		wp_nonce_field( 'gtro_add_to_cart', 'gtro_nonce' );
		echo '<input type="hidden" name="gtro_action" value="add_to_cart">';

		// Ajouter cette ligne pour empêcher la résoumission.
		echo '<input type="hidden" name="form_submitted" value="' . esc_attr( time() ) . '">';

		// 1. SECTION SÉLECTION DES VÉHICULES.
		$selection_type = get_post_meta( $product->get_id(), '_gtro_vehicle_selection_type', true ) ?? 'single';
		$max_vehicles   = array(
			'single'    => 1,
			'double'    => 2,
			'triple'    => 3,
			'quadruple' => 4,
		);

		// Récupérer les voitures activées.
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

		echo '<div class="gtro-vehicle-selection" data-selection-type="' . esc_attr( $selection_type ) . '">';
		echo '<h3>' . sprintf(
			// Translators: 1: Maximum number of vehicles.
			esc_html__( 'Sélection de véhicule(s) (%d maximum)', 'gtro-product-manager' ),
			esc_html( $max_vehicles[ $selection_type ] )
		) . '</h3>';

		if ( 'single' !== $selection_type ) {
			echo '<div class="vehicle-counter">' .
			sprintf(
				// Translators: 1: Maximum number of vehicles, 2: Maximum number of vehicles.
				esc_html__( 'Véhicules sélectionnés: <span>0</span>/%d', 'gtro-product-manager' ),
				esc_html( $max_vehicles[ $selection_type ] )
			) .
			'</div>';
		}

		// Grille de véhicules.
		echo '<div class="vehicles-grid">';
		foreach ( $voitures_activees as $voiture ) {
			$vehicle_id = sanitize_title( $voiture['modeles'] );
			echo '<div class="vehicle-card" data-value="' . esc_attr( $vehicle_id ) . '" 
					data-category="' . esc_attr( $voiture['categorie'] ) . '">';

			if ( isset( $voiture['image_voiture'] ) ) {
				$image_url = wp_get_attachment_image_url( $voiture['image_voiture'], 'full' );
				if ( $image_url ) {
					echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $voiture['modeles'] ) . '">';
				}
			}

			echo '<h4>' . esc_html( $voiture['modeles'] ) . '</h4>';
			echo '<div class="selection-indicator"></div>';
			echo '</div>';
		}
		echo '</div>';

		// Zone des véhicules sélectionnés pour les sélections multiples.
		if ( 'single' !== $selection_type ) {
			echo '<div class="selected-vehicles-container">';
			echo '<h4>' . esc_html__( 'Véhicules sélectionnés', 'gtro-product-manager' ) . '</h4>';
			echo '<div class="selected-vehicles-list"></div>';
			echo '</div>';
		}

		// Champ caché pour les véhicules sélectionnés.
		echo '<input type="hidden" name="gtro_vehicle" class="gtro-vehicle-input" value="" required>';

		// 2. SECTION TOURS OU FORMULE.
		$max_tours = intval( get_post_meta( $product->get_id(), '_gtro_max_tours', true ) );

		if ( $max_tours > 0 ) {
			echo '<div class="gtro-extra-laps">';
			echo '<h3>' . esc_html__( 'Tours supplémentaires', 'gtro-product-manager' ) . '</h3>';
			echo '<input class="numberstyle" type="number" name="gtro_extra_laps" value="0" min="0" max="' . esc_attr( $max_tours ) . '">';
			echo '</div>';
		} else {
			$selected_formule = get_post_meta( $product->get_id(), '_gtro_formule', true );

			if ( ! empty( $selected_formule ) ) {
				$formules = rwmb_meta( 'formules_list', array( 'object_type' => 'setting' ), 'gtro_options' );
				foreach ( $formules as $formule ) {
					if ( sanitize_title( $formule['nom_formule'] ) === $selected_formule ) {
						if ( isset( $formule['options_formule'] ) && ! empty( $formule['options_formule'] ) ) {
								echo '<div class="gtro-formule-options">';
								echo '<h3>' . esc_html__( 'Options disponibles', 'gtro-product-manager' ) . '</h3>';
								echo '<select name="gtro_formule_option" required>';
								echo '<option value="">' . esc_html__( 'Choisissez votre option', 'gtro-product-manager' ) . '</option>';

							foreach ( $formule['options_formule'] as $option ) {
								if ( isset( $option['nom_option_formule'] ) && isset( $option['prix_formule'] ) ) {
									echo '<option value="' . esc_attr( sanitize_title( $option['nom_option_formule'] ) ) . '" 
										data-price="' . esc_attr( $option['prix_formule'] ) . '">'
										. esc_html( $option['nom_option_formule'] )
										. ' - ' . wp_kses_post( wc_price( $option['prix_formule'] ) )
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

		// 3. SECTION SÉLECTION DE DATE.
		$selected_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		if ( ! empty( $selected_group ) ) {
			$dates = rwmb_meta( 'dates_' . sanitize_title( $selected_group ), array( 'object_type' => 'setting' ), 'gtro_options' );

			echo '<div class="gtro-date-selection">';
			echo '<h3>' . esc_html__( 'Choisir une date', 'gtro-product-manager' ) . '</h3>';
			echo '<select name="gtro_date">';
			echo '<option value="">' . esc_html__( 'Je choisirais plus tard', 'gtro-product-manager' ) . '</option>';

			if ( ! empty( $dates ) ) {
				// Filtrer et trier les dates.
				$valid_dates = array();
				$today       = gmdate( 'Y-m-d' ); // Date du jour.

				foreach ( $dates as $date_item ) {
					if ( isset( $date_item['date'] ) && $date_item['date'] >= $today ) {
						$valid_dates[] = $date_item;
					}
				}

				// Trier les dates.
				usort(
					$valid_dates,
					function ( $a, $b ) {
						return strcmp( $a['date'], $b['date'] );
					}
				);

				// Afficher les dates triées.
				foreach ( $valid_dates as $date_item ) {
					$formatted_date = date_i18n(
						get_option( 'date_format' ),
						strtotime( $date_item['date'] )
					);
					$promo_text     = isset( $date_item['promo'] ) && $date_item['promo'] > 0
						? sprintf( ' (Promo: %d%%)', $date_item['promo'] )
						: '';

					echo '<option value="' . esc_attr( $date_item['date'] ) . '">'
						. esc_html( $formatted_date . $promo_text )
						. '</option>';
				}
			}
			echo '</select>';
			echo '</div>';
		}

		// 4. SECTION OPTIONS SUPPLÉMENTAIRES.
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
			echo '<h3>' . esc_html__( 'Options supplémentaires', 'gtro-product-manager' ) . '</h3>';

			foreach ( $options_activees as $option ) {
				$option_id = sanitize_title( $option['options'] );
				echo '<div class="gtro-option">';
				echo '<label>';
				echo '<input type="checkbox" name="gtro_options[]" value="' . esc_attr( $option_id ) . '">';
				echo esc_html( $option['options'] );
				if ( isset( $option['prix_options'] ) ) {
					echo ' (+' . wp_kses_post( wc_price( $option['prix_options'] ) ) . ')';
				}
				echo '</label>';
				echo '</div>';
			}
			echo '</div>';
		}

		// Fermer le formulaire.
		echo '<input type="hidden" name="gtro_action" value="add_to_cart">'; // Ajout d'un champ de contrôle.
		echo '</div>';
	}

	/**
	 * Affiche les options supplémentaires dans le panier
	 *
	 * @param array $item_data Les métadonnées du produit.
	 * @param array $cart_item Les données de l'élément du panier.
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
	 * @since  1.0.0
	 * @return void
	 */
	public function display_price_details() {
		global $product;

		// Vérifier si c'est un produit GTRO.
		$date_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		if ( empty( $date_group ) ) {
			return;
		}

		// Afficher le conteneur pour les détails du prix.
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
	 * Calcule le prix total d'un produit GTRO.
	 *
	 * @param float  $base_price Le prix de base.
	 * @param string $vehicle Les véhicules sélectionnés.
	 * @param int    $extra_laps Nombre de tours supplémentaires.
	 * @param string $selected_date Date sélectionnée.
	 * @param array  $selected_options Options sélectionnées.
	 * @param string $formule_option Formule sélectionnée.
	 * @param int    $product_id ID du produit.
	 * @return float Prix total calculé.
	 */
	private function calculate_total_price( $base_price, $vehicle = '', $extra_laps = 0, $selected_date = '', $selected_options = array(), $formule_option = '', $product_id = 0 ) {
		// Prix de base.
		$total = floatval( $base_price );

		// Supplément véhicules.
		if ( ! empty( $vehicle ) ) {
			$vehicles            = explode( ',', $vehicle );
			$available_voitures  = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
			$vehicle_supplements = 0;

			foreach ( $vehicles as $selected_vehicle ) {
				foreach ( $available_voitures as $voiture ) {
					if ( sanitize_title( $voiture['modeles'] ) === $selected_vehicle && isset( $voiture['supplement_base'] ) ) {
						$vehicle_supplements += floatval( $voiture['supplement_base'] );
					}
				}
			}
			$total += $vehicle_supplements;
		}

		// Tours supplémentaires.
		if ( $extra_laps > 0 ) {
			$extra_laps_total = 0;
			foreach ( $vehicles as $selected_vehicle ) {
				foreach ( $available_voitures as $voiture ) {
					if ( sanitize_title( $voiture['modeles'] ) === $selected_vehicle && isset( $voiture['categorie'] ) ) {
						$category        = $voiture['categorie'];
						$prix_categories = rwmb_meta( 'prix_categories', array( 'object_type' => 'setting' ), 'gtro_options' );
						foreach ( $prix_categories as $cat ) {
							if ( $cat['categorie'] === $category ) {
								$price_per_lap     = floatval( $cat['prix_tour_sup'] );
								$extra_laps_total += ( $extra_laps * $price_per_lap );
							}
						}
					}
				}
			}
			$total += $extra_laps_total;
		}

		// Remise combo.
		$selected_combo = get_post_meta( $product_id, '_gtro_combo_promo', true );
		if ( ! empty( $selected_combo ) ) {
			$combos_list = rwmb_meta( 'combos_voitures', array( 'object_type' => 'setting' ), 'gtro_options' );
			foreach ( $combos_list as $combo ) {
				if ( isset( $combo['nom_promo_combo'] ) && sanitize_title( $combo['nom_promo_combo'] ) === $selected_combo && isset( $combo['remise'] ) ) {
					$discount_percentage = floatval( $combo['remise'] );
					$discount_amount     = round( $total * ( $discount_percentage / 100 ), 2 );
					$total               = round( $total - $discount_amount, 2 );
					break;
				}
			}
		}

		// Promotion date.
		if ( ! empty( $selected_date ) ) {
			$selected_group = get_post_meta( $product_id, '_gtro_date_group', true );
			$dates          = rwmb_meta( 'dates_' . sanitize_title( $selected_group ), array( 'object_type' => 'setting' ), 'gtro_options' );
			foreach ( $dates as $date ) {
				if ( $date['date'] === $selected_date && isset( $date['promo'] ) && $date['promo'] > 0 ) {
					$promo_amount = round( $total * ( floatval( $date['promo'] ) / 100 ), 2 );
					$total        = round( $total - $promo_amount, 2 );
					break;
				}
			}
		}

		// Options supplémentaires.
		if ( ! empty( $selected_options ) ) {
			$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );
			foreach ( $selected_options as $option_slug ) {
				foreach ( $available_options as $option ) {
					if ( sanitize_title( $option['options'] ) === $option_slug ) {
						$total = round( $total + floatval( $option['prix_options'] ), 2 );
					}
				}
			}
		}

		// Arrondi final à l'euro supérieur.
		return ceil( $total );
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
		// Ne modifier le prix que sur la page du produit.
		if ( ! is_product() ) {
			return $price_html;
		}

		// Vérifier si c'est un produit GTRO.
		$date_group = get_post_meta( $product->get_id(), '_gtro_date_group', true );
		if ( empty( $date_group ) ) {
			return $price_html;
		}

		$base_price = $product->get_price();

		// Ajouter une note sur les options.
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
	 * Ajoute les options supplémentaires du produit GTRO au panier.
	 *
	 * Ce hook est déclenché lorsque le formulaire de commande d'un
	 * produit GTRO est soumis. Il récupère les informations du formulaire,
	 * calcule le prix total en prenant en compte les options et les
	 * promotions, puis sauvegarde les données dans le panier.
	 *
	 * @param array $cart_item_data Les données de l'élément du panier.
	 * @param int   $product_id     L'ID du produit.
	 * @param int   $variation_id   L'ID de la variation du produit.
	 *
	 * @return array Les données de l'élément du panier mise à jour.
	 */
	public function add_gtro_options_to_cart( $cart_item_data, $product_id, $variation_id = 0 ) {
		// error_log('=== DÉBUT add_gtro_options_to_cart ===');
		// error_log('POST data: ' . print_r($_POST, true)); ?

		// Vérifier le nonce.
		if ( ! isset( $_POST['gtro_nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_text_field( $_POST['gtro_nonce'] ) ), 'gtro_add_to_cart' ) ) {
			return $cart_item_data;
		}

		// Récupérer les données.
		$vehicle          = isset( $_POST['gtro_vehicle'] ) ? sanitize_text_field( wp_unslash( $_POST['gtro_vehicle'] ) ) : '';
		$extra_laps       = isset( $_POST['gtro_extra_laps'] ) ? intval( wp_unslash( $_POST['gtro_extra_laps'] ) ) : 0;
		$selected_date    = isset( $_POST['gtro_date'] ) ? sanitize_text_field( wp_unslash( $_POST['gtro_date'] ) ) : '';
		$selected_options = isset( $_POST['gtro_options'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['gtro_options'] ) ) : array();

		// error_log('Données reçues:');
		// error_log('Vehicle: ' . $vehicle);
		// error_log('Extra laps: ' . $extra_laps);
		// error_log('Date: ' . $selected_date);
		// error_log('Options: ' . print_r($selected_options, true)); ?

		// Calculer le prix.
		$product    = wc_get_product( $product_id );
		$base_price = $product->get_regular_price();

		$new_price = $this->calculate_total_price(
			floatval( $base_price ),
			$vehicle,
			$extra_laps,
			$selected_date,
			$selected_options,
			'',
			$product_id
		);

		// error_log('Prix calculé: ' . $new_price); ?

		// Sauvegarder les données.
		$cart_item_data['gtro_vehicle']     = $vehicle;
		$cart_item_data['gtro_date']        = $selected_date;
		$cart_item_data['gtro_options']     = $selected_options;
		$cart_item_data['gtro_extra_laps']  = $extra_laps;
		$cart_item_data['gtro_total_price'] = $new_price;

		// error_log('=== FIN add_gtro_options_to_cart ==='); ?
		return $cart_item_data;
	}

	/**
	 * Valide les options GTRO avant l'ajout au panier.
	 *
	 * @param  bool $passed     Si la validation est
	 *                          passée.
	 * @param  int  $product_id L'ID du produit.
	 *
	 * @return bool Si la validation est passée.
	 */
	public function validate_gtro_options( $passed, $product_id ) {

		// Vérifier si c'est une soumission de formulaire.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return $passed;
		}

		// Vérifier si c'est une action d'ajout au panier intentionnelle.
		if ( ! isset( $_POST['gtro_action'] ) || 'add_to_cart' !== $_POST['gtro_action'] || ! isset( $_POST['add-to-cart'] ) ) {
			return $passed;
		}

		// Vérifier le nonce.
		if ( ! isset( $_POST['gtro_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gtro_nonce'] ) ), 'gtro_add_to_cart' ) ) {
			wc_add_notice( __( 'Erreur de sécurité', 'gtro-product-manager' ), 'error' );
			return false;
		}

		// Vérifier que c'est un produit GTRO.
		$date_group = get_post_meta( $product_id, '_gtro_date_group', true );
		if ( empty( $date_group ) ) {
			return $passed;
		}

		// Vérifier la sélection du véhicule.
		if ( ! isset( $_POST['gtro_vehicle'] ) || empty( $_POST['gtro_vehicle'] ) ) {
			wc_add_notice( __( 'Veuillez sélectionner au moins un véhicule', 'gtro-product-manager' ), 'error' );
			return false;
		}

		// Vérifier la date.
		if ( ! isset( $_POST['gtro_date'] ) || empty( $_POST['gtro_date'] ) ) {
			wc_add_notice( __( 'Veuillez sélectionner une date', 'gtro-product-manager' ), 'error' );
			return false;
		}

		// Vérifier si c'est un produit avec formule.
		$max_tours = intval( get_post_meta( $product_id, '_gtro_max_tours', true ) );
		if ( 0 === $max_tours ) {
			if ( ! isset( $_POST['gtro_formule_option'] ) || empty( $_POST['gtro_formule_option'] ) ) {
				wc_add_notice( __( 'Veuillez sélectionner une option de formule', 'gtro-product-manager' ), 'error' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Récupère les données GTRO du produit en session
	 *
	 * @param  array $cart_item Les données du produit dans le panier.
	 * @param  array $values    Les données du produit en session.
	 * @return array Les données du produit dans le panier avec les données GTRO.
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		// Récupérer les données sauvegardées.
		if ( isset( $values['gtro_vehicle'] ) ) {
			$cart_item['gtro_vehicle']        = $values['gtro_vehicle'];
			$cart_item['gtro_date']           = $values['gtro_date'] ?? '';
			$cart_item['gtro_options']        = $values['gtro_options'] ?? array();
			$cart_item['gtro_extra_laps']     = $values['gtro_extra_laps'] ?? 0;
			$cart_item['gtro_formule_option'] = $values['gtro_formule_option'] ?? '';
			$cart_item['gtro_total_price']    = $values['gtro_total_price'] ?? 0;

			// Recalculer le prix total.
			if ( $cart_item['gtro_total_price'] > 0 ) {
				$cart_item['data']->set_price( $cart_item['gtro_total_price'] );
			}
		}

		return $cart_item;
	}

	/**
	 * Récupère les dates et leurs promotions associées pour un produit
	 *
	 * @param  int $product_id ID du produit.
	 * @return array Tableau des dates avec leurs promotions
	 */
	private function get_dates_with_promos( $product_id ) {
		$dates_with_promos = array();

		// Récupérer les dates et promos depuis la metabox.
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
	public function update_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['gtro_total_price'] ) ) {
				// Recalculer le prix total.
				$product    = $cart_item['data'];
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

				$cart_item['data']->set_price( $new_price );
			}
		}
	}

	/**
	 * Affiche les métadonnées personnalisées de l'élément du panier
	 *
	 * @param  array $item_data Les métadonnées de l'élément du panier.
	 * @param  array $cart_item Les données de l'élément du panier.
	 * @return array Les métadonnées de l'élément du panier avec les données GTRO
	 */
	public function display_cart_item_custom_meta_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['gtro_vehicle'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Véhicules', 'gtro-product-manager' ),
				'value' => str_replace( ',', ', ', $cart_item['gtro_vehicle'] ),
			);
		}

		if ( isset( $cart_item['gtro_extra_laps'] ) && $cart_item['gtro_extra_laps'] > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Tours supplémentaires', 'gtro-product-manager' ),
				'value' => $cart_item['gtro_extra_laps'],
			);
		}

		if ( isset( $cart_item['gtro_formule_option'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Formule', 'gtro-product-manager' ),
				'value' => $cart_item['gtro_formule_option'],
			);
		}

		if ( isset( $cart_item['gtro_date'] ) && ! empty( $cart_item['gtro_date'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Date', 'gtro-product-manager' ),
				'value' => $cart_item['gtro_date'],
			);
		}

		if ( isset( $cart_item['gtro_options'] ) && ! empty( $cart_item['gtro_options'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Options', 'gtro-product-manager' ),
				'value' => implode( ', ', $cart_item['gtro_options'] ),
			);
		}

		return $item_data;
	}

	/**
	 * Retrieves GTRO item data to display in the cart.
	 *
	 * @param array $item_data Existing item data in the cart.
	 * @param array $cart_item The cart item containing GTRO data.
	 * @return array Updated item data with GTRO metadata.
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['gtro_vehicle'] ) ) {
			$vehicles = explode( ',', $cart_item['gtro_vehicle'] );
			$formatted_vehicles = array();

			// Récupérer les données des véhicules.
			$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );

			foreach ( $vehicles as $vehicle_slug ) {
				foreach ( $available_voitures as $voiture ) {
					if ( sanitize_title( $voiture['modeles'] ) === $vehicle_slug ) {
						$formatted_vehicles[] = $voiture['modeles'];
						break;
					}
				}
			}

			$item_data[] = array(
				'key'   => __( 'Véhicules', 'gtro-product-manager' ),
				'value' => implode( ', ', $formatted_vehicles ),
			);
		}

		// Tours supplémentaires.
		if ( isset( $cart_item['gtro_extra_laps'] ) && $cart_item['gtro_extra_laps'] > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Tours supplémentaires', 'gtro-product-manager' ),
				'value' => $cart_item['gtro_extra_laps'],
			);
		}

		// Date.
		if ( isset( $cart_item['gtro_date'] ) && ! empty( $cart_item['gtro_date'] ) ) {
			$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $cart_item['gtro_date'] ) );
			$item_data[] = array(
				'key'   => __( 'Date', 'gtro-product-manager' ),
				'value' => $formatted_date,
			);
		}

		// Options.
		if ( isset( $cart_item['gtro_options'] ) && ! empty( $cart_item['gtro_options'] ) ) {
			$formatted_options = array();
			$available_options = rwmb_meta( 'options_supplementaires', array( 'object_type' => 'setting' ), 'gtro_options' );

			foreach ( $cart_item['gtro_options'] as $option_slug ) {
				foreach ( $available_options as $option ) {
					if ( sanitize_title( $option['options'] ) === $option_slug ) {
						$formatted_options[] = $option['options'];
						break;
					}
				}
			}

			$item_data[] = array(
				'key'   => __( 'Options', 'gtro-product-manager' ),
				'value' => implode( ', ', $formatted_options ),
			);
		}

		return $item_data;
	}

	/**
	 * Ajoute les métadonnées de l'élément du panier (GTRO) à la commande.
	 *
	 * @param WC_Order_Item_Product $item          L'élément de la commande.
	 * @param string                $cart_item_key La clé de l'élément du panier.
	 * @param array                 $values        Les valeurs du formulaire GTRO.
	 * @param WC_Order              $order         La commande (non utilisé mais requis par WooCommerce).
	 */
	public function checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['gtro_vehicle'] ) ) {
			$vehicles = explode( ',', $values['gtro_vehicle'] );
			$item->add_meta_data(
				__( 'Véhicules', 'gtro-product-manager' ),
				implode( ', ', array_map( 'ucfirst', $vehicles ) )
			);
		}

		if ( isset( $values['gtro_extra_laps'] ) && $values['gtro_extra_laps'] > 0 ) {
			$item->add_meta_data(
				__( 'Tours supplémentaires', 'gtro-product-manager' ),
				$values['gtro_extra_laps']
			);
		}

		if ( isset( $values['gtro_date'] ) && ! empty( $values['gtro_date'] ) ) {
			$item->add_meta_data(
				__( 'Date', 'gtro-product-manager' ),
				$values['gtro_date']
			);
		}

		if ( isset( $values['gtro_options'] ) && ! empty( $values['gtro_options'] ) ) {
			$item->add_meta_data(
				__( 'Options', 'gtro-product-manager' ),
				implode( ', ', array_map( 'ucfirst', $values['gtro_options'] ) )
			);
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

		// Récupérer les voitures avec leurs suppléments de base et catégories.
		$available_voitures = rwmb_meta( 'voitures_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		$vehicles_data      = array();
		foreach ( $available_voitures as $voiture ) {
			if ( isset( $voiture['modeles'] ) && isset( $voiture['categorie'] ) ) {
				$vehicle_key                   = sanitize_title( $voiture['modeles'] );
				$vehicles_data[ $vehicle_key ] = array(
					'supplement_base' => isset( $voiture['supplement_base'] ) ? floatval( $voiture['supplement_base'] ) : 0,
					'categorie'       => $voiture['categorie'],
				);
			}
		}

		// Récupérer les prix par tours pour chaque catégorie.
		$prix_categories = rwmb_meta( 'prix_categories', array( 'object_type' => 'setting' ), 'gtro_options' );
		$category_prices = array();
		foreach ( $prix_categories as $cat ) {
			if ( isset( $cat['categorie'] ) && isset( $cat['prix_tour_sup'] ) ) {
				$category_prices[ $cat['categorie'] ] = floatval( $cat['prix_tour_sup'] );
			}
		}

		// Récupérer les dates et promos.
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

		// Récupérer les options disponibles.
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

		// Récupérer les formules et leurs prix.
		$formules_data = array();
		$formules      = rwmb_meta( 'formules_gtro', array( 'object_type' => 'setting' ), 'gtro_options' );
		if ( ! empty( $formules ) ) {
			foreach ( $formules as $formule ) {
				if ( isset( $formule['nom_formule'] ) && isset( $formule['prix_formule'] ) ) {
					$formule_id                   = sanitize_title( $formule['nom_formule'] );
					$formules_data[ $formule_id ] = floatval( $formule['prix_formule'] );
				}
			}
		}

		// Récupérer le combo sélectionné pour ce produit.
		$selected_combo = get_post_meta( $product_id, '_gtro_combo_promo', true );
		$combo_discount = 0;

		// Récupérer les combos depuis les métaboxes.
		$combos_list = rwmb_meta( 'combos_voitures', array( 'object_type' => 'setting' ), 'gtro_options' );

		// Vérifier si le combo existe et récupérer sa remise.
		if ( ! empty( $combos_list ) && ! empty( $selected_combo ) ) {
			foreach ( $combos_list as $combo ) {
				if ( isset( $combo['nom_promo_combo'] )
					&& sanitize_title( $combo['nom_promo_combo'] ) === $selected_combo
					&& isset( $combo['remise'] )
				) {
					$combo_discount = floatval( $combo['remise'] );
					break;
				}
			}
		}

		wp_localize_script(
			'gtro-public',
			'gtroData',
			array(
				'basePrice'        => floatval( $product->get_price() ),
				'vehiclesData'     => $vehicles_data,
				'categoryPrices'   => $category_prices,
				'datesPromo'       => empty( $dates_with_promos ) ? array() : $dates_with_promos,
				'availableOptions' => empty( $available_options ) ? array() : $available_options,
				'showPriceDetails' => true,
				'maxTours'         => intval( get_post_meta( $product_id, '_gtro_max_tours', true ) ),
				'formulesData'     => $formules_data,
				'comboDiscount'    => $combo_discount, // Assurez-vous que cette valeur est bien passée.
			)
		);

		wp_add_inline_script(
			'gtro-public',
			'
			if (window.history.replaceState) {
				window.history.replaceState(null, null, window.location.href);
			}
			
			// Désactiver la résoumission du formulaire
			window.addEventListener("pageshow", function(event) {
				if (event.persisted) {
					window.location.reload();
				}
			});
		'
		);
	}
}
