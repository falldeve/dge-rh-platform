<?php

use App\Livewire\Rh\AgentsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function rhUser(): User
{
    return User::factory()->create(['role' => 'admin_rh', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('la RH désactive puis réactive un compte agent', function () {
    $agentUser = User::factory()->create(['role' => 'agent', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $agentUser->id);
    expect($agentUser->fresh()->compte_actif)->toBeFalse();

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $agentUser->id);
    expect($agentUser->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver le super-admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $admin->id)
        ->assertStatus(403);

    expect($admin->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver son propre compte', function () {
    $rh = rhUser();

    Livewire::actingAs($rh)->test(AgentsIndex::class)
        ->call('basculerCompte', $rh->id)
        ->assertStatus(403);

    expect($rh->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver un compte non-agent (dg, courrier, etc.)', function () {
    $dg = User::factory()->create(['role' => 'dg', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $dg->id)
        ->assertStatus(403);

    expect($dg->fresh()->compte_actif)->toBeTrue();

    $courrier = User::factory()->create(['role' => 'courrier', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $courrier->id)
        ->assertStatus(403);

    expect($courrier->fresh()->compte_actif)->toBeTrue();
});
