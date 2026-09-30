<?php

use App\Livewire\Rh\EntitesManager;
use App\Models\Entite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhEntites(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'admin_rh']);
}

it('interdit la gestion des entités au bureau courrier et aux autres rôles (403)', function () {
    $this->withoutVite();
    $cr = User::create(['name' => 'CR', 'matricule' => 'CR9', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);
    $agent = User::create(['name' => 'Ag', 'matricule' => 'AG1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($cr)->get('/rh/entites')->assertForbidden();
    $this->actingAs($agent)->get('/rh/entites')->assertForbidden();
});

it('autorise uniquement la RH', function () {
    $this->withoutVite();

    $this->actingAs(adminRhEntites())->get('/rh/entites')->assertOk();
});

it('supprime une entité sans sous-entités', function () {
    $bureau = Entite::create(['code' => 'BC', 'nom' => 'Bureau Courrier', 'type' => 'service']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->call('supprimer', $bureau->id);

    expect(Entite::find($bureau->id))->toBeNull();
});

it('bloque la suppression d’une entité ayant des sous-entités', function () {
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    Entite::create(['code' => 'DOE-CARTE', 'nom' => 'Carte', 'type' => 'division', 'parent_id' => $doe->id]);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->call('supprimer', $doe->id);

    expect(Entite::find($doe->id))->not->toBeNull();
});

it('re-rattache une division à une autre direction (édition du parent)', function () {
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    $dfc = Entite::create(['code' => 'DFC', 'nom' => 'Formation', 'type' => 'direction']);
    $div = Entite::create(['code' => 'JUR', 'nom' => 'Juridique', 'type' => 'division', 'parent_id' => $doe->id]);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->call('edit', $div->id)
        ->set('parent_id', $dfc->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($div->fresh()->parent_id)->toBe($dfc->id);
});

it('crée une entité de type service', function () {
    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'SP')
        ->set('nom', 'Secrétariat Particulier')
        ->set('type', 'service')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entite::where('code', 'SP')->exists())->toBeTrue();
});

it('crée une division rattachée à une direction', function () {
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'DOE-CARTE')
        ->set('nom', 'Division Carte électorale')
        ->set('type', 'division')
        ->set('parent_id', $doe->id)
        ->call('save')
        ->assertHasNoErrors();

    $div = Entite::where('code', 'DOE-CARTE')->first();
    expect($div->parent_id)->toBe($doe->id);
});

it('refuse un code dupliqué', function () {
    Entite::create(['code' => 'SP', 'nom' => 'X', 'type' => 'service']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'SP')
        ->set('nom', 'Y')
        ->set('type', 'service')
        ->call('save')
        ->assertHasErrors('code');

    expect(Entite::where('code', 'SP')->count())->toBe(1);
});

it('annuler réinitialise le formulaire d’édition', function () {
    $e = Entite::create(['code' => 'BC', 'nom' => 'Bureau Courrier', 'type' => 'service']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->call('edit', $e->id)
        ->assertSet('editingId', $e->id)
        ->call('annuler')
        ->assertSet('editingId', null)
        ->assertSet('code', '')
        ->assertSet('type', 'service');
});
