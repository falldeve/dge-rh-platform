<?php

use App\Livewire\Rh\TableauBord;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('interdit le tableau de bord RH aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/tableau-bord')->assertForbidden();
});

it('affiche les agrégats à la DRHF', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_chef']);

    Livewire::actingAs($rh)
        ->test(TableauBord::class)
        ->assertSee('En attente DRHF')
        ->assertSee('DG');
});
