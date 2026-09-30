# Bibliothèque documentaire (Module A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer la bibliothèque documentaire de la DGE (encyclopédie électorale) : données + import en masse du corpus fourni (loi.md + dossier `projet eletion`) + consultation (recherche, rubriques, fiche avec rendu texte/PDF), ouverte à tous les agents. Prépare l'ancrage de l'Assistant (chunks).

**Architecture:** Table `documents` à trois sources (`fichier`, `lien`, `texte`) reliée à `rubriques`, plus `document_chunks` (texte découpé, pour l'ancrage Assistant). Un service `ExtracteurTexte` (interface + impl pdftotext/PhpWord + faux de test) et un `Chunker` alimentent une commande idempotente `bibliotheque:importer`. Deux composants Livewire consultent (`Bibliotheque\Index`, `Bibliotheque\FicheDocument`).

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, MySQL (SQLite in-memory en test), `phpoffice/phpword`, `pdftotext` (poppler-utils), `Str::markdown()` (league/commonmark, déjà tiré par Laravel).

**Périmètre (ce plan) :** données + chunks + extraction + import + consultation + nav.
**Hors périmètre (plus tard) :** gestion CRUD archiviste/admin (l'import seed tout), OCR des scans (Tesseract), outil d'ancrage `rechercher_bibliotheque` (= Assistant Phase 2). Spec : `docs/superpowers/specs/2026-08-05-bibliotheque-documentaire-design.md`.

## Global Constraints

- PHP 8.3 (plateforme figée) ; Laravel 12 + Livewire 3 ; tests **Pest** (`php artisan test`), SQLite in-memory.
- **`document_chunks` : index FULLTEXT créé UNIQUEMENT sous MySQL** (`if (DB::connection()->getDriverName() === 'mysql')`) — SQLite ne supporte pas FULLTEXT, l'index le ferait planter en test.
- La **recherche de consultation** (composant Index) porte sur `documents.titre`, `resume`, `mots_cles`, `reference` via `LIKE` (marche partout) ; PAS sur les chunks (les chunks servent l'ancrage Assistant, hors de ce plan).
- **Aucun test ne dépend de `pdftotext` ni des vrais fichiers du corpus** : les tests lient un faux `ExtracteurTexte` et utilisent des fixtures temporaires. La commande d'import lit de vrais chemins seulement à l'exécution manuelle.
- Chemins d'affichage fichier : `Document::fichierUrl()` = `'/storage/'.ltrim($fichier_path,'/')` (jamais `Storage::url()`/`asset()` — casse avec APP_URL:8000).
- Seuil de chunking : texte extrait > **800 caractères** → chunks ; sinon document sans chunks (scan/consultation seule).
- Consultation ouverte : middleware `['auth','verified','password.change']` (tous agents). Charte DGB, layout `components.layouts.rh`.
- Rubriques de départ (seed, ordre) : Code électoral · Lois · Décrets & règlements · Ordonnances · Rapports CENA · Textes historiques (JO 1960-1982) · Archives.

---

### Task 1: Dépendances + data model (rubriques, documents, chunks)

**Files:**
- Modify: `composer.json` (via `composer require phpoffice/phpword`)
- Create: migrations `..._create_rubriques_table.php`, `..._create_documents_table.php`, `..._create_document_chunks_table.php`
- Create: `app/Models/Rubrique.php`, `app/Models/Document.php`, `app/Models/DocumentChunk.php`
- Create: `database/factories/RubriqueFactory.php`, `database/factories/DocumentFactory.php`
- Test: `tests/Feature/Bibliotheque/ModelesBibliothequeTest.php`

**Interfaces:**
- Produces: `Rubrique` (id, nom unique, slug unique, description?, icone?, ordre, actif ; hasMany documents). `Document` (titre, rubrique_id nullOnDelete, type enum, reference?, date_document?, resume?, mots_cles?, source enum[fichier,lien,texte], fichier_path?, url?, contenu? longtext, publie_par?, actif ; belongsTo rubrique ; hasMany chunks ; `fichierUrl():?string`, `estPdf():bool`, `estTexte():bool`). `DocumentChunk` (document_id, ordre, contenu longtext ; belongsTo document).

- [ ] **Step 1: Installer PhpWord**

```bash
cd /Users/admin/dge-rh-platform && composer require "phpoffice/phpword:^1.3"
```

- [ ] **Step 2: Écrire le test des modèles (échec attendu)**

Create `tests/Feature/Bibliotheque/ModelesBibliothequeTest.php` :

```php
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
```

- [ ] **Step 3: Lancer le test (échec)**

Run: `php artisan test --filter=ModelesBibliothequeTest`
Expected: FAIL

- [ ] **Step 4: Migration rubriques**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubriques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icone')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubriques');
    }
};
```

- [ ] **Step 5: Migration documents**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->foreignId('rubrique_id')->nullable()->constrained('rubriques')->nullOnDelete();
            $table->enum('type', ['loi', 'decret', 'reglement', 'rapport', 'circulaire', 'guide', 'archive', 'ordonnance', 'autre'])->default('autre');
            $table->string('reference')->nullable();
            $table->date('date_document')->nullable();
            $table->text('resume')->nullable();
            $table->string('mots_cles')->nullable();
            $table->enum('source', ['fichier', 'lien', 'texte']);
            $table->string('fichier_path')->nullable();
            $table->string('url')->nullable();
            $table->longText('contenu')->nullable();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
```

