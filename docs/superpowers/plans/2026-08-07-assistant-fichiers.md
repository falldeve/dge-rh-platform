# Assistant — Analyse de fichiers (Phase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Permettre aux agents de **joindre des documents** au chat de l'assistant (PDF, images, Word, Excel) pour que Claude les exploite : PDF/Word/Excel → texte extrait côté serveur, images → vision native. Ouvert à tous (premium + gratuit).

**Architecture:** Table `pieces_jointes` reliée aux `messages`. À l'envoi, les fichiers sont stockés sur le disque **privé** (`local`), le texte est extrait (réutilise `App\Support\Bibliotheque\ExtracteurTexte`, étendu pour Excel). Un `ConstructeurContenu` transforme un message + ses pièces en blocs de contenu API (texte / image base64 / texte extrait). L'interface `AssistantIA::repondre(array $messages, ...)` est inchangée : le `content` d'un message devient un tableau de blocs quand il porte des pièces (string sinon).

**Tech Stack:** Laravel 12, Livewire 3 (`WithFileUploads`), Pest 3, `phpoffice/phpspreadsheet` (Excel), `phpoffice/phpword` (déjà là), `pdftotext` (déjà là).

## Global Constraints

- PHP 8.3 ; Livewire 3 ; Pest ; SQLite in-memory en test ; **aucun test n'appelle l'API Claude réelle** (le faux `AssistantIA` est lié via `app()->instance`).
- Fichiers uploadés stockés sur le disque **`local`** (privé, non servi publiquement) — dossier `assistant/`. Jamais sur `public`.
- Formats acceptés : `pdf, jpg, jpeg, png, webp, gif, docx, xlsx` ; **max 10 Mo/fichier, max 5 fichiers/message**.
- Extraction : PDF (`pdftotext`), DOCX (PhpWord), XLSX (PhpSpreadsheet) via `ExtracteurTexte` ; images → PAS d'extraction (vision native). Scan/texte vide → note « contenu non extractible ».
- Contenu API par message : `string` si pas de pièce (rétro-compat) ; sinon tableau de blocs `[{type:text}, {type:image,source:base64}, {type:text (doc extrait)}]`.
- Quota/crédits inchangés (le check `assistantCreditsRestants()>0` existe déjà) ; les fichiers augmentent les jetons via le texte extrait / images.
- Réutiliser l'existant : `AssistantIA`/`FauxAssistant`/`ClaudeReponse`, `Conversation`/`Message`, `Str`.

---

### Task 1: Deps + extraction Excel + table `pieces_jointes`

**Files:**
- Modify: `composer.json` (via require), `app/Support/Bibliotheque/ExtracteurTextePoppler.php`
- Create: migration `..._create_pieces_jointes_table.php`, `app/Models/PieceJointe.php`
- Modify: `app/Models/Message.php` (relation)
- Test: `tests/Feature/Assistant/PiecesJointesTest.php`

**Interfaces:**
- Produces: `pieces_jointes(id, message_id → messages cascade, nom_original, chemin, type_mime, taille, texte_extrait longText nullable, timestamps)`. `PieceJointe` model (`estImage(): bool`). `Message::piecesJointes(): HasMany`. `ExtracteurTexte::extraire()` gère désormais `.xlsx`.

- [ ] **Step 1: Installer PhpSpreadsheet**

```bash
cd /Users/admin/dge-rh-platform && composer require "phpoffice/phpspreadsheet:^3.4"
```

- [ ] **Step 2: Test (échec attendu)**

Create `tests/Feature/Assistant/PiecesJointesTest.php` :

