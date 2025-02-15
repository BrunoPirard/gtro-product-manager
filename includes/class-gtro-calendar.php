<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('GTRO_Calendar_Display')):

class GTRO_Calendar_Display {
    private $groupes_existants;

    public function __construct() {
        add_shortcode('display_calendar', array($this, 'calendar_shortcode'));
        $this->groupes_existants = get_option('gtro_groupes_dates', array());
        add_action('wp_enqueue_scripts', array($this, 'enqueue_calendar_styles'));
    }

    public function enqueue_calendar_styles() {
        wp_enqueue_style(
            'gtro-calendar-style',
            GTRO_PLUGIN_URL . 'assets/css/gtro-calendar.css',
            array(),
            GTRO_VERSION
        );
    }

    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'groupe' => 'all',
            'view' => 'month'
        ), $atts);

        $dates = $this->get_all_dates($atts['groupe']);
        return $this->generate_calendar($dates);
    }

    private function get_all_dates($groupe = 'all') {
        // Code existant pour get_all_dates()...
    }

    private function generate_color_from_string($string) {
        // Code existant pour generate_color_from_string()...
    }

    private function get_template_path($template) {
        $default_path = GTRO_PLUGIN_DIR . 'templates/calendar/';
        $theme_path = get_stylesheet_directory() . '/gtro/calendar/';

        if (file_exists($theme_path . $template)) {
            return $theme_path . $template;
        }
        return $default_path . $template;
    }

    private function generate_calendar($dates) {
        ob_start();
        include $this->get_template_path('calendar.php');
        return ob_get_clean();
    }
}

endif;

