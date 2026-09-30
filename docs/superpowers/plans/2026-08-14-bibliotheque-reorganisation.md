# Réorganisation bibliothèque — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Réorganiser la bibliothèque électorale en rubriques par type, classées par année, en purgeant les documents hors-sujet — sans ré-importer.

**Architecture:** Une classe pure `ClasseurDocuments` porte toute la logique (année, hors-sujet, rubrique). Une commande `bibliotheque:reclasser` l'applique sur les documents `source=fichier` déjà en base (dry-run par défaut, suppression sous double drapeau). La page Bibliothèque groupe les documents d'une rubrique par année.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, MySQL (prod) / SQLite in-memory (tests).

## Global Constraints

- PHP 8.3, Laravel 12, Livewire 3.
- Tests : Pest 3, base SQLite in-memory (`RefreshDatabase`).
- Charte DGB existante (tokens `:root`, classes `.card`), pas de nouveau design system.
- L'enum `documents.type` reste `[loi, decret, reglement, rapport, circulaire, guide, archive, ordonnance, autre]` — NE PAS le modifier.
- Documents `source = 'texte'` (sections `loi.md`) : jamais déplacés ni supprimés.
- Suppression définitive uniquement si `--appliquer` ET `--supprimer`.
- Fichiers stockés sur `Storage::disk('public')`, chemin dans `documents.fichier_path`.

---

## File Structure

- Create `app/Support/Bibliotheque/ClasseurDocuments.php` — logique pure de classement.
- Create `database/migrations/2026_08_14_000001_add_annee_to_documents_table.php` — colonne `annee`.
- Create `app/Console/Commands/ReclasserBibliotheque.php` — commande.
- Modify `app/Models/Document.php` — `annee` fillable + cast.
- Modify `app/Livewire/Bibliotheque/Index.php` — groupement par année.
- Modify `resources/views/livewire/bibliotheque/index.blade.php` — accordéons par année.
- Create `tests/Feature/Bibliotheque/ClasseurDocumentsTest.php`
- Create `tests/Feature/Bibliotheque/ReclasserBibliothequeTest.php`
- Create `tests/Feature/Bibliotheque/BibliothequeGroupageTest.php`

---

## Task 1: Migration `annee`

**Files:**
- Create: `database/migrations/2026_08_14_000001_add_annee_to_documents_table.php`
- Modify: `app/Models/Document.php`

**Interfaces:**
- Produces: colonne `documents.annee` (`unsignedSmallInteger` nullable, indexée) ; `Document::$fillable` inclut `annee` ; cast `annee => integer`.

- [ ] **Step 1: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('annee')->nullable()->index()->after('date_document');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('annee');
        });
    }
};
```

- [ ] **Step 2: Ajouter `annee` au modèle**

Dans `app/Models/Document.php`, ajouter `'annee'` au tableau `$fillable` et, s'il existe un `$casts`, y ajouter `'annee' => 'integer'` (sinon créer `protected $casts = ['annee' => 'integer'];`). Vérifier d'abord la présence de `date_document` dans `$casts` pour suivre le style existant.

- [ ] **Step 3: Migrer**

Run: `php artisan migrate`
Expected: migration `add_annee_to_documents_table` exécutée, colonne présente.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_14_000001_add_annee_to_documents_table.php app/Models/Document.php
git commit -m "feat(biblio): colonne annee sur documents"
```

---

## Task 2: Classe `ClasseurDocuments`

**Files:**
- Create: `app/Support/Bibliotheque/ClasseurDocuments.php`
- Test: `tests/Feature/Bibliotheque/ClasseurDocumentsTest.php`

**Interfaces:**
- Produces :
  - `ClasseurDocuments::RUBRIQUES` : `array<int,string>` — noms canoniques ordonnés.
  - `annee(?string $titre, ?string $reference = null, ?\DateTimeInterface $dateDocument = null): ?int`
  - `estHorsSujet(string $titre): bool`
  - `rubrique(string $titre): string` — renvoie un nom de `RUBRIQUES`.
  - `typePourRubrique(string $rubrique): string` — renvoie une valeur de l'enum `type`.

- [ ] **Step 1: Écrire les tests**

