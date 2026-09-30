<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\Validation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unAgent(): Agent
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    return Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('crée une demande liée à un agent', function () {
    $agent = unAgent();

    $demande = Demande::create([
        'agent_id' => $agent->id,
        'type' => 'conge_annuel',
        'date_debut' => '2026-08-01',
        'date_fin' => '2026-08-05',
        'nb_jours' => 5,
        'statut' => 'soumise',
    ]);

    expect($demande->agent->id)->toBe($agent->id);
    expect($agent->demandes()->count())->toBe(1);
    expect($demande->date_debut->format('Y-m-d'))->toBe('2026-08-01');
    expect($demande->meta)->toBeNull();
});

it('stocke meta en tableau JSON', function () {
    $agent = unAgent();
    $demande = Demande::create([
        'agent_id' => $agent->id, 'type' => 'ordre_mission',
        'date_debut' => '2026-08-01', 'date_fin' => '2026-08-02',
        'nb_jours' => 2, 'statut' => 'emise',
        'meta' => ['destination' => 'Saint-Louis', 'objet' => 'Supervision'],
    ]);

    expect($demande->fresh()->meta['destination'])->toBe('Saint-Louis');
});

it('journalise une validation', function () {
    $agent = unAgent();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $v = Validation::create([
        'demande_id' => $demande->id, 'validateur_id' => $agent->id,
        'niveau' => 'chef', 'decision' => 'ok', 'commentaire' => 'OK',
    ]);

    expect($demande->validations()->count())->toBe(1);
    expect($v->demande->id)->toBe($demande->id);
    expect($v->validateur->id)->toBe($agent->id);
});
