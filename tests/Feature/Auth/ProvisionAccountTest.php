<?php

use App\Actions\ProvisionAccount;
use App\Mail\AccountProvisioned;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('provisionne un compte avec mot de passe provisoire et envoie l’email', function () {
    Mail::fake();

    $user = app(ProvisionAccount::class)->handle([
        'name' => 'Awa DIOP', 'matricule' => 'COURRIER', 'email' => 'awa@dge.sn', 'role' => 'courrier',
    ]);

    expect($user->role)->toBe('courrier');
    expect($user->must_change_password)->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeTrue();          // vérifié d'office (admin vouche)
    Mail::assertSent(AccountProvisioned::class, fn ($m) => $m->hasTo('awa@dge.sn'));
});

it('refuse un email déjà utilisé', function () {
    User::create(['name' => 'X', 'matricule' => 'Z', 'email' => 'awa@dge.sn', 'password' => bcrypt('x'), 'role' => 'agent']);

    expect(fn () => app(ProvisionAccount::class)->handle(['name' => 'Y', 'email' => 'awa@dge.sn', 'role' => 'agent']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
