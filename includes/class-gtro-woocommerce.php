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
        
        // Debug hook
        add_action('admin_init', [$this, 'debug_meta_box_data']);
    }

    // Changez private en public
    public function debug_meta_box_data() {
        error_log('=== Début Debug GTRO ===');
        
        // Debug des settings
        $settings = get_option('gtro');
        error_log('Settings GTRO : ' . print_r($settings, true));
        
        // Debug des options supplémentaires
        $options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro');
        error_log('Options supplémentaires : ' . print_r($options, true));
        
        // Debug des voitures
        $voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro');
        error_log('Voitures GTRO : ' . print_r($voitures, true));
        
        error_log('=== Fin Debug GTRO ===');
    }

    public function add_gtro_product_panel() {
        global $post;
        
        // Debug direct dans le panneau
        echo '<!-- Début Debug -->';
        echo '<div style="display:none;">';
        echo '<h4>Debug Data</h4>';
        echo '<pre>';
        echo 'Settings GTRO : ';
        print_r(get_option('gtro'));
        echo "\n\nOptions supplémentaires : ";
        print_r(rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro'));
        echo '</pre>';
        echo '</div>';
        echo '<!-- Fin Debug -->';

        global $post;
        ?>
        <div id="gtro_options_product_data" class="panel woocommerce_options_panel">
            <?php
            // Sélection de la catégorie
            woocommerce_wp_select([
                'id'      => '_gtro_category',
                'label'   => __('Catégorie GTRO', 'gtro-product-manager'),
                'options' => $this->get_gtro_categories()
            ]);

            // Sélection du groupe de dates
            woocommerce_wp_select([
                'id'      => '_gtro_date_group',
                'label'   => __('Groupe de dates', 'gtro-product-manager'),
                'options' => $this->get_gtro_date_groups()
            ]);

            // Dans la méthode add_gtro_product_panel()
            woocommerce_wp_text_input([
                'id'          => '_gtro_max_tours',
                'label'       => __('Nombre maximum de tours', 'gtro-product-manager'),
                'type'        => 'number',
                'desc_tip'    => true,
                'description' => __('Nombre maximum de tours autorisés pour ce véhicule', 'gtro-product-manager'),
                'custom_attributes' => [
                    'min'  => '1',
                    'step' => '1'
                ]
            ]);

            woocommerce_wp_text_input([
                'id'          => '_gtro_prix_tour',
                'label'       => __('Prix par tour supplémentaire', 'gtro-product-manager'),
                'type'        => 'number',
                'desc_tip'    => true,
                'description' => __('Prix pour chaque tour supplémentaire', 'gtro-product-manager'),
                'custom_attributes' => [
                    'min'  => '0',
                    'step' => '0.01'
                ]
            ]);

            // Section des options disponibles avec debug amélioré
            echo '<div class="options-group">';
            echo '<h4>' . __('Options disponibles', 'gtro-product-manager') . '</h4>';

            $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro');
            
            if (empty($available_options)) {
                echo '<p>Aucune option trouvée. Données brutes :</p>';
                echo '<pre style="display:none;">';
                print_r($available_options);
                echo '</pre>';
            } else {
                foreach ($available_options as $option) {
                    woocommerce_wp_checkbox([
                        'id'          => '_gtro_option_' . sanitize_title($option['options']),
                        'label'       => $option['options'],
                        'description' => isset($option['prix_options']) ? sprintf(__('Prix: %s€', 'gtro-product-manager'), $option['prix_options']) : '',
                        'value'       => get_post_meta($post->ID, '_gtro_option_' . sanitize_title($option['options']), true)
                    ]);
                }
            }
            echo '</div>';

            ?>
        </div>
        <?php
    }

    public function save_gtro_product_options($post_id) {
        $category = isset($_POST['_gtro_category']) ? sanitize_text_field($_POST['_gtro_category']) : '';
        update_post_meta($post_id, '_gtro_category', $category);

        $date_group = isset($_POST['_gtro_date_group']) ? sanitize_text_field($_POST['_gtro_date_group']) : '';
        update_post_meta($post_id, '_gtro_date_group', $date_group);

        $has_options = isset($_POST['_gtro_has_options']) ? 'yes' : 'no';
        update_post_meta($post_id, '_gtro_has_options', $has_options);

        // Sauvegarder le nombre maximum de tours
        if (isset($_POST['_gtro_max_tours'])) {
            update_post_meta($post_id, '_gtro_max_tours', absint($_POST['_gtro_max_tours']));
        }

        // Sauvegarder le prix par tour
        if (isset($_POST['_gtro_prix_tour'])) {
            update_post_meta($post_id, '_gtro_prix_tour', wc_format_decimal($_POST['_gtro_prix_tour']));
        }

        // Sauvegarder les options sélectionnées
        $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro');
        if (!empty($available_options)) {
            foreach ($available_options as $option) {
                if (isset($option['options'])) {
                    $option_id = '_gtro_option_' . sanitize_title($option['options']);
                    $option_value = isset($_POST[$option_id]) ? 'yes' : 'no';
                    update_post_meta($post_id, $option_id, $option_value);
                }
            }
        }
    }

    private function get_gtro_categories() {
    // Debug
    error_log('Debug voitures_gtro: ' . print_r(rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro'), true));
    
    $voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro');
    $options = ['' => __('Sélectionner une catégorie', 'gtro-product-manager')];
    
    // Catégories statiques (en attendant la correction)
    $options['cat1'] = __('Catégorie 1', 'gtro-product-manager');
    $options['cat2'] = __('Catégorie 2', 'gtro-product-manager');
    $options['cat3'] = __('Catégorie 3', 'gtro-product-manager');
    
    return $options;
}


    private function get_category_label($category_key) {
        $labels = [
            'cat1' => __('Catégorie 1', 'gtro-product-manager'),
            'cat2' => __('Catégorie 2', 'gtro-product-manager'),
            'cat3' => __('Catégorie 3', 'gtro-product-manager'),
            'catn' => __('Catégorie N', 'gtro-product-manager'),
        ];
        return isset($labels[$category_key]) ? $labels[$category_key] : $category_key;
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
        
        $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro');
        if (empty($available_options)) return;

        echo '<div class="gtro-options">';
        echo '<h3>' . __('Options additionnelles', 'gtro-product-manager') . '</h3>';
        
        foreach ($available_options as $option) {
            $option_id = sanitize_title($option['options']);
            $checked = get_post_meta($product->get_id(), '_gtro_option_' . $option_id, true) === 'yes';
            
            if ($checked) {
                echo '<div class="gtro-option">';
                echo '<label>';
                echo '<input type="checkbox" name="gtro_option[' . $option_id . ']" value="' . $option['prix_options'] . '">';
                echo esc_html($option['options']) . ' (+' . wc_price($option['prix_options']) . ')';
                echo '</label>';
                echo '</div>';
            }
        }
        echo '</div>';
    }

    public function add_gtro_options_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['gtro_option'])) {
            $cart_item_data['gtro_options'] = $_POST['gtro_option'];
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
}
