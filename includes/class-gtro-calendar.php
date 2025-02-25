<?php
/**
 * Classe de gestion du calendrier
 *
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/includes
 * @since      1.0.0
 */

namespace GTRO_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Handles loading for the plugin.
 *
 * @package GTRO_Product_Manager
 * @since 1.0.0
 */
class GTRO_Calendar {

	/**
	 * The name of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $plugin_name    The name of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The date groups of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array    $date_groups    The current version of this plugin.
	 */
	private $date_groups = array();

	/**
	 * Initialize the GTRO_Calendar class and set its properties.
	 *
	 * This constructor sets the plugin name and version, registers the
	 * 'display_calendar' shortcode, and enqueues styles and scripts
	 * for the calendar functionality.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of the plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		// Initialiser les groupes de dates dynamiquement.
		$this->init_date_groups();

		// Enregistrer le shortcode.
		add_shortcode( 'display_calendar', array( $this, 'calendar_shortcode' ) );

		// Enregistrer les assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_action( 'wp_ajax_load_calendar', array( $this, 'ajax_load_calendar' ) );
		add_action( 'wp_ajax_nopriv_load_calendar', array( $this, 'ajax_load_calendar' ) );
	}

	/**
	 * Convertit le nom du mois en anglais en français
	 *
	 * @param string $month_name Nom du mois en anglais.
	 * @return string Nom du mois en français
	 */
	private function translate_month( $month_name ) {
		$months = array(
			'January'   => 'Janvier',
			'February'  => 'Février',
			'March'     => 'Mars',
			'April'     => 'Avril',
			'May'       => 'Mai',
			'June'      => 'Juin',
			'July'      => 'Juillet',
			'August'    => 'Août',
			'September' => 'Septembre',
			'October'   => 'Octobre',
			'November'  => 'Novembre',
			'December'  => 'Décembre',
		);

		return isset( $months[ $month_name ] ) ? $months[ $month_name ] : $month_name;
	}

	/**
	 * Initialiser les groupes de dates dynamiquement
	 *
	 * Cette fonction parcourt les groupes de dates enregistrés dans les options
	 * et les stocke dans un tableau associatif avec des clés de type "monoplace"
	 * et des valeurs contenant les informations suivantes :
	 *
	 * - meta_key : le nom de la clé de métadonnées pour stocker les dates
	 * - settings_page : la page des paramètres où trouver les options de ce groupe
	 * - color : la couleur associée au groupe
	 * - name : le nom du groupe
	 *
	 * @since 1.0.0
	 */
	private function init_date_groups() {
		$this->date_groups = array();
		$groupes_existants = get_option( 'gtro_groupes_dates', array() );

		foreach ( $groupes_existants as $groupe ) {
			$slug = sanitize_title( $groupe );
			// Utilisez un préfixe unique pour la meta_key.
			$meta_key = 'gtro_dates_' . $slug;
			add_filter( 'update_option_' . $meta_key, array( $this, 'maybe_update_dates_index' ), 10, 2 );
			$this->date_groups[ $slug ] = array(
				'meta_key'      => $meta_key,
				'settings_page' => 'gtro_options',
				'color'         => $this->get_group_color( $slug ),
				'name'          => $groupe,
			);
		}
	}

	/**
	 * Get the color associated with a given date group slug.
	 *
	 * This function defines an array of default colors for specific date groups
	 * and returns the color associated with the given slug if it exists. If not,
	 * it generates a color based on the MD5 hash of the slug.
	 *
	 * @param string $slug The slug of the date group.
	 * @return string The color associated with the date group.
	 */
	private function get_group_color( $slug ) {
		$settings = get_option( 'gtro_options' );

		// Chercher d'abord dans les couleurs personnalisées.
		if ( ! empty( $settings['group_colors'] ) ) {
			foreach ( $settings['group_colors'] as $group_color ) {
				if ( $group_color['group_name'] === $slug && ! empty( $group_color['color'] ) ) {
					return $group_color['color'];
				}
			}
		}

		// Couleurs par défaut si aucune couleur personnalisée n'est trouvée.
		$default_colors = array(
			'monoplace' => '#ff6b6b',
			'gt'        => '#4ecdc4',
		);

		return isset( $default_colors[ $slug ] ) ? $default_colors[ $slug ] : '#' . substr( md5( $slug ), 0, 6 );
	}


	/**
	 * Retrieve available date groups for selection options.
	 *
	 * This function fetches the list of date groups stored in the WordPress options
	 * and constructs an associative array. The array keys are the sanitized slugs
	 * of the group names, and the values are the original group names. It includes
	 * a default option for selecting a group.
	 *
	 * @return array An associative array with group slugs as keys and group names
	 *               as values, including a default option.
	 */
	private function get_available_groups_options() {
		$groups  = get_option( 'gtro_groupes_dates', array() );
		$options = array( '' => __( 'Sélectionnez un groupe', 'gtro-product-manager' ) );

		foreach ( $groups as $group ) {
			$slug             = sanitize_title( $group );
			$options[ $slug ] = $group;
		}

		return $options;
	}

