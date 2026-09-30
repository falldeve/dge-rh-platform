<?php

use App\Livewire\Rh\DirectionsManager;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhDirections(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('crée une direction', function () {
    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->set('code', 'DFC')
        ->set('nom', 'Direction Formation Communication')
        ->call('save')
        ->assertHasNoErrors();

    expect(Direction::where('code', 'DFC')->exists())->toBeTrue();
});

it('refuse un code de direction dupliqué', function () {
    Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->set('code', 'DG')
        ->set('nom', 'Autre')
        ->call('save')
        ->assertHasErrors('code');

    expect(Direction::where('code', 'DG')->count())->toBe(1);
});

it('désigne un chef et promeut son compte', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Biram SENE', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->call('designerChef', $dir->id, $agent->id);

    expect($dir->fresh()->chef_id)->toBe($agent->id);
    expect($user->fresh()->role)->toBe('chef_direction');
});

it('désigne un chef sans compte sans erreur', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Sans', 'noms' => 'COMPTE', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->call('designerChef', $dir->id, $agent->id);

    expect($dir->fresh()->chef_id)->toBe($agent->id);
});
