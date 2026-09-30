<?php

use App\Livewire\Admin\ComptesSysteme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('le super-admin désactive un compte système (courrier)', function () {
    $courrier = User::factory()->create(['role' => 'courrier', 'compte_actif' => true]);

    Livewire::actingAs(adminUser())->test(ComptesSysteme::class)
        ->call('basculer', $courrier->id);

    expect($courrier->fresh()->compte_actif)->toBeFalse();
});

it('interdit de désactiver un autre super-admin', function () {
    $autreAdmin = User::factory()->create(['role' => 'admin', 'compte_actif' => true]);

    Livewire::actingAs(adminUser())->test(ComptesSysteme::class)
        ->call('basculer', $autreAdmin->id)
        ->assertStatus(403);

    expect($autreAdmin->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver son propre compte', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)->test(ComptesSysteme::class)
        ->call('basculer', $admin->id)
        ->assertStatus(403);

    expect($admin->fresh()->compte_actif)->toBeTrue();
});
