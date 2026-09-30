<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\Demande;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SoumettreDemande
{
    public function handle(
        Agent $agent,
        string $type,
        string $dateDebut,
        string $dateFin,
        ?string $motif = null,
        array $meta = [],
        ?string $pieceJointePath = null,
    ): Demande {
        if (! in_array($type, Demande::TYPES_SELF_SERVICE, true)) {
            throw ValidationException::withMessages([
                'type' => "Ce type n'est pas disponible en libre-service. Les ordres de mission sont initiés par le secrétaire de direction.",
            ]);
        }

        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();

        if ($fin->lt($debut)) {
            throw ValidationException::withMessages([
                'date_fin' => "La date de fin doit être postérieure ou égale à la date de début.",
            ]);
        }

        $nbJours = $debut->diffInDays($fin) + 1;

        if ($type === 'conge_annuel' && $nbJours > (float) $agent->solde_conge_jours) {
            throw ValidationException::withMessages([
                'nb_jours' => "Solde de congé insuffisant ({$agent->solde_conge_jours} j disponibles).",
            ]);
        }

        $direction = $agent->direction;
        $estChef = $direction && $direction->chef_id === $agent->id;
        $sansChef = ! $direction || $direction->chef_id === null;
        $sautN1 = $estChef || $sansChef; // pas de chef à solliciter

        // Permission = 1 seul niveau (chef). Sans chef à solliciter → directement finalisée.
        // Congé = 2 niveaux (chef → DRHF).
        if ($type === 'permission') {
            $statut = $sautN1 ? Demande::STATUT_VALIDEE_RH : Demande::STATUT_SOUMISE;
        } else {
            $statut = $sautN1 ? Demande::STATUT_VALIDEE_CHEF : Demande::STATUT_SOUMISE;
        }

        return Demande::create([
            'agent_id' => $agent->id,
            'type' => $type,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'nb_jours' => $nbJours,
            'motif' => $motif,
            'statut' => $statut,
            'piece_jointe_path' => $pieceJointePath,
            'meta' => $meta ?: null,
        ]);
    }
}
