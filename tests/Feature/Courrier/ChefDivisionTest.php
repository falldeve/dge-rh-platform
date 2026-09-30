<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Livewire\Courrier\MesCourriers;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxChefDivision(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    // Chef de division : rôle "agent" mais chef_agent_id d'une entité division.
    $u = User::create(['name' => 'Aliou CISSE', 'matricule' => 'AL1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);
    $a = Agent::create(['prenoms' => 'Aliou', 'noms' => 'CISSE', 'matricule' => 'AL1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $u->id]);

    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    $div = Entite::create(['code' => 'DOE-CARTE', 'nom' => 'Carte', 'type' => 'division', 'parent_id' => $doe->id, 'chef_agent_id' => $a->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);
    $c = Courrier::create(['numero' => 'CAS-1', 'objet' => 'Pour la division', 'expediteur' => 'DOE', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $c->id, 'niveau' => 'direction', 'entite_source_id' => $doe->id, 'saisi_par' => $bc->id])->destinataires()->sync([$div->id]);

    return compact('u', 'div', 'c');
}

it('un chef de division (rôle agent) accède à /mes-courriers', function () {
    $this->withoutVite();
    ['u' => $u] = ctxChefDivision();

    $this->actingAs($u)->get('/mes-courriers')->assertOk();
});

it('le chef de division voit le courrier cascadé à sa division', function () {
    ['u' => $u] = ctxChefDivision();

    Livewire::actingAs($u)->test(MesCourriers::class)->assertSee('CAS-1');
});

it('le chef de division ouvre la fiche du courrier de sa division', function () {
    ['u' => $u, 'c' => $c] = ctxChefDivision();

    Livewire::actingAs($u)->test(CourrierEntite::class, ['courrier' => $c])->assertOk();
});

it('un agent sans entité gérée ne voit aucun courrier', function () {
    ctxChefDivision();
    $lambda = User::create(['name' => 'Lambda', 'matricule' => 'LA1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    Livewire::actingAs($lambda)->test(MesCourriers::class)->assertDontSee('CAS-1');
});