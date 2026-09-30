<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedAgent(): void
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('affiche la page login (inscription publique retirée)', function () {
    $this->withoutVite();
    $this->get('/login')->assertOk()->assertSeeLivewire(Login::class);
    $this->get('/register')->assertNotFound();
});

it('inscrit un agent via le composant et le connecte', function () {
    seedAgent();

    Livewire::test(Register::class)
        ->set('matricule', '636324/D')
        ->set('noms', 'SENE')
        ->set('prenoms', 'Biram')
        ->set('email', 'biram@dge.sn')
        ->set('password', 'motdepasse1')
        ->set('password_confirmation', 'motdepasse1')
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    expect(User::where('matricule', '636324/D')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('affiche une erreur de validation sur matricule inconnu', function () {
    seedAgent();

    Livewire::test(Register::class)
        ->set('matricule', '000000/X')
        ->set('noms', 'SENE')
        ->set('prenoms', 'Biram')
        ->set('email', 'biram@dge.sn')
        ->set('password', 'motdepasse1')
        ->set('password_confirmation', 'motdepasse1')
        ->call('register')
        ->assertHasErrors('matricule');

    $this->assertGuest();
});

it('connecte un agent existant via le composant', function () {
    \Illuminate\Support\Facades\Mail::fake();
    seedAgent();
    $user = app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1');
    $user->markEmailAsVerified();

    // Un compte email + mot de passe valides déclenche désormais le défi 2FA
    // (pas de connexion immédiate) au lieu d'atterrir directement sur /dashboard.
    Livewire::test(Login::class)
        ->set('matricule', '636324/D')
        ->set('password', 'motdepasse1')
        ->call('login')
        ->assertRedirect(route('two-factor.show'));

    $this->assertGuest();
    expect(session()->has('pending_2fa'))->toBeTrue();
});

it('refuse la connexion avec un mauvais mot de passe', function () {
    seedAgent();
    $user = app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1');
    $user->markEmailAsVerified();

    Livewire::test(Login::class)
        ->set('matricule', '636324/D')
        ->set('password', 'faux')
        ->call('login')
        ->assertHasErrors('matricule');

    $this->assertGuest();
});

it('déconnecte un utilisateur', function () {
    seedAgent();
    $user = app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1');
    $user->markEmailAsVerified();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
