<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentParcours(): Agent
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    return Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

function rhParcours(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
}

it('un agent a des formations et des expériences', function () {
    $agent = agentParcours();
    $agent->formations()->create(['annee_debut' => 2004, 'mois_debut' => 1, 'annee_fin' => 2015, 'mois_fin' => 12, 'domaine' => 'Droit', 'etablissement' => 'UCAD', 'ville' => 'Dakar']);
    $agent->experiences()->create(['annee_debut' => 2011, 'mois_debut' => 2, 'activite' => 'Enquêteur', 'employeur' => "Ministère de l'Intérieur", 'ville' => 'Dakar']);

    expect($agent->formations()->count())->toBe(1);
    expect($agent->experiences()->count())->toBe(1);
});

it('le RH enregistre scolarité et expérience via le formulaire', function () {
    $agent = agentParcours();

    Livewire::actingAs(rhParcours())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('formations', [
            ['annee_debut' => 2004, 'mois_debut' => 1, 'annee_fin' => 2015, 'mois_fin' => 12, 'domaine' => 'Droit', 'etablissement' => 'UCAD', 'ville' => 'Dakar'],
        ])
        ->set('experiences', [
            ['annee_debut' => 2011, 'mois_debut' => 2, 'annee_fin' => 2015, 'mois_fin' => 3, 'activite' => 'Enquêteur', 'employeur' => "Ministère de l'Intérieur", 'ville' => 'Dakar'],
        ])
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent->refresh();
    expect($agent->formations()->count())->toBe(1);
    expect($agent->formations()->first()->domaine)->toBe('Droit');
    expect($agent->experiences()->count())->toBe(1);
    expect($agent->experiences()->first()->activite)->toBe('Enquêteur');
});

it('remplace les entrées existantes à la sauvegarde', function () {
    $agent = agentParcours();
    $agent->experiences()->create(['annee_debut' => 2000, 'activite' => 'Ancien', 'employeur' => 'X']);

    Livewire::actingAs(rhParcours())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('experiences', [
            ['annee_debut' => 2011, 'mois_debut' => 2, 'annee_fin' => null, 'mois_fin' => null, 'activite' => 'Nouveau', 'employeur' => 'Y', 'ville' => null],
        ])
        ->call('save');

    $agent->refresh();
    expect($agent->experiences()->count())->toBe(1);
    expect($agent->experiences()->first()->activite)->toBe('Nouveau');
});

it('refuse une formation sans domaine ni établissement', function () {
    $agent = agentParcours();

    Livewire::actingAs(rhParcours())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('formations', [
            ['annee_debut' => 2004, 'mois_debut' => 1, 'annee_fin' => null, 'mois_fin' => null, 'domaine' => '', 'etablissement' => '', 'ville' => null],
        ])
        ->call('save')
        ->assertHasErrors(['formations.0.domaine', 'formations.0.etablissement']);
});
