<?php

use App\Models\Document;
use App\Models\Rubrique;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function docFichier(string $titre, array $attrs = []): Document
{
    $r = Rubrique::firstOrCreate(['nom' => 'Divers'], ['slug' => 'divers', 'ordre' => 99, 'actif' => true]);

    return Document::create(array_merge([
        'titre' => $titre,
        'rubrique_id' => $r->id,
        'type' => 'autre',
        'source' => 'fichier',
        'fichier_path' => 'bibliotheque/'.md5($titre).'.pdf',
        'actif' => true,
    ], $attrs));
}

it('dry-run n écrit rien', function () {
    $d = docFichier('Décret n° 2014-01');
    $this->artisan('bibliotheque:reclasser')->assertSuccessful();
    expect($d->fresh()->rubrique->nom)->toBe('Divers'); // inchangé
});

it('appliquer réassigne rubrique, type et année', function () {
    $d = docFichier('Décret n° 2014-01 portant convocation du corps électoral 2014');
    $this->artisan('bibliotheque:reclasser --appliquer')->assertSuccessful();
    $d->refresh();
    expect($d->rubrique->nom)->toBe('Décrets');
    expect($d->type)->toBe('decret');
    expect($d->annee)->toBe(2014);
});

it('ne supprime pas sans double drapeau', function () {
    $d = docFichier('Budget 2013');
    $this->artisan('bibliotheque:reclasser --appliquer')->assertSuccessful();
    expect(Document::find($d->id))->not->toBeNull();
});

it('supprime entrée, fichier et chunks avec --appliquer --supprimer', function () {
    Storage::fake('public');
    $d = docFichier('Budget 2013');
    Storage::disk('public')->put($d->fichier_path, 'x');
    DocumentChunk::create(['document_id' => $d->id, 'ordre' => 0, 'contenu' => 'x']);

    $this->artisan('bibliotheque:reclasser --appliquer --supprimer')->assertSuccessful();

    expect(Document::find($d->id))->toBeNull();
    expect(DocumentChunk::where('document_id', $d->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing($d->fichier_path);
});

it('laisse les documents source=texte intacts', function () {
    $r = Rubrique::firstOrCreate(['nom' => 'Textes historiques (JO 1960-1982)'], ['slug' => 'th', 'ordre' => 50, 'actif' => true]);
    $d = Document::create([
        'titre' => 'Budget 1975', 'rubrique_id' => $r->id, 'type' => 'archive',
        'source' => 'texte', 'contenu' => 'x', 'actif' => true,
    ]);
    $this->artisan('bibliotheque:reclasser --appliquer --supprimer')->assertSuccessful();
    expect(Document::find($d->id))->not->toBeNull();
    expect($d->fresh()->rubrique->nom)->toBe('Textes historiques (JO 1960-1982)');
});

it('est idempotent', function () {
    $d = docFichier('Rapport Annuel CENA 2015');
    $this->artisan('bibliotheque:reclasser --appliquer')->assertSuccessful();
    $r1 = $d->fresh()->rubrique->nom;
    $this->artisan('bibliotheque:reclasser --appliquer')->assertSuccessful();
    expect($d->fresh()->rubrique->nom)->toBe($r1)->toBe('Rapports CENA');
});

it('ne supprime rien avec --supprimer seul (sans --appliquer)', function () {
    $d = docFichier('Budget 2013');
    $this->artisan('bibliotheque:reclasser --supprimer')->assertSuccessful();
    expect(App\Models\Document::find($d->id))->not->toBeNull();
});

it('supprime un hors-sujet sans fichier_path sans planter', function () {
    Storage::fake('public');
    $d = docFichier('Budget 2013', ['fichier_path' => null]);
    DocumentChunk::create(['document_id' => $d->id, 'ordre' => 0, 'contenu' => 'x']);

    $this->artisan('bibliotheque:reclasser --appliquer --supprimer')->assertSuccessful();

    expect(Document::find($d->id))->toBeNull();
    expect(DocumentChunk::where('document_id', $d->id)->count())->toBe(0);
});

it('supprime une donnée carte/fichier avec les deux drapeaux', function () {
    $d = docFichier('CARTE ELECTORALE ETRANGER 2019');
    $this->artisan('bibliotheque:reclasser --appliquer --supprimer')->assertSuccessful();
    expect(App\Models\Document::find($d->id))->toBeNull();
});
it('ne supprime pas une donnée carte/fichier en dry-run', function () {
    $d = docFichier('CARTE ELECTORALE ETRANGER 2019');
    $this->artisan('bibliotheque:reclasser')->assertSuccessful();
    expect(App\Models\Document::find($d->id))->not->toBeNull();
});

it('supprime un document junk avec les deux drapeaux', function () {
    $d = docFichier('Scan_Doc0002');
    $this->artisan('bibliotheque:reclasser --appliquer --supprimer')->assertSuccessful();
    expect(App\Models\Document::find($d->id))->toBeNull();
});
it('ne supprime pas le junk en dry-run', function () {
    $d = docFichier('Doc1');
    $this->artisan('bibliotheque:reclasser')->assertSuccessful();
    expect(App\Models\Document::find($d->id))->not->toBeNull();
});
