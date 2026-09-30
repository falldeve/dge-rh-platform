<?php

use App\Livewire\Annuaire;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxProfil(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);

    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DG', 'matricule' => 'CHEF1', 'direction_id' => $dg->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);

    $rhUser = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
    $dgUser = User::create(['name' => 'DG', 'matricule' => 'DGU', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'dg']);
    $simple = User::create(['name' => 'A', 'matricule' => 'AGT', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $agentDg = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dg->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $agentDoe = Agent::create(['prenoms' => 'Bou', 'noms' => 'FALL', 'matricule' => 'A2', 'direction_id' => $doe->id, 'statut' => 'autre', 'solde_conge_jours' => 10]);

    return compact('dg', 'doe', 'chefUser', 'rhUser', 'dgUser', 'simple', 'agentDg', 'agentDoe');
}

it('la policy autorise RH et DG sur tous, le chef sur sa direction seulement', function () {
    ['rhUser' => $rh, 'dgUser' => $dg, 'chefUser' => $chef, 'simple' => $simple, 'agentDg' => $aDg, 'agentDoe' => $aDoe] = ctxProfil();

    expect(Gate::forUser($rh)->allows('voir', $aDoe))->toBeTrue();
    expect(Gate::forUser($dg)->allows('voir', $aDoe))->toBeTrue();
    expect(Gate::forUser($chef)->allows('voir', $aDg))->toBeTrue();   // même direction
    expect(Gate::forUser($chef)->allows('voir', $aDoe))->toBeFalse(); // autre direction
    expect(Gate::forUser($simple)->allows('voir', $aDg))->toBeFalse();
});

it('la page profil respecte les droits', function () {
    $this->withoutVite();
    ['rhUser' => $rh, 'dgUser' => $dg, 'chefUser' => $chef, 'simple' => $simple, 'agentDg' => $aDg, 'agentDoe' => $aDoe] = ctxProfil();

    $this->get(route('agents.profil', $aDg))->assertRedirect('/login'); // invité
    $this->actingAs($rh)->get(route('agents.profil', $aDoe))->assertOk()->assertSee('FALL');
    $this->actingAs($dg)->get(route('agents.profil', $aDoe))->assertOk();
    $this->actingAs($chef)->get(route('agents.profil', $aDg))->assertOk();
    $this->actingAs($chef)->get(route('agents.profil', $aDoe))->assertForbidden();
    $this->actingAs($simple)->get(route('agents.profil', $aDg))->assertForbidden();
});

it('interdit l’annuaire aux agents simples', function () {
    $this->withoutVite();
    ['simple' => $simple] = ctxProfil();
    $this->actingAs($simple)->get('/annuaire')->assertForbidden();
});

it('l’annuaire du chef ne montre que sa direction', function () {
    ['chefUser' => $chef, 'agentDg' => $aDg] = ctxProfil();

    Livewire::actingAs($chef)
        ->test(Annuaire::class)
        ->assertSee('DIOP')      // agent DG (sa direction)
        ->assertDontSee('FALL'); // agent DOE
});

it('l’annuaire du DG montre toutes les directions', function () {
    ['dgUser' => $dg] = ctxProfil();

    Livewire::actingAs($dg)
        ->test(Annuaire::class)
        ->assertSee('DIOP')
        ->assertSee('FALL');
});
