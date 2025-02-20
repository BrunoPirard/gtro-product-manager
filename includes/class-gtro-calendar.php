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
		// Initialiser les groupes de dates dynamiquement
        $this->init_date_groups();

		// Enregistrer le shortcode
		add_shortcode( 'display_calendar', array( $this, 'calendar_shortcode' ) );

		// Enregistrer les assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_action('wp_ajax_load_calendar', array($this, 'ajax_load_calendar'));
    	add_action('wp_ajax_nopriv_load_calendar', array($this, 'ajax_load_calendar'));
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
        $groupes_existants = get_option('gtro_groupes_dates', array());
        
        foreach ($groupes_existants as $groupe) {
            $slug = sanitize_title($groupe);
            $this->date_groups[$slug] = array(
                'meta_key' => 'dates_' . $slug,
                'settings_page' => 'gtro_options',
                'color' => $this->get_group_color($slug), // Vous pouvez définir des couleurs par défaut
                'name' => $groupe
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
    private function get_group_color($slug) {
        // Définir des couleurs par défaut ou les récupérer depuis les options
        $colors = array(
            'monoplace' => '#ff6b6b',
            'gt' => '#4ecdc4',
            // Ajouter d'autres couleurs par défaut si nécessaire
        );
        return isset($colors[$slug]) ? $colors[$slug] : '#' . substr(md5($slug), 0, 6);
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
		// Enregistrer le CSS
		wp_register_style(
			$this->plugin_name . '-calendar',
			GTRO_PLUGIN_URL . 'public/css/gtro-calendar.css',
			array(),
			$this->version
		);

		// Enregistrer le JavaScript
		wp_register_script(
			$this->plugin_name . '-calendar-js',
			GTRO_PLUGIN_URL . 'public/js/gtro-calendar.js',
			array('jquery'),
			$this->version,
			true
		);

		// Localiser le script avec l'URL AJAX
		wp_localize_script(
			$this->plugin_name . '-calendar-js',
			'gtroAjax',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('gtro_calendar_nonce')
			)
		);
	}

	/**
	 * Shortcode pour afficher le calendrier des dates
	 *
	 * @param array $atts {
	 *                    Shortcode attributes
	 *
	 * @type string $groups Type de dates à afficher (monoplace, gt ou all)
	 * @type string $view   Type de vue (month ou list)
	 * @type int    $year   Année à afficher
	 * }
	 *
	 * @return string HTML du calendrier
	 */
	public function calendar_shortcode($atts) {
		// Charger les styles et scripts nécessaires
		wp_enqueue_style($this->plugin_name . '-calendar');
		wp_enqueue_script($this->plugin_name . '-calendar-js');

		// Définir les paramètres par défaut
		$atts = shortcode_atts(
			array(
				'groups' => 'all',
				'view' => 'month',
				'year' => gmdate('Y')
			),
			$atts
		);

		// Récupérer les dates
		$dates = $this->get_all_dates($atts['groups']);
		
		// Générer et retourner le calendrier
		return $this->generate_calendar($dates, $atts['year']);
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
		$year = isset($_GET['year']) ? intval($_GET['year']) : gmdate('Y');
		$dates = $this->get_all_dates('all', $year);
		$calendar = $this->generate_calendar($dates, $year);
		wp_send_json_success($calendar);
		wp_die();
	}

	/**
	 * Retrieves all dates grouped by type (monoplace or gt)
	 *
	 * @since 1.0.0
	 *
	 * @param string $groups If 'all', retrieves all dates. If 'monoplace' or 'gt',
	 *                       only retrieves dates for the specified type.
	 *
	 * @return array An array of dates, each with keys 'date', 'group', 'color', and 'name'.
	 *               The 'group' key is either 'monoplace' or 'gt'. The 'color' key is the color
	 *               of the date, which is yellow if the date has a promo value greater than 0.
	 *               The 'name' key is the name of the group, with a promo value appended if applicable.
	 */
	private function get_all_dates($groups = 'all', $year = null) {
		$all_dates = array();
		$year = $year ?: gmdate('Y');

		foreach ($this->date_groups as $group_key => $group_info) {
			if ($groups === 'all' || $groups === $group_key) {
				$dates_group = get_option('gtro_options');

				if (isset($dates_group[$group_info['meta_key']])) {
					foreach ($dates_group[$group_info['meta_key']] as $entry) {
						if (!empty($entry['date'])) {
							$date_year = date('Y', strtotime($entry['date']));
							if ($date_year == $year) { // Filtrer par année
								// Déterminer la couleur en fonction de la promo
								$color = isset($entry['promo']) && $entry['promo'] > 0 
									? '#FFD700'  // Couleur promo
									: $group_info['color'];  // Couleur normale

								// Créer le nom de l'événement
								$event_name = $group_info['name'];
								if (isset($entry['promo']) && $entry['promo'] > 0) {
									$event_name .= ' (Promo: ' . $entry['promo'] . '%)';
								}

								// Ajouter la date au tableau
								$all_dates[] = array(
									'date' => $entry['date'],
									'group' => $group_key,
									'color' => $color,
									'name' => $event_name
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
	 * Génère un calendrier annuel affichant les événements groupés par mois.
	 *
	 * @param  array $dates Tableau contenant les événements, avec les clés 'date', 'group', 'color' et 'name'.
	 * @return string Le code HTML du calendrier annuel.
	 */
	private function generate_calendar( $dates ) {
		$year = gmdate( 'Y' );
		$html = '<div class="custom-calendar-year">';

		// Ajouter la navigation
		$html .= '<div class="calendar-navigation">';
		$html .= '<button class="prev-year" data-year="'.($year-1).'">← '.($year-1).'</button>';
		$html .= '<h2>'.$year.'</h2>';
		$html .= '<button class="next-year" data-year="'.($year+1).'">'.($year+1).' →</button>';
		$html .= '</div>';

		// Légende des groupes
		$html .= '<div class="calendar-legend">';
		foreach ( $this->date_groups as $group_key => $group_info ) {
			// Légende normale
			$html .= sprintf(
				'<div class="legend-item"><span class="color-dot" style="background-color: %s"></span> %s</div>',
				$group_info['color'],
				$group_info['name']
			);
			// Légende promo
			$html .= sprintf(
				'<div class="legend-item"><span class="color-dot" style="background-color: %s"></span> %s (Promo)</div>',
				'#FFD700',
				$group_info['name']
			);
		}
		$html .= '</div>';

		// Générer un calendrier pour chaque mois
		for ( $month = 1; $month <= 12; $month++ ) {
			$first_day         = mktime( 0, 0, 0, $month, 1, $year );
			$number_days       = gmdate( 't', $first_day );
			$first_day_of_week = gmdate( 'w', $first_day );

			$html .= '<div class="month-calendar">';
			$html .= '<div class="month-header">';
			$html .= '<h3>' . date( 'F', $first_day ) . '</h3>';
			$html .= '</div>';

			// En-têtes des jours de la semaine
			$html .= '<div class="calendar-grid">';
			$days  = array( 'D', 'L', 'M', 'M', 'J', 'V', 'S' );
			foreach ( $days as $day ) {
				$html .= '<div class="calendar-header-cell">' . $day . '</div>';
			}

			// Cases du calendrier
			$day_count   = 1;
			$total_cells = $first_day_of_week + $number_days;

			for ( $i = 0; $i < ceil( $total_cells / 7 ) * 7; $i++ ) {
				if ( $i < $first_day_of_week || $i >= $total_cells ) {
					$html .= '<div class="calendar-cell empty"></div>';
				} else {
					$current_date = gmdate( 'Y-m-d', mktime( 0, 0, 0, $month, $day_count, $year ) );
					$html        .= '<div class="calendar-cell">';
					$html        .= $day_count;

					// Vérifier si des événements existent pour cette date
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

			$html .= '</div></div>'; // Fin du mois
		}

		$html .= '</div>'; // Fin du calendrier annuel
		return $html;
	}
}