```php
<?php

use App\Support\Bibliotheque\ClasseurDocuments;

beforeEach(fn () => $this->c = new ClasseurDocuments());

it('extrait l année la plus récente du titre', function () {
    expect($this->c->annee('Résultats Elections Presidentielles 1963 - 2012'))->toBe(2012);
    expect($this->c->annee('élections du 22 mars 2009'))->toBe(2009);
});

it('donne la priorité à date_document', function () {
    expect($this->c->annee('rapport 2010', null, new DateTime('2016-03-20')))->toBe(2016);
});

it('renvoie null sans année plausible', function () {
    expect($this->c->annee('Code électoral'))->toBeNull();
    expect($this->c->annee('document 3012'))->toBeNull(); // hors intervalle
});

it('détecte les documents hors-sujet', function () {
    expect($this->c->estHorsSujet('ETAT DE PAIEMENT APPUI FINANCIER 2012'))->toBeTrue();
    expect($this->c->estHorsSujet('Certificat de cessation de service'))->toBeTrue();
    expect($this->c->estHorsSujet('Budget 2013'))->toBeTrue();
});

it('protège les documents finance à signal électoral', function () {
    expect($this->c->estHorsSujet('Budget de la CENA pour les élections 2012'))->toBeFalse();
    expect($this->c->estHorsSujet('Résultats du scrutin présidentiel'))->toBeFalse();
});

it('classe par type via mots-clés du titre', function () {
    expect($this->c->rubrique('Décret n° 2014-01 portant convocation'))->toBe('Décrets');
    expect($this->c->rubrique('Arrêté modifiant la carte électorale'))->toBe('Arrêtés');
    expect($this->c->rubrique('CODE ELECTORAL 2012'))->toBe('Code électoral');
    expect($this->c->rubrique('Loi organique n° 2017-01'))->toBe('Lois');
    expect($this->c->rubrique('Guide pratique du bureau de vote'))->toBe('Guides pratiques & bréviaires');
    expect($this->c->rubrique('Rapport Annuel CENA 2015'))->toBe('Rapports CENA');
    expect($this->c->rubrique('Mission d Audit du Fichier Electoral 2010'))->toBe('Audit du fichier électoral');
    expect($this->c->rubrique('Rapport du comité de veille 2012'))->toBe('Comité de veille');
    expect($this->c->rubrique('Mission d observation UE 2012'))->toBe('Missions d\'observation');
    expect($this->c->rubrique('Compte rendu réunion coordination DGE'))->toBe('Comptes rendus & réunions');
    expect($this->c->rubrique('Rapport général sur le parrainage'))->toBe('Investitures');
    expect($this->c->rubrique('Liste des Partis Politiques 2012'))->toBe('Données & cartes électorales');
});

it('range par défaut les non typés vers Données & cartes électorales', function () {
    expect($this->c->rubrique('EXPO DU TRONE'))->toBe('Données & cartes électorales');
});

it('mappe la rubrique vers un type de l enum', function () {
    expect($this->c->typePourRubrique('Décrets'))->toBe('decret');
    expect($this->c->typePourRubrique('Rapports CENA'))->toBe('rapport');
    expect($this->c->typePourRubrique('Guides pratiques & bréviaires'))->toBe('guide');
    expect($this->c->typePourRubrique('Constitution'))->toBe('loi');
});
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `php artisan test tests/Feature/Bibliotheque/ClasseurDocumentsTest.php`
Expected: FAIL (classe absente).

- [ ] **Step 3: Écrire la classe**

```php
<?php

namespace App\Support\Bibliotheque;

use Illuminate\Support\Str;

class ClasseurDocuments
{
    /** Rubriques canoniques, dans l'ordre d'affichage. */
    public const RUBRIQUES = [
        'Constitution',
        'Code électoral',
        'Lois',
        'Ordonnances',
        'Décrets',
        'Arrêtés',
        'Circulaires & instructions',
        'Décisions du Conseil constitutionnel',
        'Guides pratiques & bréviaires',
        'Rapports CENA',
        'Audit du fichier électoral',
        'Comité de veille',
        "Missions d'observation",
        'Rapports divers',
        'Comptes rendus & réunions',
        'Investitures',
        'Données & cartes électorales',
        'Textes historiques (JO 1960-1982)',
    ];

