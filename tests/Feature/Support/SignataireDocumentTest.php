<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Support\SignataireDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxSignataire(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations Électorales']);
    $chef = Agent::create(['prenoms' => 'Abdoul Aziz', 'noms' => 'SARR', 'matricule' => 'C1', 'direction_id' => $dir->id, 'statut' => 'police', 'fonction' => 'Directeur des Opérations Électorales', 'solde_conge_jours' => 0]);
    $dir->update(['chef_id' => $chef->id]);
    $agent = Agent::create(['prenoms' => 'Aliou', 'noms' => 'CISSE', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);

    return compact('dir', 'chef', 'agent');
}

it('congé : signé par la DRH (config), pas le chef', function () {
    config(['dge.drh_nom' => 'Ndeye Astou GUEYE', 'dge.drh_fonction' => 'Directeur des Ressources Humaines et des Finances']);
    ['agent' => $agent] = ctxSignataire();
    $conge = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-05', 'nb_jours' => 5, 'statut' => 'validee_rh']);

    expect(SignataireDocument::pour($conge))
        ->toBe(['Ndeye Astou GUEYE', 'Directeur des Ressources Humaines et des Finances']);
});

it('permission : signée par le chef de direction', function () {
    ['agent' => $agent] = ctxSignataire();
    $perm = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-07-15', 'date_fin' => '2026-07-20', 'nb_jours' => 6, 'statut' => 'validee_rh', 'motif' => 'Raison familiale']);

    [$nom, $fonction] = SignataireDocument::pour($perm);
    expect($nom)->toContain('SARR');
    expect($fonction)->toBe('Directeur des Opérations Électorales');
});

it('permission sans chef désigné → Directeur Général', function () {
    config(['dge.dg_nom' => 'Le Directeur Général', 'dge.dg_fonction' => 'Directeur Général des Élections']);
    $dir = Direction::create(['code' => 'SI', 'nom' => 'Service Informatique']);
    $agent = Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'A2', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $perm = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-07-15', 'date_fin' => '2026-07-20', 'nb_jours' => 6, 'statut' => 'validee_rh', 'motif' => 'x']);

    expect(SignataireDocument::pour($perm))
        ->toBe(['Le Directeur Général', 'Directeur Général des Élections']);
});
