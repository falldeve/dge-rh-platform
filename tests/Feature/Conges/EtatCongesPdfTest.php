<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exporte l’état des congés en PDF', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);

    $response = $this->actingAs($rh)->get('/rh/etat-conges.pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('interdit l’export PDF aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/etat-conges.pdf')->assertForbidden();
});