```php
<?php

use App\Models\Conversation;
use App\Models\PieceJointe;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

it('relie un message à ses pièces jointes', function () {
    $conv = Conversation::factory()->create();
    $msg = $conv->messages()->create(['role' => 'user', 'contenu' => [['type' => 'text', 'text' => 'Voir pièce']]]);
    $msg->piecesJointes()->create([
        'nom_original' => 'note.pdf', 'chemin' => 'assistant/note.pdf',
        'type_mime' => 'application/pdf', 'taille' => 1234, 'texte_extrait' => 'contenu',
    ]);

    expect($msg->piecesJointes)->toHaveCount(1)
        ->and($msg->piecesJointes->first()->estImage())->toBeFalse();
});

it('extrait le texte d’un fichier xlsx', function () {
    $ss = new Spreadsheet;
    $sheet = $ss->getActiveSheet();
    $sheet->setCellValue('A1', 'Région');
    $sheet->setCellValue('B1', 'Inscrits');
    $sheet->setCellValue('A2', 'Dakar');
    $sheet->setCellValue('B2', '1250000');
    $chemin = sys_get_temp_dir().'/test_'.uniqid().'.xlsx';
    (new Xlsx($ss))->save($chemin);

    $texte = app(ExtracteurTexte::class)->extraire($chemin);
    @unlink($chemin);

    expect($texte)->toContain('Dakar')->and($texte)->toContain('1250000');
});
```

- [ ] **Step 3: Lancer (échec)**

Run: `php artisan test --filter=PiecesJointesTest` → FAIL.

- [ ] **Step 4: Étendre l'extracteur pour XLSX**

In `app/Support/Bibliotheque/ExtracteurTextePoppler.php`, add (before the final `return '';`), and add `use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;` at top (alias to avoid clashing with PhpWord `IOFactory`) :

```php
        if ($ext === 'xlsx') {
            try {
                $classeur = SpreadsheetIOFactory::load($cheminAbsolu);
                $texte = '';
                foreach ($classeur->getAllSheets() as $feuille) {
                    foreach ($feuille->toArray() as $ligne) {
                        $cellules = array_filter($ligne, fn ($c) => $c !== null && $c !== '');
                        if ($cellules !== []) {
                            $texte .= implode(' | ', $cellules)."\n";
                        }
                    }
                }

                return trim($texte);
            } catch (\Throwable) {
                return '';
            }
        }
```

- [ ] **Step 5: Migration `pieces_jointes`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('type_mime');
            $table->unsignedBigInteger('taille');
            $table->longText('texte_extrait')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
```

- [ ] **Step 6: Modèle + relation**

`app/Models/PieceJointe.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    protected $fillable = ['message_id', 'nom_original', 'chemin', 'type_mime', 'taille', 'texte_extrait'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->type_mime, 'image/');
    }
}
```

Add to `app/Models/Message.php` :

```php
public function piecesJointes(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(PieceJointe::class, 'message_id');
}
```

- [ ] **Step 7: Migrer + relancer (succès)**

Run: `php artisan migrate && php artisan test --filter=PiecesJointesTest`
Expected: PASS. Puis full `php artisan test` — report totals.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock app/Support/Bibliotheque/ExtracteurTextePoppler.php database/migrations app/Models/PieceJointe.php app/Models/Message.php tests/Feature/Assistant/PiecesJointesTest.php
git commit -m "feat(assistant): table pièces jointes + extraction xlsx"
```

---

### Task 2: Constructeur de contenu API (message → blocs)

**Files:**
- Create: `app/Support/Assistant/ConstructeurContenu.php`
- Test: `tests/Feature/Assistant/ConstructeurContenuTest.php`

**Interfaces:**
- Produces: `ConstructeurContenu::pour(Message $m): string|array` — `string` si aucune pièce (texte concaténé) ; sinon tableau de blocs : bloc texte (si texte non vide), puis par pièce : image → `{type:image, source:{type:base64, media_type, data}}` (base64 lu depuis le disque `local`) ; document avec `texte_extrait` → `{type:text, text:"[Document joint : nom]\n{extrait}"}` ; sinon note « contenu non extractible ».

- [ ] **Step 1: Test (échec attendu)**

Create `tests/Feature/Assistant/ConstructeurContenuTest.php` :

