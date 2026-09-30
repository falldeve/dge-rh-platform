<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxDoc(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Aliou CISSE', 'matricule' => 'A1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Aliou', 'noms' => 'CISSE', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $user->id]);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $autre = User::create(['name' => 'Autre', 'matricule' => 'B1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $perm = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-07-15', 'date_fin' => '2026-07-20', 'nb_jours' => 6, 'statut' => 'validee_rh', 'motif' => 'Raison familiale']);
    $conge = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-05', 'nb_jours' => 5, 'statut' => 'validee_rh']);
    $enCours = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-02', 'nb_jours' => 2, 'statut' => 'soumise']);

    return compact('user', 'rh', 'autre', 'perm', 'conge', 'enCours');
}

it('le propriétaire télécharge le PDF de sa permission validée', function () {
    ['user' => $u, 'perm' => $perm] = ctxDoc();
    $r = $this->actingAs($u)->get(route('demandes.document', $perm));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('application/pdf');
});

it('la DRHF télécharge le PDF (congé ou permission)', function () {
    ['rh' => $rh, 'perm' => $perm, 'conge' => $conge] = ctxDoc();
    $this->actingAs($rh)->get(route('demandes.document', $perm))->assertOk();
    $this->actingAs($rh)->get(route('demandes.document', $conge))->assertOk();
});

it('un autre agent ne peut pas télécharger (403)', function () {
    ['autre' => $autre, 'perm' => $perm] = ctxDoc();
    $this->actingAs($autre)->get(route('demandes.document', $perm))->assertForbidden();
});

it('refuse une demande non validée (404)', function () {
    ['user' => $u, 'enCours' => $enCours] = ctxDoc();
    $this->actingAs($u)->get(route('demandes.document', $enCours))->assertNotFound();
});

it('la vue décision de permission contient le texte réglementaire', function () {
    ['perm' => $perm] = ctxDoc();
    $perm->loadMissing('agent');
    $html = view('pdf.decision-permission', [
        'd' => $perm, 'agent' => $perm->agent,
        'nbJoursLettres' => App\Support\NombreEnLettres::convertir(6),
        'signataireNom' => 'Abdoul Aziz SARR',
        'signataireFonction' => 'Directeur des Opérations électorales',
    ])->render();

    expect($html)->toContain('Aliou')->toContain('CISSE');
    expect($html)->toContain('six (6)');
    expect($html)->toContain('PERMISSION');
    expect($html)->toContain('Directeur des Opérations électorales');
});
