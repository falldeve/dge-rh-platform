<?php

use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function ctxAcces(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $autreU = User::create(['name' => 'Autre', 'matricule' => 'AU1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $autreA = Agent::create(['prenoms' => 'Au', 'noms' => 'TR', 'matricule' => 'AU1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $autreU->id]);

    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);
    $eSi = Entite::create(['code' => 'SI', 'nom' => 'Informatique', 'type' => 'direction']);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $courrier = Courrier::create(['numero' => '1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    $imp = Imputation::create(['courrier_id' => $courrier->id, 'niveau' => 'dg', 'saisi_par' => $bc->id]);
    $imp->destinataires()->sync([$eDoe->id]); // imputé à DOE

    return compact('chefU', 'autreU', 'bc', 'courrier', 'eDoe');
}

it('liste les entités gérées par un utilisateur', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe] = ctxAcces();

    expect($chef->agentEntitesGereesIds())->toBe([$eDoe->id]);
});

it('scope pourEntites filtre les courriers imputés à ces entités', function () {
    ['courrier' => $c, 'eDoe' => $eDoe] = ctxAcces();

    expect(Courrier::pourEntites([$eDoe->id])->pluck('id')->all())->toBe([$c->id]);
    expect(Courrier::pourEntites([999])->count())->toBe(0);
});

it('la policy autorise le chef de l’entité destinataire, refuse les autres', function () {
    ['chefU' => $chef, 'autreU' => $autre, 'bc' => $bc, 'courrier' => $c] = ctxAcces();

    expect(Gate::forUser($chef)->allows('voir', $c))->toBeTrue();   // chef de DOE (destinataire)
    expect(Gate::forUser($autre)->allows('voir', $c))->toBeFalse(); // ne gère aucune entité destinataire
    expect(Gate::forUser($bc)->allows('voir', $c))->toBeTrue();     // bureau courrier voit tout
});
