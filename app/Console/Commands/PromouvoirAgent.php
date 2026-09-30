<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromouvoirAgent extends Command
{
    protected $signature = 'rh:promouvoir {matricule} {role}';
    protected $description = 'Attribue un rôle (agent|chef_direction|admin_rh|dg|secretaire|courrier|archiviste) à un compte existant';

    private const ROLES = ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire', 'courrier', 'archiviste'];

    public function handle(): int
    {
        $role = $this->argument('role');
        if (! in_array($role, self::ROLES, true)) {
            $this->error("Rôle invalide « {$role} ». Valeurs : ".implode(', ', self::ROLES));
            return self::FAILURE;
        }

        $matricule = trim($this->argument('matricule'));
        $user = User::where('matricule', $matricule)->first();
        if (! $user) {
            $this->error("Aucun compte pour le matricule « {$matricule} ». L'agent doit d'abord créer son compte.");
            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("{$user->name} ({$matricule}) est maintenant : {$role}.");

        return self::SUCCESS;
    }
}