	/**
	 * Register the styles and scripts for the calendar.
	 *
	 * This function registers the CSS and JavaScript files required for the calendar
	 * functionality. The styles and scripts are registered with unique handles
	 * incorporating the plugin name and version. The script has a dependency on jQuery
	 * and is set to load in the footer.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		// Enregistrer le CSS.
		wp_register_style(
			$this->plugin_name . '-calendar',
			GTRO_PLUGIN_URL . 'public/css/gtro-calendar.css',
			array(),
			$this->version
		);

		// Enregistrer le JavaScript.
		wp_register_script(
			$this->plugin_name . '-calendar-js',
			GTRO_PLUGIN_URL . 'public/js/gtro-calendar.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localiser le script avec l'URL AJAX.
		wp_localize_script(
			$this->plugin_name . '-calendar-js',
			'gtroAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gtro_calendar_nonce' ),
			)
		);
	}

	/**
	 * Shortcode pour afficher le calendrier des dates
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @type string $groups Type de dates à afficher (monoplace, gt ou all).
	 * @type string $view   Type de vue (month ou list).
	 * @type int    $year   Année à afficher.
	 *
	 * @return string HTML du calendrier
	 */
	public function calendar_shortcode( $atts ) {
		// Charger les styles et scripts nécessaires.
		wp_enqueue_style( $this->plugin_name . '-calendar' );
		wp_enqueue_script( $this->plugin_name . '-calendar-js' );

		// Définir les paramètres par défaut.
		$atts = shortcode_atts(
			array(
				'groups' => 'all',
				'view'   => 'month',
				'year'   => gmdate( 'Y' ),
			),
			$atts
		);

		// Récupérer les dates.
		$dates = $this->get_all_dates( $atts['groups'] );

		// Générer et retourner le calendrier.
		return $this->generate_calendar( $dates, $atts['year'] );
	}

	/**
	 * Handles AJAX request to load and return the calendar for a specified year.
	 *
	 * This method checks for a 'year' parameter in the GET request.
	 * If provided, it retrieves the calendar for that year; otherwise, it defaults
	 * to the current year. The calendar is generated based on all date groups and
	 * returned as a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function ajax_load_calendar() {
		// Vérifier le nonce.
		if ( ! check_ajax_referer( 'gtro_calendar_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
			wp_die();
		}

		$year = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : gmdate( 'Y' );
		// Sanitize l'entrée.
		$year = filter_var( $year, FILTER_VALIDATE_INT );
		if ( ! $year ) {
			$year = gmdate( 'Y' );
		}

		$dates    = $this->get_all_dates( 'all', $year );
		$calendar = $this->generate_calendar( $dates, $year );

		wp_send_json_success( $calendar );
		wp_die();
	}

	/**
	 * Retrieves all dates for a given year, filtered by date groups.
	 *
	 * @param string $groups Type de dates à afficher (monoplace, gt ou all).
	 * @param int    $year   Année à afficher.
	 *
	 * @return array Tableau de dates, chaque date étant un tableau contenant
	 *               les clés date, group, color et name.
	 */
	private function get_all_dates( $groups = 'all', $year = null ) {
		$all_dates = array();

		if ( ! $year ) {
			$year = gmdate( 'Y' );
		}

		// Récupérer les options, y compris la couleur de promotion.
		$settings    = get_option( 'gtro_options' );
		$promo_color = ! empty( $settings['promo_color'] ) ? $settings['promo_color'] : '#FFD700'; // Couleur par défaut si non définie.

		foreach ( $this->date_groups as $group_key => $group_info ) {
			if ( 'all' === $groups || $groups === $group_key ) {
				$dates_group = get_option( 'gtro_options' );

				if ( isset( $dates_group[ $group_info['meta_key'] ] ) ) {
					foreach ( $dates_group[ $group_info['meta_key'] ] as $entry ) {
						if ( ! empty( $entry['date'] ) ) {
							$date_year = gmdate( 'Y', strtotime( $entry['date'] ) );
							if ( $date_year === $year ) {
								// Utiliser la couleur de promotion depuis les paramètres.
								$color = ( isset( $entry['promo'] ) && $entry['promo'] > 0 )
									? $promo_color
									: $group_info['color'];

								// Créer le nom de l'événement.
								$event_name = $group_info['name'];
								if ( isset( $entry['promo'] ) && $entry['promo'] > 0 ) {
									$event_name .= ' (Promo: ' . $entry['promo'] . '%)';
								}

								$all_dates[] = array(
									'date'  => $entry['date'],
									'group' => $group_key,
									'color' => $color,
									'name'  => $event_name,
								);
							}
						}
					}
				}
			}
		}
		return $all_dates;
	}

