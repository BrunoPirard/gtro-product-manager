<?php
/**
 * The settigs functionality of the plugin.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/admin
 */

namespace GTRO_Plugin;

/**
 * Class Settings
 *
 * Handles settings for the plugin.
 *
 * @package GTRO_Product_Manager
 * @since 1.0.0
 */
class GTRO_Settings {

	/**
	 * The meta boxes of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array    $meta_boxes    The meta boxes.
	 */
	private $meta_boxes = array();

	/**
	 * Enregistre les champs de réglage pour la page de paramètres.
	 *
	 * Les champs sont enregistrés en utilisant le hook "rwmb_meta_boxes"
	 * et la page de paramètres est enregistrée en utilisant le hook
	 * "mb_settings_pages". On ajoute également un hook pour gérer le
	 * cas où un utilisateur ajoute un nouveau groupe de dates.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// S'assurer que le hook n'est ajouté qu'une seule fois.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_settings_fields' ), 10, 1 );
		add_filter( 'mb_settings_pages', array( $this, 'register_settings_pages' ), 10, 1 );
		add_action( 'rwmb_after_save_post', array( $this, 'handle_new_group' ), 10, 2 );

		// Ajouter un filtre pour mettre à jour dynamiquement les options des selects.
		add_filter( 'rwmb_select_options', array( $this, 'update_category_options' ), 10, 2 );
	}

	/**
	 * Updates the options for category and combo select fields.
	 *
	 * @param  array $options The original options for the select field.
	 * @param  array $field   The field data containing the field ID.
	 * @return array Updated options if the field is category or combo related, otherwise
	 *               the original options.
	 */
	public function update_category_options( $options, $field ) {
		// Vérifier si c'est un champ de catégorie.
		if ( 'categorie' === $field['id'] ) {
			return $this->get_categories_options();
		}

		// Vérifier si c'est un champ de combo.
		if ( 'nom_promo_combo' === $field['id'] ) {
			return $this->get_combos_options();
		}

		return $options;
	}

	/**
	 * Handle the submission of a new group name.
	 *
	 * @since 1.0.0
	 */
	public function handle_new_group() {
		// Vérification du nonce.
		if ( ! isset( $_POST['gtro_new_group_nonce'] ) ) {
			return;
		}

		// Sanitize et unslash le nonce.
		$nonce = sanitize_text_field( wp_unslash( $_POST['gtro_new_group_nonce'] ) );

		// Vérification du nonce.
		if ( ! wp_verify_nonce( $nonce, 'gtro_new_group_action' ) ) {
			return;
		}

		// Vérification de l'existence des données.
		if ( ! isset( $_POST['nouveau_groupe'] ) || empty( $_POST['nouveau_groupe'] ) ) {
			return;
		}

		// Nettoyage et validation des données.
		$nouveau_groupe = sanitize_text_field( wp_unslash( $_POST['nouveau_groupe'] ) );

		// Traitement des données.
		$groupes_existants = get_option( 'gtro_groupes_dates', array() );
		if ( ! in_array( $nouveau_groupe, $groupes_existants, true ) ) {
			$groupes_existants[] = $nouveau_groupe;
			update_option( 'gtro_groupes_dates', $groupes_existants );
		}
	}

