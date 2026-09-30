# Assistant — Ancrage bibliothèque (RAG) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ancrer les réponses de l'assistant sur les **documents de la bibliothèque** (corpus 100 % sénégalais). À chaque question, on récupère les extraits pertinents des `document_chunks`, on les injecte dans le contexte + un prompt système fort (cas sénégalais, citer les textes, ne rien inventer), et on affiche les **sources** (liens vers les fiches) sous la réponse. Pour **tous les agents** (premium + gratuit). Approche **RAG pré-récupération** (pas d'outil agentique).

**Architecture:** Un service `RechercheBibliotheque` interroge `document_chunks` (MySQL FULLTEXT `MATCH … AGAINST`, fallback `LIKE` sous SQLite) joints aux `documents` actifs. `Assistant\Index::envoyer()` récupère les extraits pour la question, les injecte dans le prompt système de l'appel Claude (contexte transitoire), renforce le prompt d'ancrage Sénégal, et persiste les documents-sources dans un bloc `sources` du message assistant pour l'affichage des citations.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, MySQL (FULLTEXT) / SQLite in-memory (LIKE) en test.

## Global Constraints

- PHP 8.3 ; Livewire 3 ; Pest ; **aucun test n'appelle l'API réelle** (faux `AssistantIA` lié via `app()->instance`).
- Récupération : `document_chunks` joints à `documents` **actifs uniquement** ; MySQL `MATCH(contenu) AGAINST(? IN NATURAL LANGUAGE MODE)` ; **SQLite fallback** `LIKE` par mots (le FULLTEXT n'existe pas sous SQLite → les tests utilisent le fallback).
- Requête vide/trop courte → `[]` (pas d'injection, pas de sources).
- Contexte injecté seulement s'il y a des extraits (une question hors-sujet comme « bonjour » ne renvoie rien → aucun surcoût de jetons).
- Sources persistées comme bloc `['type'=>'sources','documents'=>[{id,titre,reference}]]` dans `messages.contenu` (JSON) du message **assistant** — pas de migration. `ConstructeurContenu` ne renvoie que le texte des blocs `text` (le bloc `sources` est ignoré côté API).
- Ancrage pour **tous** les niveaux (pas de gate premium).
- Préserver Phase 1/1.5 : quota, `#[Locked] conversationId` (IDOR), rollback IA, pièces jointes, crédits, streaming.

---

### Task 1: Service `RechercheBibliotheque`

**Files:**
- Create: `app/Support/Bibliotheque/RechercheBibliotheque.php`
- Test: `tests/Feature/Bibliotheque/RechercheBibliothequeTest.php`

**Interfaces:**
- Produces: `RechercheBibliotheque::rechercher(string $requete, int $limit = 6): array` — liste de `['contenu'=>string,'document_id'=>int,'titre'=>string,'reference'=>?string]` (extraits pertinents, documents actifs). `[]` si requête vide.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/RechercheBibliothequeTest.php` :

```php
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
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=RechercheBibliothequeTest` → FAIL.

- [ ] **Step 3: Implémenter**

`app/Support/Bibliotheque/RechercheBibliotheque.php` :

```php
<?php

namespace App\Support\Bibliotheque;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

final class RechercheBibliotheque
{
    /** @return array<int,array{contenu:string,document_id:int,titre:string,reference:?string}> */
    public function rechercher(string $requete, int $limit = 6): array
    {
        $requete = trim($requete);
        if (mb_strlen($requete) < 3) {
            return [];
        }

        $base = DocumentChunk::query()
            ->join('documents', 'documents.id', '=', 'document_chunks.document_id')
            ->where('documents.actif', true)
            ->limit($limit)
            ->select([
                'document_chunks.contenu as contenu',
                'documents.id as document_id',
                'documents.titre as titre',
                'documents.reference as reference',
            ]);

        if (DB::connection()->getDriverName() === 'mysql') {
            $base->whereRaw('MATCH(document_chunks.contenu) AGAINST (? IN NATURAL LANGUAGE MODE)', [$requete])
                ->orderByRaw('MATCH(document_chunks.contenu) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$requete]);
        } else {
            // Fallback SQLite/autres : LIKE par mots significatifs.
            $mots = array_filter(preg_split('/\s+/', $requete) ?: [], fn ($m) => mb_strlen($m) >= 3);
            if ($mots === []) {
                return [];
            }
            $base->where(function ($q) use ($mots) {
                foreach ($mots as $mot) {
                    $q->orWhere('document_chunks.contenu', 'like', '%'.$mot.'%');
                }
            });
        }

        return $base->get()->map(fn ($r) => [
            'contenu' => (string) $r->contenu,
            'document_id' => (int) $r->document_id,
            'titre' => (string) $r->titre,
            'reference' => $r->reference !== null ? (string) $r->reference : null,
        ])->all();
    }
}
```

- [ ] **Step 4: Relancer (succès)**

Run: `php artisan test --filter=RechercheBibliothequeTest`
Expected: PASS. Puis full `php artisan test` — report totals.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Bibliotheque/RechercheBibliotheque.php tests/Feature/Bibliotheque/RechercheBibliothequeTest.php
git commit -m "feat(bibliotheque): service de recherche des chunks (FULLTEXT/LIKE)"
```

---

### Task 2: Ancrage dans le chat + citations

**Files:**
- Modify: `app/Livewire/Assistant/Index.php`, `resources/views/livewire/assistant/index.blade.php`
- Test: `tests/Feature/Assistant/AncrageTest.php`

**Interfaces:**
- Consumes: `RechercheBibliotheque`, `ConstructeurContenu`, `AssistantIA`.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Assistant/AncrageTest.php` :

```php
<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\ClaudeReponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Faux qui capture le prompt système reçu (pour vérifier l'injection d'extraits).
class FauxCapteur implements AssistantIA
{
    public static string $systemRecu = '';

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        self::$systemRecu = $system;

        return new ClaudeReponse('Réponse ancrée.', 500, 200);
    }
}

function agentAncrage(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);
}

function seedDocParrainage(): Document
{
    $d = Document::factory()->for(Rubrique::factory())->create(['titre' => 'Code électoral — Parrainage', 'reference' => 'Loi 2021-35', 'actif' => true]);
    $d->chunks()->create(['ordre' => 0, 'contenu' => "Le parrainage citoyen est requis pour toute candidature à l'élection présidentielle au Sénégal."]);

    return $d;
}

it('injecte les extraits de la bibliothèque dans le prompt système', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'Explique le parrainage des candidats')
        ->call('envoyer');

    expect(FauxCapteur::$systemRecu)->toContain('parrainage citoyen')
        ->and(FauxCapteur::$systemRecu)->toContain('Loi 2021-35')
        ->and(FauxCapteur::$systemRecu)->toContain('Sénégal');
});

