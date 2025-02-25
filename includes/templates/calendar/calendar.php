<?php
/**
 * The calendar functionality of the plugin.
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/admin
 */

?>
<div id="circuit_dates_options" class="panel woocommerce_options_panel">
	<?php
	woocommerce_wp_select(
		array(
			'id'      => '_product_date_type',
			'label'   => __( 'Type de dates', 'gtro-calendar' ),
			'options' => array(
				''     => __( 'Sélectionner...', 'gtro-calendar' ),
				'gt'   => __( 'Dates GT', 'gtro-calendar' ),
				'mono' => __( 'Dates Monoplace', 'gtro-calendar' ),
			),
		)
	);

	woocommerce_wp_text_input(
		array(
			'id'                => '_variation_default_price',
			'label'             => __( 'Prix de base des variations', 'gtro-calendar' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'step' => 'any',
				'min'  => '0',
			),
		)
	);
	?>
</div>
