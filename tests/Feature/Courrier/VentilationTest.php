<?php

use App\Livewire\Courrier\FicheCourrier;
use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxVentilation(): array
{
    $u = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    $si = Entite::create(['code' => 'SI', 'nom' => 'Informatique', 'type' => 'direction']);
    $c = Courrier::create(['numero' => '000145', 'objet' => 'Convocation', 'expediteur' => 'Préfecture', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    return compact('u', 'doe', 'si', 'c');
}

it('le bureau courrier ventile un courrier (destinataires + mentions)', function () {
    ['u' => $u, 'doe' => $doe, 'si' => $si, 'c' => $c] = ctxVentilation();

    Livewire::actingAs($u)
        ->test(FicheCourrier::class, ['courrier' => $c])
        ->set('destinataires', [$doe->id, $si->id])
        ->set('mentions', ['etude_reponse', 'information'])
        ->set('observations', 'Traiter en urgence')
        ->set('signataire_nom', 'Le Directeur Général')
        ->call('ventiler')
        ->assertHasNoErrors();

    $imp = $c->imputations()->first();
    expect($imp)->not->toBeNull();
    expect($imp->niveau)->toBe('dg');
    expect($imp->mentions)->toBe(['etude_reponse', 'information']);
    expect($imp->destinataires()->count())->toBe(2);
    expect($imp->saisi_par)->toBe($u->id);
});

it('exige au moins un destinataire', function () {
    ['u' => $u, 'c' => $c] = ctxVentilation();

    Livewire::actingAs($u)
        ->test(FicheCourrier::class, ['courrier' => $c])
        ->set('destinataires', [])
        ->set('mentions', ['information'])
        ->call('ventiler')
        ->assertHasErrors('destinataires');

    expect(Imputation::count())->toBe(0);
});
