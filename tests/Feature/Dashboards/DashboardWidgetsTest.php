<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('affiche le solde et le nombre de demandes en cours pour l’agent', function () {
    $this->withoutVite();
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 17, 'user_id' => $user->id]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Solde de congé')
        ->assertSee('17')
        ->assertSee('Demandes en cours');
});

it('affiche le nombre de demandes à valider pour le chef', function () {
    $this->withoutVite();
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $this->actingAs($chefUser)->get('/dashboard')
        ->assertOk()
        ->assertSee('À valider');
});
