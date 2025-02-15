<?php
namespace GTRO_Plugin;

if (!defined('ABSPATH')) exit;

class GTRO_Calendar {

    private $plugin_name;
    private $version;
    private $date_groups = array(
        'monoplace' => array(
            'meta_key' => 'dates_monoplace', // Changé pour correspondre à l'ID dans settings
            'settings_page' => 'gtro_options', // Changé pour utiliser option_name des settings
            'color' => '#ff6b6b',
            'name' => 'Monoplace'
        ),
        'gt' => array(
            'meta_key' => 'dates_gt',
            'settings_page' => 'gtro_options',
            'color' => '#4ecdc4',
            'name' => 'GT'
        )
    );

    /**
     * Initialize the GTRO_Calendar class and set its properties.
     *
     * This constructor sets the plugin name and version, registers the 
     * 'display_calendar' shortcode, and enqueues styles and scripts 
     * for the calendar functionality.
     *
     * @since 1.0.0
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of the plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Enregistrer le shortcode
        add_shortcode('display_calendar', array($this, 'calendar_shortcode'));
        
        // Enregistrer les assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
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
        wp_register_style(
            $this->plugin_name . '-calendar',
            GTRO_PLUGIN_URL . 'public/css/gtro-calendar.css',
            array(),
            $this->version
        );

        wp_register_script(
            $this->plugin_name . '-calendar-js',
            GTRO_PLUGIN_URL . 'public/js/gtro-calendar.js',
            array('jquery'),
            $this->version,
            true
        );
    }

    /**
     * Shortcode pour afficher le calendrier des dates
     *
     * @param array $atts {
     *     Shortcode attributes
     *
     *     @type string $groups Type de dates à afficher (monoplace, gt ou all)
     *     @type string $view Type de vue (month ou list)
     * }
     *
     * @return string HTML du calendrier
     */
    public function calendar_shortcode($atts) {
        // Charger les styles uniquement quand le shortcode est utilisé
        wp_enqueue_style($this->plugin_name . '-calendar');
        wp_enqueue_script($this->plugin_name . '-calendar-js');

        $atts = shortcode_atts(array(
            'groups' => 'all',
            'view' => 'month'
        ), $atts);

        $dates = $this->get_all_dates($atts['groups']);
        return $this->generate_calendar($dates);
    }

    /**
     * Retrieves all dates grouped by type (monoplace or gt)
     *
     * @since 1.0.0
     *
     * @param string $groups If 'all', retrieves all dates. If 'monoplace' or 'gt',
     *                        only retrieves dates for the specified type.
     *
     * @return array An array of dates, each with keys 'date', 'group', 'color', and 'name'.
     *               The 'group' key is either 'monoplace' or 'gt'. The 'color' key is the color
     *               of the date, which is yellow if the date has a promo value greater than 0.
     *               The 'name' key is the name of the group, with a promo value appended if applicable.
     */
    private function get_all_dates($groups = 'all') {
        $all_dates = array();
        
        //error_log('Début get_all_dates avec groups: ' . $groups);
        
        foreach ($this->date_groups as $group_key => $group_info) {
            if ($groups === 'all' || $groups === $group_key) {
                $dates_group = get_option('gtro_options');
                //error_log('Options complètes: ' . print_r($dates_group, true));
                
                if (isset($dates_group[$group_info['meta_key']]) && is_array($dates_group[$group_info['meta_key']])) {
                    foreach ($dates_group[$group_info['meta_key']] as $entry) {
                        if (!empty($entry['date'])) {
                            $date = $entry['date'];
                            $promo_value = isset($entry['promo']) ? intval($entry['promo']) : 0;
                            
                            $all_dates[] = array(
                                'date' => $date,
                                'group' => $group_key,
                                'color' => $promo_value > 0 ? '#FFD700' : $group_info['color'],
                                'name' => $group_info['name'] . ($promo_value > 0 ? sprintf(' (Promo %d%%)', $promo_value) : '')
                            );
                            
                            //error_log("Date ajoutée pour $group_key: $date avec promo: $promo_value");
                        }
                    }
                }
            }
        }
        
        //error_log('Dates finales: ' . print_r($all_dates, true));
        return $all_dates;
    }

    /**
     * Génère un calendrier annuel affichant les événements groupés par mois.
     * 
     * @param array $dates Tableau contenant les événements, avec les clés 'date', 'group', 'color' et 'name'.
     * @return string Le code HTML du calendrier annuel.
     */
    private function generate_calendar($dates) {
        
        $year = date('Y');
        $html = '<div class="custom-calendar-year">';
        
        // Légende des groupes
        $html .= '<div class="calendar-legend">';
        foreach ($this->date_groups as $group_key => $group_info) {
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
        for ($month = 1; $month <= 12; $month++) {
            $firstDay = mktime(0, 0, 0, $month, 1, $year);
            $numberDays = date('t', $firstDay);
            $firstDayOfWeek = date('w', $firstDay);
            
            $html .= '<div class="month-calendar">';
            $html .= '<div class="month-header">';
            $html .= '<h3>' . date('F', $firstDay) . '</h3>';
            $html .= '</div>';

            // En-têtes des jours de la semaine
            $html .= '<div class="calendar-grid">';
            $days = array('D', 'L', 'M', 'M', 'J', 'V', 'S');
            foreach ($days as $day) {
                $html .= '<div class="calendar-header-cell">' . $day . '</div>';
            }

            // Cases du calendrier
            $dayCount = 1;
            $totalCells = $firstDayOfWeek + $numberDays;

            for ($i = 0; $i < ceil($totalCells/7)*7; $i++) {
                if ($i < $firstDayOfWeek || $i >= $totalCells) {
                    $html .= '<div class="calendar-cell empty"></div>';
                } else {
                    $currentDate = date('Y-m-d', mktime(0, 0, 0, $month, $dayCount, $year));
                    $html .= '<div class="calendar-cell">';
                    $html .= $dayCount;

                    // Vérifier si des événements existent pour cette date
                    foreach ($dates as $event) {
                        if ($event['date'] === $currentDate) {
                            $html .= sprintf(
                                '<div class="event-dot" style="background-color: %s" title="%s"></div>',
                                $event['color'],
                                $event['name']
                            );
                        }
                    }

                    $html .= '</div>';
                    $dayCount++;
                }
            }

            $html .= '</div></div>'; // Fin du mois
        }

        $html .= '</div>'; // Fin du calendrier annuel
        return $html;
    }
}
