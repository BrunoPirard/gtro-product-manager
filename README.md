# GTRO Product Manager - Documentation

## Description

Plugin WordPress/WooCommerce pour la gestion de réservations de tours en voiture avec options personnalisables.

## Fonctionnalités actuelles

-   Sélection de véhicules par catégorie
-   Gestion des suppléments par catégorie de véhicule
-   Ajout de tours supplémentaires
-   Gestion des promotions par date
-   Options additionnelles configurables
-   Calcul dynamique des prix
-   Affichage détaillé du prix
-   Calendrier des disponibilités

## Structure du plugin

gtro-product-manager/
├── admin/
│ ├── css/
│ │ └── gtro-admin.css
│ ├── js/
│ │ └── gtro-admin.js
│ └── class-gtro-admin.php
├── includes/
│ ├── class-gtro-activator.php
│ ├── class-gtro-calendar.php
│ ├── class-gtro-dates-manager.php
│ ├── class-gtro-deactivator.php
├── class-gtro-i8n.php
├── class-gtro-loader.php
├── class-gtro-settings.php
├── class-gtro-woocommerce.php
│ └── class-gtro.php
├── languages/
│ └── gtro-product-manager.pot
├── public/
│ ├── css/
├── gtro-calendar.css
│ │ └── gtro-public.css
│ ├── js/
├── gtro-calendar.js
│ │ └── gtro-public.js
│ └── class-gtro-public.php
├── index.php
├── uninstall.php
├── README.md
└── gtro-product-manager.php

# Points à améliorer/développer

### Priorité Haute

-   Validation des données côté serveur

### Priorité Moyenne

-   Interface d'administration plus intuitive
-   Système de cache pour les calculs de prix

### Priorité Basse

-   Export/Import des configurations

## Configuration actuelle

-   Meta Box pour la gestion des options
-   Options stockées dans les settings WordPress
-   Intégration avec WooCommerce pour les produits

## Notes techniques importantes

-   Les catégories de véhicules utilisent le préfixe 'cat' (cat1, cat2, cat3)
-   Les prix sont stockés en centimes
-   Les promotions sont en pourcentage
-   Structure des données :

$gtroData = array(
'basePrice' => float,
'pricePerLap' => float,
'categorySupplements' => array(
'cat1' => float,
'cat2' => float,
'cat3' => float
),
'datesPromo' => array(),
'availableOptions' => array()
);

## Dépendances

-   WordPress 6.0+
-   WooCommerce 8.0+
-   Meta Box Plugin
-   PHP 8.2+

## Shortcodes disponibles

[display_calendar] - Affiche le calendrier des disponibilités

## Options:

-   groups="all|monoplace|gt" (défaut: all)
-   view="month" (défaut: month)

## Bugs connus

-   Pas de variations

## Notes pour le développement
