<?php

use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\ReinitialiserMotDePasse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('envoie le lien de réinitialisation', function () {
    Notification::fake();
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'agent']);

    Livewire::test(MotDePasseOublie::class)->set('email', 'm1@dge.sn')->call('envoyer')->assertHasNoErrors();

    Notification::assertSentTo($u, ResetPassword::class);
});

it('réinitialise le mot de passe avec un token valide', function () {
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('ancien'), 'role' => 'agent']);
    $token = Password::createToken($u);

    Livewire::test(ReinitialiserMotDePasse::class, ['token' => $token, 'email' => 'm1@dge.sn'])
        ->set('email', 'm1@dge.sn')
        ->set('password', 'nouveau1234')->set('password_confirmation', 'nouveau1234')
        ->call('reinitialiser')
        ->assertRedirect(route('login'));

    expect(Illuminate\Support\Facades\Hash::check('nouveau1234', $u->fresh()->password))->toBeTrue();
});
