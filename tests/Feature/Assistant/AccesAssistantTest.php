<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tout agent connecté et vérifié accède à l’assistant', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/assistant')->assertOk();
});

it('un invité est redirigé vers le login', function () {
    $this->get('/assistant')->assertRedirect('/login');
});

it('le lien Assistant IA apparaît dans la nav pour un agent', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/assistant')->assertSee('Assistant IA');
});

it('le lien de gestion assistant apparaît pour l’admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($admin)->get('/assistant')->assertSee('Gérer l’assistant');
});
