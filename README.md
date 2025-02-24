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
-   Gestion des erreurs et messages utilisateur
-   Sécurisation des requêtes AJAX
-   Optimisation du calcul des prix

### Priorité Moyenne

-   Interface d'administration plus intuitive
-   Système de cache pour les calculs de prix
-   Gestion des conflits de réservation
-   Rapports et statistiques

### Priorité Basse

-   Export/Import des configurations
-   Système de templates personnalisables
-   Intégration avec d'autres plugins

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

Problème potentiel avec l'affichage des prix si le format monétaire est modifié
Les dates de promotion doivent être vérifiées pour le format

## Prochaines étapes suggérées

Implémenter un système de validation robuste
Ajouter des hooks pour plus de flexibilité
Créer une documentation utilisateur
Optimiser les performances du calcul de prix
Améliorer l'UX du formulaire de réservation

## Notes pour le développement

Utiliser wp_localize_script pour passer les données au JS
Suivre les standards WordPress pour le code
Documenter toutes les fonctions et hooks

Ce README servira de référence pour la suite du développement et permettra de maintenir une vision claire du projet.
J'ai ajouté une section "Shortcodes disponibles" et amélioré le formatage général pour une meilleure lisibilité. Les listes sont maintenant correctement formatées avec des tirets, et j'ai ajouté des blocs de code avec la syntaxe appropriée.

```

```
