<?php

namespace App\Actions;

use App\Models\Demande;
use App\Models\User;
use App\Models\Validation;
use App\Notifications\DemandeDecisionNotification;
use Illuminate\Auth\Access\AuthorizationException;

class DeciderDemande
{
    public function handle(Demande $demande, User $acteur, string $niveau, string $decision, ?string $commentaire = null): Demande
    {
        if (! in_array($decision, ['ok', 'refus'], true)) {
            throw new \InvalidArgumentException("Décision invalide.");
        }

        $demande->loadMissing('agent.direction', 'agent.user');

        if ($niveau === 'chef') {
            $this->autoriserChef($demande, $acteur);
            $this->exigerStatut($demande, Demande::STATUT_SOUMISE);
            if ($decision === 'refus') {
                $nouveauStatut = Demande::STATUT_REFUSEE;
            } elseif ($demande->type === 'permission') {
                // Permission : la validation du chef est finale (la DRHF est seulement informée).
                $nouveauStatut = Demande::STATUT_VALIDEE_RH;
            } else {
                // Congé : passe à la DRHF (niveau 2).
                $nouveauStatut = Demande::STATUT_VALIDEE_CHEF;
            }
        } elseif ($niveau === 'rh') {
            $this->autoriserRh($acteur);
            $this->exigerStatut($demande, Demande::STATUT_VALIDEE_CHEF);
            $nouveauStatut = $decision === 'ok' ? Demande::STATUT_VALIDEE_RH : Demande::STATUT_REFUSEE;
        } else {
            throw new \InvalidArgumentException("Niveau invalide.");
        }

        Validation::create([
            'demande_id' => $demande->id,
            'validateur_id' => $acteur->agent?->id,
            'niveau' => $niveau,
            'decision' => $decision,
            'commentaire' => $commentaire,
        ]);

        $demande->update(['statut' => $nouveauStatut]);

        if ($nouveauStatut === Demande::STATUT_VALIDEE_RH && $demande->estCongeAnnuel()) {
            $agent = $demande->agent;
            $agent->decrement('solde_conge_jours', $demande->nb_jours);
        }

        // Permission validée par le chef : la DRHF est informée (sans validation).
        if ($niveau === 'chef' && $decision === 'ok' && $demande->type === 'permission') {
            $this->informerRh($demande);
        }

        $this->notifierAgent($demande, $nouveauStatut, $niveau);

        return $demande;
    }

    private function informerRh(Demande $demande): void
    {
        $message = "Permission de {$demande->agent->nomComplet()} validée par le chef de direction (pour information).";

        User::where('role', 'admin_rh')->get()->each(function (User $rh) use ($demande, $message) {
            $rh->notify(new DemandeDecisionNotification($demande, $message));
        });
    }

    private function autoriserChef(Demande $demande, User $acteur): void
    {
        $direction = $demande->agent->direction;
        $estChef = $acteur->agent
            && $direction
            && $direction->chef_id === $acteur->agent->id;

        if (! $estChef) {
            throw new AuthorizationException("Vous n'êtes pas le chef de cette direction.");
        }
    }

    private function autoriserRh(User $acteur): void
    {
        if (! $acteur->isAdminRh()) {
            throw new AuthorizationException("Réservé à la DRHF.");
        }
    }

    private function exigerStatut(Demande $demande, string $statut): void
    {
        if ($demande->statut !== $statut) {
            throw new \DomainException("Transition invalide depuis le statut « {$demande->statut} ».");
        }
    }

    private function notifierAgent(Demande $demande, string $statut, string $niveau): void
    {
        $user = $demande->agent->user;
        if (! $user) {
            return;
        }

        $message = match (true) {
            $statut === Demande::STATUT_REFUSEE => "Votre demande a été refusée.",
            $statut === Demande::STATUT_VALIDEE_CHEF => "Votre demande a été validée par votre chef de direction (en attente DRHF).",
            $statut === Demande::STATUT_VALIDEE_RH && $niveau === 'chef' => "Votre permission a été validée par votre chef de direction.",
            $statut === Demande::STATUT_VALIDEE_RH => "Votre demande a été validée par la DRHF.",
            default => "Votre demande a changé de statut.",
        };

        $user->notify(new DemandeDecisionNotification($demande, $message));
    }
}
