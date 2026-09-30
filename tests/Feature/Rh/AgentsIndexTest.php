<?php

use App\Livewire\Rh\AgentsIndex;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhIndex(): User
{
    return User::create([
        'name' => 'RH', 'matricule' => 'RH-1',
        'password' => bcrypt('secret'), 'email_verified_at' => now(), 'role' => 'admin_rh',
    ]);
}

function seedDeuxAgents(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations Électorales']);
    $a = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dg->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $b = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => '700000/Z', 'direction_id' => $doe->id, 'statut' => 'police', 'solde_conge_jours' => 20]);

    return [$dg, $doe, $a, $b];
}

it('interdit l’accès aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agent)->get('/rh/agents')->assertForbidden();
});

it('redirige les invités vers login', function () {
    $this->get('/rh/agents')->assertRedirect('/login');
});

it('liste les agents pour l’admin RH', function () {
    $this->withoutVite();
    seedDeuxAgents();

    $this->actingAs(adminRhIndex())->get('/rh/agents')
        ->assertOk()
        ->assertSeeLivewire(AgentsIndex::class)
        ->assertSee('SENE')
        ->assertSee('DIOP');
});

it('filtre par recherche sur nom/matricule', function () {
    seedDeuxAgents();

    Livewire::actingAs(adminRhIndex())
        ->test(AgentsIndex::class)
        ->set('search', 'Biram')
        ->assertSee('SENE')
        ->assertDontSee('DIOP');
});

it('filtre par direction', function () {
    [$dg] = seedDeuxAgents();

    Livewire::actingAs(adminRhIndex())
        ->test(AgentsIndex::class)
        ->set('directionId', $dg->id)
        ->assertSee('SENE')
        ->assertDontSee('DIOP');
});