- [ ] **Step 6: Migration document_chunks (FULLTEXT MySQL seulement)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('ordre')->default(0);
            $table->longText('contenu');
            $table->timestamps();
        });

        // FULLTEXT uniquement sous MySQL (SQLite ne le supporte pas — tests).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_chunks ADD FULLTEXT bibliotheque_chunks_ft (contenu)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
```

- [ ] **Step 7: Modèles**

`app/Models/Rubrique.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubrique extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'slug', 'description', 'icone', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
```

`app/Models/Document.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre', 'rubrique_id', 'type', 'reference', 'date_document',
        'resume', 'mots_cles', 'source', 'fichier_path', 'url', 'contenu',
        'publie_par', 'actif',
    ];

    protected function casts(): array
    {
        return ['date_document' => 'date', 'actif' => 'boolean'];
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('ordre');
    }

    public function fichierUrl(): ?string
    {
        return $this->fichier_path ? '/storage/'.ltrim($this->fichier_path, '/') : null;
    }

    public function estPdf(): bool
    {
        return $this->source === 'fichier' && str_ends_with(strtolower((string) $this->fichier_path), '.pdf');
    }

    public function estTexte(): bool
    {
        return $this->source === 'texte';
    }
}
```

`app/Models/DocumentChunk.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = ['document_id', 'ordre', 'contenu'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
```

- [ ] **Step 8: Factories**

`database/factories/RubriqueFactory.php` :

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RubriqueFactory extends Factory
{
    public function definition(): array
    {
        $nom = $this->faker->unique()->words(2, true);

        return ['nom' => ucfirst($nom), 'slug' => Str::slug($nom), 'ordre' => 0, 'actif' => true];
    }
}
```

`database/factories/DocumentFactory.php` :

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titre' => $this->faker->sentence(4),
            'type' => 'autre',
            'source' => 'texte',
            'contenu' => '# '.$this->faker->sentence(),
            'actif' => true,
        ];
    }
}
```

- [ ] **Step 9: Migrer + relancer (succès)**

Run: `php artisan migrate && php artisan test --filter=ModelesBibliothequeTest`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock database/migrations database/factories app/Models tests/Feature/Bibliotheque
git commit -m "feat(bibliotheque): data model (rubriques, documents, chunks) + PhpWord"
```

---

### Task 2: Services d'extraction + chunking

**Files:**
- Create: `app/Support/Bibliotheque/ExtracteurTexte.php` (interface)
- Create: `app/Support/Bibliotheque/ExtracteurTextePoppler.php` (impl)
- Create: `app/Support/Bibliotheque/FauxExtracteur.php` (test double)
- Create: `app/Support/Bibliotheque/Chunker.php`
- Create: `app/Providers/BibliothequeServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Feature/Bibliotheque/ExtractionTest.php`

**Interfaces:**
- Produces:
  - `ExtracteurTexte::extraire(string $cheminAbsolu): string` — renvoie le texte brut d'un `.pdf` (pdftotext) ou `.docx` (PhpWord) ; `''` si non extractible.
  - `Chunker::decouper(string $texte, int $mots = 800): array<int,string>` — liste de morceaux (~`$mots` mots, chevauchement ~1 phrase), vide si texte vide.

- [ ] **Step 1: Test (échec attendu)**

Create `tests/Feature/Bibliotheque/ExtractionTest.php` :

```php
<?php

use App\Support\Bibliotheque\Chunker;

it('découpe un long texte en morceaux d’environ N mots', function () {
    $texte = trim(str_repeat('mot ', 2000)); // 2000 mots
    $morceaux = (new Chunker)->decouper($texte, mots: 800);

    expect(count($morceaux))->toBeGreaterThanOrEqual(2)
        ->and(str_word_count($morceaux[0]))->toBeLessThanOrEqual(900);
});

it('renvoie une liste vide pour un texte vide', function () {
    expect((new Chunker)->decouper('   '))->toBe([]);
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=ExtractionTest`
Expected: FAIL

