<?php

use App\Actions\RegisterAgentAccount;
use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
    app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1');
});

it('authentifie avec le matricule et le bon mot de passe', function () {
    expect(Auth::attempt(['matricule' => '636324/D', 'password' => 'motdepasse1']))->toBeTrue();
    expect(Auth::check())->toBeTrue();
});

it('rejette un mauvais mot de passe', function () {
    expect(Auth::attempt(['matricule' => '636324/D', 'password' => 'faux']))->toBeFalse();
});

it('rejette un matricule inconnu', function () {
    expect(Auth::attempt(['matricule' => '999999/Z', 'password' => 'motdepasse1']))->toBeFalse();
});
