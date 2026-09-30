<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhForm(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('met à jour la fiche et le solde d’un agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->assertSet('prenoms', 'Biram')
        ->set('fonction', 'Chef de bureau')
        ->set('solde_conge_jours', 42)
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent->refresh();
    expect($agent->fonction)->toBe('Chef de bureau');
    expect((float) $agent->solde_conge_jours)->toBe(42.0);
});

it('attribue un rôle au compte lié de l’agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Biram SENE', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->assertSet('role', 'agent')
        ->set('role', 'chef_direction')
        ->call('save');

    expect($user->fresh()->role)->toBe('chef_direction');
});

it('refuse un rôle invalide', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'B S', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('role', 'roi')
        ->call('save')
        ->assertHasErrors('role');

    expect($user->fresh()->role)->toBe('agent');
});
