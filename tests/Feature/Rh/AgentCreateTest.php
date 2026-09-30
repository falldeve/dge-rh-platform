<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhCreate(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('crée un agent avec les champs saisis', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', 'Fatou')
        ->set('noms', 'NDIAYE')
        ->set('matricule', '800000/A')
        ->set('profession', 'Secrétaire')
        ->set('fonction', 'Assistante')
        ->set('direction_id', $dir->id)
        ->set('statut', 'contractuel_pav')
        ->set('solde_conge_jours', 25)
        ->set('telephone', '770000000')
        ->set('email', 'fatou@example.sn')
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent = Agent::where('matricule', '800000/A')->first();
    expect($agent)->not->toBeNull();
    expect($agent->prenoms)->toBe('Fatou');
    expect($agent->direction_id)->toBe($dir->id);
    expect($agent->statut)->toBe('contractuel_pav');
    expect((float) $agent->solde_conge_jours)->toBe(25.0);
});

it('refuse un agent sans prénom/nom/direction', function () {
    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', '')
        ->set('noms', '')
        ->call('save')
        ->assertHasErrors(['prenoms', 'noms', 'direction_id']);

    expect(Agent::count())->toBe(0);
});

it('refuse un matricule déjà utilisé', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'DUP1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', 'C')
        ->set('noms', 'D')
        ->set('matricule', 'DUP1')
        ->set('direction_id', $dir->id)
        ->call('save')
        ->assertHasErrors('matricule');

    expect(Agent::count())->toBe(1);
});
