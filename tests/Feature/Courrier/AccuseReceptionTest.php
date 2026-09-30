<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Models\AccuseReception;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxAccuse(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef DOE', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $c = Courrier::create(['numero' => '1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);

    return compact('chefU', 'eDoe', 'c');
}

it('le destinataire accuse réception de son entité', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe, 'c' => $c] = ctxAccuse();

    Livewire::actingAs($chef)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->call('accuser', $eDoe->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accuses_reception', [
        'courrier_id' => $c->id,
        'entite_id' => $eDoe->id,
        'user_id' => $chef->id,
    ]);
});

it('accuser deux fois ne crée pas de doublon', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe, 'c' => $c] = ctxAccuse();

    Livewire::actingAs($chef)->test(CourrierEntite::class, ['courrier' => $c])
        ->call('accuser', $eDoe->id)
        ->call('accuser', $eDoe->id);

    expect(AccuseReception::where('courrier_id', $c->id)->where('entite_id', $eDoe->id)->count())->toBe(1);
});

it('refuse d’accuser pour une entité non gérée (403)', function () {
    ['c' => $c, 'eDoe' => $eDoe] = ctxAccuse();
    $autre = User::create(['name' => 'Autre', 'matricule' => 'AU1', 'password' => bcrypt('s'), 'role' => 'archiviste']);

    Livewire::actingAs($autre)->test(CourrierEntite::class, ['courrier' => $c])
        ->call('accuser', $eDoe->id)
        ->assertForbidden();

    expect(AccuseReception::count())->toBe(0);
});