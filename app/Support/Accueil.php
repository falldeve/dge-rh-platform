<?php

namespace App\Support;

use App\Models\User;

class Accueil
{
    /** Route d'accueil selon le rôle (et la gestion d'entité pour les agents). */
    public static function pour(User $user): string
    {
        return match ($user->role) {
            'admin' => route('rh.tableau-bord'),
            'admin_rh' => route('rh.tableau-bord'),
            'dg' => route('dg.tableau-bord'),
            'chef_direction' => route('validation.chef'),
            'secretaire' => route('missions.mes'),
            'courrier' => route('courriers.registre'),
            'archiviste' => route('archives'),
            default => $user->gereEntite() ? route('mes-courriers') : route('dashboard'),
        };
    }
}