it('persiste les sources sur le message assistant', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    $doc = seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'parrainage des candidats')
        ->call('envoyer');

    $conv = Conversation::firstOrFail();
    $assistant = $conv->messages()->where('role', 'assistant')->firstOrFail();
    $bloc = collect($assistant->contenu)->firstWhere('type', 'sources');
    expect($bloc)->not->toBeNull()
        ->and($bloc['documents'][0]['id'])->toBe($doc->id)
        ->and($bloc['documents'][0]['titre'])->toBe('Code électoral — Parrainage');
});

it('n’injecte pas de contexte ni de sources hors sujet', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'Bonjour')
        ->call('envoyer');

    $assistant = Conversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail();
    expect(collect($assistant->contenu)->firstWhere('type', 'sources'))->toBeNull();
    // le prompt d'ancrage de base reste, mais sans extraits
    expect(FauxCapteur::$systemRecu)->not->toContain('Extraits de la bibliothèque');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=AncrageTest` → FAIL.

- [ ] **Step 3: Composant — récupération + injection + sources**

In `app/Livewire/Assistant/Index.php` (read it first), inside `envoyer(...)`, AFTER the user message + attachments are persisted and BEFORE calling `$assistant->repondre(...)`:

```php
        // Ancrage : récupérer les extraits pertinents de la bibliothèque.
        $extraits = $texte !== '' ? app(\App\Support\Bibliotheque\RechercheBibliotheque::class)->rechercher($texte) : [];
        $system = $this->promptSysteme();
        $sources = [];
        if ($extraits !== []) {
            $bloc = "Extraits de la bibliothèque électorale de la DGE (Sénégal) — fonde ta réponse sur ces extraits et cite les documents (titre + référence) :\n\n";
            foreach ($extraits as $e) {
                $ref = $e['reference'] ? ' · '.$e['reference'] : '';
                $bloc .= "— [{$e['titre']}{$ref}]\n{$e['contenu']}\n\n";
            }
            $system .= "\n\n".$bloc;

            $sources = collect($extraits)
                ->unique('document_id')
                ->map(fn ($e) => ['id' => $e['document_id'], 'titre' => $e['titre'], 'reference' => $e['reference']])
                ->values()->all();
        }