- [ ] **Step 3: Chunker**

```php
<?php

namespace App\Support\Bibliotheque;

final class Chunker
{
    /** @return array<int,string> */
    public function decouper(string $texte, int $mots = 800): array
    {
        $texte = trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
        if ($texte === '') {
            return [];
        }

        $tokens = explode(' ', $texte);
        $morceaux = [];
        $chevauchement = 40; // ~1-2 phrases de recouvrement
        $pas = max(1, $mots - $chevauchement);

        for ($i = 0; $i < count($tokens); $i += $pas) {
            $tranche = array_slice($tokens, $i, $mots);
            if ($tranche === []) {
                break;
            }
            $morceaux[] = implode(' ', $tranche);
            if ($i + $mots >= count($tokens)) {
                break;
            }
        }

        return $morceaux;
    }
}
```

- [ ] **Step 4: Interface + impl + faux**

`app/Support/Bibliotheque/ExtracteurTexte.php` :

```php
<?php

namespace App\Support\Bibliotheque;

interface ExtracteurTexte
{
    /** Texte brut d'un .pdf ou .docx ; '' si non extractible. */
    public function extraire(string $cheminAbsolu): string;
}
```

`app/Support/Bibliotheque/ExtracteurTextePoppler.php` :

```php
<?php

namespace App\Support\Bibliotheque;

use Illuminate\Support\Facades\Process;
use PhpOffice\PhpWord\IOFactory;

final class ExtracteurTextePoppler implements ExtracteurTexte
{
    public function extraire(string $cheminAbsolu): string
    {
        if (! is_file($cheminAbsolu)) {
            return '';
        }
        $ext = strtolower(pathinfo($cheminAbsolu, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $res = Process::run(['pdftotext', '-enc', 'UTF-8', $cheminAbsolu, '-']);

            return $res->successful() ? trim($res->output()) : '';
        }

        if ($ext === 'docx') {
            try {
                $doc = IOFactory::load($cheminAbsolu);
                $texte = '';
                foreach ($doc->getSections() as $section) {
                    foreach ($section->getElements() as $el) {
                        if (method_exists($el, 'getText')) {
                            $texte .= $el->getText()."\n";
                        }
                    }
                }

                return trim($texte);
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
```

`app/Support/Bibliotheque/FauxExtracteur.php` :

```php
<?php

namespace App\Support\Bibliotheque;

final class FauxExtracteur implements ExtracteurTexte
{
    /** @param array<string,string> $parChemin  chemin => texte renvoyé */
    public function __construct(private array $parChemin = [], private string $defaut = '') {}

    public function extraire(string $cheminAbsolu): string
    {
        return $this->parChemin[$cheminAbsolu] ?? $this->parChemin[basename($cheminAbsolu)] ?? $this->defaut;
    }
}
```

- [ ] **Step 5: Provider + enregistrement**

`app/Providers/BibliothequeServiceProvider.php` :

```php
<?php

namespace App\Providers;

use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\ExtracteurTextePoppler;
use Illuminate\Support\ServiceProvider;

class BibliothequeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExtracteurTexte::class, ExtracteurTextePoppler::class);
    }
}
```

Add `App\Providers\BibliothequeServiceProvider::class` to `bootstrap/providers.php` (additive).

- [ ] **Step 6: Relancer (succès)**

Run: `php artisan test --filter=ExtractionTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Support/Bibliotheque app/Providers/BibliothequeServiceProvider.php bootstrap/providers.php tests/Feature/Bibliotheque/ExtractionTest.php
git commit -m "feat(bibliotheque): extraction (pdf/docx) + chunking"
```

---

### Task 3: Commande d'import `bibliotheque:importer`

**Files:**
- Create: `app/Console/Commands/ImporterBibliotheque.php`
- Create: `app/Support/Bibliotheque/ParseurLoiMd.php`
- Test: `tests/Feature/Bibliotheque/ImportTest.php`, `tests/Feature/Bibliotheque/ParseurLoiMdTest.php`

**Interfaces:**
- Consumes: `ExtracteurTexte`, `Chunker`, `Rubrique`, `Document`, `DocumentChunk`.
- Produces: `ParseurLoiMd::sections(string $markdown): array<int,array{titre:string,contenu:string}>` (une entrée par `## `). Commande `bibliotheque:importer {--loi=} {--dossier=}` (idempotente : upsert des documents par `titre`).

