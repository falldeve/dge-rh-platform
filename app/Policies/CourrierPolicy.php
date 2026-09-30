<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    /**
     * Peut consulter un courrier :
     * - bureau courrier + archiviste : tous
     * - chef/secrétaire d'une entité : si le courrier est imputé à une entité qu'il gère
     */
    public function voir(User $user, Courrier $courrier): bool
    {
        if ($user->isCourrier() || $user->isArchiviste()) {
            return true;
        }

        $ids = $user->agentEntitesGereesIds();
        if (empty($ids)) {
            return false;
        }

        return $courrier->imputations()
            ->whereHas('destinataires', fn ($q) => $q->whereIn('entites.id', $ids))
            ->exists();
    }
}
