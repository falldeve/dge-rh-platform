<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function userAvecRole(string $role): User
{
    return User::create([
        'name' => 'Test', 'matricule' => 'M-'.$role,
        'password' => bcrypt('secret'), 'email_verified_at' => now(), 'role' => $role,
    ]);
}

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin_rh'])->get('/_zone-rh', fn () => 'ok');
});

it('expose des helpers de rôle', function () {
    $rh = userAvecRole('admin_rh');
    expect($rh->isAdminRh())->toBeTrue();
    expect($rh->isAgent())->toBeFalse();
    expect($rh->hasRole('admin_rh', 'dg'))->toBeTrue();
    expect(userAvecRole('agent')->isAgent())->toBeTrue();
    expect(userAvecRole('chef_direction')->isChefDirection())->toBeTrue();
    expect(userAvecRole('dg')->isDg())->toBeTrue();
});

it('laisse passer le bon rôle', function () {
    $this->actingAs(userAvecRole('admin_rh'))->get('/_zone-rh')->assertOk();
});

it('bloque un rôle non autorisé avec 403', function () {
    $this->actingAs(userAvecRole('agent'))->get('/_zone-rh')->assertForbidden();
});

it('protège le dashboard derrière auth', function () {
    $this->withoutVite();
    $this->get('/dashboard')->assertRedirect('/login');
    $this->actingAs(userAvecRole('agent'))->get('/dashboard')->assertOk();
});
