<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tout agent connecté accède à la bibliothèque', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque')->assertOk();
});

it('un invité est redirigé', function () {
    $this->get('/bibliotheque')->assertRedirect('/login');
});

it('le lien Bibliothèque apparaît dans la nav', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/assistant')->assertSee(route('bibliotheque'), false);
});
