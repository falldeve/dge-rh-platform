<?php

use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userTfa(): User
{
    return User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'x@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);
}

it('émet un code 2FA vérifiable une seule fois', function () {
    $u = userTfa();
    $svc = app(TwoFactorChallenge::class);

    $code = $svc->issueFor($u);

    expect($code)->toHaveLength(6);
    expect($svc->verify($u, '000000'))->toBeFalse();      // mauvais code
    expect($svc->verify($u, $code))->toBeTrue();          // bon code
    expect($svc->verify($u, $code))->toBeFalse();         // déjà consommé
});

it('bloque après trop de tentatives', function () {
    $u = userTfa();
    $svc = app(TwoFactorChallenge::class);
    $svc->issueFor($u);

    for ($i = 0; $i < 5; $i++) {
        $svc->verify($u, '999999');
    }
    // 5 tentatives ratées → même un bon code est refusé
    expect($svc->verify($u, '111111'))->toBeFalse();
});

it('reconnaît un appareil de confiance non expiré', function () {
    $u = userTfa();
    $mgr = app(TrustedDeviceManager::class);

    $token = $mgr->remember($u, 'UA-test');

    expect($mgr->matches($u, $token))->toBeTrue();
    expect($mgr->matches($u, 'jeton-inconnu'))->toBeFalse();
    expect($mgr->matches($u, null))->toBeFalse();
});
