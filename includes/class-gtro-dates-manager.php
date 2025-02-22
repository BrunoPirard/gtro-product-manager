<?php
namespace GTRO_Plugin;

class GTRO_Dates_Manager {
    /**
     * Les slugs de groupes disponibles
     */
    private $available_groups = ['monoplace', 'biplace'];

    /**
     * Récupère les dates en promotion pour un ou plusieurs groupes
     * 
     * @param string|array $group_slugs Le(s) slug(s) du/des groupe(s)
     * @return array Liste des dates en promotion
     */
    public function get_promo_dates($group_slugs = null) {
        $promo_dates = array();
        $dates_group = get_option('gtro_options');
        
        // Si aucun groupe n'est spécifié, utiliser tous les groupes disponibles
        if ($group_slugs === null) {
            $group_slugs = $this->available_groups;
        }
        
        // Convertir en tableau si c'est une chaîne
        if (is_string($group_slugs)) {
            $group_slugs = [$group_slugs];
        }
        
        foreach ($group_slugs as $slug) {
            $meta_key = 'dates_' . $slug;
            if (isset($dates_group[$meta_key])) {
                foreach ($dates_group[$meta_key] as $entry) {
                    if (!empty($entry['date']) && isset($entry['promo']) && $entry['promo'] > 0) {
                        $promo_dates[$slug][] = array(
                            'date' => $entry['date'],
                            'promo' => $entry['promo']
                        );
                    }
                }
            }
        }
        
        return $promo_dates;
    }

    /**
     * Récupère la liste des groupes disponibles
     * 
     * @return array Liste des slugs de groupes
     */
    public function get_available_groups() {
        return $this->available_groups;
    }
}
