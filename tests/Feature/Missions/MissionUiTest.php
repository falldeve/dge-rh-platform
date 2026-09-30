<?php

use App\Livewire\Missions\MesMissions;
use App\Livewire\Missions\NouvelleMission;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxUiMission(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $agent = Agent::create(['prenoms' => 'Papa', 'noms' => 'NIANG', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20]);

    return compact('dir', 'secUser', 'agent');
}

it('interdit l’espace missions aux non secrétaires (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/missions')->assertForbidden();
});

it('le secrétaire crée un ordre de mission', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();

    Livewire::actingAs($sec)
        ->test(NouvelleMission::class)
        ->set('agent_id', $agent->id)
        ->set('date_debut', '2026-06-14')
        ->set('date_fin', '2026-06-16')
        ->set('motif', 'Mission DGE')
        ->set('destination', 'Dakar – Mbour – Dakar')
        ->set('moyen_transport', 'AD 31057')
        ->set('signataires', ['dg'])
        ->call('creer')
        ->assertRedirect(route('missions.mes'));

    $demande = Demande::where('agent_id', $agent->id)->first();
    expect($demande->statut)->toBe(Demande::STATUT_EMISE);
    expect($demande->meta['destination'])->toBe('Dakar – Mbour – Dakar');
});

it('exige au moins un signataire', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();

    Livewire::actingAs($sec)
        ->test(NouvelleMission::class)
        ->set('agent_id', $agent->id)
        ->set('date_debut', '2026-06-14')
        ->set('date_fin', '2026-06-16')
        ->set('signataires', [])
        ->call('creer')
        ->assertHasErrors('signataires');

    expect(Demande::count())->toBe(0);
});

it('liste les missions de la direction du secrétaire', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();
    Demande::create(['agent_id' => $agent->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'MAMISSION', 'meta' => ['signataires' => ['dg']]]);
    $autre = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $agentAutre = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autre->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $agentAutre->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'AUTREMISSION', 'meta' => ['signataires' => ['dg']]]);

    Livewire::actingAs($sec)
        ->test(MesMissions::class)
        ->assertSee('MAMISSION')
        ->assertDontSee('AUTREMISSION');
});
