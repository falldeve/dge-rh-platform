<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxPdf(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $chef = Agent::create(['prenoms' => 'Le', 'noms' => 'DIRECTEUR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'fonction' => 'Directeur', 'solde_conge_jours' => 0]);
    $dir->update(['chef_id' => $chef->id]);
    $agent = Agent::create(['prenoms' => 'Papa Ibrahima', 'noms' => 'NIANG', 'matricule' => '710.231/F', 'direction_id' => $dir->id, 'statut' => 'police', 'fonction' => 'Agent DGE', 'solde_conge_jours' => 20]);
    $mission = Demande::create(['agent_id' => $agent->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'Mission DGE', 'meta' => ['destination' => 'Dakar – Mbour – Dakar', 'moyen_transport' => 'AD 31057', 'signataires' => ['dg', 'directeur']]]);

    return compact('dir', 'secUser', 'agent', 'chef', 'mission');
}

it('le secrétaire télécharge le PDF de l’ordre de mission', function () {
    ['secUser' => $sec, 'mission' => $mission] = ctxPdf();

    $response = $this->actingAs($sec)->get(route('missions.imprimer', $mission));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('interdit l’impression à un secrétaire d’une autre direction', function () {
    ['mission' => $mission] = ctxPdf();
    $autreDir = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $autreSecU = User::create(['name' => 'Sec2', 'matricule' => 'SEC2', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'secretaire']);
    Agent::create(['prenoms' => 'Sec', 'noms' => 'DEUX', 'matricule' => 'SEC2', 'direction_id' => $autreDir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $autreSecU->id]);

    $this->actingAs($autreSecU)->get(route('missions.imprimer', $mission))->assertForbidden();
});

it('la vue PDF contient les champs et les signataires résolus', function () {
    ['mission' => $mission, 'agent' => $agent, 'chef' => $chef] = ctxPdf();

    $signataires = app(\App\Http\Controllers\OrdreMissionPdfController::class)->resoudreSignataires($mission);
    $html = view('pdf.ordre-mission', ['m' => $mission, 'agent' => $agent, 'signataires' => $signataires])->render();

    expect($html)->toContain('Papa Ibrahima')->toContain('NIANG');
    expect($html)->toContain('Dakar – Mbour – Dakar');
    expect($html)->toContain(config('dge.dg_fonction'));
    expect($html)->toContain('DIRECTEUR');
});
