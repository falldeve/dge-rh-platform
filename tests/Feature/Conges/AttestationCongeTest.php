<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxAttestation(string $statut = 'validee_rh'): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Cheikh Tidiane', 'noms' => 'DIALLO', 'matricule' => '716.398/J', 'direction_id' => $dir->id, 'statut' => 'police', 'profession' => 'Adjudant de Police', 'solde_conge_jours' => 10]);
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-03-18', 'date_fin' => '2026-04-16', 'nb_jours' => 30, 'statut' => $statut]);

    return compact('dir', 'rh', 'agent', 'demande');
}

it('la RH télécharge l’attestation d’un congé validé (PDF)', function () {
    ['rh' => $rh, 'demande' => $demande] = ctxAttestation();

    $response = $this->actingAs($rh)->get(route('conges.attestation', $demande));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('refuse l’attestation d’un congé non validé (404)', function () {
    ['rh' => $rh, 'demande' => $demande] = ctxAttestation('soumise');

    $this->actingAs($rh)->get(route('conges.attestation', $demande))->assertNotFound();
});

it('interdit l’attestation aux non admin_rh (403)', function () {
    $this->withoutVite();
    ['demande' => $demande] = ctxAttestation();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agentUser)->get(route('conges.attestation', $demande))->assertForbidden();
});

it('la vue attestation contient le texte réglementaire résolu', function () {
    ['demande' => $demande, 'agent' => $agent] = ctxAttestation();
    $demande->loadMissing('agent');

    $html = view('pdf.attestation-conge', [
        'd' => $demande,
        'agent' => $agent,
        'nbJoursLettres' => \App\Support\NombreEnLettres::convertir($demande->nb_jours),
        'dateReprise' => $demande->date_fin->copy()->addDay(),
        'signataireNom' => config('dge.drh_nom'),
        'signataireFonction' => config('dge.drh_fonction'),
    ])->render();

    expect($html)->toContain('Cheikh Tidiane')->toContain('DIALLO');
    expect($html)->toContain('716.398/J');
    expect($html)->toContain('trente (30)');
    expect($html)->toContain(config('dge.drh_fonction')); // signataire = DRHF (valide le congé)
});