```php
<?php

use App\Models\Conversation;
use App\Support\Assistant\ConstructeurContenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function messageAvec(array $contenu, array $pieces = [])
{
    $conv = Conversation::factory()->create();
    $m = $conv->messages()->create(['role' => 'user', 'contenu' => $contenu]);
    foreach ($pieces as $p) {
        $m->piecesJointes()->create($p);
    }

    return $m->fresh('piecesJointes');
}

it('renvoie une string quand pas de pièce', function () {
    $m = messageAvec([['type' => 'text', 'text' => 'Bonjour']]);
    expect(ConstructeurContenu::pour($m))->toBe('Bonjour');
});

it('construit des blocs avec texte extrait d’un document', function () {
    $m = messageAvec(
        [['type' => 'text', 'text' => 'Analyse ceci']],
        [['nom_original' => 'loi.pdf', 'chemin' => 'assistant/loi.pdf', 'type_mime' => 'application/pdf', 'taille' => 10, 'texte_extrait' => 'Article premier.']],
    );

    $blocs = ConstructeurContenu::pour($m);
    expect($blocs)->toBeArray()
        ->and($blocs[0])->toMatchArray(['type' => 'text', 'text' => 'Analyse ceci'])
        ->and($blocs[1]['type'])->toBe('text')
        ->and($blocs[1]['text'])->toContain('loi.pdf')
        ->and($blocs[1]['text'])->toContain('Article premier.');
});

it('construit un bloc image base64 pour une image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('assistant/photo.png', 'FAKEIMG');
    $m = messageAvec(
        [['type' => 'text', 'text' => 'Décris']],
        [['nom_original' => 'photo.png', 'chemin' => 'assistant/photo.png', 'type_mime' => 'image/png', 'taille' => 7, 'texte_extrait' => null]],
    );

    $blocs = ConstructeurContenu::pour($m);
    expect($blocs[1]['type'])->toBe('image')
        ->and($blocs[1]['source']['type'])->toBe('base64')
        ->and($blocs[1]['source']['media_type'])->toBe('image/png')
        ->and($blocs[1]['source']['data'])->toBe(base64_encode('FAKEIMG'));
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=ConstructeurContenuTest` → FAIL.

- [ ] **Step 3: Implémenter**

`app/Support/Assistant/ConstructeurContenu.php` :

```php
<?php

namespace App\Support\Assistant;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

final class ConstructeurContenu
{
    /** @return string|array<int,array<string,mixed>> */
    public static function pour(Message $m): string|array
    {
        $texte = collect($m->contenu)->pluck('text')->implode('');

        if ($m->piecesJointes->isEmpty()) {
            return $texte;
        }

        $blocs = [];
        if ($texte !== '') {
            $blocs[] = ['type' => 'text', 'text' => $texte];
        }

        foreach ($m->piecesJointes as $pj) {
            if ($pj->estImage() && Storage::disk('local')->exists($pj->chemin)) {
                $blocs[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $pj->type_mime,
                        'data' => base64_encode(Storage::disk('local')->get($pj->chemin)),
                    ],
                ];
            } elseif (filled($pj->texte_extrait)) {
                $blocs[] = ['type' => 'text', 'text' => "[Document joint : {$pj->nom_original}]\n".$pj->texte_extrait];
            } else {
                $blocs[] = ['type' => 'text', 'text' => "[Document joint : {$pj->nom_original} — contenu non extractible (scan ?)]"];
            }
        }

        return $blocs;
    }
}
```

- [ ] **Step 4: Relancer (succès)**

Run: `php artisan test --filter=ConstructeurContenuTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/Assistant/ConstructeurContenu.php tests/Feature/Assistant/ConstructeurContenuTest.php
git commit -m "feat(assistant): constructeur de contenu API (texte + image + doc extrait)"
```

---

### Task 3: Upload dans le chat + intégration + vue

**Files:**
- Modify: `app/Livewire/Assistant/Index.php`, `resources/views/livewire/assistant/index.blade.php`
- Test: `tests/Feature/Assistant/ChatFichiersTest.php`

**Interfaces:**
- Consumes: `ExtracteurTexte`, `ConstructeurContenu`, `PieceJointe`, `AssistantIA`, `WithFileUploads`.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Assistant/ChatFichiersTest.php` :

```php
<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\FauxAssistant;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\FauxExtracteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentFichier(array $attrs = []): User
{
    return User::factory()->create(array_merge(['email_verified_at' => now(), 'must_change_password' => false], $attrs));
}

