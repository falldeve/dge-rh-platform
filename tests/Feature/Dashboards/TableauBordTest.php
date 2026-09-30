<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Support\TableauBord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedStats(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $a1 = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dg->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $a2 = Agent::create(['prenoms' => 'Modou', 'noms' => 'FALL', 'matricule' => 'A2', 'direction_id' => $dg->id, 'statut' => 'autre', 'solde_conge_jours' => 5]);
    $a3 = Agent::create(['prenoms' => 'Bou', 'noms' => 'NDOYE', 'matricule' => 'A3', 'direction_id' => $doe->id, 'statut' => 'autre', 'solde_conge_jours' => 20]);

    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'soumise']);
    Demande::create(['agent_id' => $a2->id, 'type' => 'permission', 'date_debut' => '2026-08-02', 'date_fin' => '2026-08-02', 'nb_jours' => 1, 'statut' => 'soumise']);
    Demande::create(['agent_id' => $a3->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_chef']);
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);
    Demande::create(['agent_id' => $a2->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'validee_rh']);

    return compact('dg', 'doe', 'a1', 'a2', 'a3');
}

it('compte les demandes par statut', function () {
    seedStats();

    $s = TableauBord::parStatut();

    expect($s['soumise'])->toBe(2);
    expect($s['validee_chef'])->toBe(1);
    expect($s['validee_rh'])->toBe(2);
    expect($s['refusee'])->toBe(0);
    expect($s['emise'])->toBe(0);
});

it('compte les demandes par type', function () {
    seedStats();

    $t = TableauBord::parType();

    expect($t['conge_annuel'])->toBe(3);
    expect($t['permission'])->toBe(2);
    expect($t['ordre_mission'])->toBe(0);
});

it('compte les files d’attente', function () {
    ['dg' => $dg] = seedStats();

    expect(TableauBord::enAttenteRh())->toBe(1);
    expect(TableauBord::enAttenteChef($dg->id))->toBe(2);
});

it('calcule le taux d’absence par direction à une date donnée', function () {
    ['dg' => $dg, 'doe' => $doe] = seedStats();

    $lignes = TableauBord::parDirection('2026-08-05');
    $dgLigne = $lignes->firstWhere('code', 'DG');
    $doeLigne = $lignes->firstWhere('code', 'DOE');

    expect($dgLigne['agents'])->toBe(2);
    expect($dgLigne['en_conge'])->toBe(1);
    expect($dgLigne['taux'])->toBe(50.0);

    expect($doeLigne['agents'])->toBe(1);
    expect($doeLigne['en_conge'])->toBe(0);
    expect($doeLigne['taux'])->toBe(0.0);
});
