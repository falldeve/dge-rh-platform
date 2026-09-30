<?php

use App\Actions\DeciderDemande;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contexte(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);

    $rhUser = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $agentUser = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $agentUser->id]);

    return compact('dir', 'chefUser', 'chefAgent', 'rhUser', 'agentUser', 'agent');
}

function demandeConge(Agent $agent, int $jours = 5): Demande
{
    return Demande::create([
        'agent_id' => $agent->id, 'type' => 'conge_annuel',
        'date_debut' => '2026-08-01', 'date_fin' => '2026-08-0'.$jours,
        'nb_jours' => $jours, 'statut' => Demande::STATUT_SOUMISE,
    ]);
}

it('le chef valide : soumise -> validee_chef + notif agent', function () {
    ['chefUser' => $chef, 'agent' => $agent, 'agentUser' => $agentUser] = contexte();
    $demande = demandeConge($agent);

    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_CHEF);
    expect($demande->validations()->where('niveau', 'chef')->where('decision', 'ok')->count())->toBe(1);
    expect($agentUser->fresh()->notifications()->count())->toBe(1);
});

it('un non-chef ne peut pas valider au niveau chef', function () {
    ['rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent);

    expect(fn () => app(DeciderDemande::class)->handle($demande, $rh, 'chef', 'ok'))
        ->toThrow(AuthorizationException::class);
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('la RH valide un congé : validee_chef -> validee_rh + décrément solde', function () {
    ['chefUser' => $chef, 'rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent, 5);
    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    app(DeciderDemande::class)->handle($demande->fresh(), $rh, 'rh', 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(15.0);
});

it('une permission est finalisée par le chef (1 niveau), sans toucher le solde, DRHF informée', function () {
    ['chefUser' => $chef, 'rhUser' => $rh, 'agent' => $agent, 'agentUser' => $agentUser] = contexte();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => Demande::STATUT_SOUMISE]);

    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    // Validée directement (pas d'étape DRHF), solde intact
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(20.0);
    // Agent notifié + DRHF informée
    expect($agentUser->fresh()->notifications()->count())->toBe(1);
    expect($rh->fresh()->notifications()->count())->toBe(1);
});

it('un refus chef passe en refusee et notifie l’agent', function () {
    ['chefUser' => $chef, 'agent' => $agent, 'agentUser' => $agentUser] = contexte();
    $demande = demandeConge($agent);

    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'refus', 'Effectif insuffisant');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_REFUSEE);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(20.0);
    expect($agentUser->fresh()->notifications()->count())->toBe(1);
});

it('la RH ne peut pas agir tant que le chef n’a pas validé', function () {
    ['rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent);

    expect(fn () => app(DeciderDemande::class)->handle($demande, $rh, 'rh', 'ok'))
        ->toThrow(\DomainException::class);
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_SOUMISE);
});