    private const FINANCE = [
        'budget', 'paiement', 'etat financier', 'financ', 'salaire', 'indemnit',
        'facture', 'bon de commande', 'decharge', 'certificat', 'cessation',
        'comptable', 'depense', 'orsec', 'acte de donation', 'repertoire telephonique',
        'curriculum', 'cv ',
    ];

    private const SIGNAUX = [
        'election', 'electoral', 'scrutin', 'vote', 'parrainage', 'candidat',
        'referendum', 'cena', 'recensement', 'parti', 'carte electeur',
        'liste electorale', 'code electoral',
    ];

    public function annee(?string $titre, ?string $reference = null, ?\DateTimeInterface $dateDocument = null): ?int
    {
        if ($dateDocument !== null) {
            return (int) $dateDocument->format('Y');
        }

        $max = (int) date('Y') + 1;
        $meilleure = null;
        foreach ([$reference, $titre] as $source) {
            if ($source === null) {
                continue;
            }
            if (preg_match_all('/\b(1[89]\d{2}|20\d{2})\b/', $source, $m)) {
                foreach ($m[1] as $a) {
                    $a = (int) $a;
                    if ($a >= 1840 && $a <= $max && ($meilleure === null || $a > $meilleure)) {
                        $meilleure = $a;
                    }
                }
            }
        }

        return $meilleure;
    }

    public function estHorsSujet(string $titre): bool
    {
        $t = $this->n($titre);
        $finance = false;
        foreach (self::FINANCE as $mot) {
            if (str_contains($t, $mot)) {
                $finance = true;
                break;
            }
        }
        if (! $finance) {
            return false;
        }
        foreach (self::SIGNAUX as $mot) {
            if (str_contains($t, $mot)) {
                return false;
            }
        }

        return true;
    }

    public function rubrique(string $titre): string
    {
        $t = $this->n($titre);

        return match (true) {
            str_contains($t, 'constitution') => 'Constitution',
            str_contains($t, 'code electoral') => 'Code électoral',
            str_contains($t, 'ordonnance') => 'Ordonnances',
            str_contains($t, 'decret') => 'Décrets',
            str_contains($t, 'arrete') => 'Arrêtés',
            str_contains($t, 'circulaire') || str_contains($t, 'instruction') || str_contains($t, 'note de service') => 'Circulaires & instructions',
            str_contains($t, 'conseil constitutionnel') || str_contains($t, 'decision n') || str_contains($t, 'avis n') => 'Décisions du Conseil constitutionnel',
            str_contains($t, 'loi ') || str_contains($t, 'loi n') || str_contains($t, 'loi organique') => 'Lois',
            str_contains($t, 'guide') || str_contains($t, 'breviaire') || str_contains($t, 'manuel') || str_contains($t, 'formation') => 'Guides pratiques & bréviaires',
            str_contains($t, 'cena') => 'Rapports CENA',
            str_contains($t, 'audit') || str_contains($t, 'mafe') => 'Audit du fichier électoral',
            str_contains($t, 'comite de veille') => 'Comité de veille',
            str_contains($t, 'observation') => "Missions d'observation",
            str_contains($t, 'compte rendu') || str_contains($t, 'reunion') || str_contains($t, 'proces verbal') || str_contains($t, 'pv ') || str_contains($t, 'coordination') || str_contains($t, 'cpdn') || str_contains($t, 'ctrce') => 'Comptes rendus & réunions',
            str_contains($t, 'investiture') || str_contains($t, 'parrainage') || str_contains($t, 'cautionnement') => 'Investitures',
            str_contains($t, 'carte electorale') || str_contains($t, 'liste des partis') || str_contains($t, 'repartition') || str_contains($t, 'bureaux de vote') || str_contains($t, 'electeurs') => 'Données & cartes électorales',
            str_contains($t, 'rapport') || str_contains($t, 'mission') || str_contains($t, 'bilan') || str_contains($t, 'evaluation') => 'Rapports divers',
            default => 'Données & cartes électorales',
        };
    }

    public function typePourRubrique(string $rubrique): string
    {
        return match ($rubrique) {
            'Constitution', 'Code électoral', 'Lois' => 'loi',
            'Ordonnances' => 'ordonnance',
            'Décrets' => 'decret',
            'Arrêtés' => 'reglement',
            'Circulaires & instructions' => 'circulaire',
            'Guides pratiques & bréviaires' => 'guide',
            'Rapports CENA', 'Audit du fichier électoral', 'Comité de veille',
            "Missions d'observation", 'Rapports divers', 'Comptes rendus & réunions' => 'rapport',
            default => 'archive',
        };
    }