	/**
	 * Register the settings page for GTRO.
	 *
	 * This function checks if the GTRO settings page has already been
	 * registered. If not, it adds it to the list of registered settings
	 * pages. The page includes the following tabs: Dates, Voitures, Prix
	 * and Options.
	 *
	 * @param  array $settings_pages The list of registered settings pages.
	 * @return array The updated list of registered settings pages.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_pages( $settings_pages ) {
		if ( ! isset( $settings_pages['gtro'] ) ) {
			$settings_pages['gtro'] = array(
				'id'          => 'gtro',
				'menu_title'  => __( 'Réglages GTRO', 'gtro-product-manager' ),
				'option_name' => 'gtro_options',
				'tabs'        => array(
					'voitures'         => __( 'Voitures', 'gtro-product-manager' ),
					'prix'             => __( 'Prix', 'gtro-product-manager' ),
					'formules'         => __( 'Formules', 'gtro-product-manager' ),
					'dates'            => __( 'Dates', 'gtro-product-manager' ),
					'options'          => __( 'Options', 'gtro-product-manager' ),
					'categories'       => array(
						'label' => __( 'Catégories et combos', 'gtro-product-manager' ),
						'icon'  => 'dashicons-category',
					),
					// Ajout de l'onglet personnalisation dans le tableau 'tabs'.
					'personnalisation' => array(
						'label' => __( 'Personnalisation', 'gtro-product-manager' ),
						'icon'  => 'dashicons-admin-customizer',
					),
				),
			);
		}
		return $settings_pages;
	}

	/**
	 * Register the settings fields for the GTRO settings page.
	 *
	 * This function dynamically registers meta boxes for the GTRO settings page
	 * in the WordPress admin area. It includes tabs for managing dates, vehicles,
	 * pricing configurations, and additional options. The function also ensures
	 * that existing date groups and their respective meta boxes are added
	 * dynamically based on saved options.
	 *
	 * @param  array $meta_boxes The array of registered meta boxes.
	 * @return array The updated array of registered meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_fields( $meta_boxes ) {

		// Éviter la duplication en vérifiant si déjà ajouté.
		if ( ! empty( $this->meta_boxes ) ) {
			return $this->meta_boxes;
		}

		// Onglet Voitures.
		$meta_boxes[] = array(
			'title'          => __( 'Voitures', 'gtro-product-manager' ),
			'id'             => 'voitures',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'voitures',
			'fields'         => array(
				array(
					'name'          => __( 'Voitures GTRO', 'gtro-product-manager' ),
					'id'            => 'voitures_gtro',
					'type'          => 'group',
					'clone'         => true,
					'sort_clone'    => true,
					'clone_default' => false,
					'fields'        => array(
						array(
							'name' => __( 'Modèles', 'gtro-product-manager' ),
							'id'   => 'modeles',
							'type' => 'text',
						),
						array(
							'name' => __( 'Image voiture', 'gtro-product-manager' ),
							'id'   => 'image_voiture',
							'type' => 'single_image',
						),
						array(
							'name' => __( 'Supplément base', 'gtro-product-manager' ),
							'id'   => 'supplement_base',
							'type' => 'number',
						),
						array(
							'name'    => __( 'Catégorie', 'gtro-product-manager' ),
							'id'      => 'categorie',
							'type'    => 'select',
							'options' => $this->get_categories_options(),
						),
					),
				),
			),
		);

		// Onglet Prix.
		$meta_boxes[] = array(
			'title'          => __( 'Configuration des prix', 'gtro-product-manager' ),
			'id'             => 'prix-config',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'prix',
			'fields'         => array(
				array(
					'name'          => __( 'Prix par catégorie', 'gtro-product-manager' ),
					'id'            => 'prix_categories',
					'type'          => 'group',
					'clone'         => true,
					'sort_clone'    => true,
					'clone_default' => false,
					'fields'        => array(
						array(
							'name'    => __( 'Catégorie', 'gtro-product-manager' ),
							'id'      => 'categorie',
							'type'    => 'select',
							'options' => $this->get_categories_options(),
						),
						array(
							'name' => __( 'Prix tour supplémentaire', 'gtro-product-manager' ),
							'id'   => 'prix_tour_sup',
							'type' => 'number',
						),
					),
				),
				array(
					'name'          => __( 'Combinaisons multi-voitures', 'gtro-product-manager' ),
					'id'            => 'combos_voitures',
					'type'          => 'group',
					'clone'         => true,
					'sort_clone'    => true,
					'clone_default' => false,
					'fields'        => array(
						array(
							'name'    => __( 'Nom promo combo', 'gtro-product-manager' ),
							'id'      => 'nom_promo_combo',
							'type'    => 'select',
							'options' => $this->get_combos_options(),
						),
						array(
							'name' => __( 'Remise (%)', 'gtro-product-manager' ),
							'id'   => 'remise',
							'type' => 'number',
							'step' => '0.5',
							'min'  => '0',
							'max'  => '100',
						),
					),
				),
			),
		);

		// Onglet Formules.
		$meta_boxes[] = array(
			'title'          => __( 'Formules GTRO', 'gtro-product-manager' ),
			'id'             => 'formules',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'formules',
			'fields'         => array(
				array(
					'name'       => __( 'Liste des formules', 'gtro-product-manager' ),
					'id'         => 'formules_list',
					'type'       => 'group',
					'clone'      => true,
					'sort_clone' => true,
					'fields'     => array(
						array(
							'name' => __( 'Nom de la formule', 'gtro-product-manager' ),
							'id'   => 'nom_formule',
							'type' => 'text',
						),
						array(
							'name'   => __( 'Options formule', 'gtro-product-manager' ),
							'id'     => 'options_formule',
							'type'   => 'group',
							'clone'  => true,
							'fields' => array(
								array(
									'name' => __( 'Nom de l\'option', 'gtro-product-manager' ),
									'id'   => 'nom_option_formule',
									'type' => 'text',
								),
								array(
									'name' => __( 'Prix formule', 'gtro-product-manager' ),
									'id'   => 'prix_formule',
									'type' => 'number',
								),
							),
						),
					),
				),
			),
		);

		// Onglet Dates.
		$meta_boxes[] = array(
			'title'          => __( 'Dates GTRO', 'gtro-product-manager' ),
			'id'             => 'dates',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'dates',
			'fields'         => array(
				array(
					'name' => __( 'Créer un nouveau groupe de dates', 'gtro-product-manager' ),
					'id'   => 'nouveau_groupe',
					'type' => 'text',
					'desc' => __( 'Entrez un nom pour créer un nouveau groupe de dates (ex: Monoplace, GT)', 'gtro-product-manager' ),
				),
			),
		);

		// Pour chaque groupe existant, on crée dynamiquement une nouvelle metabox.
		$groupes_existants = get_option( 'gtro_groupes_dates', array() ); // Stocke les noms des groupes.
		if ( ! empty( $groupes_existants ) ) {
			foreach ( $groupes_existants as $groupe ) {
				$slug = sanitize_title( $groupe );

				$meta_boxes[] = array(
					// translators: %s: Nom du groupe de dates.
					'title'          => sprintf( esc_html__( 'Dates %s', 'gtro-product-manager' ), $groupe ),
					'id'             => 'dates_' . $slug,
					'settings_pages' => array( 'gtro' ),
					'tab'            => 'dates',
					// translators: %s: Groupe de dates.
					'fields'         => array(
						array(
							// translators: %s: Nom du groupe de dates.
							'name'       => sprintf( esc_html__( 'Dates disponibles - %s', 'gtro-product-manager' ), $groupe ),
							'id'         => 'dates_' . $slug,
							'type'       => 'group',
							'clone'      => true,
							'sort_clone' => true,
							'fields'     => array(
								array(
									'name' => __( 'Date', 'gtro-product-manager' ),
									'id'   => 'date',
									'type' => 'date',
								),
								array(
									'name' => __( 'Promo (%)', 'gtro-product-manager' ),
									'id'   => 'promo',
									'type' => 'number',
									'min'  => 0,
									'max'  => 100,
								),
							),
						),
					),
				);
			}
		}

		// Onglet Options.
		$meta_boxes[] = array(
			'title'          => __( 'Options GTRO', 'gtro-product-manager' ),
			'id'             => 'options-gtro',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'options',
			'fields'         => array(
				array(
					'name'          => __( 'Options supplémentaires', 'gtro-product-manager' ),
					'id'            => 'options_supplementaires',
					'type'          => 'group',
					'clone'         => true,
					'sort_clone'    => true,
					'clone_default' => false,
					'fields'        => array(
						array(
							'name' => __( 'Options', 'gtro-product-manager' ),
							'id'   => 'options',
							'type' => 'text',
						),
						array(
							'name' => __( 'Prix options', 'gtro-product-manager' ),
							'id'   => 'prix_options',
							'type' => 'number',
						),
					),
				),
			),
		);

		// Onglet Categories.
		$meta_boxes[] = array(
			'title'          => __( 'Gestion des catégories et combos', 'gtro-product-manager' ),
			'id'             => 'categories',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'categories',
			'fields'         => array(
				array(
					'name'       => __( 'Liste des catégories', 'gtro-product-manager' ),
					'id'         => 'categories_list',
					'type'       => 'group',
					'clone'      => true,
					'sort_clone' => true,
					'fields'     => array(
						array(
							'name' => __( 'Nom de la catégorie', 'gtro-product-manager' ),
							'id'   => 'nom_categorie',
							'type' => 'text',
						),
					),
				),
				array(
					'name'       => __( 'Liste des combos', 'gtro-product-manager' ),
					'id'         => 'combos_list',
					'type'       => 'group',
					'clone'      => true,
					'sort_clone' => true,
					'fields'     => array(
						array(
							'name' => __( 'Nom du combo', 'gtro-product-manager' ),
							'id'   => 'nom_combo',
							'type' => 'text',
						),
					),
				),
			),
		);

		// Onglet Personnalisation.
		$meta_boxes[] = array(
			'title'          => __( 'Personnalisation GTRO', 'gtro-product-manager' ),
			'id'             => 'personnalisation',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'personnalisation',
			'fields'         => array(
				// Section Documentation.
				array(
					'type' => 'custom_html',
					'std'  => '
						<div class="gtro-docs" style="background: #fff; padding: 20px; border-left: 4px solid #2271b1;">
							<h3 style="margin : 0px 0px 0px 0px">' . __( 'Documentation', 'gtro-product-manager' ) . '</h3>
							<h4>' . __( 'Shortcodes disponibles :', 'gtro-product-manager' ) . '</h4>
							<code>[display_calendar]</code> - ' . __( 'Affiche le calendrier des dates', 'gtro-product-manager' ) . '<br>
							<code>[display_calendar groups="monoplace"]</code> - ' . __( 'Affiche le calendrier des dates par groupe', 'gtro-product-manager' ) . '<br>
							</br><h4>' . __( 'Fonctions disponibles :', 'gtro-product-manager' ) . '</h4>
							<code>get_promo_dates($group_slugs = null)</code> - ' . __( 'Récupère les dates en promotion', 'gtro-product-manager' ) . '<br>
							<code>get_available_groups()</code> - ' . __( 'Récupère la liste des groupes disponibles', 'gtro-product-manager' ) . '
						</div>
					',
				),

				// Couleurs des groupes de dates.
				array(
					'name'   => __( 'Couleurs des groupes de dates', 'gtro-product-manager' ),
					'id'     => 'group_colors',
					'type'   => 'group',
					'clone'  => true,
					'fields' => array(
						array(
							'name'    => __( 'Groupe de dates', 'gtro-product-manager' ),
							'id'      => 'group_name',
							'type'    => 'select',
							'options' => $this->get_available_groups_options(),
						),
						array(
							'name'    => __( 'Couleur', 'gtro-product-manager' ),
							'id'      => 'color',
							'type'    => 'color',
							'default' => '#000000',
						),
					),
				),

				// Couleur des promotions.
				array(
					'name'    => __( 'Couleur des dates en promotion', 'gtro-product-manager' ),
					'id'      => 'promo_color',
					'type'    => 'color',
					'default' => '#FFD700',
					'desc'    => __( 'Choisissez la couleur pour les dates en promotion dans le calendrier', 'gtro-product-manager' ),
				),

				// Suppression des données.
				array(
					'name' => __( 'Suppression des données', 'gtro-product-manager' ),
					'id'   => 'delete_data',
					'type' => 'checkbox',
					'desc' => __( 'Supprimer toutes les données du plugin lors de sa désinstallation', 'gtro-product-manager' ),
				),
			),
		);

		return $meta_boxes;
	}

	/**
	 * Retrieve category options from the settings.
	 *
	 * This function fetches the list of categories stored in the GTRO settings
	 * and constructs an associative array of category slugs mapped to their names.
	 * Each category name is sanitized to create a unique slug.
	 *
	 * @return array An associative array of category slugs as keys and their
	 *               respective names as values.
	 */
	private function get_categories_options() {
		$options = array( '' => __( 'Sélectionnez une catégorie', 'gtro-product-manager' ) );

		// Récupérer les catégories depuis les options.
		$settings = get_option( 'gtro_options' );

		if ( ! empty( $settings['categories_list'] ) ) {
			foreach ( $settings['categories_list'] as $categorie ) {
				if ( ! empty( $categorie['nom_categorie'] ) ) {
					$slug             = sanitize_title( $categorie['nom_categorie'] );
					$options[ $slug ] = $categorie['nom_categorie'];
				}
			}
		}

		return $options;
	}

