<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un agent rattaché à une direction', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    $agent = Agent::create([
        'prenoms' => 'Biram',
        'noms' => 'SENE',
        'matricule' => '636324/D',
        'profession' => 'Magistrat',
        'fonction' => 'Directeur général des Elections',
        'direction_id' => $dir->id,
        'statut' => 'fonctionnaire',
        'solde_conge_jours' => 30,
    ]);

    expect($agent->direction->code)->toBe('DG');
    expect($dir->agents()->count())->toBe(1);
    expect($agent->user_id)->toBeNull();
});

it('impose un matricule unique quand présent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    expect(fn () => Agent::create(['prenoms' => 'C', 'noms' => 'D', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
