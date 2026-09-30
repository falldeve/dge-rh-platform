<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('un agent voit Bibliothèque et Assistant IA dans sa barre (tableau de bord)', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $this->actingAs($agent)->get('/dashboard')
        ->assertOk()
        ->assertSee('Bibliothèque')
        ->assertSee('Assistant IA');
});

it('les liens agent pointent vers les bonnes routes', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $this->actingAs($agent)->get('/dashboard')
        ->assertSee(route('bibliotheque'), false)
        ->assertSee(route('assistant'), false);
});
