<?php

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\RechercheBibliotheque;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function docAvecChunk(string $titre, string $contenu, bool $actif = true): Document
{
    $d = Document::factory()->for(Rubrique::factory())->create(['titre' => $titre, 'actif' => $actif, 'reference' => 'Réf '.$titre]);
    $d->chunks()->create(['ordre' => 0, 'contenu' => $contenu]);

    return $d;
}

it('trouve un extrait pertinent (documents actifs)', function () {
    docAvecChunk('Parrainage', "Le parrainage des candidats à l'élection présidentielle exige un nombre minimal de signatures.");
    docAvecChunk('Budget', 'Dispositions financières et comptables diverses.');

    $res = app(RechercheBibliotheque::class)->rechercher('parrainage candidats');

    expect($res)->not->toBeEmpty()
        ->and($res[0]['titre'])->toBe('Parrainage')
        ->and($res[0]['contenu'])->toContain('parrainage')
        ->and($res[0]['reference'])->toBe('Réf Parrainage');
});

it('exclut les documents inactifs', function () {
    docAvecChunk('Secret', 'Texte confidentiel sur le parrainage.', actif: false);

    $res = app(RechercheBibliotheque::class)->rechercher('parrainage');

    expect(collect($res)->pluck('titre'))->not->toContain('Secret');
});

it('renvoie vide pour une requête vide', function () {
    docAvecChunk('X', 'contenu parrainage');
    expect(app(RechercheBibliotheque::class)->rechercher('   '))->toBe([]);
});
