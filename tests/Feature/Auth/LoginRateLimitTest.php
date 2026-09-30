<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => RateLimiter::clear('login:127.0.0.1|m1'));

it('bloque après 5 tentatives de mot de passe échouées', function () {
    User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('secret123'), 'role' => 'agent']);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)->set('matricule', 'M1')->set('password', 'faux')->call('login');
    }

    Livewire::test(Login::class)
        ->set('matricule', 'M1')->set('password', 'secret123')
        ->call('login')
        ->assertHasErrors('matricule'); // bloqué (trop de tentatives) malgré le bon mot de passe
});
