<?php
namespace GTRO_Plugin;

class GTRO_WooCommerce {
    /**
     * Register all actions and filters for the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        add_action('woocommerce_product_data_tabs', [$this, 'add_gtro_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_gtro_product_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_gtro_product_options']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_gtro_options'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_filter('woocommerce_add_cart_item_data', [$this, 'add_gtro_options_to_cart'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_gtro_options'], 10, 3);
        add_filter('woocommerce_get_price_html', [$this, 'modify_price_display'], 10, 2);
        add_filter('woocommerce_calculate_totals', [$this, 'calculate_totals'], 10, 1);

        //add_shortcode('gtro_product_options', [$this, 'display_gtro_options_shortcode']);
        // Debug hook
        //add_action('admin_init', [$this, 'debug_meta_box_data']);
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
     * Affiche les groupes de dates et leurs dates associées dans les journaux d'erreurs.
     *
     * Utile pour déboguer les métadonnées des groupes de dates.
     */
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

    /**
     * Ajoute l'onglet "GTRO Options" à la page de produit.
     *
     * @param array $tabs Les onglets de la page de produit.
     * @return array Les onglets de la page de produit, avec l'onglet "GTRO Options" ajouté.
     */
    public function add_gtro_product_tab($tabs) {
        $tabs['gtro_options'] = array(
            'label'    => __('GTRO Options', 'gtro-product-manager'),
            'target'   => 'gtro_options_product_data',
            'class'    => array('hide_if_grouped', 'hide_if_external'),
            'priority' => 90
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
   
    /**
     * Sauvegarde les options GTRO du produit.
     *
     * Marque d'abord toutes les options existantes comme "no", puis
     * sauvegarde les valeurs cochées.
     *
     * @param int $post_id L'ID du produit.
     */
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
        $groupes = get_option('gtro_groupes_dates', []);
        $options = ['' => __('Sélectionner un groupe', 'gtro-product-manager')];
        
        foreach ($groupes as $groupe) {
            $options[sanitize_title($groupe)] = $groupe;
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
        $max_tours = intval(get_post_meta($product->get_id(), '_gtro_max_tours', true));

        // N'afficher la section que si max_tours > 0
        if ($max_tours > 0) {
            echo '<div class="gtro-extra-laps">';
            echo '<h3>' . __('Tours supplémentaires', 'gtro-product-manager') . '</h3>';
            echo '<input type="number" name="gtro_extra_laps" value="0" min="0" max="' . esc_attr($max_tours) . '">';
            echo '</div>';
        }

        // 4. Sélecteur de dates
        // Récupérer le groupe de dates sélectionné pour ce produit
        $selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
        //error_log('Groupe de dates sélectionné : ' . $selected_group);

        if (!empty($selected_group)) {
            // Récupérer les dates du groupe depuis les settings
            $dates = rwmb_meta('dates_' . sanitize_title($selected_group), ['object_type' => 'setting'], 'gtro_options');
            //error_log('Dates du groupe ' . $selected_group . ' : ' . print_r($dates, true));
            
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

        // 5. Options supplémentaires
        $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro_options');
        $options_activees = [];

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
    }

    /**
     * Affiche les options supplémentaires dans le panier
     *
     * @param array $item_data Les métadonnées du produit
     * @param array $cart_item Les données de l'élément du panier
     *
     * @return array Les métadonnées du produit
     */
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

    /**
     * Calcule le prix total d'un stage
     *
     * 1. Ajuste le prix en fonction de la catégorie du véhicule
     * 2. Calcule le prix des tours supplémentaires
     * 3. Applique la promotion de la date si elle existe
     * 4. Ajoute le prix des options (après la promo)
     *
     * @param int $base_price Le prix de base du stage
     * @param string $vehicle La catégorie du véhicule
     * @param int $extra_laps Le nombre de tours supplémentaires
     * @param string $selected_date La date sélectionnée
     * @param array $selected_options Les options supplémentaires sélectionnées
     *
     * @return int Le prix total du stage
     */
    private function calculate_total_price($base_price, $vehicle = '', $extra_laps = 0, $selected_date = '', $selected_options = array()) {
        $total = $base_price;
        
        // 1. Ajuster le prix en fonction de la catégorie du véhicule
        if (!empty($vehicle)) {
            $available_voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro_options');
            foreach ($available_voitures as $voiture) {
                if (sanitize_title($voiture['modeles']) === $vehicle) {
                    // Ajouter le supplément selon la catégorie
                    switch ($voiture['categorie']) {
                        case '2':
                            $total += 50; // Supplément catégorie 2
                            break;
                        case '3':
                            $total += 100; // Supplément catégorie 3
                            break;
                    }
                    break;
                }
            }
        }
        
        // 2. Calculer le prix des tours supplémentaires
        if ($extra_laps > 0) {
            $price_per_lap = get_option('gtro_price_per_lap', 50);
            $total += ($extra_laps * $price_per_lap);
        }
        
        // Sauvegarder le total avant promo pour les options
        $total_before_promo = $total;
        
        // 3. Appliquer la promotion de la date si elle existe
        if (!empty($selected_date)) {
            global $product;
            $selected_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
            $dates = rwmb_meta('dates_' . sanitize_title($selected_group), ['object_type' => 'setting'], 'gtro_options');
            
            foreach ($dates as $date) {
                if ($date['date'] === $selected_date && isset($date['promo']) && $date['promo'] > 0) {
                    $discount = $total * ($date['promo'] / 100);
                    $total -= $discount;
                    break;
                }
            }
        }
        
        // 4. Ajouter le prix des options (après la promo)
        if (!empty($selected_options)) {
            $available_options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro_options');
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
     * @param string $price_html Le prix HTML du produit.
     * @param WC_Product $product Le produit.
     * @return string Le prix modifié.
     */
    public function modify_price_display($price_html, $product) {
        // Ne modifier le prix que sur la page du produit
        if (!is_product()) {
            return $price_html;
        }

        // Vérifier si c'est un produit GTRO
        $date_group = get_post_meta($product->get_id(), '_gtro_date_group', true);
        if (empty($date_group)) {
            return $price_html;
        }

        $base_price = $product->get_price();
        
        // Ajouter une note sur les options
        $price_html = '<span class="base-price">' . wc_price($base_price) . '</span>';
        $price_html .= '<br><small class="price-note">' . __('Prix de base hors options', 'gtro-product-manager') . '</small>';
        
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
    public function calculate_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['gtro_total_price'])) {
                $cart_item['data']->set_price($cart_item['gtro_total_price']);
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
     * @param array $cart_item_data Les données du produit dans le panier.
     * @param int $product_id L'ID du produit.
     * @param int $variation_id L'ID de la variation du produit.
     * @return array Les données du produit mises à jour.
     */
    public function add_gtro_options_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['gtro_vehicle'])) {
            $cart_item_data['gtro_vehicle'] = sanitize_text_field($_POST['gtro_vehicle']);
        }
        
        if (isset($_POST['gtro_extra_laps'])) {
            $cart_item_data['gtro_extra_laps'] = intval($_POST['gtro_extra_laps']);
        }
        
        if (isset($_POST['gtro_date'])) {
            $cart_item_data['gtro_date'] = sanitize_text_field($_POST['gtro_date']);
        }
        
        // Calculer le nouveau prix
        $product = wc_get_product($product_id);
        $base_price = $product->get_price();

        $new_price = $this->calculate_total_price(
        $base_price,
        $cart_item_data['gtro_vehicle'] ?? '',
        $cart_item_data['gtro_extra_laps'] ?? 0,
        $cart_item_data['gtro_date'] ?? '',
        isset($_POST['gtro_options']) ? array_map('sanitize_text_field', $_POST['gtro_options']) : array()
        );

        
        $cart_item_data['gtro_total_price'] = $new_price;
        
        return $cart_item_data;
    }

    /**
     * Valide les données GTRO envoyées par le formulaire
     * pour s'assurer qu'elles sont bien remplies.
     *
     * @param bool $passed Si les données sont valides.
     * @param int $product_id L'ID du produit.
     * @param int $quantity La quantité du produit.
     * @return bool Si les données sont valides.
     */
    public function validate_gtro_options($passed, $product_id, $quantity) {
        if (!isset($_POST['gtro_vehicle']) || empty($_POST['gtro_vehicle'])) {
            wc_add_notice(__('Veuillez sélectionner un véhicule', 'gtro-product-manager'), 'error');
            $passed = false;
        }
        
        if (!isset($_POST['gtro_date']) || empty($_POST['gtro_date'])) {
            wc_add_notice(__('Veuillez sélectionner une date', 'gtro-product-manager'), 'error');
            $passed = false;
        }
        
        return $passed;
    }

    /**
     * Récupère les données GTRO du produit en session
     *
     * @param array $cart_item Les données du produit dans le panier.
     * @param array $values Les données du produit en session.
     *
     * @return array Les données du produit dans le panier avec les données GTRO.
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['gtro_vehicle'])) {
            $cart_item['gtro_vehicle'] = $values['gtro_vehicle'];
        }
        
        if (isset($values['gtro_extra_laps'])) {
            $cart_item['gtro_extra_laps'] = $values['gtro_extra_laps'];
        }
        
        if (isset($values['gtro_date'])) {
            $cart_item['gtro_date'] = $values['gtro_date'];
        }
        
        if (isset($values['gtro_total_price'])) {
            $cart_item['gtro_total_price'] = $values['gtro_total_price'];
        }
        
        return $cart_item;
    }

    /**
     * Récupère les dates et leurs promotions associées pour un produit
     *
     * @param int $product_id ID du produit
     * @return array Tableau des dates avec leurs promotions
     */
    private function get_dates_with_promos($product_id) {
        $dates_with_promos = array();
        
        // Récupérer les dates et promos depuis la metabox
        $dates = get_post_meta($product_id, '_gtro_dates', true);
        
        if (!empty($dates) && is_array($dates)) {
            foreach ($dates as $date) {
                if (!empty($date['date']) && isset($date['promo'])) {
                    $dates_with_promos[] = array(
                        'date' => $date['date'],
                        'promo' => floatval($date['promo'])
                    );
                }
            }
        }
        
        return $dates_with_promos;
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
        if (!is_product()) {
            return;
        }

        $plugin_dir = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script('gtro-public', $plugin_dir . 'public/js/gtro-public.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('gtro-public', $plugin_dir . 'public/css/gtro-public.css', array(), '1.0.0', 'all');

        $product_id = get_the_ID();
        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        // Récupérer les voitures avec leurs suppléments de base et catégories
        $available_voitures = rwmb_meta('voitures_gtro', ['object_type' => 'setting'], 'gtro_options');
        $vehicles_data = array();
        foreach ($available_voitures as $voiture) {
            if (isset($voiture['modeles']) && isset($voiture['supplement_base']) && isset($voiture['categorie'])) {
                $vehicles_data[sanitize_title($voiture['modeles'])] = array(
                    'supplement_base' => floatval($voiture['supplement_base']),
                    'categorie' => $voiture['categorie']
                );
            }
        }

        // Récupérer les prix par tours pour chaque catégorie
        $prix_categories = rwmb_meta('prix_categories', ['object_type' => 'setting'], 'gtro_options');
        $category_prices = array();
        foreach ($prix_categories as $cat) {
            if (isset($cat['categorie']) && isset($cat['prix_tour_sup'])) {
                $category_prices[$cat['categorie']] = floatval($cat['prix_tour_sup']);
            }
        }

        // Récupérer les dates et promos
        $selected_group = get_post_meta($product_id, '_gtro_date_group', true);
        $dates_with_promos = array();
        if (!empty($selected_group)) {
            $dates = rwmb_meta('dates_' . sanitize_title($selected_group), ['object_type' => 'setting'], 'gtro_options');
            if (!empty($dates)) {
                foreach ($dates as $date) {
                    if (isset($date['date']) && isset($date['promo'])) {
                        $dates_with_promos[] = array(
                            'date' => $date['date'],
                            'promo' => floatval($date['promo'])
                        );
                    }
                }
            }
        }

        // Récupérer les options disponibles
        $available_options = array();
        $options = rwmb_meta('options_supplementaires', ['object_type' => 'setting'], 'gtro_options');
        if (!empty($options)) {
            foreach ($options as $option) {
                if (isset($option['options']) && isset($option['prix_options'])) {
                    $option_id = sanitize_title($option['options']);
                    $available_options[$option_id] = floatval($option['prix_options']);
                }
            }
        }

        wp_localize_script('gtro-public', 'gtroData', array(
            'basePrice' => floatval($product->get_price()),
            'vehiclesData' => $vehicles_data,
            'categoryPrices' => $category_prices,
            'datesPromo' => empty($dates_with_promos) ? array() : $dates_with_promos,
            'availableOptions' => empty($available_options) ? array() : $available_options,
            'showPriceDetails' => true,
            'maxTours' => intval(get_post_meta($product_id, '_gtro_max_tours', true))
        ));
    }
}
