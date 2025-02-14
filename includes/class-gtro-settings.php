<?php
namespace GTRO_Plugin;

class GTRO_Settings {
    private $meta_boxes = [];

    public function __construct() {
        // S'assurer que le hook n'est ajouté qu'une seule fois
        add_filter('rwmb_meta_boxes', [$this, 'register_settings_fields'], 10, 1);
        add_filter('mb_settings_pages', [$this, 'register_settings_pages'], 10, 1);
        add_action('rwmb_after_save_post', [$this, 'handle_new_group'], 10, 2);
    }

    public function handle_new_group($post_id, $post = null) {
        if (!isset($_POST['nouveau_groupe']) || empty($_POST['nouveau_groupe'])) {
            return;
        }

        $nouveau_groupe = sanitize_text_field($_POST['nouveau_groupe']);
        $groupes_existants = get_option('gtro_groupes_dates', array());

        if (!in_array($nouveau_groupe, $groupes_existants)) {
            $groupes_existants[] = $nouveau_groupe;
            update_option('gtro_groupes_dates', $groupes_existants);
        }
    }

    public function register_settings_pages($settings_pages) {
        if (!isset($settings_pages['gtro'])) {
            $settings_pages['gtro'] = [
                'id'          => 'gtro',
                'menu_title' => __('GTRO Settings', 'gtro-product-manager'),
                'option_name' => 'gtro_options',
                'tabs'       => [
                    'dates'    => __('Dates', 'gtro-product-manager'),
                    'voitures' => __('Voitures', 'gtro-product-manager'),
                    'prix'     => __('Prix', 'gtro-product-manager'),
                    'options'  => __('Options', 'gtro-product-manager'),
                ],
            ];
        }
        return $settings_pages;
    }

