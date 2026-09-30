<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée une conversation avec messages', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->for($user)->create(['niveau' => 'premium']);
    $conv->messages()->create([
        'role' => 'user',
        'contenu' => [['type' => 'text', 'text' => 'Bonjour']],
    ]);

    expect($conv->messages)->toHaveCount(1)
        ->and($conv->messages->first()->contenu[0]['text'])->toBe('Bonjour')
        ->and($conv->user->is($user))->toBeTrue();
});

it('a des colonnes assistant sur users avec valeurs par défaut', function () {
    $user = User::factory()->create();
    expect($user->assistant_ia_actif)->toBeFalse()
        ->and($user->assistant_quota_credits)->toBe(0);
});
