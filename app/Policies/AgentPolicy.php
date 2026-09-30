<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    /**
     * Qui peut consulter le profil d'un agent :
     * - Admin RH et DG : tous les agents
     * - Chef de direction : uniquement les agents de sa direction
     */
    public function voir(User $user, Agent $agent): bool
    {
        if ($user->isAdminRh() || $user->isDg()) {
            return true;
        }

        if ($user->isChefDirection()) {
            return $user->agent !== null && $user->agent->direction_id === $agent->direction_id;
        }

        return false;
    }
}