- [ ] **Step 1: Test du parseur (échec attendu)**

Create `tests/Feature/Bibliotheque/ParseurLoiMdTest.php` :

```php
<?php

use App\Support\Bibliotheque\ParseurLoiMd;

it('découpe le markdown par titre de niveau 2', function () {
    $md = "# Recueil\n\n## 1. Loi A du 1960\n\nCorps A.\n\n### Titre premier\nSous-corps.\n\n## 2. Décret B\n\nCorps B.\n";
    $sections = (new ParseurLoiMd)->sections($md);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['titre'])->toBe('1. Loi A du 1960')
        ->and($sections[0]['contenu'])->toContain('Corps A.')
        ->and($sections[0]['contenu'])->toContain('Titre premier')
        ->and($sections[1]['titre'])->toBe('2. Décret B');
});
```

- [ ] **Step 2: Lancer (échec), puis écrire le parseur**

Run: `php artisan test --filter=ParseurLoiMdTest` → FAIL.

`app/Support/Bibliotheque/ParseurLoiMd.php` :

```php
<?php

namespace App\Support\Bibliotheque;

final class ParseurLoiMd
{
    /** @return array<int,array{titre:string,contenu:string}> */
    public function sections(string $markdown): array
    {
        $lignes = preg_split('/\r?\n/', $markdown) ?: [];
        $sections = [];
        $courant = null;

        foreach ($lignes as $ligne) {
            if (preg_match('/^##\s+(?!#)(.+)$/', $ligne, $m)) {
                if ($courant) {
                    $sections[] = $courant;
                }
                $courant = ['titre' => trim($m[1]), 'contenu' => ''];
            } elseif ($courant !== null) {
                $courant['contenu'] .= $ligne."\n";
            }
        }
        if ($courant) {
            $sections[] = $courant;
        }

        foreach ($sections as &$s) {
            $s['contenu'] = trim($s['contenu']);
        }

        return $sections;
    }
}
```

Re-run: PASS.

- [ ] **Step 3: Test de la commande (échec attendu)**

Create `tests/Feature/Bibliotheque/ImportTest.php` :

```php
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
```

- [ ] **Step 4: Lancer (échec)**

Run: `php artisan test --filter=ImportTest`
Expected: FAIL (commande manquante)

- [ ] **Step 5: Écrire la commande**

`app/Console/Commands/ImporterBibliotheque.php` :

