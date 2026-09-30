<?php

use App\Livewire\Auth\Register;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentSansCompte(): Agent
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);

    return Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
}

it('inscrit un agent avec email et envoie la vérification', function () {
    Notification::fake();
    $this->withoutVite();
    agentSansCompte();

    Livewire::test(Register::class)
        ->set('matricule', 'A1')->set('noms', 'DIOP')->set('prenoms', 'Awa')
        ->set('email', 'awa@dge.sn')
        ->set('password', 'motdepasse')->set('password_confirmation', 'motdepasse')
        ->call('register');

    $user = User::where('email', 'awa@dge.sn')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('refuse un email déjà utilisé', function () {
    $this->withoutVite();
    agentSansCompte();
    User::create(['name' => 'Y', 'matricule' => 'Z9', 'email' => 'awa@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);

    Livewire::test(Register::class)
        ->set('matricule', 'A1')->set('noms', 'DIOP')->set('prenoms', 'Awa')
        ->set('email', 'awa@dge.sn')
        ->set('password', 'motdepasse')->set('password_confirmation', 'motdepasse')
        ->call('register')
        ->assertHasErrors('email');
});

it('un utilisateur non vérifié est bloqué hors de l’app par le middleware verified', function () {
    $this->withoutVite();
    $u = User::create(['name' => 'NV', 'matricule' => 'NV1', 'email' => 'nv@dge.sn', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $this->actingAs($u)->get('/rh/tableau-bord')->assertRedirect(route('verification.notice'));
});

it('le lien signé vérifie l’email', function () {
    $u = User::create(['name' => 'NV', 'matricule' => 'NV2', 'email' => 'nv2@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);
    Event::fake();

    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify', now()->addMinutes(60),
        ['id' => $u->id, 'hash' => sha1($u->email)]
    );

    $this->actingAs($u)->get($url)->assertRedirect();
    expect($u->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});