	/**
	 * Récupère la liste des combos disponibles
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
	 * Récupère les dates et leurs promotions associées pour un groupe de dates
	 *
	 * @param  string $groupe_name Nom du groupe de dates.
	 * @return array Tableau des dates avec leurs promotions
	 */
	public function gtro_get_dates_with_promo( $groupe_name ) {
		$slug             = sanitize_title( $groupe_name );
		$dates            = rwmb_get_value( 'dates_' . $slug, array( 'object_type' => 'setting' ), 'gtro' );
		$dates_with_promo = array();

		if ( ! empty( $dates ) ) {
			foreach ( $dates as $date_entry ) {
				if ( isset( $date_entry['promo'] ) && $date_entry['promo'] > 0 ) {
					$dates_with_promo[] = array(
						'date'  => $date_entry['date'],
						'promo' => $date_entry['promo'],
					);
				}
			}
		}

		return $dates_with_promo;
	}

	/**
	 * Récupère la liste des groupes disponibles pour les options de sélection
	 *
	 * @return array Liste des groupes sous forme de tableau associatif
	 */
	private function get_available_groups_options() {
		$groups  = get_option( 'gtro_groupes_dates', array() );
		$options = array( '' => __( 'Sélectionnez un groupe', 'gtro-product-manager' ) );

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$slug             = sanitize_title( $group );
				$options[ $slug ] = $group;
			}
		}

		return $options;
	}

	/**
	 * Vérifie si les données doivent être supprimées lors de la désinstallation
	 *
	 * @return bool
	 */
	public function should_delete_data() {
		$settings = get_option( 'gtro_options' );
		return ! empty( $settings['delete_data'] );
	}
}
