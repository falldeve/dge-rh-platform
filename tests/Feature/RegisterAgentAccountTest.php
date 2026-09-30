<?php

use App\Actions\RegisterAgentAccount;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function agentPourInscription(): Agent
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    return Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('crée un compte quand matricule + nom + prénom correspondent', function () {
    $agent = agentPourInscription();

    $user = app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1');

    expect($user)->toBeInstanceOf(User::class);
    expect($user->matricule)->toBe('636324/D');
    expect($user->role)->toBe('agent');
    expect($user->name)->toBe('Biram SENE');
    expect($agent->fresh()->user_id)->toBe($user->id);
    expect(\Illuminate\Support\Facades\Hash::check('motdepasse1', $user->password))->toBeTrue();
});

it('accepte une casse et des espaces différents', function () {
    agentPourInscription();

    $user = app(RegisterAgentAccount::class)->handle(' 636324/D ', 'sene', 'biram', 'biram@dge.sn', 'motdepasse1');

    expect($user->matricule)->toBe('636324/D');
});

it('refuse un matricule inconnu', function () {
    agentPourInscription();

    expect(fn () => app(RegisterAgentAccount::class)->handle('000000/X', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1'))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(0);
});

it('refuse si le nom ne correspond pas au matricule', function () {
    agentPourInscription();

    expect(fn () => app(RegisterAgentAccount::class)->handle('636324/D', 'DIOP', 'Biram', 'biram@dge.sn', 'motdepasse1'))
        ->toThrow(ValidationException::class);
});

it('refuse un agent déjà réclamé', function () {
    $agent = agentPourInscription();
    $existing = User::create(['name' => 'X', 'matricule' => 'ZZZ', 'email' => 'zzz@dge.sn', 'password' => bcrypt('x'), 'role' => 'agent']);
    $agent->update(['user_id' => $existing->id]);

    expect(fn () => app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'biram@dge.sn', 'motdepasse1'))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(1);
});
