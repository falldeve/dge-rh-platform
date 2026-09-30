<?php

use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un user avec rôle et le relie à un agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create([
        'name' => 'Biram SENE',
        'matricule' => '636324/D',
        'password' => bcrypt('secret'),
        'role' => 'admin_rh',
    ]);
    $agent = Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire',
        'solde_conge_jours' => 30, 'user_id' => $user->id,
    ]);

    expect($user->role)->toBe('admin_rh');
    expect($user->agent->id)->toBe($agent->id);
});
