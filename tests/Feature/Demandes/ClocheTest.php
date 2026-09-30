<?php

use App\Livewire\Notifications\Cloche;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use App\Notifications\DemandeDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userAvecNotif(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Awa', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $user->id]);
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'refusee']);
    $user->notify(new DemandeDecisionNotification($demande, 'Votre demande a été refusée.'));

    return [$user->fresh(), $demande];
}

it('affiche le nombre de notifications non lues', function () {
    [$user] = userAvecNotif();

    Livewire::actingAs($user)
        ->test(Cloche::class)
        ->assertSee('1')
        ->assertSee('refusée');
});

it('marque toutes les notifications comme lues', function () {
    [$user] = userAvecNotif();

    Livewire::actingAs($user)
        ->test(Cloche::class)
        ->call('toutMarquerLu');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
