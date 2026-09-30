<?php

namespace App\Actions;

use App\Mail\AccountProvisioned;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProvisionAccount
{
    /** Rôles provisionnables — jamais 'admin' (le super-admin ne se crée pas via ce flux). */
    public const ROLES = ['agent', 'admin_rh', 'courrier', 'archiviste', 'dg', 'chef_direction', 'secretaire'];

    /** @param array{name:string,email:string,role:string,matricule?:string,agent_id?:int} $data */
    public function handle(array $data): User
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(self::ROLES)],
        ])->validate();

        $temp = Str::password(12); // mot de passe provisoire aléatoire

        $user = User::create([
            'name' => $data['name'],
            'matricule' => $data['matricule'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($temp),
            'role' => $data['role'],
            'must_change_password' => true,
        ]);
        $user->markEmailAsVerified(); // l'admin garantit l'adresse ; le mail provisoire prouve sa validité

        if (! empty($data['agent_id'])) {
            Agent::whereKey($data['agent_id'])->update(['user_id' => $user->id]);
        }

        Mail::to($user->email)->send(new AccountProvisioned($user, $temp));

        return $user;
    }
}
