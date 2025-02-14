<?php
namespace GTRO_Plugin;

class GTRO_WooCommerce {
    public function __construct() {
        add_action('woocommerce_product_data_tabs', [$this, 'add_gtro_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_gtro_product_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_gtro_product_options']);
        
        // Ajout des hooks pour le front-end
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_gtro_options']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_gtro_options_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_gtro_options_in_cart'], 10, 2);

        add_shortcode('gtro_product_options', [$this, 'display_gtro_options_shortcode']);
        add_action('wp_head', [$this, 'add_custom_styles']);
        // Debug hook
        add_action('admin_init', [$this, 'debug_meta_box_data']);
    }

    public function display_gtro_options_shortcode() {
        ob_start();
        $this->display_gtro_options();
        return ob_get_clean();
    }

    public function debug_meta_box_data() {
        error_log('=== Début Debug GTRO ===');
        
        // Debug des groupes de dates
        $groupes = get_option('gtro_groupes_dates', []);
        error_log('Groupes de dates : ' . print_r($groupes, true));
        
        // Pour chaque groupe, afficher ses dates
        foreach ($groupes as $groupe) {
            $dates = rwmb_meta('dates_' . sanitize_title($groupe), ['object_type' => 'setting'], 'gtro');
            error_log('Dates pour ' . $groupe . ' : ' . print_r($dates, true));
        }
        
        error_log('=== Fin Debug GTRO ===');
    }


    public function add_gtro_product_tab($tabs) {
        $tabs['gtro_options'] = array(
            'label'    => __('GTRO Options', 'gtro-product-manager'),
            'target'   => 'gtro_options_product_data',
            'class'    => array('hide_if_grouped', 'hide_if_external'),
            'priority' => 90
        );
        return $tabs;
    }

    public function add_gtro_product_panel() {
        global $post;
        ?>
        <div id="gtro_options_product_data" class="panel woocommerce_options_panel">
            <?php
            // Section des voitures disponibles
            echo '<div class="options-group">';
            echo '<h4>' . __('Voitures disponibles', 'gtro-product-manager') . '</h4>';

            // Récupérer les voitures
            $available_voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro_options');

            if (!empty($available_voitures)) {
                foreach ($available_voitures as $voiture) {
                    if (isset($voiture['modeles'])) {
                        $voiture_id = '_gtro_voiture_' . sanitize_title($voiture['modeles']);
                        woocommerce_wp_checkbox([
                            'id'          => $voiture_id,
                            'label'       => $voiture['modeles'],
                            'description' => sprintf(__('Catégorie: %s', 'gtro-product-manager'), $voiture['categorie']),
                            'value'       => get_post_meta($post->ID, $voiture_id, true)
                        ]);
                    }
                }
            }
            echo '</div>';

            // Sélection du groupe de dates
            woocommerce_wp_select([
                'id'      => '_gtro_date_group',
                'label'   => __('Groupe de dates', 'gtro-product-manager'),
                'options' => $this->get_gtro_date_groups()
            ]);

            // Nombre maximum de tours
            woocommerce_wp_text_input([
                'id'          => '_gtro_max_tours',
                'label'       => __('Nombre maximum de tours', 'gtro-product-manager'),
                'type'        => 'number',
                'desc_tip'    => true,
                'description' => __('Nombre maximum de tours autorisés', 'gtro-product-manager'),
                'custom_attributes' => [
                    'min'  => '1',
                    'step' => '1'
                ]
            ]);

            // Section des options disponibles
            echo '<div class="options-group">';
            echo '<h4>' . __('Options disponibles', 'gtro-product-manager') . '</h4>';

            $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro_options');

            if (!empty($available_options)) {
                foreach ($available_options as $option) {
                    if (isset($option['options']) && isset($option['prix_options'])) {
                        $option_id = '_gtro_option_' . sanitize_title($option['options']);
                        woocommerce_wp_checkbox([
                            'id'          => $option_id,
                            'label'       => $option['options'],
                            'description' => sprintf(__('Prix: %s€', 'gtro-product-manager'), $option['prix_options']),
                            'value'       => get_post_meta($post->ID, $option_id, true)
                        ]);
                    }
                }
            }
            echo '</div>';
            ?>
        </div>
        <?php
    }
   
   public function save_gtro_product_options($post_id) {
        // 1. D'abord, marquer toutes les options existantes comme "no"
        $available_voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro_options');
        $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro_options');

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

    private function get_gtro_date_groups() {
        $groupes = get_option('gtro_groupes_dates', []);
        $options = ['' => __('Sélectionner un groupe', 'gtro-product-manager')];
        
        foreach ($groupes as $groupe) {
            $options[sanitize_title($groupe)] = $groupe;
        }
        
        return $options;
    }

    public function display_gtro_options() {
        global $product;
        
        // Récupérer les voitures activées
        $available_voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro_options');
        $voitures_activees = [];
        
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
        
        // Afficher le sélecteur de voitures
        echo '<div class="gtro-vehicle-selection">';
        echo '<h3>' . __('Sélection du véhicule', 'gtro-product-manager') . '</h3>';
        echo '<select name="gtro_vehicle" required>';
        echo '<option value="">' . __('Choisissez votre véhicule', 'gtro-product-manager') . '</option>';
        
        foreach ($voitures_activees as $voiture) {
            echo '<option value="' . esc_attr(sanitize_title($voiture['modeles'])) . '" 
                data-category="' . esc_attr($voiture['categorie']) . '">' 
                . esc_html($voiture['modeles']) 
                . '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // 3. Tours supplémentaires
        // Récupérer le nombre max de tours
        $max_tours = get_post_meta($product->get_id(), '_gtro_max_tours', true);
        error_log('Nombre max de tours : ' . $max_tours);
        echo '<div class="gtro-extra-laps">';
        echo '<h3>' . __('Tours supplémentaires', 'gtro-product-manager') . '</h3>';
        echo '<input type="number" name="gtro_extra_laps" value="0" min="0" max="10">';
        echo '</div>';

        // 4. Sélecteur de dates
        // Récupérer le groupe de dates sélectionné pour ce produit
        $selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
        error_log('Groupe de dates sélectionné : ' . $selected_group);

        if (!empty($selected_group)) {
            // Récupérer les dates du groupe depuis les settings
            $dates = rwmb_meta('dates_' . sanitize_title($selected_group), ['object_type' => 'setting'], 'gtro_options');
            error_log('Dates du groupe ' . $selected_group . ' : ' . print_r($dates, true));
            
            echo '<div class="gtro-date-selection">';
            echo '<h3>' . __('Choisir une date', 'gtro-product-manager') . '</h3>';
            echo '<select name="gtro_date" required>';
            echo '<option value="">' . __('Sélectionnez une date', 'gtro-product-manager') . '</option>';
            
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
    }

    public function add_gtro_options_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['gtro_options'])) {
            $cart_item_data['gtro_options'] = array();
            foreach ($_POST['gtro_options'] as $option_name => $price) {
                $cart_item_data['gtro_options'][] = array(
                    'name' => $option_name,
                    'price' => floatval($price)
                );
                // Ajouter le prix de l'option au prix total
                if (!isset($cart_item_data['gtro_total_extra'])) {
                    $cart_item_data['gtro_total_extra'] = 0;
                }
                $cart_item_data['gtro_total_extra'] += floatval($price);
            }
        }
        return $cart_item_data;
    }

    public function display_gtro_options_in_cart($item_data, $cart_item) {
        if (isset($cart_item['gtro_options'])) {
            foreach ($cart_item['gtro_options'] as $option_id => $price) {
                $item_data[] = array(
                    'key'   => __('Option', 'gtro-product-manager'),
                    'value' => $option_id . ' (+' . wc_price($price) . ')'
                );
            }
        }
        return $item_data;
    }

    public function add_custom_styles() {
        ?>
        <style>
            .gtro-vehicle-selection,
            .gtro-laps-selection,
            .gtro-options {
                margin-bottom: 20px;
            }
            
            .gtro-vehicle-select {
                width: 100%;
                max-width: 300px;
                padding: 8px;
            }
            
            .gtro-option {
                margin-bottom: 10px;
            }
            
            .quantity input[name="gtro_extra_laps"] {
                width: 80px;
            }
        </style>
        <?php
    }
}
