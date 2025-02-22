<?php
namespace GTRO_Plugin;

class GTRO_Dates_Manager {
    /**
     * Récupère les dates en promotion pour un groupe spécifique
     * 
     * @param string $group_slug Le slug du groupe de dates
     * @return array Liste des dates en promotion
     */
    public function get_promo_dates($group_slug) {
        $promo_dates = array();
        
        // Récupérer les options
        $dates_group = get_option('gtro_options');
        $meta_key = 'dates_' . $group_slug;

        // Vérifier si le groupe existe
        if (isset($dates_group[$meta_key])) {
            foreach ($dates_group[$meta_key] as $entry) {
                // Vérifier si la date existe et si elle a une promotion
                if (!empty($entry['date']) && isset($entry['promo']) && $entry['promo'] > 0) {
                    $promo_dates[] = array(
                        'date' => $entry['date'],
                        'promo' => $entry['promo']
                    );
                }
            }
        }

        return $promo_dates;
    }
}