	/**
	 * Filtrer pour mettre à jour l'index des dates si nécessaire
	 *
	 * Ce filtre est utilisé pour mettre à jour l'index des dates stockées
	 * en base de données. Si des dates sont mises à jour, l'index est recalculé.
	 *
	 * @param array $old_value Valeur précédente du champ des dates.
	 * @param array $new_value Nouvelle valeur du champ des dates.
	 *
	 * @return array Valeur mise à jour du champ des dates.
	 */
	public function maybe_update_dates_index( $old_value, $new_value ) {
		// Logique pour mettre à jour l'index si nécessaire.
		return $new_value;
	}

	/**
	 * Génère le calendrier HTML pour une année donnée
	 *
	 * @since 1.0.0
	 *
	 * @param array $dates Tableau de dates, chaque date étant un tableau
	 *                     contenant les clés 'date', 'group', 'color', et 'name'.
	 * @param int   $year  Année du calendrier.
	 *
	 * @return string HTML du calendrier.
	 */
	private function generate_calendar( $dates, $year ) {
		$html = '<div class="calendar-content">';  // Conteneur principal.

		// 1. Section navigation et légende (statique).
		$html .= '<div class="calendar-header">';
		// Navigation.
		$html .= '<div class="calendar-navigation">';
		$html .= sprintf(
			'<button class="prev-year" data-year="%d">← %d</button>',
			( $year - 1 ),
			( $year - 1 )
		);
		$html .= sprintf( '<h2>%d</h2>', $year );
		$html .= sprintf(
			'<button class="next-year" data-year="%d">%d →</button>',
			( $year + 1 ),
			( $year + 1 )
		);
		$html .= '</div>';

		// Légende.
		$html       .= '<div class="calendar-legend">';
		$settings    = get_option( 'gtro_options' );
		$promo_color = ! empty( $settings['promo_color'] ) ? $settings['promo_color'] : '#FFD700';

		foreach ( $this->date_groups as $group_key => $group_info ) {
			$html .= sprintf(
				'<div class="legend-item"><span class="color-dot" style="background-color: %s"></span> %s</div>',
				$group_info['color'],
				$group_info['name']
			);
			$html .= sprintf(
				'<div class="legend-item"><span class="color-dot" style="background-color: %s"></span> %s (Promo)</div>',
				$promo_color, // Utiliser la couleur de promotion personnalisée.
				$group_info['name']
			);
		}
		$html .= '</div></div>';

		// 2. Section calendrier (partie qui sera mise à jour par AJAX).
		$html .= '<div class="calendar-container">';
		if ( empty( $dates ) ) {
			$html .= '<div class="no-dates-message">Aucune date disponible pour l\'année ' . $year . '</div>';
		}

		$html .= '<div class="custom-calendar-year">';
		// Génération des mois.
		for ( $month = 1; $month <= 12; $month++ ) {
			$first_day         = mktime( 0, 0, 0, $month, 1, $year );
			$number_days       = gmdate( 't', $first_day );
			$first_day_of_week = gmdate( 'w', $first_day );

			$html .= '<div class="month-calendar">';
			$html .= '<div class="month-header">';
			$html .= '<h3>' . $this->translate_month( gmdate( 'F', $first_day ) ) . '</h3>';
			$html .= '</div>';

			// En-têtes des jours de la semaine.
			$html .= '<div class="calendar-grid">';
			$days  = array( 'D', 'L', 'M', 'M', 'J', 'V', 'S' );
			foreach ( $days as $day ) {
				$html .= '<div class="calendar-header-cell">' . $day . '</div>';
			}

			// Cases du calendrier.
			$day_count   = 1;
			$total_cells = $first_day_of_week + $number_days;
			$max_cells   = ceil( $total_cells / 7 ) * 7;

			for ( $i = 0; $i < $max_cells; $i++ ) {
				if ( $i < $first_day_of_week || $i >= $total_cells ) {
					$html .= '<div class="calendar-cell empty"></div>';
				} else {
					$current_date = gmdate( 'Y-m-d', mktime( 0, 0, 0, $month, $day_count, $year ) );
					$html        .= '<div class="calendar-cell">';
					$html        .= $day_count;

					// Vérifier si des événements existent pour cette date.
					foreach ( $dates as $event ) {
						if ( $event['date'] === $current_date ) {
							$html .= sprintf(
								'<div class="event-dot" style="background-color: %s" title="%s"></div>',
								$event['color'],
								$event['name']
							);
						}
					}

					$html .= '</div>';
					++$day_count;
				}
			}

			$html .= '</div></div>'; // Fin du mois.
		}

		$html .= '</div>'; // Fin custom-calendar-year.
		$html .= '</div>'; // Fin calendar-container.

		return $html;
	}
}