    private function n(string $s): string
    {
        $s = Str::lower(Str::ascii($s));

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
```

- [ ] **Step 4: Lancer, vérifier le succès**

Run: `php artisan test tests/Feature/Bibliotheque/ClasseurDocumentsTest.php`
Expected: PASS (tous verts).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Bibliotheque/ClasseurDocuments.php tests/Feature/Bibliotheque/ClasseurDocumentsTest.php
git commit -m "feat(biblio): classeur de documents (annee, hors-sujet, rubrique)"
```

---

## Task 3: Commande `bibliotheque:reclasser`

**Files:**
- Create: `app/Console/Commands/ReclasserBibliotheque.php`
- Test: `tests/Feature/Bibliotheque/ReclasserBibliothequeTest.php`

**Interfaces:**
- Consumes: `ClasseurDocuments` (Task 2), `Document`, `Rubrique`, colonne `annee` (Task 1).
- Produces: commande artisan `bibliotheque:reclasser {--appliquer} {--supprimer}`.

- [ ] **Step 1: Écrire les tests**

```php
<?php

use App\Models\Document;
use App\Models\Rubrique;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Storage;

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
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `php artisan test tests/Feature/Bibliotheque/ReclasserBibliothequeTest.php`
Expected: FAIL (commande absente).

- [ ] **Step 3: Écrire la commande**

```php
<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\ClasseurDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReclasserBibliotheque extends Command
{
    protected $signature = 'bibliotheque:reclasser {--appliquer} {--supprimer}';

    protected $description = 'Réorganise la bibliothèque (rubriques par type + année) et purge le hors-sujet.';

    public function handle(ClasseurDocuments $classeur): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $supprimer = (bool) $this->option('supprimer');
        $rubriques = $this->seedRubriques($appliquer);

        $aSupprimer = [];
        $parRubrique = [];
        $sansAnnee = 0;
        $reclasses = 0;

        Document::where('source', 'fichier')->chunkById(200, function ($lot) use (
            $classeur, $appliquer, $supprimer, $rubriques, &$aSupprimer, &$parRubrique, &$sansAnnee, &$reclasses
        ) {
            foreach ($lot as $doc) {
                if ($classeur->estHorsSujet($doc->titre)) {
                    $aSupprimer[] = $doc->titre;
                    if ($appliquer && $supprimer) {
                        Storage::disk('public')->delete($doc->fichier_path);
                        $doc->chunks()->delete();
                        $doc->delete();
                    }

                    continue;
                }

                $nom = $classeur->rubrique($doc->titre);
                $type = $classeur->typePourRubrique($nom);
                $annee = $classeur->annee($doc->titre, $doc->reference, $doc->date_document);

                $parRubrique[$nom] = ($parRubrique[$nom] ?? 0) + 1;
                if ($annee === null) {
                    $sansAnnee++;
                }
                $reclasses++;

                if ($appliquer) {
                    $doc->update([
                        'rubrique_id' => $rubriques[$nom],
                        'type' => $type,
                        'annee' => $annee,
                    ]);
                }
            }
        });

        if ($appliquer) {
            $this->desactiverRubriquesVides();
        }

        $this->rapport($aSupprimer, $parRubrique, $sansAnnee, $reclasses, $appliquer, $supprimer);

        return self::SUCCESS;
    }

    /** @return array<string,int> nom => id */
    private function seedRubriques(bool $appliquer): array
    {
        $ids = [];
        foreach (ClasseurDocuments::RUBRIQUES as $i => $nom) {
            if ($appliquer) {
                $r = Rubrique::updateOrCreate(
                    ['nom' => $nom],
                    ['slug' => Str::slug($nom), 'ordre' => $i, 'actif' => true],
                );
            } else {
                $r = Rubrique::firstOrNew(['nom' => $nom]);
                $r->id ??= 0;
            }
            $ids[$nom] = $r->id;
        }

        return $ids;
    }

    private function desactiverRubriquesVides(): void
    {
        Rubrique::whereNotIn('nom', ClasseurDocuments::RUBRIQUES)
            ->whereDoesntHave('documents', fn ($q) => $q->where('actif', true))
            ->update(['actif' => false]);
    }

    private function rapport(array $aSupprimer, array $parRubrique, int $sansAnnee, int $reclasses, bool $appliquer, bool $supprimer): void
    {
        $mode = $appliquer ? ($supprimer ? 'APPLIQUER + SUPPRIMER' : 'APPLIQUER') : 'DRY-RUN (aucune écriture)';
        $this->info("Mode : $mode");
        $this->line('Reclassés : '.$reclasses.' | sans année : '.$sansAnnee);
        arsort($parRubrique);
        foreach ($parRubrique as $nom => $n) {
            $this->line(sprintf('  %-42s %5d', $nom, $n));
        }
        $this->newLine();
        $this->warn('Hors-sujet détectés : '.count($aSupprimer).($appliquer && $supprimer ? ' (SUPPRIMÉS)' : ' (non supprimés)'));
        foreach (array_slice($aSupprimer, 0, 50) as $titre) {
            $this->line('  ✗ '.$titre);
        }
        if (count($aSupprimer) > 50) {
            $this->line('  … +'.(count($aSupprimer) - 50).' autres');
        }
    }
}
```

- [ ] **Step 4: Lancer, vérifier le succès**

Run: `php artisan test tests/Feature/Bibliotheque/ReclasserBibliothequeTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ReclasserBibliotheque.php tests/Feature/Bibliotheque/ReclasserBibliothequeTest.php
git commit -m "feat(biblio): commande reclasser (dry-run, purge sous double drapeau)"
```

---

## Task 4: Groupement par année dans la page Bibliothèque

**Files:**
- Modify: `app/Livewire/Bibliotheque/Index.php`
- Modify: `resources/views/livewire/bibliotheque/index.blade.php`
- Test: `tests/Feature/Bibliotheque/BibliothequeGroupageTest.php`

**Interfaces:**
- Consumes: colonne `annee`, `Document`, `Rubrique`.
- Produces: quand `rubriqueId` est défini, la vue reçoit `groupesAnnee` : `Collection<int|string, Collection<Document>>` triée par année décroissante, clé `'Non daté'` en dernier.

- [ ] **Step 1: Écrire le test**

```php
<?php

use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Bibliotheque\Index;

it('groupe les documents d une rubrique par année décroissante', function () {
    $u = User::factory()->create();
    $r = Rubrique::create(['nom' => 'Décrets', 'slug' => 'decrets', 'ordre' => 5, 'actif' => true]);
    Document::create(['titre' => 'Décret A', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => 2012, 'actif' => true]);
    Document::create(['titre' => 'Décret B', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => 2019, 'actif' => true]);
    Document::create(['titre' => 'Décret C', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => null, 'actif' => true]);

    Livewire::actingAs($u)->test(Index::class)
        ->set('rubriqueId', $r->id)
        ->assertViewHas('groupesAnnee', function ($g) {
            $cles = $g->keys()->all();

            return $cles[0] === 2019 && $cles[1] === 2012 && end($cles) === 'Non daté';
        });
});
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `php artisan test tests/Feature/Bibliotheque/BibliothequeGroupageTest.php`
Expected: FAIL (`groupesAnnee` absent).

- [ ] **Step 3: Modifier le composant**

Dans `app/Livewire/Bibliotheque/Index.php`, méthode `render()` : après la construction de `$q`, si `$this->rubriqueId` est défini, produire un groupement par année. Remplacer le tableau passé à la vue pour inclure `groupesAnnee` (null quand aucune rubrique n'est choisie).

```php
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

    $groupesAnnee = null;
    $documents = null;

    if ($this->rubriqueId && $this->recherche === '') {
        // Mode rubrique : tout charger, grouper par année décroissante.
        $tous = (clone $q)->orderByDesc('annee')->orderBy('titre')->get();
        $groupesAnnee = $tous->groupBy(fn ($d) => $d->annee ?? 'Non daté')
            ->sortKeysDesc()
            ->sortBy(fn ($grp, $cle) => $cle === 'Non daté' ? 1 : 0);
    } else {
        $documents = $q->latest('date_document')->latest('id')->paginate(12);
    }

    return view('livewire.bibliotheque.index', [
        'documents' => $documents,
        'groupesAnnee' => $groupesAnnee,
        'rubriques' => Rubrique::where('actif', true)->withCount('documents')->orderBy('ordre')->get(),
    ]);
}
```

Note : `sortKeysDesc()` trie les années ; le `sortBy` final rejette la clé `'Non daté'` en fin tout en préservant l'ordre des années (tri stable de Laravel).

- [ ] **Step 4: Modifier la vue**

Dans `resources/views/livewire/bibliotheque/index.blade.php`, encadrer le rendu de la grille existante par une condition : si `$groupesAnnee` n'est pas null, afficher les accordéons par année ; sinon, la grille paginée actuelle. Ajouter un bloc `<details open>` par groupe (charte DGB) :

```blade
@if (! is_null($groupesAnnee))
    <div class="biblio-annees">
        @forelse ($groupesAnnee as $annee => $docs)
            <details class="card" open style="margin-bottom:12px">
                <summary style="cursor:pointer;font-weight:700;color:var(--navy)">
                    {{ $annee }} <span style="color:var(--muted);font-weight:500">· {{ $docs->count() }}</span>
                </summary>
                <div style="margin-top:10px;display:grid;gap:8px">
                    @foreach ($docs as $doc)
                        <a href="{{ route('bibliotheque.document', $doc) }}"
                           style="display:block;padding:8px 10px;border:1px solid var(--line);border-radius:8px;text-decoration:none;color:var(--ink)">
                            {{ $doc->titre }}
                        </a>
                    @endforeach
                </div>
            </details>
        @empty
            <p style="color:var(--muted)">Aucun document dans cette rubrique.</p>
        @endforelse
    </div>
@else
    {{-- Grille paginée existante : conserver le bloc actuel tel quel --}}
@endif
```

Reprendre le bloc de grille existant à l'intérieur du `@else` (ne pas le supprimer). Vérifier le nom exact de la route de fiche : `route('bibliotheque.document', $doc)` (défini dans `routes/web.php`).

- [ ] **Step 5: Lancer, vérifier le succès**

Run: `php artisan test tests/Feature/Bibliotheque/BibliothequeGroupageTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Bibliotheque/Index.php resources/views/livewire/bibliotheque/index.blade.php tests/Feature/Bibliotheque/BibliothequeGroupageTest.php
git commit -m "feat(biblio): groupement par année dans la page rubrique"
```

---

## Task 5: Exécution réelle du reclassement (données)

**Files:** aucune (opération de données, hors tests).

**Interfaces:** Consumes la commande de Task 3.

- [ ] **Step 1: Sauvegarde DB**

```bash
mysqldump -u root dge_rh documents document_chunks rubriques > ~/dge-rh-platform/storage/backup-biblio-$(php -r 'echo date("Ymd-His");').sql
```

- [ ] **Step 2: Dry-run et revue**

Run: `php -d memory_limit=2048M artisan bibliotheque:reclasser`
Vérifier le rapport : répartition par rubrique cohérente, liste des hors-sujet à supprimer plausible (finance/admin uniquement).

- [ ] **Step 3: Appliquer (reclassement seul, sans suppression)**

Run: `php -d memory_limit=2048M artisan bibliotheque:reclasser --appliquer`

- [ ] **Step 4: Appliquer avec suppression (après validation de la liste)**

Run: `php -d memory_limit=2048M artisan bibliotheque:reclasser --appliquer --supprimer`

- [ ] **Step 5: Vérification visuelle**

Ouvrir la page Bibliothèque (dev server), cliquer une rubrique (ex. Décrets), vérifier le groupement par année et l'absence de documents finance.

---

## Self-Review

- **Spec coverage** : taxonomie (Task 2/3), colonne année (Task 1), extraction année (Task 2), commande dry-run/appliquer/supprimer (Task 3), source=texte préservé (Task 3), rangement forcé (Task 2 défaut), UI par année (Task 4), exécution réelle + sauvegarde (Task 5). ✔
- **Placeholders** : la vue Task 4 référence « conserver le bloc de grille existant » — c'est une instruction sur du code déjà présent, pas un placeholder de logique nouvelle. ✔
- **Type consistency** : `ClasseurDocuments::RUBRIQUES`, `rubrique()`, `typePourRubrique()`, `annee()` utilisés à l'identique en Task 3 ; `groupesAnnee` cohérent entre composant et vue. ✔
