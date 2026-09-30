<?php

use App\Actions\InitierMission;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function ctxMission(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $agent = Agent::create(['prenoms' => 'Papa Ibrahima', 'noms' => 'NIANG', 'matricule' => '710.231/F', 'direction_id' => $dir->id, 'statut' => 'police', 'fonction' => 'Agent DGE', 'solde_conge_jours' => 20]);

    return compact('dir', 'secUser', 'secAgent', 'agent');
}

it('crée un ordre de mission emise pour un agent de la direction', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    $meta = ['destination' => 'Dakar – Mbour – Dakar', 'moyen_transport' => 'AD 31057', 'indice' => '', 'groupe' => '', 'imputation' => '', 'chapitre' => '', 'article' => ''];

    $demande = app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', 'Mission DGE', $meta, ['dg']);

    expect($demande->type)->toBe('ordre_mission');
    expect($demande->statut)->toBe(Demande::STATUT_EMISE);
    expect($demande->agent_id)->toBe($agent->id);
    expect($demande->meta['destination'])->toBe('Dakar – Mbour – Dakar');
    expect($demande->meta['signataires'])->toBe(['dg']);
});

it('accepte deux signataires', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    $demande = app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', 'Mission', [], ['dg', 'directeur']);

    expect($demande->meta['signataires'])->toBe(['dg', 'directeur']);
});

it('refuse un secrétaire pour un agent d’une autre direction', function () {
    ['secUser' => $sec] = ctxMission();
    $autre = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $agentAutre = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autre->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    expect(fn () => app(InitierMission::class)->handle($sec, $agentAutre, '2026-06-14', '2026-06-16', null, [], ['dg']))
        ->toThrow(AuthorizationException::class);
    expect(Demande::count())->toBe(0);
});

it('refuse un utilisateur non secrétaire', function () {
    ['agent' => $agent] = ctxMission();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    expect(fn () => app(InitierMission::class)->handle($rh, $agent, '2026-06-14', '2026-06-16', null, [], ['dg']))
        ->toThrow(AuthorizationException::class);
});

it('refuse une liste de signataires vide ou invalide', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    expect(fn () => app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', null, [], []))
        ->toThrow(ValidationException::class);
    expect(fn () => app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', null, [], ['roi']))
        ->toThrow(ValidationException::class);
});