    public function register_settings_fields($meta_boxes) {

        // Éviter la duplication en vérifiant si déjà ajouté
        if (!empty($this->meta_boxes)) {
            return $this->meta_boxes;
        }

        // Onglet Dates
        $meta_boxes[] = [
            'title'          => __('Dates GTRO', 'gtro-product-manager'),
            'id'             => 'dates',
            'settings_pages' => ['gtro'],
            'tab'            => 'dates',
            'fields'         => [
                [
                    'name'        => __('Créer un nouveau groupe de dates', 'gtro-product-manager'),
                    'id'          => 'nouveau_groupe',
                    'type'        => 'text',
                    'desc'        => __('Entrez un nom pour créer un nouveau groupe de dates (ex: Monoplace, GT)', 'gtro-product-manager'),
                ],
            ],
        ];

        // Pour chaque groupe existant, on crée dynamiquement une nouvelle metabox
        $groupes_existants = get_option('gtro_groupes_dates', array()); // Stocke les noms des groupes
        if (!empty($groupes_existants)) {
            foreach ($groupes_existants as $groupe) {
                $slug = sanitize_title($groupe);
                $meta_boxes[] = [
                    'title'          => sprintf(__('Dates %s', 'gtro-product-manager'), $groupe),
                    'id'             => 'dates_' . $slug,
                    'settings_pages' => ['gtro'],
                    'tab'            => 'dates',
                    'fields'         => [
                        [
                            'name'    => sprintf(__('Dates disponibles - %s', 'gtro-product-manager'), $groupe),
                            'id'      => 'dates_' . $slug,
                            'type'    => 'group',
                            'clone'   => true,
                            'sort_clone' => true,
                            'fields'  => [
                                [
                                    'name' => __('Date', 'gtro-product-manager'),
                                    'id'   => 'date',
                                    'type' => 'date',
                                ],
                                [
                                    'name' => __('Promo (%)', 'gtro-product-manager'),
                                    'id'   => 'promo',
                                    'type' => 'number',
                                    'min'  => 0,
                                    'max'  => 100,
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        // Onglet Voitures
        $meta_boxes[] = [
            'title'          => __('Voitures', 'gtro-product-manager'),
            'id'             => 'voitures',
            'settings_pages' => ['gtro'],
            'tab'            => 'voitures',
            'fields'         => [
                [
                    'name'    => __('Voitures GTRO', 'gtro-product-manager'),
                    'id'      => 'voitures_gtro',
                    'type'    => 'group',
                    'clone'   => true,
                    'fields'  => [
                        [
                            'name' => __('Modèles', 'gtro-product-manager'),
                            'id'   => 'modeles',
                            'type' => 'text',
                        ],
                        [
                            'name' => __('Prix CAT', 'gtro-product-manager'),
                            'id'   => 'prix_cat',
                            'type' => 'number',
                        ],
                        [
                            'name' => __('Image voiture', 'gtro-product-manager'),
                            'id'   => 'image_voiture',
                            'type' => 'single_image',
                        ],
                        [
                            'name' => __('Catégorie', 'gtro-product-manager'),
                            'id'   => 'categorie',
                            'type' => 'select',
                            'options' => [
                                'cat1' => __('Catégorie 1', 'gtro-product-manager'),
                                'cat2' => __('Catégorie 2', 'gtro-product-manager'),
                                'cat3' => __('Catégorie 3', 'gtro-product-manager'),
                                'catn' => __('Catégorie N', 'gtro-product-manager'),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Nouvelle section pour les prix
        $meta_boxes[] = [
            'title'          => __('Configuration des prix', 'gtro-product-manager'),
            'id'             => 'prix-config',
            'settings_pages' => ['gtro'],
            'tab'            => 'prix',
            'fields'         => [
                [
                    'name'    => __('Prix par catégorie', 'gtro-product-manager'),
                    'id'      => 'prix_categories',
                    'type'    => 'group',
                    'clone'   => true,
                    'fields'  => [
                        [
                            'name' => __('Catégorie', 'gtro-product-manager'),
                            'id'   => 'categorie',
                            'type' => 'select',
                            'options' => [
                                'cat1' => __('Catégorie 1', 'gtro-product-manager'),
                                'cat2' => __('Catégorie 2', 'gtro-product-manager'),
                                'cat3' => __('Catégorie 3', 'gtro-product-manager'),
                                'catn' => __('Catégorie N', 'gtro-product-manager'),
                            ],
                        ],
                        [
                            'name' => __('Prix tour supplémentaire', 'gtro-product-manager'),
                            'id'   => 'prix_tour_sup',
                            'type' => 'number',
                        ],
                    ],
                ],
                [
                    'name'    => __('Combinaisons multi-voitures', 'gtro-product-manager'),
                    'id'      => 'combos_voitures',
                    'type'    => 'group',
                    'clone'   => true,
                    'fields'  => [
                        [
                            'name' => __('Type de combo', 'gtro-product-manager'),
                            'id'   => 'type_combo',
                            'type' => 'select',
                            'options' => [
                                '2gt' => __('2 GT', 'gtro-product-manager'),
                                '3gt' => __('3 GT', 'gtro-product-manager'),
                                '4gt' => __('4 GT', 'gtro-product-manager'),
                            ],
                        ],
                        [
                            'name' => __('Remise (%)', 'gtro-product-manager'),
                            'id'   => 'remise',  // Changé de 'Remise Multi' à 'remise'
                            'type' => 'number',
                            'step' => '0.5',
                            'min'  => '0',
                            'max'  => '100',
                        ],
                    ],
                ],
            ],
        ];

        // Onglet Options
        $meta_boxes[] = [
            'title'          => __('Options GTRO', 'gtro-product-manager'),
            'id'             => 'options-gtro',
            'settings_pages' => ['gtro'],
            'tab'            => 'options',
            'fields'         => [
                [
                    'name'    => __('Options supplémentaires', 'gtro-product-manager'),
                    'id'      => 'options_supplementaires',
                    'type'    => 'group',
                    'clone'   => true,
                    'fields'  => [
                        [
                            'name' => __('Options', 'gtro-product-manager'),
                            'id'   => 'options',
                            'type' => 'text',
                        ],
                        [
                            'name' => __('Prix options', 'gtro-product-manager'),
                            'id'   => 'prix_options',
                            'type' => 'number',
                        ],
                    ],
                ],
            ],
        ];

        return $meta_boxes;
    }
}
