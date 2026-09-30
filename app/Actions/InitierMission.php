<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InitierMission
{
    public const SIGNATAIRES = ['dg', 'directeur'];

    public function handle(
        User $secretaire,
        Agent $agent,
        string $dateDebut,
        string $dateFin,
        ?string $motif,
        array $meta,
        array $signataires,
    ): Demande {
        if (! $secretaire->isSecretaire() || ! $secretaire->agent) {
            throw new AuthorizationException("Réservé au secrétaire de direction.");
        }

        if ($agent->direction_id !== $secretaire->agent->direction_id) {
            throw new AuthorizationException("Cet agent n'appartient pas à votre direction.");
        }

        $signataires = array_values(array_unique($signataires));
        if ($signataires === [] || count($signataires) > 2 || array_diff($signataires, self::SIGNATAIRES) !== []) {
            throw ValidationException::withMessages([
                'signataires' => "Choisissez 1 ou 2 signataires parmi : Directeur Général, Directeur de la direction.",
            ]);
        }

        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();
        if ($fin->lt($debut)) {
            throw ValidationException::withMessages([
                'date_fin' => "La date de retour doit être postérieure ou égale à la date de départ.",
            ]);
        }

        $meta['signataires'] = $signataires;

        return Demande::create([
            'agent_id' => $agent->id,
            'type' => 'ordre_mission',
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'nb_jours' => $debut->diffInDays($fin) + 1,
            'motif' => $motif,
            'statut' => Demande::STATUT_EMISE,
            'meta' => $meta,
        ]);
    }
}
