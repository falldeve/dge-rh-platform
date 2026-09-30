<?php

use App\Models\Entite;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seed les entités (directions, services, divisions)', function () {
    $this->seed(\Database\Seeders\EntiteSeeder::class);

    expect(Entite::where('type', 'direction')->count())->toBe(5);
    expect(Entite::where('type', 'service')->count())->toBeGreaterThanOrEqual(4);
    expect(Entite::where('type', 'division')->count())->toBeGreaterThanOrEqual(1);

    $doe = Entite::where('code', 'DOE')->first();
    $division = Entite::where('type', 'division')->where('parent_id', $doe->id)->first();
    expect($division)->not->toBeNull();
    expect($division->parent->code)->toBe('DOE');
    expect($doe->enfants()->count())->toBeGreaterThanOrEqual(1);
});

it('relie chef et secrétaire à des agents', function () {
    $dir = App\Models\Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = App\Models\Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    $sec = App\Models\Agent::create(['prenoms' => 'C', 'noms' => 'D', 'matricule' => 'X2', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    $e = Entite::create(['code' => 'TEST', 'nom' => 'Test', 'type' => 'service', 'chef_agent_id' => $chef->id, 'secretaire_agent_id' => $sec->id]);

    expect($e->chef->id)->toBe($chef->id);
    expect($e->secretaire->id)->toBe($sec->id);
});
