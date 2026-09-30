<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Livewire\Courrier\MesCourriers;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Diligence;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxDiligence(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $c = Courrier::create(['numero' => 'C-1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);

    return compact('chefU', 'eDoe', 'c');
}

it('le destinataire répond à sa hiérarchie (diligence)', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe, 'c' => $c] = ctxDiligence();

    Livewire::actingAs($chef)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('reponse', 'Dossier traité, transmis au service concerné.')
        ->call('repondre')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('diligences', [
        'courrier_id' => $c->id,
        'entite_id' => $eDoe->id,
        'user_id' => $chef->id,
        'contenu' => 'Dossier traité, transmis au service concerné.',
    ]);
});

it('la réponse vide est refusée', function () {
    ['chefU' => $chef, 'c' => $c] = ctxDiligence();

    Livewire::actingAs($chef)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('reponse', '')
        ->call('repondre')
        ->assertHasErrors('reponse');

    expect(Diligence::count())->toBe(0);
});

it('un utilisateur non destinataire ne peut pas répondre (403)', function () {
    ['c' => $c] = ctxDiligence();
    $arch = User::create(['name' => 'Arch', 'matricule' => 'AR1', 'password' => bcrypt('s'), 'role' => 'archiviste']);

    Livewire::actingAs($arch)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('reponse', 'test')
        ->call('repondre')
        ->assertForbidden();

    expect(Diligence::count())->toBe(0);
});

it('filtre lu / non lu sur la liste (basé sur accusé de réception)', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe, 'c' => $c] = ctxDiligence();
    $autre = Courrier::create(['numero' => 'C-2', 'objet' => 'Autre', 'expediteur' => 'Z', 'date_arrivee' => '2026-07-08', 'enregistre_par' => User::where('matricule', 'BC1')->first()->id]);
    Imputation::create(['courrier_id' => $autre->id, 'niveau' => 'dg', 'saisi_par' => User::where('matricule', 'BC1')->first()->id])->destinataires()->sync([$eDoe->id]);

    // C-1 accusé (lu), C-2 non.
    \App\Models\AccuseReception::create(['courrier_id' => $c->id, 'entite_id' => $eDoe->id, 'user_id' => $chef->id]);

    Livewire::actingAs($chef)->test(MesCourriers::class)
        ->set('statut', 'non_lus')->assertSee('C-2')->assertDontSee('C-1');

    Livewire::actingAs($chef)->test(MesCourriers::class)
        ->set('statut', 'lus')->assertSee('C-1')->assertDontSee('C-2');
});