<?php

return [
    'dg_nom' => env('DGE_DG_NOM', 'Le Directeur Général'),
    'dg_fonction' => env('DGE_DG_FONCTION', 'Directeur Général des Élections'),

    // Signataire des attestations de congé = le Directeur des Ressources Humaines.
    'drh_nom' => env('DGE_DRH_NOM', 'Ndeye Astou GUEYE'),
    'drh_fonction' => env('DGE_DRH_FONCTION', 'Directeur des Ressources Humaines et des Finances'),

    // Défi 2FA par e-mail. Désactivable en local pour les tests (DGE_2FA=false).
    'deux_facteurs' => (bool) env('DGE_2FA', true),
];
