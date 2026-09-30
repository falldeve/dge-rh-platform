<?php

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\FauxExtracteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('seed les rubriques de départ', function () {
    $loi = tempnam(sys_get_temp_dir(), 'loi').'.md';
    file_put_contents($loi, "# R\n\n## 1. Loi test\n\nCorps.\n");
    $dossier = sys_get_temp_dir().'/biblio_vide_'.uniqid();
    mkdir($dossier);

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier])->assertOk();

    expect(Rubrique::whereIn('nom', ['Code électoral', 'Rapports CENA', 'Textes historiques (JO 1960-1982)'])->count())->toBe(3);
});

it('importe loi.md en documents texte chunkés', function () {
    $corps = str_repeat('article ', 1000);
    $loi = tempnam(sys_get_temp_dir(), 'loi').'.md';
    file_put_contents($loi, "# R\n\n## 1. Loi n°60-036 du 5 juillet 1960\n\n$corps\n\n## 2. Décret n°60-237\n\npetit corps\n");
    $dossier = sys_get_temp_dir().'/biblio_vide_'.uniqid();
    mkdir($dossier);

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier])->assertOk();

    $d1 = Document::where('titre', '1. Loi n°60-036 du 5 juillet 1960')->firstOrFail();
    expect($d1->source)->toBe('texte')
        ->and($d1->type)->toBe('loi')
        ->and($d1->chunks()->count())->toBeGreaterThanOrEqual(1);

    $d2 = Document::where('titre', '2. Décret n°60-237')->firstOrFail();
    expect($d2->type)->toBe('decret');
});

it('est idempotente (relancer ne duplique pas)', function () {
    $loi = tempnam(sys_get_temp_dir(), 'loi').'.md';
    file_put_contents($loi, "# R\n\n## 1. Loi test\n\nCorps.\n");
    $dossier = sys_get_temp_dir().'/biblio_vide_'.uniqid();
    mkdir($dossier);

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier]);
    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier]);

    expect(Document::where('titre', '1. Loi test')->count())->toBe(1);
});

it('ingère un fichier avec texte en document fichier chunké, un scan sans chunks', function () {
    $dossier = sys_get_temp_dir().'/biblio_'.uniqid();
    mkdir($dossier);
    file_put_contents($dossier.'/Code Electoral Edition 2018.pdf', '%PDF-fake');
    file_put_contents($dossier.'/rapport cena 2010.pdf', '%PDF-scan');

    // Faux extracteur : le "code" a du texte (>800), le "cena" est un scan (vide)
    app()->instance(ExtracteurTexte::class, new FauxExtracteur([
        'Code Electoral Edition 2018.pdf' => str_repeat('article electoral ', 200),
        'rapport cena 2010.pdf' => '',
    ]));

    $loi = tempnam(sys_get_temp_dir(), 'loi').'.md';
    file_put_contents($loi, "# vide\n");

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier])->assertOk();

    $code = Document::where('titre', 'like', '%Code Electoral Edition 2018%')->firstOrFail();
    expect($code->source)->toBe('fichier')
        ->and($code->fichier_path)->not->toBeNull()
        ->and($code->chunks()->count())->toBeGreaterThanOrEqual(1);

    $cena = Document::where('titre', 'like', '%rapport cena 2010%')->firstOrFail();
    expect($cena->source)->toBe('fichier')
        ->and($cena->chunks()->count())->toBe(0);
});

it('gère deux fichiers de même nom mais d’extensions différentes sans collision', function () {
    $dossier = sys_get_temp_dir().'/biblio_'.uniqid();
    mkdir($dossier);
    file_put_contents($dossier.'/note.pdf', '%PDF-note');
    file_put_contents($dossier.'/note.docx', 'DOCX-note');

    app()->instance(ExtracteurTexte::class, new FauxExtracteur([
        'note.pdf' => 'texte court pdf',
        'note.docx' => 'texte court docx',
    ]));

    $loi = tempnam(sys_get_temp_dir(), 'loi').'.md';
    file_put_contents($loi, "# vide\n");

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier])->assertOk();

    $documents = Document::where('titre', 'note')->get();
    expect($documents)->toHaveCount(2);
    expect($documents->pluck('fichier_path')->unique())->toHaveCount(2);

    $this->artisan('bibliotheque:importer', ['--loi' => $loi, '--dossier' => $dossier])->assertOk();

    expect(Document::where('titre', 'note')->count())->toBe(2);
});
