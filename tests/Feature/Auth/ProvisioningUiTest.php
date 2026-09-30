<?php

use App\Livewire\Admin\ComptesSysteme;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function superAdmin(): User
{
    $u = User::create(['name' => 'Super', 'matricule' => 'ADMIN', 'email' => 'admin@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin']);

    return $u;
}

it('le super-admin crée un compte courrier', function () {
    Mail::fake();
    Livewire::actingAs(superAdmin())->test(ComptesSysteme::class)
        ->set('name', 'Bureau Courrier')->set('matricule', 'COURRIER')->set('email', 'courrier@dge.sn')->set('role', 'courrier')
        ->call('creer')->assertHasNoErrors();

    expect(User::where('email', 'courrier@dge.sn')->where('role', 'courrier')->exists())->toBeTrue();
});

it('la page comptes système est interdite aux non-admin (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'email' => 'rh@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/admin/comptes')->assertForbidden();
});

it('la RH crée le compte d’un agent avec son email', function () {
    Mail::fake();
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'email' => 'rh@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin_rh']);

    Livewire::actingAs($rh)->test(\App\Livewire\Rh\AgentsIndex::class)
        ->call('creerCompte', $agent->id, 'awa@dge.sn');

    $agent->refresh();
    expect($agent->user_id)->not->toBeNull();
    expect(User::find($agent->user_id)->role)->toBe('agent');
});

it('la route d’inscription publique est supprimée (404)', function () {
    $this->withoutVite();
    $this->get('/register')->assertNotFound();
});

it('le super-admin accède à tout (bypass des rôles)', function () {
    $this->withoutVite();
    $admin = superAdmin();

    $this->actingAs($admin)->get('/rh/tableau-bord')->assertOk();   // route role:admin_rh
    $this->actingAs($admin)->get('/courriers')->assertOk();          // route role:courrier
    $this->actingAs($admin)->get('/archives')->assertOk();           // route role:archiviste
    $this->actingAs($admin)->get('/admin/comptes')->assertOk();
});
