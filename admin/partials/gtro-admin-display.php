<?php
/**
 * Provide a admin area view for the plugin
 *
 * @since      1.0.0
 * @package    GTRO_Product_Manager
 * @subpackage GTRO_Product_Manager/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="gtro-admin-content">
        <div class="gtro-admin-section">
            <h2>Gestion des produits GTRO</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom du produit</th>
                        <th>Prix</th>
                        <th>Dates de promotion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5">Aucun produit trouvé</td>
                    </tr>
                    <!-- Les produits seront ajoutés ici dynamiquement -->
                </tbody>
            </table>

            <div class="gtro-admin-actions">
                <a href="#" class="button button-primary">Ajouter un nouveau produit</a>
            </div>
        </div>
    </div>
</div>
