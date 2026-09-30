<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepte le rôle secretaire et son helper', function () {
    $user = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);

    expect($user->fresh()->role)->toBe('secretaire');
    expect($user->isSecretaire())->toBeTrue();
    expect($user->isAgent())->toBeFalse();
});

it('promeut un compte en secretaire via la commande', function () {
    User::create(['name' => 'X', 'matricule' => 'M1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->artisan('rh:promouvoir', ['matricule' => 'M1', 'role' => 'secretaire'])->assertExitCode(0);

    expect(User::where('matricule', 'M1')->first()->role)->toBe('secretaire');
});

it('expose la configuration du DG', function () {
    expect(config('dge.dg_nom'))->not->toBeNull();
    expect(config('dge.dg_fonction'))->not->toBeNull();
});
