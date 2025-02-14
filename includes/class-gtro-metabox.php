<?php

namespace GTRO_Plugin;

if (!class_exists('GTRO_Plugin\GTRO_Metabox')) {
class GTRO_Metabox {
    private $prefix = 'gtro_';

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_price_meta_box'));
        add_action('save_post', array($this, 'save_price_meta_box'));
    }

    public function add_price_meta_box() {
        add_meta_box(
            'gtro_price_meta_box',
            __('GTRO Prix et Options', 'gtro-product-manager'),
            array($this, 'render_price_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    public function render_price_meta_box($post) {
        wp_nonce_field('gtro_price_meta_box', 'gtro_price_meta_box_nonce');

        // Récupération des valeurs existantes
        $base_price = get_post_meta($post->ID, '_gtro_base_price', true);
        $promo_dates = get_post_meta($post->ID, '_gtro_promo_dates', true);
        $extra_lap_price = get_post_meta($post->ID, '_gtro_extra_lap_price', true);

        ?>
        <div class="gtro-meta-box-container">
            <!-- Prix de base -->
            <p>
                <label for="gtro_base_price"><?php _e('Prix de base:', 'gtro-product-manager'); ?></label>
                <input type="number" 
                       id="gtro_base_price" 
                       name="gtro_base_price" 
                       value="<?php echo esc_attr($base_price); ?>"
                       step="0.01">
            </p>

            <!-- Prix du tour supplémentaire -->
            <p>
                <label for="gtro_extra_lap_price"><?php _e('Prix par tour supplémentaire:', 'gtro-product-manager'); ?></label>
                <input type="number" 
                       id="gtro_extra_lap_price" 
                       name="gtro_extra_lap_price" 
                       value="<?php echo esc_attr($extra_lap_price); ?>"
                       step="0.01">
            </p>

            <!-- Dates promotionnelles -->
            <div class="gtro-promo-dates">
                <h4><?php _e('Dates promotionnelles', 'gtro-product-manager'); ?></h4>
                <div id="gtro-promo-dates-container">
                    <?php
                    if (!empty($promo_dates) && is_array($promo_dates)) {
                        foreach ($promo_dates as $index => $promo) {
                            $this->render_promo_date_row($index, $promo);
                        }
                    }
                    ?>
                </div>
                <button type="button" class="button" id="add-promo-date">
                    <?php _e('Ajouter une date promotionnelle', 'gtro-product-manager'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private function render_promo_date_row($index, $promo) {
        ?>
        <div class="promo-date-row">
            <input type="date" 
                   name="gtro_promo_dates[<?php echo $index; ?>][date]" 
                   value="<?php echo esc_attr($promo['date']); ?>">
            <input type="number" 
                   name="gtro_promo_dates[<?php echo $index; ?>][discount]" 
                   value="<?php echo esc_attr($promo['discount']); ?>"
                   min="0" 
                   max="100" 
                   placeholder="Réduction en %">
            <button type="button" class="button remove-promo-date">×</button>
        </div>
        <?php
    }

    public function save_price_meta_box($post_id) {
        if (!isset($_POST['gtro_price_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['gtro_price_meta_box_nonce'], 'gtro_price_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sauvegarde du prix de base
        if (isset($_POST['gtro_base_price'])) {
            update_post_meta(
                $post_id,
                '_gtro_base_price',
                floatval($_POST['gtro_base_price'])
            );
        }

        // Sauvegarde du prix du tour supplémentaire
        if (isset($_POST['gtro_extra_lap_price'])) {
            update_post_meta(
                $post_id,
                '_gtro_extra_lap_price',
                floatval($_POST['gtro_extra_lap_price'])
            );
        }

        // Sauvegarde des dates promotionnelles
        if (isset($_POST['gtro_promo_dates'])) {
            $promo_dates = array_map(function($promo) {
                return array(
                    'date' => sanitize_text_field($promo['date']),
                    'discount' => min(100, max(0, intval($promo['discount'])))
                );
            }, $_POST['gtro_promo_dates']);

            update_post_meta(
                $post_id,
                '_gtro_promo_dates',
                $promo_dates
            );
        }
    }
}

}