```php
<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\Chunker;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\ParseurLoiMd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImporterBibliotheque extends Command
{
    protected $signature = 'bibliotheque:importer {--loi=} {--dossier=}';

    protected $description = 'Importe le corpus (loi.md + dossier) dans la bibliothèque.';

    private const RUBRIQUES = [
        'Code électoral', 'Lois', 'Décrets & règlements', 'Ordonnances',
        'Rapports CENA', 'Textes historiques (JO 1960-1982)', 'Archives',
    ];

    public function handle(ExtracteurTexte $extracteur, Chunker $chunker, ParseurLoiMd $parseur): int
    {
        $rubriques = $this->seedRubriques();

        if ($loi = $this->option('loi')) {
            $this->importerLoiMd($loi, $rubriques['Textes historiques (JO 1960-1982)'], $parseur, $chunker);
        }

        if ($dossier = $this->option('dossier')) {
            $this->importerDossier($dossier, $rubriques, $extracteur, $chunker);
        }

        $this->info('Import terminé.');

        return self::SUCCESS;
    }

    /** @return array<string,int>  nom => id */
    private function seedRubriques(): array
    {
        $ids = [];
        foreach (self::RUBRIQUES as $i => $nom) {
            $r = Rubrique::updateOrCreate(
                ['nom' => $nom],
                ['slug' => Str::slug($nom), 'ordre' => $i, 'actif' => true],
            );
            $ids[$nom] = $r->id;
        }

        return $ids;
    }

    private function importerLoiMd(string $chemin, int $rubriqueId, ParseurLoiMd $parseur, Chunker $chunker): void
    {
        if (! is_file($chemin)) {
            $this->warn("loi.md introuvable: $chemin");

            return;
        }

        foreach ($parseur->sections((string) file_get_contents($chemin)) as $s) {
            $doc = Document::updateOrCreate(
                ['titre' => $s['titre']],
                [
                    'rubrique_id' => $rubriqueId,
                    'type' => $this->typeDepuisTitre($s['titre']),
                    'source' => 'texte',
                    'contenu' => $s['contenu'],
                    'reference' => $this->referenceDepuisTitre($s['titre']),
                    'actif' => true,
                ],
            );
            $this->rechunker($doc, $s['contenu'], $chunker);
        }
        $this->line('loi.md importé.');
    }

    private function importerDossier(string $dossier, array $rubriques, ExtracteurTexte $extracteur, Chunker $chunker): void
    {
        if (! is_dir($dossier)) {
            $this->warn("dossier introuvable: $dossier");

            return;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fichier) {
            /** @var \SplFileInfo $fichier */
            $ext = strtolower($fichier->getExtension());
            $nom = $fichier->getFilename();
            if (! in_array($ext, ['pdf', 'docx'], true) || str_starts_with($nom, '~$')) {
                continue;
            }

            $titre = pathinfo($nom, PATHINFO_FILENAME);
            [$rubrique, $type] = $this->classer($titre);

            $contenu = (string) file_get_contents($fichier->getPathname());
            $destination = 'bibliotheque/'.Str::slug($titre).'.'.$ext;
            Storage::disk('public')->put($destination, $contenu);

            $doc = Document::updateOrCreate(
                ['titre' => $titre],
                [
                    'rubrique_id' => $rubriques[$rubrique],
                    'type' => $type,
                    'source' => 'fichier',
                    'fichier_path' => $destination,
                    'actif' => true,
                ],
            );

            $texte = $extracteur->extraire($fichier->getPathname());
            if (mb_strlen($texte) > 800) {
                $this->rechunker($doc, $texte, $chunker);
            } else {
                $doc->chunks()->delete();
            }
        }
        $this->line('dossier importé.');
    }

    private function rechunker(Document $doc, string $texte, Chunker $chunker): void
    {
        $doc->chunks()->delete();
        foreach ($chunker->decouper($texte) as $i => $morceau) {
            $doc->chunks()->create(['ordre' => $i, 'contenu' => $morceau]);
        }
    }

    private function typeDepuisTitre(string $titre): string
    {
        $t = Str::lower($titre);

        return match (true) {
            str_contains($t, 'ordonnance') => 'ordonnance',
            str_contains($t, 'décret'), str_contains($t, 'decret') => 'decret',
            str_contains($t, 'loi') => 'loi',
            default => 'autre',
        };
    }

    private function referenceDepuisTitre(string $titre): ?string
    {
        return preg_match('/n[°o]\s?[\d\-\/]+/ui', $titre, $m) ? trim($m[0]) : null;
    }

    /** @return array{0:string,1:string}  [rubrique, type] */
    private function classer(string $titre): array
    {
        $t = Str::lower($titre);

        return match (true) {
            str_contains($t, 'cena') || str_contains($t, 'rapport') => ['Rapports CENA', 'rapport'],
            str_contains($t, 'code') => ['Code électoral', 'loi'],
            str_contains($t, 'décret') || str_contains($t, 'decret') => ['Décrets & règlements', 'decret'],
            str_contains($t, 'présidentielle') || str_contains($t, 'presidentielle') || str_contains($t, 'revue') => ['Archives', 'archive'],
            default => ['Archives', 'autre'],
        };
    }
}
```

- [ ] **Step 6: Relancer les tests (succès)**

Run: `php artisan test --filter=ImportTest` puis `php artisan test --filter=ParseurLoiMdTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/ImporterBibliotheque.php app/Support/Bibliotheque/ParseurLoiMd.php tests/Feature/Bibliotheque/ImportTest.php tests/Feature/Bibliotheque/ParseurLoiMdTest.php
git commit -m "feat(bibliotheque): commande d'import (loi.md + dossier)"
```

---

### Task 4: Consultation — `Bibliotheque\Index` (recherche + rubriques + résultats)

**Files:**
- Create: `app/Livewire/Bibliotheque/Index.php`
- Create: `resources/views/livewire/bibliotheque/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Bibliotheque/ConsultationTest.php`

**Interfaces:**
- Consumes: `Document`, `Rubrique`.
- Produces: route `bibliotheque` (`/bibliotheque`).

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/ConsultationTest.php` :

```php
<?php

use App\Livewire\Bibliotheque\Index;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentBiblio(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
}

it('liste les documents actifs et filtre par recherche', function () {
    $r = Rubrique::factory()->create(['nom' => 'Lois']);
    Document::factory()->for($r)->create(['titre' => 'Loi sur le parrainage', 'mots_cles' => 'parrainage', 'actif' => true]);
    Document::factory()->for($r)->create(['titre' => 'Décret budget', 'actif' => true]);
    Document::factory()->create(['titre' => 'Document inactif', 'actif' => false]);

    Livewire::actingAs(agentBiblio())->test(Index::class)
        ->assertSee('Loi sur le parrainage')
        ->assertDontSee('Document inactif')
        ->set('recherche', 'parrainage')
        ->assertSee('Loi sur le parrainage')
        ->assertDontSee('Décret budget');
});

