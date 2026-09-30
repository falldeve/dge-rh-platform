<?php

use App\Livewire\Auth\ChangerMotDePasse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userAChanger(): User
{
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('provisoire'), 'role' => 'admin_rh', 'must_change_password' => true]);

    return $u;
}

it('redirige un utilisateur must_change vers le changement de mot de passe', function () {
    $this->withoutVite();
    $u = userAChanger();

    $this->actingAs($u)->get('/rh/tableau-bord')->assertRedirect(route('password.change'));
});

it('le changement de mot de passe lève le drapeau et connecte à l’app', function () {
    $u = userAChanger();

    Livewire::actingAs($u)->test(ChangerMotDePasse::class)
        ->set('password', 'nouveauMotDePasse1')
        ->set('password_confirmation', 'nouveauMotDePasse1')
        ->call('changer')
        ->assertHasNoErrors();

    $u->refresh();
    expect($u->must_change_password)->toBeFalse();
    expect(Hash::check('nouveauMotDePasse1', $u->password))->toBeTrue();
});
