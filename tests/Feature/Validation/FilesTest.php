<?php

use App\Livewire\Validation\FileChef;
use App\Livewire\Validation\FileRh;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxValidation(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);
    $rhUser = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $agentUser = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $agentUser->id]);

    return compact('dir', 'chefUser', 'rhUser', 'agent');
}

it('interdit la file chef aux non chefs (403)', function () {
    $this->withoutVite();
    ['rhUser' => $rh] = ctxValidation();

    $this->actingAs($rh)->get('/validation/chef')->assertForbidden();
});

it('le chef voit et valide une demande soumise de sa direction', function () {
    ['chefUser' => $chef, 'agent' => $agent] = ctxValidation();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => Demande::STATUT_SOUMISE, 'motif' => 'PERMTEST']);

    Livewire::actingAs($chef)
        ->test(FileChef::class)
        ->assertSee('PERMTEST')
        ->call('decider', $demande->id, 'ok');

    // Permission : la validation du chef est finale
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
});

it('le chef ne voit pas les demandes d’une autre direction', function () {
    ['chefUser' => $chef] = ctxValidation();
    $autreDir = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $autreAgent = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autreDir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $autreAgent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => Demande::STATUT_SOUMISE, 'motif' => 'AILLEURS']);

    Livewire::actingAs($chef)->test(FileChef::class)->assertDontSee('AILLEURS');
});

it('la RH voit et valide une demande validee_chef', function () {
    ['rhUser' => $rh, 'agent' => $agent] = ctxValidation();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-05', 'nb_jours' => 5, 'statut' => Demande::STATUT_VALIDEE_CHEF, 'motif' => 'CONGRH']);

    Livewire::actingAs($rh)
        ->test(FileRh::class)
        ->assertSee('CONGRH')
        ->call('decider', $demande->id, 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(15.0);
});

it('interdit la file RH aux non admin_rh (403)', function () {
    $this->withoutVite();
    ['chefUser' => $chef] = ctxValidation();

    $this->actingAs($chef)->get('/validation/rh')->assertForbidden();
});
