<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactor;
use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userVerifie(string $mat = 'M1'): User
{
    $u = User::create(['name' => 'X', 'matricule' => $mat, 'email' => strtolower($mat).'@dge.sn', 'password' => bcrypt('secret123'), 'role' => 'admin_rh']);
    $u->markEmailAsVerified();

    return $u;
}

it('un login valide déclenche le 2FA (pas de connexion immédiate)', function () {
    Mail::fake();
    $u = userVerifie();

    Livewire::test(Login::class)
        ->set('matricule', 'M1')->set('password', 'secret123')
        ->call('login')
        ->assertRedirect(route('two-factor.show'));

    expect(auth()->check())->toBeFalse();
    Mail::assertSent(TwoFactorCode::class);
    expect(session()->has('pending_2fa'))->toBeTrue();
});

it('rejette un mauvais mot de passe sans 2FA', function () {
    Mail::fake();
    userVerifie();

    Livewire::test(Login::class)
        ->set('matricule', 'M1')->set('password', 'mauvais')
        ->call('login')
        ->assertHasErrors('matricule');

    Mail::assertNothingSent();
});

it('le code 2FA correct connecte l’utilisateur', function () {
    $u = userVerifie();
    // simule l'état pending après login
    session()->put('pending_2fa', ['id' => $u->id]);
    $code = app(TwoFactorChallenge::class)->issueFor($u);

    Livewire::test(TwoFactor::class)
        ->set('code', $code)
        ->call('verifier')
        ->assertHasNoErrors();

    expect(auth()->id())->toBe($u->id);
    expect(session()->has('pending_2fa'))->toBeFalse();
});

it('un code 2FA erroné est refusé', function () {
    $u = userVerifie();
    session()->put('pending_2fa', ['id' => $u->id]);
    app(TwoFactorChallenge::class)->issueFor($u);

    Livewire::test(TwoFactor::class)
        ->set('code', '000000')
        ->call('verifier')
        ->assertHasErrors('code');

    expect(auth()->check())->toBeFalse();
});

it('un compte sans email est redirigé vers la capture d’email', function () {
    Mail::fake();
    $u = User::create(['name' => 'Legacy', 'matricule' => 'LEG', 'email' => null, 'password' => bcrypt('secret123'), 'role' => 'agent']);

    Livewire::test(Login::class)
        ->set('matricule', 'LEG')->set('password', 'secret123')
        ->call('login')
        ->assertRedirect(route('email.requis'));

    expect(session('pending_email_user'))->toBe($u->id);
});
