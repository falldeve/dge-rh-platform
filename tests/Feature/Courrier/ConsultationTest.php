<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Livewire\Courrier\MesCourriers;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxConsult(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef DOE', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $mien = Courrier::create(['numero' => 'MIEN', 'objet' => 'POUR DOE', 'expediteur' => 'P', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $mien->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);
    $autre = Courrier::create(['numero' => 'AUTRE', 'objet' => 'PAS POUR MOI', 'expediteur' => 'Q', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);

    return compact('chefU', 'eDoe', 'mien', 'autre');
}

it('un agent sans entité gérée voit une liste vide (pas d’erreur)', function () {
    ctxConsult();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    // Accès ouvert (auth), mais le composant ne montre aucun courrier non géré.
    Livewire::actingAs($agent)->test(MesCourriers::class)
        ->assertOk()
        ->assertDontSee('POUR DOE');
});

it('liste seulement les courriers de mon entité', function () {
    ['chefU' => $chef] = ctxConsult();

    Livewire::actingAs($chef)
        ->test(MesCourriers::class)
        ->assertSee('POUR DOE')
        ->assertDontSee('PAS POUR MOI');
});

it('la fiche entité respecte la policy', function () {
    ['chefU' => $chef, 'mien' => $mien, 'autre' => $autre] = ctxConsult();

    Livewire::actingAs($chef)->test(CourrierEntite::class, ['courrier' => $mien])->assertOk();

    // Livewire::test() catches AuthorizationException and renders a 403 response
    // (RequestBroker excludes AuthorizationException from re-throw), so assert on the response.
    Livewire::actingAs($chef)->test(CourrierEntite::class, ['courrier' => $autre])->assertForbidden();
});

it('réinitialise les filtres de Mes courriers', function () {
    ['chefU' => $chef] = ctxConsult();

    Livewire::actingAs($chef)->test(MesCourriers::class)
        ->set('search', 'abc')->set('statut', 'lus')
        ->call('reinitialiser')
        ->assertSet('search', '')->assertSet('statut', '');
});
