<?php

use App\Actions\SoumettreDemande;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Demande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function directionAvecChef(?Agent &$chef = null): Direction
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $dir->update(['chef_id' => $chef->id]);

    return $dir->fresh();
}

function agentDe(Direction $dir): Agent
{
    return Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 30]);
}

it('calcule nb_jours en jours calendaires inclusifs', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    $demande = app(SoumettreDemande::class)->handle($agent, 'conge_annuel', '2026-08-01', '2026-08-05');

    expect($demande->nb_jours)->toBe(5);
    expect($demande->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('une permission d’un chef est finalisée directement', function () {
    $chef = null;
    $dir = directionAvecChef($chef);

    $demande = app(SoumettreDemande::class)->handle($chef, 'permission', '2026-08-10', '2026-08-10');

    expect($demande->nb_jours)->toBe(1);
    expect($demande->statut)->toBe(Demande::STATUT_VALIDEE_RH); // permission = 1 niveau
});

it('une permission sans chef dans la direction est finalisée directement', function () {
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $agent = agentDe($dir);

    $demande = app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-01', '2026-08-02');

    expect($demande->statut)->toBe(Demande::STATUT_VALIDEE_RH);
});

it('un congé d’un chef passe à la DRHF (validee_chef)', function () {
    $chef = null;
    $dir = directionAvecChef($chef);

    $demande = app(SoumettreDemande::class)->handle($chef, 'conge_annuel', '2026-08-01', '2026-08-05');

    expect($demande->statut)->toBe(Demande::STATUT_VALIDEE_CHEF); // congé = 2 niveaux
});

it('refuse une date de fin antérieure au début', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-05', '2026-08-01'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});

it('refuse un congé annuel dépassant le solde', function () {
    $dir = directionAvecChef();
    $agent = Agent::create(['prenoms' => 'Petit', 'noms' => 'SOLDE', 'matricule' => 'P1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 3]);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'conge_annuel', '2026-08-01', '2026-08-10'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});

it('n’applique pas le contrôle de solde à une permission', function () {
    $dir = directionAvecChef();
    $agent = Agent::create(['prenoms' => 'Petit', 'noms' => 'SOLDE', 'matricule' => 'P1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    $demande = app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-01', '2026-08-10', 'raison');

    expect($demande->nb_jours)->toBe(10);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(0.0);
});

it('refuse un ordre de mission en libre-service (réservé au secrétaire)', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'ordre_mission', '2026-08-01', '2026-08-02'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});
