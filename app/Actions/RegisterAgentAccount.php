<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterAgentAccount
{
    public function handle(string $matricule, string $noms, string $prenoms, string $email, string $password): User
    {
        $agent = Agent::query()
            ->whereRaw('LOWER(TRIM(matricule)) = ?', [strtolower(trim($matricule))])
            ->whereRaw('LOWER(TRIM(noms)) = ?', [strtolower(trim($noms))])
            ->whereRaw('LOWER(TRIM(prenoms)) = ?', [strtolower(trim($prenoms))])
            ->first();

        if (! $agent) {
            throw ValidationException::withMessages([
                'matricule' => "Aucun agent ne correspond à ce matricule et à ce nom. Contactez la DRHF.",
            ]);
        }

        if ($agent->user_id !== null) {
            throw ValidationException::withMessages([
                'matricule' => "Un compte existe déjà pour cet agent.",
            ]);
        }

        $user = User::create([
            'name' => $agent->nomComplet(),
            'matricule' => $agent->matricule,
            'email' => strtolower(trim($email)),
            'password' => Hash::make($password),
            'role' => 'agent',
        ]);

        $agent->update(['user_id' => $user->id]);

        return $user;
    }
}
