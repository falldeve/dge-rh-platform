<?php

use App\Models\User;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactor;
use App\Livewire\Auth\EmailRequis;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('refuse la connexion d’un compte désactivé', function () {
    $u = User::factory()->create([
        'matricule' => 'X001', 'password' => Hash::make('secret'),
        'email' => 'x@dge.sn', 'email_verified_at' => now(),
        'compte_actif' => false,
    ]);

    Livewire::test(Login::class)
        ->set('matricule', 'X001')
        ->set('password', 'secret')
        ->call('login')
        ->assertHasErrors('matricule');

    expect(auth()->check())->toBeFalse();
});

it('déconnecte un utilisateur désactivé en pleine session (middleware)', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'compte_actif' => false]);

    $this->actingAs($u)->get('/dashboard')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('un compte actif accède normalement', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'compte_actif' => true]);
    $this->actingAs($u)->get('/dashboard')->assertOk();
});

it('un compte désactivé pendant l’attente 2FA n’est pas connecté', function () {
    $user = User::factory()->create([
        'compte_actif' => true, 'email_verified_at' => now(), 'must_change_password' => false,
    ]);
    $code = app(TwoFactorChallenge::class)->issueFor($user);
    session()->put('pending_2fa', ['id' => $user->id]);

    $user->update(['compte_actif' => false]);

    Livewire::test(TwoFactor::class)
        ->set('code', $code)
        ->call('verifier')
        ->assertRedirect(route('login'));

    expect(session()->has('pending_2fa'))->toBeFalse();
    $this->assertGuest();
});

it('un compte désactivé pendant l’attente email n’est pas connecté', function () {
    $user = User::factory()->create(['compte_actif' => true, 'email' => null, 'email_verified_at' => null]);
    session()->put('pending_email_user', $user->id);

    $user->update(['compte_actif' => false]);

    Livewire::test(EmailRequis::class)
        ->set('email', 'nouveau@dge.sn')
        ->call('enregistrer')
        ->assertRedirect(route('login'));

    expect(session()->has('pending_email_user'))->toBeFalse();
    $this->assertGuest();
});