it('filtre par rubrique', function () {
    $lois = Rubrique::factory()->create(['nom' => 'Lois']);
    $decrets = Rubrique::factory()->create(['nom' => 'Décrets']);
    Document::factory()->for($lois)->create(['titre' => 'Une loi']);
    Document::factory()->for($decrets)->create(['titre' => 'Un décret']);

    Livewire::actingAs(agentBiblio())->test(Index::class)
        ->set('rubriqueId', $lois->id)
        ->assertSee('Une loi')
        ->assertDontSee('Un décret');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=ConsultationTest`
Expected: FAIL

- [ ] **Step 3: Composant**

`app/Livewire/Bibliotheque/Index.php` :

```php
<?php

namespace App\Livewire\Bibliotheque;

use App\Models\Document;
use App\Models\Rubrique;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public ?int $rubriqueId = null;

    #[Url]
    public string $type = '';

    public function updating($name): void
    {
        if (in_array($name, ['recherche', 'rubriqueId', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function choisirRubrique(?int $id): void
    {
        $this->rubriqueId = $id;
        $this->resetPage();
    }

    public function render()
    {
        $q = Document::query()->where('actif', true)->with('rubrique');

        if ($this->recherche !== '') {
            $t = '%'.$this->recherche.'%';
            $q->where(fn ($w) => $w->where('titre', 'like', $t)
                ->orWhere('resume', 'like', $t)
                ->orWhere('mots_cles', 'like', $t)
                ->orWhere('reference', 'like', $t));
        }
        if ($this->rubriqueId) {
            $q->where('rubrique_id', $this->rubriqueId);
        }
        if ($this->type !== '') {
            $q->where('type', $this->type);
        }

        return view('livewire.bibliotheque.index', [
            'documents' => $q->latest('date_document')->latest('id')->paginate(12),
            'rubriques' => Rubrique::where('actif', true)->withCount('documents')->orderBy('ordre')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue (encyclopédie minimale — design soigné en passe ultérieure)**

`resources/views/livewire/bibliotheque/index.blade.php` :

```blade
<div class="biblio">
    <header class="card" style="text-align:center;padding:2rem;">
        <h1>Bibliothèque électorale</h1>
        <input type="search" wire:model.live.debounce.300ms="recherche"
               placeholder="Rechercher une loi, un décret, un thème…" style="width:100%;max-width:640px;padding:.75rem;">
    </header>

    <section class="tuiles" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin:1rem 0;">
        <button class="card @if(!$rubriqueId) actif @endif" wire:click="choisirRubrique(null)">Toutes ({{ $rubriques->sum('documents_count') }})</button>
        @foreach ($rubriques as $r)
            <button class="card @if($rubriqueId===$r->id) actif @endif" wire:click="choisirRubrique({{ $r->id }})">
                {{ $r->icone }} {{ $r->nom }} <span class="badge">{{ $r->documents_count }}</span>
            </button>
        @endforeach
    </section>

    <section class="resultats" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
        @forelse ($documents as $doc)
            <a class="card" href="{{ route('bibliotheque.document', $doc) }}" wire:navigate>
                <span class="badge">{{ $doc->rubrique?->nom }}</span>
                <span class="badge">{{ $doc->type }}</span>
                <h3>{{ $doc->titre }}</h3>
                @if ($doc->reference)<p>{{ $doc->reference }}</p>@endif
                @if ($doc->resume)<p>{{ Str::limit($doc->resume, 140) }}</p>@endif
            </a>
        @empty
            <p>Aucun document. Lancez <code>php artisan bibliotheque:importer</code> pour charger le corpus.</p>
        @endforelse
    </section>

    <div style="margin-top:1rem;">{{ $documents->links() }}</div>
</div>
```

- [ ] **Step 5: Routes (les DEUX, ici)**

In `routes/web.php`, add both imports and both routes inside the `['auth','verified','password.change']` group. On enregistre **aussi** la route `bibliotheque.document` maintenant (la vue Index génère son URL ; générer une URL n'autoload pas la classe cible, donc c'est OK même si `FicheDocument` n'existe qu'à la Task 5) :

```php
use App\Livewire\Bibliotheque\Index as BibliothequeIndex;
use App\Livewire\Bibliotheque\FicheDocument;
// ...
Route::get('/bibliotheque', BibliothequeIndex::class)->name('bibliotheque');
Route::get('/bibliotheque/document/{document}', FicheDocument::class)->name('bibliotheque.document');
```

- [ ] **Step 6: Relancer (succès)**

Run: `php artisan test --filter=ConsultationTest`
Expected: PASS (le lien `route('bibliotheque.document', $doc)` ne génère qu'une URL — `FicheDocument` n'est pas instanciée tant que la route n'est pas atteinte)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Bibliotheque/Index.php resources/views/livewire/bibliotheque/index.blade.php routes/web.php tests/Feature/Bibliotheque/ConsultationTest.php
git commit -m "feat(bibliotheque): consultation (recherche + rubriques + résultats)"
```

---

### Task 5: Fiche document — `Bibliotheque\FicheDocument` (rendu texte / PDF / téléchargement)

**Files:**
- Create: `app/Livewire/Bibliotheque/FicheDocument.php`
- Create: `resources/views/livewire/bibliotheque/fiche-document.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Bibliotheque/FicheDocumentTest.php`

**Interfaces:**
- Consumes: `Document`, `Str::markdown()`.
- Produces: route `bibliotheque.document` (`/bibliotheque/document/{document}`).

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/FicheDocumentTest.php` :

```php
<?php

use App\Livewire\Bibliotheque\FicheDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentFiche(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
}

it('rend le markdown d’un document texte', function () {
    $d = Document::factory()->create(['source' => 'texte', 'contenu' => "## Article premier\n\nLe corps électoral.", 'titre' => 'Loi X']);

    Livewire::actingAs(agentFiche())->test(FicheDocument::class, ['document' => $d])
        ->assertSee('Article premier')
        ->assertSee('Le corps électoral')
        ->assertSee('Loi X');
});

it('expose l’URL fichier et le drapeau PDF pour une source fichier', function () {
    $d = Document::factory()->create(['source' => 'fichier', 'fichier_path' => 'bibliotheque/code.pdf', 'contenu' => null, 'titre' => 'Code']);

    Livewire::actingAs(agentFiche())->test(FicheDocument::class, ['document' => $d])
        ->assertSee('/storage/bibliotheque/code.pdf');
});

it('interdit l’accès à un invité', function () {
    $d = Document::factory()->create();
    $this->get(route('bibliotheque.document', $d))->assertRedirect('/login');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=FicheDocumentTest`
Expected: FAIL

- [ ] **Step 3: Composant**

`app/Livewire/Bibliotheque/FicheDocument.php` :

```php
<?php

namespace App\Livewire\Bibliotheque;

use App\Models\Document;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FicheDocument extends Component
{
    public Document $document;

    public function mount(Document $document): void
    {
        $this->document = $document->load('rubrique');
    }

    public function render()
    {
        $html = $this->document->estTexte() && $this->document->contenu
            ? Str::markdown($this->document->contenu)
            : null;

        return view('livewire.bibliotheque.fiche-document', ['html' => $html]);
    }
}
```

- [ ] **Step 4: Vue**

`resources/views/livewire/bibliotheque/fiche-document.blade.php` :

```blade
<div class="fiche">
    <a href="{{ route('bibliotheque') }}" wire:navigate>← Bibliothèque</a>

    <header class="card">
        <span class="badge">{{ $document->rubrique?->nom }}</span>
        <span class="badge">{{ $document->type }}</span>
        <h1>{{ $document->titre }}</h1>
        @if ($document->reference)<p><strong>{{ $document->reference }}</strong></p>@endif
        @if ($document->date_document)<p>{{ $document->date_document->format('d/m/Y') }}</p>@endif
        @if ($document->resume)<p>{{ $document->resume }}</p>@endif
    </header>

    <section class="card">
        @if ($html)
            <article class="prose">{!! $html !!}</article>
        @elseif ($document->source === 'fichier' && $document->fichierUrl())
            @if ($document->estPdf())
                <iframe src="{{ $document->fichierUrl() }}" style="width:100%;height:80vh;border:0;"></iframe>
            @endif
            <a class="btn" href="{{ $document->fichierUrl() }}" download>Télécharger</a>
        @elseif ($document->source === 'lien' && $document->url)
            <a class="btn" href="{{ $document->url }}" target="_blank" rel="noopener">Ouvrir le lien ↗</a>
        @else
            <p>Contenu non disponible.</p>
        @endif
    </section>
</div>
```

- [ ] **Step 5: Route (déjà enregistrée en Task 4)**

La route `bibliotheque.document` a été enregistrée en Task 4 Step 5 (avec son import). **Rien à ajouter ici** — vérifier juste qu'elle pointe bien sur `App\Livewire\Bibliotheque\FicheDocument` (le composant créé à cette task).

- [ ] **Step 6: Relancer (succès)**

Run: `php artisan test --filter=FicheDocumentTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Bibliotheque/FicheDocument.php resources/views/livewire/bibliotheque/fiche-document.blade.php routes/web.php tests/Feature/Bibliotheque/FicheDocumentTest.php
git commit -m "feat(bibliotheque): fiche document (rendu texte/PDF + téléchargement)"
```

---

### Task 6: Navigation + accès de bout en bout

**Files:**
- Modify: `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Bibliotheque/AccesBibliothequeTest.php`

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/AccesBibliothequeTest.php` :

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tout agent connecté accède à la bibliothèque', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque')->assertOk();
});

it('un invité est redirigé', function () {
    $this->get('/bibliotheque')->assertRedirect('/login');
});

it('le lien Bibliothèque apparaît dans la nav', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque')->assertSee('Bibliothèque');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=AccesBibliothequeTest`
Expected: FAIL

- [ ] **Step 3: Ajouter le lien nav**

In `resources/views/components/layouts/rh.blade.php`, **READ the existing sidebar first** and copy the real link pattern (`rh-nav a`, active class `on`, `$ico` SVG map — same convention used for the "Assistant IA" link). Add, visible to all connected agents (section Personnel) :

```blade
<a href="{{ route('bibliotheque') }}" class="{{ request()->routeIs('bibliotheque*') ? 'on' : '' }}">
    {!! $ico['livre'] ?? $ico['chat'] !!} <span>Bibliothèque</span>
</a>
```

Add a `livre` (book) SVG entry to the `$ico` array, consistent with existing icons.

- [ ] **Step 4: Relancer + suite complète**

Run: `php artisan test --filter=AccesBibliothequeTest` puis `php artisan test`
Expected: PASS (toute la suite verte)

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/layouts/rh.blade.php tests/Feature/Bibliotheque/AccesBibliothequeTest.php
git commit -m "feat(bibliotheque): navigation + accès"
```

---

## Après ce plan

- **Seeder réel** : `php artisan bibliotheque:importer --loi="/Users/admin/Desktop/MES PROJET/loi.md" --dossier="/Users/admin/Documents/projet eletion"` puis `php artisan storage:link` (si pas fait) pour servir les PDF.
- **Assistant Phase 2 — ancrage** : outil `rechercher_bibliotheque` (FULLTEXT MySQL `MATCH(contenu) AGAINST` sur `document_chunks`, fallback LIKE) branché dans `AssistantClaude` ; citations vers `bibliotheque.document`.
- **Gestion CRUD** (archiviste/admin) : ajout/édition/désactivation manuelle de rubriques et documents.
- **OCR** (optionnel) : Tesseract français pour rendre les scans (Code 2021 JO, CENA…) cherchables.
- **Passe design ui-ux-pro-max** : encyclopédie (hero, tuiles, cartes, lecteur), états vides, charte DGB.

## Self-Review (effectuée)

- **Couverture spec** : sources texte/fichier/lien (Task 1), `texte`+`contenu` (Task 1), chunks + FULLTEXT MySQL-only (Task 1), extraction pdf/docx + seuil 800 (Task 2/3), import loi.md 1-doc-par-`##` + dossier + idempotence + scan-sans-chunks (Task 3), consultation recherche/rubriques/filtres/actifs-seuls (Task 4), fiche rendu markdown/PDF/téléchargement (Task 5), accès ouvert + nav (Task 6). Gestion CRUD + ancrage + OCR + design → déclarés hors périmètre.
- **Placeholders** : aucun — tests et implémentations complets.
- **Cohérence des types** : `ExtracteurTexte::extraire(string):string` identique (interface/impl/faux/commande) ; `Chunker::decouper(string,int):array` ; `ParseurLoiMd::sections(string):array` ; `Document::fichierUrl()/estPdf()/estTexte()` définis Task 1, utilisés Tasks 4/5 ; route `bibliotheque.document` définie Task 5, référencée dans la vue Task 4 (les deux composants livrés avant la fin ; la suite complète tourne en Task 6).
- **Ordonnancement des routes (résolu)** : les deux routes (`bibliotheque` + `bibliotheque.document`) sont enregistrées en **Task 4** Step 5, car la vue Index génère l'URL de la fiche au rendu. Générer une URL nommée n'autoload PAS la classe cible, donc enregistrer la route vers `FicheDocument` avant de créer la classe (Task 5) est sans risque et ConsultationTest passe. Task 5 n'ajoute pas de route.
