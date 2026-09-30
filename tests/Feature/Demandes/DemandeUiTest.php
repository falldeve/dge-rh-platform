<?php

use App\Livewire\Demandes\MesDemandes;
use App\Livewire\Demandes\NouvelleDemande;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentConnecte(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $dir->update(['chef_id' => $chef->id]);
    $user = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $user->id]);

    return [$user, $agent];
}

it('soumet une demande de congé via le composant', function () {
    [$user, $agent] = agentConnecte();

    Livewire::actingAs($user)
        ->test(NouvelleDemande::class)
        ->set('type', 'conge_annuel')
        ->set('date_debut', '2026-08-01')
        ->set('date_fin', '2026-08-05')
        ->set('motif', 'Congé annuel')
        ->call('soumettre')
        ->assertRedirect(route('demandes.mes'));

    $demande = Demande::where('agent_id', $agent->id)->first();
    expect($demande)->not->toBeNull();
    expect($demande->nb_jours)->toBe(5);
    expect($demande->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('affiche une erreur si solde insuffisant', function () {
    [$user, $agent] = agentConnecte();
    $agent->update(['solde_conge_jours' => 2]);

    Livewire::actingAs($user)
        ->test(NouvelleDemande::class)
        ->set('type', 'conge_annuel')
        ->set('date_debut', '2026-08-01')
        ->set('date_fin', '2026-08-10')
        ->call('soumettre')
        ->assertHasErrors('nb_jours');

    expect(Demande::count())->toBe(0);
});

it('liste seulement mes demandes', function () {
    [$user, $agent] = agentConnecte();
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise', 'motif' => 'MAPERM']);
    $autre = Agent::create(['prenoms' => 'Autre', 'noms' => 'AGENT', 'matricule' => 'B1', 'direction_id' => $agent->direction_id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $autre->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise', 'motif' => 'AUTREPERM']);

    Livewire::actingAs($user)
        ->test(MesDemandes::class)
        ->assertSee('MAPERM')
        ->assertDontSee('AUTREPERM');
});

it('interdit la page demandes aux invités', function () {
    $this->get('/demandes')->assertRedirect('/login');
});
