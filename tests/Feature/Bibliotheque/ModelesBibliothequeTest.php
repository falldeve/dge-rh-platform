<?php

use App\Models\Document;
use App\Models\Rubrique;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('relie rubrique, document et chunks', function () {
    $r = Rubrique::factory()->create(['nom' => 'Lois', 'slug' => 'lois']);
    $d = Document::factory()->for($r)->create(['source' => 'texte', 'contenu' => '# Titre']);
    $d->chunks()->create(['ordre' => 0, 'contenu' => 'un extrait']);

    expect($d->rubrique->nom)->toBe('Lois')
        ->and($d->chunks)->toHaveCount(1)
        ->and($r->documents)->toHaveCount(1)
        ->and($d->estTexte())->toBeTrue();
});

it('calcule fichierUrl relatif pour une source fichier', function () {
    $d = Document::factory()->create(['source' => 'fichier', 'fichier_path' => 'bibliotheque/x.pdf']);
    expect($d->fichierUrl())->toBe('/storage/bibliotheque/x.pdf')
        ->and($d->estPdf())->toBeTrue();
});

it('renvoie null fichierUrl pour une source texte', function () {
    $d = Document::factory()->create(['source' => 'texte', 'fichier_path' => null]);
    expect($d->fichierUrl())->toBeNull();
});
