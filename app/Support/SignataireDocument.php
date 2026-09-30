<?php

namespace App\Support;

use App\Models\Demande;

class SignataireDocument
{
    /**
     * Signataire d'un document RH.
     * - Congé : le Directeur des Ressources Humaines (la DRHF valide et signe l'attestation).
     * - Permission : le chef de direction de l'agent (qui valide), à défaut le Directeur Général.
     *
     * @return array{0:string,1:string} [nom, fonction]
     */
    public static function pour(Demande $demande): array
    {
        if ($demande->type === 'conge_annuel') {
            return [config('dge.drh_nom'), config('dge.drh_fonction')];
        }

        $chef = $demande->agent->direction?->chef;

        if ($chef) {
            return [$chef->nomComplet(), $chef->fonction ?: ('Directeur de '.($demande->agent->direction?->nom ?? ''))];
        }

        return [config('dge.dg_nom'), config('dge.dg_fonction')];
    }
}
