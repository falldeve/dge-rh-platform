<?php

use App\Livewire\Dg\TableauBord;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('interdit la vue DG aux non dg (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/dg')->assertForbidden();
});

it('affiche la synthèse au DG en lecture seule', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $dg = User::create(['name' => 'DG', 'matricule' => 'DG1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'dg']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_rh']);

    Livewire::actingAs($dg)
        ->test(TableauBord::class)
        ->assertSee('Synthèse')
        ->assertSee('DG')
        ->assertDontSee('Valider');
});
