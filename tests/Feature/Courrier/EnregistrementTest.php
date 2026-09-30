<?php

use App\Livewire\Courrier\NouveauCourrier;
use App\Livewire\Courrier\Registre;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function courrierUser(): User
{
    return User::create(['name' => 'Bureau Courrier', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);
}

it('interdit le registre aux non courrier (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'agent']);

    $this->actingAs($agent)->get('/courriers')->assertForbidden();
});

it('enregistre un courrier', function () {
    $u = courrierUser();

    Livewire::actingAs($u)
        ->test(NouveauCourrier::class)
        ->set('numero', '000145')
        ->set('objet', 'Convocation réunion')
        ->set('expediteur', 'Préfecture de Dakar')
        ->set('date_arrivee', '2026-07-07')
        ->call('enregistrer')
        ->assertRedirect(route('courriers.registre'));

    $c = Courrier::where('numero', '000145')->first();
    expect($c)->not->toBeNull();
    expect($c->objet)->toBe('Convocation réunion');
    expect($c->enregistre_par)->toBe($u->id);
});

it('refuse un numéro dupliqué', function () {
    $u = courrierUser();
    Courrier::create(['numero' => 'DUP', 'objet' => 'x', 'expediteur' => 'y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    Livewire::actingAs($u)
        ->test(NouveauCourrier::class)
        ->set('numero', 'DUP')
        ->set('objet', 'z')
        ->set('expediteur', 'w')
        ->set('date_arrivee', '2026-07-08')
        ->call('enregistrer')
        ->assertHasErrors('numero');
});

it('recherche dans le registre par objet', function () {
    $u = courrierUser();
    Courrier::create(['numero' => 'A1', 'objet' => 'CONVOCATION', 'expediteur' => 'P', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);
    Courrier::create(['numero' => 'A2', 'objet' => 'FACTURE', 'expediteur' => 'F', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    Livewire::actingAs($u)
        ->test(Registre::class)
        ->set('search', 'CONVOCATION')
        ->assertSee('CONVOCATION')
        ->assertDontSee('FACTURE');
});