it('joint un PDF, l’extrait et l’enregistre comme pièce jointe', function () {
    Storage::fake('local');
    config()->set('assistant.jetons_par_credit', 1000);
    app()->instance(AssistantIA::class, new FauxAssistant('Voici l’analyse.', 1500, 500));
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: 'Texte du PDF extrait.'));
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Résume ce document')
        ->set('fichiers', [UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf')])
        ->call('envoyer');

    $conv = Conversation::where('user_id', $user->id)->firstOrFail();
    $userMsg = $conv->messages()->where('role', 'user')->firstOrFail();
    expect($userMsg->piecesJointes)->toHaveCount(1)
        ->and($userMsg->piecesJointes->first()->texte_extrait)->toBe('Texte du PDF extrait.')
        ->and($conv->messages()->where('role', 'assistant')->exists())->toBeTrue();
    Storage::disk('local')->assertExists($userMsg->piecesJointes->first()->chemin);
});

it('joint une image sans extraction', function () {
    Storage::fake('local');
    app()->instance(AssistantIA::class, new FauxAssistant('Image vue.', 900, 200));
    $user = agentFichier(['assistant_ia_actif' => false]); // gratuit peut aussi joindre

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Décris cette image')
        ->set('fichiers', [UploadedFile::fake()->image('photo.png')])
        ->call('envoyer');

    $pj = Conversation::where('user_id', $user->id)->firstOrFail()
        ->messages()->where('role', 'user')->firstOrFail()->piecesJointes->first();
    expect($pj->estImage())->toBeTrue()->and($pj->texte_extrait)->toBeNull();
});