```

Then pass `system: $system` (instead of `$this->promptSysteme()`) to `$assistant->repondre(...)`. After the response, when creating the assistant message, append the sources block if any:

```php
        $contenuAssistant = [['type' => 'text', 'text' => $rep->texte]];
        if ($sources !== []) {
            $contenuAssistant[] = ['type' => 'sources', 'documents' => $sources];
        }

        $conv->messages()->create([
            'role' => 'assistant',
            'contenu' => $contenuAssistant,
            'jetons_input' => $rep->jetonsInput,
            'jetons_output' => $rep->jetonsOutput,
            'credits' => $credits,
        ]);
```

Strengthen `promptSysteme()` for Senegal grounding — replace its body with:

```php
    private function promptSysteme(): string
    {
        return "Tu es l'assistant IA de la Direction Générale des Élections (DGE) du Sénégal. "
            ."Le cadre est exclusivement sénégalais : élections, droit et procédures électorales du Sénégal. "
            ."Réponds en français, de façon professionnelle et concise. "
            ."Quand des extraits de la bibliothèque te sont fournis, FONDE ta réponse dessus et CITE les documents (titre et référence). "
            ."N'invente jamais une loi, un décret ou une référence ; si l'information n'est pas dans les extraits fournis et que tu n'es pas certain pour le cas sénégalais, dis-le clairement et invite à consulter la bibliothèque.";
    }
```

> Ne pas casser le try/catch de rollback IA : la récupération d'extraits se fait AVANT l'appel ; en cas d'échec IA, le rollback existant supprime message/conversation comme avant.

- [ ] **Step 4: Vue — afficher les sources sous la réponse**

In `resources/views/livewire/assistant/index.blade.php`, in the message loop, after the assistant bubble text, render the sources block. Add a small style and, inside the `@foreach ($conversation->messages as $m)` assistant branch (after the `.bubble`):

```blade
@php($src = collect($m->contenu)->firstWhere('type', 'sources'))
@if ($m->role === 'assistant' && $src)
    <div class="ia-sources">
        <span class="lbl">Sources</span>
        @foreach ($src['documents'] as $d)
            <a href="{{ route('bibliotheque.document', $d['id']) }}" wire:navigate class="chip">📄 {{ $d['titre'] }}</a>
        @endforeach
    </div>
@endif
```

Add styles in the existing `<style>` block:

```css
.ia-sources{ display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:8px; }
.ia-sources .lbl{ font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted); }
.ia-sources .chip{ display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:var(--green-soft);border:1px solid #bcd6c4;color:var(--green-deep);font-size:12px;text-decoration:none;transition:transform .3s; }
.ia-sources .chip:hover{ transform:translateY(-1px); }
```

- [ ] **Step 5: Relancer + suite complète**

Run: `php artisan test --filter=AncrageTest` puis `php artisan test`
Expected: PASS (toute la suite verte)

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Assistant/Index.php resources/views/livewire/assistant/index.blade.php tests/Feature/Assistant/AncrageTest.php
git commit -m "feat(assistant): ancrage bibliothèque (RAG) + citations + prompt Sénégal"
```

---

## Après ce plan

- Le FULLTEXT MySQL sert en dev/prod ; les tests couvrent le fallback LIKE (SQLite). Vérifier en prod que l'index FULLTEXT existe (créé par la migration `document_chunks` sous MySQL).
- Amélioration possible : reformulation de requête, re-ranking, ou passage au tool-use agentique multi-hop.

## Self-Review (effectuée)

- **Couverture** : service recherche FULLTEXT/LIKE + actifs seuls (Task 1) ; injection contexte + prompt Sénégal renforcé + sources persistées + citations UI + tous agents (Task 2).
- **Placeholders** : aucun — tests + code fournis.
- **Cohérence** : `RechercheBibliotheque::rechercher(string,int):array` défini Task 1, utilisé Task 2 ; bloc `sources` dans `contenu` lu par la vue et ignoré par `ConstructeurContenu` (pas de `text`) ; `route('bibliotheque.document', id)` existe (Module A).
- **Préservation** : récupération avant l'appel → rollback IA inchangé ; quota/IDOR/streaming/pièces jointes intacts.
- **Point d'attention** : le chemin MySQL `MATCH…AGAINST` n'est pas testé (tests SQLite → LIKE) ; c'est la même approche déjà validée pour les chunks. Le prompt d'ancrage de base (Sénégal) est TOUJOURS envoyé ; les « Extraits… » ne sont ajoutés que s'il y a des résultats.
