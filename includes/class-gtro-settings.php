<?php
namespace GTRO_Plugin;

class GTRO_Settings {


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
		// S'assurer que le hook n'est ajouté qu'une seule fois
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_settings_fields' ), 10, 1 );
		add_filter( 'mb_settings_pages', array( $this, 'register_settings_pages' ), 10, 1 );
		add_action( 'rwmb_after_save_post', array( $this, 'handle_new_group' ), 10, 2 );
	}

	/**
	 * Handle the creation of a new date group after saving post data.
	 *
	 * This function checks if a new date group has been submitted via the
	 * 'nouveau_groupe' POST field. If so, it sanitizes the input and adds
	 * it to the existing date groups option if it doesn't already exist.
	 *
	 * @param int          $post_id The ID of the saved post.
	 * @param WP_Post|null $post    The post object (optional).
	 *
	 * @since 1.0.0
	 */
	public function handle_new_group( $post_id, $post = null ) {
		if ( ! isset( $_POST['nouveau_groupe'] ) || empty( $_POST['nouveau_groupe'] ) ) {
			return;
		}

		$nouveau_groupe    = sanitize_text_field( $_POST['nouveau_groupe'] );
		$groupes_existants = get_option( 'gtro_groupes_dates', array() );

		if ( ! in_array( $nouveau_groupe, $groupes_existants ) ) {
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
				'menu_title'  => __( 'GTRO Settings', 'gtro-product-manager' ),
				'option_name' => 'gtro_options',
				'tabs'        => array(
					'voitures' => __( 'Voitures', 'gtro-product-manager' ),
					'prix'     => __( 'Prix', 'gtro-product-manager' ),
					'formules' => __( 'Formules', 'gtro-product-manager' ),
					'dates'    => __( 'Dates', 'gtro-product-manager' ),
					'options'  => __( 'Options', 'gtro-product-manager' ),
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

		// Éviter la duplication en vérifiant si déjà ajouté
		if ( ! empty( $this->meta_boxes ) ) {
			return $this->meta_boxes;
		}

		// Onglet Voitures
		$meta_boxes[] = array(
			'title'          => __( 'Voitures', 'gtro-product-manager' ),
			'id'             => 'voitures',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'voitures',
			'fields'         => array(
				array(
					'name'   => __( 'Voitures GTRO', 'gtro-product-manager' ),
					'id'     => 'voitures_gtro',
					'type'   => 'group',
					'clone'  => true,
					'fields' => array(
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
							'options' => array(
								'cat1' => __( 'Catégorie 1', 'gtro-product-manager' ),
								'cat2' => __( 'Catégorie 2', 'gtro-product-manager' ),
								'cat3' => __( 'Catégorie 3', 'gtro-product-manager' ),
								'catn' => __( 'Catégorie N', 'gtro-product-manager' ),
							),
						),
					),
				),
			),
		);

		// Onglet Prix
		$meta_boxes[] = array(
			'title'          => __( 'Configuration des prix', 'gtro-product-manager' ),
			'id'             => 'prix-config',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'prix',
			'fields'         => array(
				array(
					'name'   => __( 'Prix par catégorie', 'gtro-product-manager' ),
					'id'     => 'prix_categories',
					'type'   => 'group',
					'clone'  => true,
					'fields' => array(
						array(
							'name'    => __( 'Catégorie', 'gtro-product-manager' ),
							'id'      => 'categorie',
							'type'    => 'select',
							'options' => array(
								'cat1' => __( 'Catégorie 1', 'gtro-product-manager' ),
								'cat2' => __( 'Catégorie 2', 'gtro-product-manager' ),
								'cat3' => __( 'Catégorie 3', 'gtro-product-manager' ),
								'catn' => __( 'Catégorie N', 'gtro-product-manager' ),
							),
						),
						array(
							'name' => __( 'Prix tour supplémentaire', 'gtro-product-manager' ),
							'id'   => 'prix_tour_sup',
							'type' => 'number',
						),
					),
				),
				array(
					'name'   => __( 'Combinaisons multi-voitures', 'gtro-product-manager' ),
					'id'     => 'combos_voitures',
					'type'   => 'group',
					'clone'  => true,
					'fields' => array(
						array(
							'name'    => __( 'Type de combo', 'gtro-product-manager' ),
							'id'      => 'type_combo',
							'type'    => 'select',
							'options' => array(
								'2gt' => __( '2 GT', 'gtro-product-manager' ),
								'3gt' => __( '3 GT', 'gtro-product-manager' ),
								'4gt' => __( '4 GT', 'gtro-product-manager' ),
							),
						),
						array(
							'name' => __( 'Remise (%)', 'gtro-product-manager' ),
							'id'   => 'remise',  // Changé de 'Remise Multi' à 'remise'
							'type' => 'number',
							'step' => '0.5',
							'min'  => '0',
							'max'  => '100',
						),
					),
				),
			),
		);

		// Onglet Formules
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

		// Onglet Dates
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

		// Pour chaque groupe existant, on crée dynamiquement une nouvelle metabox
		$groupes_existants = get_option( 'gtro_groupes_dates', array() ); // Stocke les noms des groupes
		if ( ! empty( $groupes_existants ) ) {
			foreach ( $groupes_existants as $groupe ) {
				$slug         = sanitize_title( $groupe );
				$meta_boxes[] = array(
					'title'          => sprintf( __( 'Dates %s', 'gtro-product-manager' ), $groupe ),
					'id'             => 'dates_' . $slug,
					'settings_pages' => array( 'gtro' ),
					'tab'            => 'dates',
					'fields'         => array(
						array(
							'name'       => sprintf( __( 'Dates disponibles - %s', 'gtro-product-manager' ), $groupe ),
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

		// Onglet Options
		$meta_boxes[] = array(
			'title'          => __( 'Options GTRO', 'gtro-product-manager' ),
			'id'             => 'options-gtro',
			'settings_pages' => array( 'gtro' ),
			'tab'            => 'options',
			'fields'         => array(
				array(
					'name'   => __( 'Options supplémentaires', 'gtro-product-manager' ),
					'id'     => 'options_supplementaires',
					'type'   => 'group',
					'clone'  => true,
					'fields' => array(
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

		return $meta_boxes;
	}
}