it('refuse un type de fichier non autorisé', function () {
    Storage::fake('local');
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'test')
        ->set('fichiers', [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])
        ->call('envoyer')
        ->assertHasErrors('fichiers.0');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=ChatFichiersTest` → FAIL.

- [ ] **Step 3: Intégrer dans le composant**

In `app/Livewire/Assistant/Index.php` : add `use Livewire\WithFileUploads;`, `use App\Support\Assistant\ConstructeurContenu;`, `use App\Support\Bibliotheque\ExtracteurTexte;`, `use Illuminate\Support\Facades\Storage;` ; add the trait `use WithFileUploads;` on the class ; add property:

```php
/** @var array<int,\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
public array $fichiers = [];
```

Change the `envoyer()` signature to also inject the extractor, and after persisting the user message, store the files + build content. Replace the `envoyer(AssistantIA $assistant)` method body's message-creation + history-building portion so it:

```php
public function envoyer(AssistantIA $assistant, ExtracteurTexte $extracteur): void
{
    $this->erreur = '';
    $texte = trim($this->saisie);
    if ($texte === '' && $this->fichiers === []) {
        return;
    }

    $this->validate([
        'fichiers' => 'array|max:5',
        'fichiers.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,gif,docx,xlsx|max:10240',
    ]);

    $user = auth()->user();
    if ($user->assistantCreditsRestants() <= 0) {
        $this->erreur = 'Vos crédits sont épuisés pour ce mois. Demandez un rechargement à l’administrateur.';

        return;
    }

    $conv = $this->conversationId
        ? $user->conversations()->findOrFail($this->conversationId)
        : $user->conversations()->create([
            'titre' => \Illuminate\Support\Str::limit($texte !== '' ? $texte : 'Document', 60),
            'modele' => $user->assistantModele(),
            'niveau' => $user->assistantEstPremium() ? 'premium' : 'gratuit',
        ]);
    $this->conversationId = $conv->id;

    $message = $conv->messages()->create([
        'role' => 'user',
        'contenu' => [['type' => 'text', 'text' => $texte]],
    ]);

    foreach ($this->fichiers as $f) {
        $chemin = $f->store('assistant', 'local');
        $mime = $f->getMimeType();
        $extrait = str_starts_with((string) $mime, 'image/')
            ? null
            : ($extracteur->extraire(Storage::disk('local')->path($chemin)) ?: null);
        $message->piecesJointes()->create([
            'nom_original' => $f->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $mime,
            'taille' => $f->getSize(),
            'texte_extrait' => $extrait,
        ]);
    }

    $this->saisie = '';
    $this->fichiers = [];

    $messages = $conv->messages()->with('piecesJointes')->get()->map(fn ($m) => [
        'role' => $m->role,
        'content' => ConstructeurContenu::pour($m),
    ])->all();

    $rep = $assistant->repondre(
        messages: $messages,
        modele: $conv->modele,
        system: $this->promptSysteme(),
        onChunk: fn (string $d) => $this->stream(to: 'reponse-en-cours', content: $d),
    );

    $credits = (int) ceil(($rep->jetonsInput + $rep->jetonsOutput) / (int) config('assistant.jetons_par_credit'));

    $conv->messages()->create([
        'role' => 'assistant',
        'contenu' => [['type' => 'text', 'text' => $rep->texte]],
        'jetons_input' => $rep->jetonsInput,
        'jetons_output' => $rep->jetonsOutput,
        'credits' => $credits,
    ]);

    $user->enregistrerConsommation($rep->jetonsInput, $rep->jetonsOutput, $credits);
}
```

> Garder `promptSysteme()` et `render()` tels quels, mais `render()` charge désormais les pièces : dans la requête de la conversation courante, eager-load `messages.piecesJointes` (`Conversation::with('messages.piecesJointes')->find(...)`).

- [ ] **Step 4: Vue — bouton joindre + pièces dans les bulles**

In `resources/views/livewire/assistant/index.blade.php`, inside `.ia-inputwrap` (before the textarea or beside the send button), add an attach control, and show selected files + render attachments in user bubbles. Add to the composer:

```blade
<label class="ia-joindre" title="Joindre un document">
    <input type="file" wire:model="fichiers" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.docx,.xlsx" style="display:none">
    <span>📎</span>
</label>
```

Above the textarea row, show pending files:

```blade
@if ($fichiers)
    <div class="ia-pending">
        @foreach ($fichiers as $i => $f)
            <span class="chip">{{ $f->getClientOriginalName() }}</span>
        @endforeach
    </div>
@endif
@error('fichiers.*') <div class="ia-alert">{{ $message }}</div> @enderror
```

In the message loop, under the bubble, render attachments:

```blade
@if ($m->piecesJointes->isNotEmpty())
    <div class="ia-pj">
        @foreach ($m->piecesJointes as $pj)
            <span class="chip">📄 {{ $pj->nom_original }}</span>
        @endforeach
    </div>
@endif
```

Add minimal styles in the existing `<style>` block:

```css
.ia-joindre{ flex:none; width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;background:var(--surface);border:1px solid var(--line);font-size:18px;transition:border-color .3s; }
.ia-joindre:hover{ border-color:var(--green); }
.ia-pending{ display:flex;flex-wrap:wrap;gap:6px;padding:0 8px 8px; }
.ia-pj{ display:flex;flex-wrap:wrap;gap:6px;margin-top:6px; }
.chip{ display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:var(--surface-2);border:1px solid var(--line);font-size:12px;color:var(--muted); }
```

(Placer le `<label class="ia-joindre">` dans `.ia-inputwrap` à gauche du `<textarea>` ; `.ia-inputwrap` est en `display:flex; align-items:flex-end`.)

- [ ] **Step 5: Relancer (succès)**

Run: `php artisan test --filter=ChatFichiersTest` puis full `php artisan test`
Expected: PASS (toute la suite verte)

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Assistant/Index.php resources/views/livewire/assistant/index.blade.php tests/Feature/Assistant/ChatFichiersTest.php
git commit -m "feat(assistant): téléversement de documents dans le chat (PDF/image/Word/Excel)"
```

---

## Après ce plan

- Coût : le texte extrait (et les images base64) sont renvoyés à chaque tour dans l'historique — pour de très gros documents, envisager plus tard un résumé/troncature ou la Files API.
- Recherche web + ancrage bibliothèque (`rechercher_bibliotheque`) = suite de l'Assistant Phase 2.

## Self-Review (effectuée)

- **Couverture** : formats pdf/image/docx/xlsx (Task 1/3), extraction xlsx (Task 1), stockage privé (Task 3), blocs contenu texte/image/doc (Task 2), upload UI + intégration + tous agents (Task 3), quota inchangé.
- **Placeholders** : aucun — tests + code complets.
- **Cohérence** : `ConstructeurContenu::pour(Message):string|array` défini Task 2, utilisé Task 3 ; `ExtracteurTexte::extraire()` étendu Task 1, utilisé Task 3 ; `Message::piecesJointes()` Task 1, utilisé Tasks 2/3 ; interface `AssistantIA::repondre` inchangée (content devient array de blocs).
- **Sécurité** : disque privé `local` (pas d'URL publique) ; mimes + tailles validés ; images en vision native, autres en texte extrait.
- **Rétro-compat** : messages sans pièce → content `string` (tests Phase 1 restent verts).
