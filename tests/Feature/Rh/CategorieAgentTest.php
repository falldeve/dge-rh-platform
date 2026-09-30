<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agentCat(array $attrs): Agent
{
    $dir = Direction::firstOrCreate(['code' => 'DG'], ['nom' => 'Direction Générale']);

    return Agent::create(array_merge([
        'prenoms' => 'X', 'noms' => 'Y', 'direction_id' => $dir->id,
        'statut' => 'autre', 'solde_conge_jours' => 0,
    ], $attrs));
}

it('classe fonctionnaire un agent avec matricule de solde', function () {
    expect(agentCat(['matricule' => '636324/D', 'profession' => 'Magistrat'])->estFonctionnaire())->toBeTrue();
    expect(agentCat(['matricule' => '616542/H', 'profession' => 'Adjudant de police'])->estFonctionnaire())->toBeTrue();
});

it('classe non-fonctionnaire les agents d’appui, PAV et NIN', function () {
    expect(agentCat(['matricule' => '1 225 2000 04032', 'profession' => "Agent d'appui"])->estFonctionnaire())->toBeFalse();
    expect(agentCat(['matricule' => 'PAV75C-041', 'profession' => 'PAV /Contractuel'])->estFonctionnaire())->toBeFalse();
    expect(agentCat(['matricule' => null, 'profession' => 'Chauffeur'])->estFonctionnaire())->toBeFalse();
});

it('l’appui l’emporte même avec un matricule à slash', function () {
    expect(agentCat(['matricule' => '700000/Z', 'profession' => "Agent d'appui (licence)"])->estFonctionnaire())->toBeFalse();
});

it('les scopes filtrent correctement', function () {
    agentCat(['matricule' => '636324/D', 'profession' => 'Magistrat']);          // fonct
    agentCat(['matricule' => '616542/H', 'profession' => 'Adjudant de police']); // fonct
    agentCat(['matricule' => '1 225 2000 04032', 'profession' => "Agent d'appui"]); // non
    agentCat(['matricule' => 'PAV75C-041', 'profession' => 'PAV']);              // non
    agentCat(['matricule' => null, 'profession' => 'Chauffeur']);                // non

    expect(Agent::fonctionnaires()->count())->toBe(2);
    expect(Agent::nonFonctionnaires()->count())->toBe(3);
});
