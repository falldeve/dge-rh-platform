<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('un agent sans drapeau est gratuit avec le forfait config', function () {
    config()->set('assistant.forfait_gratuit_credits', 50);
    $u = User::factory()->create(['assistant_ia_actif' => false]);

    expect($u->assistantEstPremium())->toBeFalse()
        ->and($u->assistantAllocationCredits())->toBe(50)
        ->and($u->assistantModele())->toBe(config('assistant.modele_gratuit'));
});

it('un agent avec drapeau est premium avec son quota', function () {
    $u = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 300]);

    expect($u->assistantEstPremium())->toBeTrue()
        ->and($u->assistantAllocationCredits())->toBe(300)
        ->and($u->assistantModele())->toBe(config('assistant.modele_premium'));
});

it('décompte la consommation du mois et calcule le restant', function () {
    $u = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    $u->enregistrerConsommation(2000, 500, 3);
    $u->enregistrerConsommation(1000, 1000, 2);

    expect($u->assistantCreditsConsommesMois())->toBe(5)
        ->and($u->assistantCreditsRestants())->toBe(95);
});

it('le super-admin est premium', function () {
    $u = User::factory()->create(['role' => 'admin', 'assistant_ia_actif' => false]);
    expect($u->assistantEstPremium())->toBeTrue();
});
