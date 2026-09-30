<?php

use App\Livewire\Rh\EtatConges;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use App\Support\EtatCongesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxEtat(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $a1 = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dg->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $a2 = Agent::create(['prenoms' => 'Modou', 'noms' => 'FALL', 'matricule' => 'A2', 'direction_id' => $doe->id, 'statut' => 'autre', 'solde_conge_jours' => 5]);
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);
    Demande::create(['agent_id' => $a2->id, 'type' => 'conge_annuel', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-05', 'nb_jours' => 5, 'statut' => 'validee_rh']);
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-20', 'date_fin' => '2026-08-25', 'nb_jours' => 6, 'statut' => 'soumise']);

    return compact('dg', 'doe', 'rh', 'a1', 'a2');
}

it('la requête ne retourne que les congés validés dans la période', function () {
    ['dg' => $dg] = ctxEtat();

    $tous = EtatCongesQuery::pour(null, null, null);
    expect($tous)->toHaveCount(2);

    $enAout = EtatCongesQuery::pour(null, '2026-08-01', '2026-08-31');
    expect($enAout)->toHaveCount(1);

    $dgSeul = EtatCongesQuery::pour($dg->id, null, null);
    expect($dgSeul)->toHaveCount(1);
});

it('interdit l’écran état des congés aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/etat-conges')->assertForbidden();
});

it('l’écran RH liste les congés validés', function () {
    ['rh' => $rh] = ctxEtat();

    Livewire::actingAs($rh)
        ->test(EtatConges::class)
        ->assertSee('DIOP')
        ->assertSee('FALL');
});

it('exporte l’état en CSV', function () {
    ['rh' => $rh] = ctxEtat();

    $response = $this->actingAs($rh)->get('/rh/etat-conges.csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('DIOP');
});

it('interdit l’export CSV aux non admin_rh (403)', function () {
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/etat-conges.csv')->assertForbidden();
});
