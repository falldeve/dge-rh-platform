<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxCascade(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $secU = User::create(['name' => 'Sec DOE', 'matricule' => 'SE1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secA = Agent::create(['prenoms' => 'Se', 'noms' => 'CR', 'matricule' => 'SE1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secU->id]);

    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'secretaire_agent_id' => $secA->id]);
    $div1 = Entite::create(['code' => 'DOE-CARTE', 'nom' => 'Carte', 'type' => 'division', 'parent_id' => $eDoe->id]);
    $div2 = Entite::create(['code' => 'DOE-JUR', 'nom' => 'Juridique', 'type' => 'division', 'parent_id' => $eDoe->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $c = Courrier::create(['numero' => '1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);

    return compact('secU', 'eDoe', 'div1', 'div2', 'c');
}

it('le chef de direction cascade aussi vers ses divisions', function () {
    ['eDoe' => $eDoe, 'div1' => $div1, 'c' => $c] = ctxCascade();

    // Un chef de direction (chef_agent_id de l'entité) doit pouvoir cascader.
    $chefU = User::create(['name' => 'Chef DOE', 'matricule' => 'CD1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CD1', 'direction_id' => Direction::first()->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $eDoe->update(['chef_agent_id' => $chefA->id]);

    Livewire::actingAs($chefU)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('divisions', [$div1->id])
        ->set('mentions_cascade', ['information'])
        ->call('cascader')
        ->assertHasNoErrors();

    $cascade = $c->imputations()->where('niveau', 'direction')->first();
    expect($cascade)->not->toBeNull();
    expect($cascade->entite_source_id)->toBe($eDoe->id);
    expect($cascade->saisi_par)->toBe($chefU->id);
});

it('le secrétaire cascade vers les divisions de sa direction', function () {
    ['secU' => $sec, 'eDoe' => $eDoe, 'div1' => $div1, 'div2' => $div2, 'c' => $c] = ctxCascade();

    Livewire::actingAs($sec)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('divisions', [$div1->id, $div2->id])
        ->set('mentions_cascade', ['execution'])
        ->set('observations_cascade', 'À traiter')
        ->call('cascader')
        ->assertHasNoErrors();

    $cascade = $c->imputations()->where('niveau', 'direction')->first();
    expect($cascade)->not->toBeNull();
    expect($cascade->entite_source_id)->toBe($eDoe->id);
    expect($cascade->mentions)->toBe(['execution']);
    expect($cascade->destinataires()->count())->toBe(2);
    expect($cascade->saisi_par)->toBe($sec->id);
});

it('exige au moins une division', function () {
    ['secU' => $sec, 'c' => $c] = ctxCascade();

    Livewire::actingAs($sec)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('divisions', [])
        ->call('cascader')
        ->assertHasErrors('divisions');

    expect(Imputation::where('niveau', 'direction')->count())->toBe(0);
});
