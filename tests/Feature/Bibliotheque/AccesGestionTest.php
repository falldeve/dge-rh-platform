<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('archiviste accède à la gestion', function () {
    $u = User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/rubriques')->assertOk();
    $this->actingAs($u)->get('/bibliotheque/gerer/documents')->assertOk();
});

it('super-admin accède à la gestion (bypass)', function () {
    $u = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/rubriques')->assertOk();
});

it('un agent est refusé (403)', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/documents')->assertForbidden();
});

it('le lien de gestion apparaît pour l’archiviste, pas pour un agent', function () {
    $arch = User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($arch)->get('/bibliotheque')->assertSee('Gérer la bibliothèque');

    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque')->assertDontSee('Gérer la bibliothèque');
});
