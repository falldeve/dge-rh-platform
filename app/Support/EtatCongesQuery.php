<?php

namespace App\Support;

use App\Models\Demande;
use Illuminate\Support\Collection;

class EtatCongesQuery
{
    public static function pour(?int $directionId, ?string $du, ?string $au): Collection
    {
        return Demande::query()
            ->with('agent.direction')
            ->where('type', 'conge_annuel')
            ->where('statut', Demande::STATUT_VALIDEE_RH)
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when($du, fn ($q) => $q->whereDate('date_fin', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_debut', '<=', $au))
            ->orderBy('date_debut')
            ->get();
    }
}
