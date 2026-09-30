<?php

use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function courrierAgent(): User
{
    return User::create(['name' => 'C', 'matricule' => 'C1', 'password' => bcrypt('s'), 'role' => 'courrier']);
}

it('crée un courrier', function () {
    $u = courrierAgent();
    $c = Courrier::create([
        'numero' => '000145', 'objet' => 'Convocation', 'expediteur' => 'Préfecture',
        'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id,
    ]);

    expect($c->numero)->toBe('000145');
    expect($c->enregistrePar->id)->toBe($u->id);
    expect($c->date_arrivee->format('Y-m-d'))->toBe('2026-07-07');
});

it('crée une imputation (ventilation) avec destinataires et mentions', function () {
    $u = courrierAgent();
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    $si = Entite::create(['code' => 'SI', 'nom' => 'Informatique', 'type' => 'direction']);
    $c = Courrier::create(['numero' => '000145', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    $imp = Imputation::create([
        'courrier_id' => $c->id, 'niveau' => 'dg', 'mentions' => ['etude_reponse', 'information'],
        'observations' => 'Traiter vite', 'signataire_nom' => 'Le DG', 'saisi_par' => $u->id,
    ]);
    $imp->destinataires()->sync([$doe->id, $si->id]);

    expect($c->imputations()->count())->toBe(1);
    expect($imp->fresh()->mentions)->toBe(['etude_reponse', 'information']);
    expect($imp->destinataires()->count())->toBe(2);
    expect($imp->destinataires->pluck('code')->sort()->values()->all())->toBe(['DOE', 'SI']);
});

it('expose la liste des mentions', function () {
    expect(Imputation::MENTIONS)->toHaveKey('etude_reponse');
    expect(count(Imputation::MENTIONS))->toBe(12);
});
