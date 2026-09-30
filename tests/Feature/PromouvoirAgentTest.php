<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function compte(string $matricule): User
{
    return User::create([
        'name' => 'Test', 'matricule' => $matricule,
        'password' => bcrypt('secret'), 'role' => 'agent',
    ]);
}

it('promeut un compte existant', function () {
    compte('636324/D');

    $this->artisan('rh:promouvoir', ['matricule' => '636324/D', 'role' => 'admin_rh'])
        ->assertExitCode(0);

    expect(User::where('matricule', '636324/D')->first()->role)->toBe('admin_rh');
});

it('refuse un rôle invalide', function () {
    compte('636324/D');

    $this->artisan('rh:promouvoir', ['matricule' => '636324/D', 'role' => 'roi'])
        ->assertExitCode(1);

    expect(User::where('matricule', '636324/D')->first()->role)->toBe('agent');
});

it('refuse un matricule sans compte', function () {
    $this->artisan('rh:promouvoir', ['matricule' => '000000/X', 'role' => 'admin_rh'])
        ->assertExitCode(1);
});
