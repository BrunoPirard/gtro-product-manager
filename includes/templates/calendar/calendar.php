<?php
// templates/product-data-panel.php
?>
<div id="circuit_dates_options" class="panel woocommerce_options_panel">
    <?php
    woocommerce_wp_select([
        'id' => '_product_date_type',
        'label' => __('Type de dates', 'gtro-calendar'),
        'options' => [
            '' => __('Sélectionner...', 'gtro-calendar'),
            'gt' => __('Dates GT', 'gtro-calendar'),
            'mono' => __('Dates Monoplace', 'gtro-calendar')
        ]
    ]);

    woocommerce_wp_text_input([
        'id' => '_variation_default_price',
        'label' => __('Prix de base des variations', 'gtro-calendar'),
        'type' => 'number',
        'custom_attributes' => [
            'step' => 'any',
            'min' => '0'
        ]
    ]);
    ?>
</div>