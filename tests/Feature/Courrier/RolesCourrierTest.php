<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepte les rôles courrier et archiviste + helpers', function () {
    $c = User::create(['name' => 'C', 'matricule' => 'C1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $a = User::create(['name' => 'A', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'archiviste']);

    expect($c->fresh()->role)->toBe('courrier');
    expect($c->isCourrier())->toBeTrue();
    expect($c->isArchiviste())->toBeFalse();
    expect($a->isArchiviste())->toBeTrue();
});

it('promeut en courrier via la commande', function () {
    User::create(['name' => 'X', 'matricule' => 'M1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->artisan('rh:promouvoir', ['matricule' => 'M1', 'role' => 'courrier'])->assertExitCode(0);

    expect(User::where('matricule', 'M1')->first()->role)->toBe('courrier');
});
