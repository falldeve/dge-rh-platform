# Bibliothèque — Administration (Gestion) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ajouter l'interface d'administration de la bibliothèque (réservée archiviste + super-admin) : CRUD des rubriques avec **photo de couverture**, et CRUD des documents (3 sources : fichier / lien / texte) avec re-chunking automatique. Complète la commande d'import en masse.

**Architecture:** Deux composants Livewire sous `Bibliotheque\Gestion\` (Rubriques, Documents) réutilisant les modèles et services du Module A (`ExtracteurTexte`, `Chunker`). Ajout d'une colonne `image_path` sur `rubriques` (photo de couverture, disque public) ; la tuile de consultation l'affiche si présente, sinon le dégradé thématique.

**Tech Stack:** Laravel 12, Livewire 3 (`WithFileUploads`), Pest 3, MySQL (SQLite in-memory en test).

## Global Constraints

- PHP 8.3 ; Livewire 3 ; Pest ; SQLite in-memory en test ; **aucun test n'appelle `pdftotext`/l'API réelle** (lier `FauxExtracteur` via `app()->instance` quand l'extraction est exercée).
- Accès gestion : middleware `['auth','verified','password.change','role:archiviste']` — le super-admin (`admin`) passe via le bypass `EnsureRole`. Consultation (`/bibliotheque`) reste ouverte à tous.
- Uploads sur le disque **public** ; chemins relatifs ; `Document::fichierUrl()`/`Rubrique::coverUrl()` renvoient `/storage/...` (jamais `Storage::url()`/`asset()`).
- Re-chunking à l'enregistrement d'un document : source `texte` → chunk `contenu` ; source `fichier` avec nouveau fichier → stocker + extraire (via `ExtracteurTexte`), texte > 800 car → chunks sinon 0 ; source `lien` → 0 chunk. Toujours **supprimer les anciens chunks** avant de recréer (`chunks()->delete()`).
- Suppression d'un document → supprimer aussi le fichier physique s'il existe.
- Charte DGB, layout `components.layouts.rh`, style cohérent avec la refonte (classes `.card/.btn/.field/.badge` + styles scopés).
- Réutiliser les services existants : `App\Support\Bibliotheque\{ExtracteurTexte, Chunker}` ; modèles `Rubrique/Document/DocumentChunk`.

---

### Task 1: Colonne photo de couverture + tuile de consultation

**Files:**
- Create: migration `..._add_image_path_to_rubriques_table.php`
- Modify: `app/Models/Rubrique.php` (fillable + `coverUrl()`)
- Modify: `resources/views/livewire/bibliotheque/index.blade.php` (tuile utilise `image_path`)
- Test: `tests/Feature/Bibliotheque/RubriqueImageTest.php`

**Interfaces:**
- Produces: `Rubrique::coverUrl(): ?string` = `image_path ? '/storage/'.ltrim(image_path,'/') : null` ; colonne `rubriques.image_path` (nullable). La tuile Index affiche `coverUrl()` en fond si présent, sinon le dégradé `cov-*`.

- [ ] **Step 1: Test (échec attendu)**

Create `tests/Feature/Bibliotheque/RubriqueImageTest.php` :

```php
<?php

use App\Models\Rubrique;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expose coverUrl relatif quand image_path est renseigné', function () {
    $r = Rubrique::factory()->create(['image_path' => 'bibliotheque/rubriques/lois.jpg']);
    expect($r->coverUrl())->toBe('/storage/bibliotheque/rubriques/lois.jpg');
});

it('coverUrl null sans image', function () {
    $r = Rubrique::factory()->create(['image_path' => null]);
    expect($r->coverUrl())->toBeNull();
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=RubriqueImageTest` → FAIL.

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubriques', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('icone');
        });
    }

    public function down(): void
    {
        Schema::table('rubriques', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
```

- [ ] **Step 4: Modèle**

Add `'image_path'` to `Rubrique::$fillable`, and:

```php
public function coverUrl(): ?string
{
    return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
}
```

- [ ] **Step 5: Tuile de consultation utilise la photo**

In `resources/views/livewire/bibliotheque/index.blade.php`, replace the `@php $img = ...` line inside the `@foreach ($rubriques as $r)` with:

```blade
@php $img = $r->coverUrl(); @endphp
```

(La colonne `image_path` remplace la convention de fichier ; le reste du markup `tile-cover` est inchangé — `$img` présent → `style="background-image:url('{{ $img }}')"`, sinon la classe `cov-*`.)

- [ ] **Step 6: Migrer + relancer (succès)**

Run: `php artisan migrate && php artisan test --filter=RubriqueImageTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Rubrique.php resources/views/livewire/bibliotheque/index.blade.php tests/Feature/Bibliotheque/RubriqueImageTest.php
git commit -m "feat(bibliotheque): colonne image_path (photo couverture) + tuile"
```

---

### Task 2: Gestion des rubriques (`Bibliotheque\Gestion\Rubriques`)

**Files:**
- Create: `app/Livewire/Bibliotheque/Gestion/Rubriques.php`
- Create: `resources/views/livewire/bibliotheque/gestion/rubriques.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Bibliotheque/GestionRubriquesTest.php`

**Interfaces:**
- Consumes: `Rubrique`, `Str::slug`, `WithFileUploads`.
- Produces: route `bibliotheque.gerer.rubriques` (`/bibliotheque/gerer/rubriques`).

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/GestionRubriquesTest.php` :

```php
<?php

use App\Livewire\Bibliotheque\Gestion\Rubriques;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function archiviste(): User
{
    return User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('interdit la gestion à un agent (403)', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque/gerer/rubriques')->assertForbidden();
});

it('l’archiviste crée une rubrique avec slug auto', function () {
    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Jurisprudence')
        ->set('description', 'Décisions')
        ->call('enregistrer');

    $r = Rubrique::where('nom', 'Jurisprudence')->firstOrFail();
    expect($r->slug)->toBe('jurisprudence')->and($r->actif)->toBeTrue();
});

it('téléverse une photo de couverture', function () {
    Storage::fake('public');
    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Archives')
        ->set('photo', UploadedFile::fake()->image('cover.jpg'))
        ->call('enregistrer');

    $r = Rubrique::where('nom', 'Archives')->firstOrFail();
    expect($r->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($r->image_path);
});

it('active/désactive et supprime une rubrique', function () {
    $r = Rubrique::factory()->create(['actif' => true]);

    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->call('basculer', $r->id)
        ->call('supprimer', $r->id);

    expect(Rubrique::find($r->id))->toBeNull();
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=GestionRubriquesTest` → FAIL.

- [ ] **Step 3: Composant**

`app/Livewire/Bibliotheque/Gestion/Rubriques.php` :

```php
<?php

namespace App\Livewire\Bibliotheque\Gestion;

use App\Models\Rubrique;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class Rubriques extends Component
{
    use WithFileUploads;

    public ?int $editId = null;

    public string $nom = '';

    public string $description = '';

    public string $icone = '';

    public int $ordre = 0;

    public bool $actif = true;

    public $photo = null;

    protected function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icone' => 'nullable|string|max:8',
            'ordre' => 'integer|min:0',
            'photo' => 'nullable|image|max:4096',
        ];
    }

    public function editer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        $this->editId = $r->id;
        $this->nom = $r->nom;
        $this->description = (string) $r->description;
        $this->icone = (string) $r->icone;
        $this->ordre = (int) $r->ordre;
        $this->actif = (bool) $r->actif;
        $this->photo = null;
    }

    public function annuler(): void
    {
        $this->reset(['editId', 'nom', 'description', 'icone', 'ordre', 'actif', 'photo']);
    }

    public function enregistrer(): void
    {
        $data = $this->validate();

        $rubrique = Rubrique::updateOrCreate(
            ['id' => $this->editId],
            [
                'nom' => $this->nom,
                'slug' => Str::slug($this->nom),
                'description' => $this->description ?: null,
                'icone' => $this->icone ?: null,
                'ordre' => $this->ordre,
                'actif' => $this->actif,
            ],
        );

        if ($this->photo) {
            $chemin = $this->photo->store('bibliotheque/rubriques', 'public');
            $rubrique->update(['image_path' => $chemin]);
        }

        $this->annuler();
    }

    public function basculer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        $r->update(['actif' => ! $r->actif]);
    }

    public function supprimer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        if ($r->image_path) {
            Storage::disk('public')->delete($r->image_path);
        }
        $r->delete(); // documents.rubrique_id → nullOnDelete
    }

    public function render()
    {
        return view('livewire.bibliotheque.gestion.rubriques', [
            'rubriques' => Rubrique::withCount('documents')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue**

`resources/views/livewire/bibliotheque/gestion/rubriques.blade.php` :

```blade
<div class="card" style="max-width:960px;margin:0 auto;">
    <h1>Rubriques — gestion</h1>

    <form wire:submit="enregistrer" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:16px;background:var(--surface-2);border-radius:14px;">
        <label>Nom <input class="field" wire:model="nom"></label>
        <label>Icône (emoji) <input class="field" wire:model="icone" maxlength="8"></label>
        <label style="grid-column:1/-1">Description <input class="field" wire:model="description"></label>
        <label>Ordre <input class="field" type="number" wire:model="ordre"></label>
        <label>Photo de couverture <input class="field" type="file" wire:model="photo" accept="image/*"></label>
        <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" wire:model="actif"> Active</label>
        <div style="grid-column:1/-1;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">{{ $editId ? 'Mettre à jour' : 'Créer' }}</button>
            @if ($editId)<button class="btn btn-ghost" type="button" wire:click="annuler">Annuler</button>@endif
        </div>
        @error('nom') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @error('photo') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
    </form>

    <table style="width:100%;border-collapse:collapse">
        <thead><tr><th>Rubrique</th><th>Docs</th><th>Ordre</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @foreach ($rubriques as $r)
            <tr style="border-top:1px solid var(--line)">
                <td>{{ $r->icone }} {{ $r->nom }}</td>
                <td>{{ $r->documents_count }}</td>
                <td>{{ $r->ordre }}</td>
                <td><button class="btn btn-ghost" wire:click="basculer({{ $r->id }})">{{ $r->actif ? 'Oui' : 'Non' }}</button></td>
                <td style="display:flex;gap:6px">
                    <button class="btn btn-ghost" wire:click="editer({{ $r->id }})">Éditer</button>
                    <button class="btn btn-ghost" wire:click="supprimer({{ $r->id }})" wire:confirm="Supprimer cette rubrique ? Les documents liés seront détachés.">Supprimer</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
```

- [ ] **Step 5: Route**

In `routes/web.php`, add `use App\Livewire\Bibliotheque\Gestion\Rubriques as GestionRubriques;` and a group:

```php
Route::middleware(['auth', 'verified', 'password.change', 'role:archiviste'])->group(function () {
    Route::get('/bibliotheque/gerer/rubriques', GestionRubriques::class)->name('bibliotheque.gerer.rubriques');
});
```

- [ ] **Step 6: Relancer (succès)**

Run: `php artisan test --filter=GestionRubriquesTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Bibliotheque/Gestion/Rubriques.php resources/views/livewire/bibliotheque/gestion/rubriques.blade.php routes/web.php tests/Feature/Bibliotheque/GestionRubriquesTest.php
git commit -m "feat(bibliotheque): gestion des rubriques (CRUD + photo couverture)"
```

---

### Task 3: Gestion des documents (`Bibliotheque\Gestion\Documents`)

**Files:**
- Create: `app/Livewire/Bibliotheque/Gestion/Documents.php`
- Create: `resources/views/livewire/bibliotheque/gestion/documents.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Bibliotheque/GestionDocumentsTest.php`

**Interfaces:**
- Consumes: `Document`, `Rubrique`, `App\Support\Bibliotheque\{ExtracteurTexte, Chunker, FauxExtracteur}`, `WithFileUploads`.
- Produces: route `bibliotheque.gerer.documents` (`/bibliotheque/gerer/documents`).

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/GestionDocumentsTest.php` :

```php
<?php

use App\Livewire\Bibliotheque\Gestion\Documents;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\FauxExtracteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function archivisteDoc(): User
{
    return User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('interdit la gestion documents à un agent (403)', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque/gerer/documents')->assertForbidden();
});

it('crée un document texte et le chunk', function () {
    $r = Rubrique::factory()->create();
    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Note interne')
        ->set('rubrique_id', $r->id)
        ->set('type', 'guide')
        ->set('source', 'texte')
        ->set('contenu', str_repeat('article ', 300))
        ->call('enregistrer');

    $d = Document::where('titre', 'Note interne')->firstOrFail();
    expect($d->source)->toBe('texte')
        ->and($d->chunks()->count())->toBeGreaterThanOrEqual(1);
});

it('crée un document fichier PDF, le stocke et le chunk via extracteur', function () {
    Storage::fake('public');
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: str_repeat('texte extrait ', 200)));
    $r = Rubrique::factory()->create();

    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Décret X')
        ->set('rubrique_id', $r->id)
        ->set('type', 'decret')
        ->set('source', 'fichier')
        ->set('fichier', UploadedFile::fake()->create('decret.pdf', 100, 'application/pdf'))
        ->call('enregistrer');

    $d = Document::where('titre', 'Décret X')->firstOrFail();
    expect($d->source)->toBe('fichier')
        ->and($d->fichier_path)->not->toBeNull()
        ->and($d->chunks()->count())->toBeGreaterThanOrEqual(1);
    Storage::disk('public')->assertExists($d->fichier_path);
});

it('exige une url pour la source lien', function () {
    $r = Rubrique::factory()->create();
    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Lien externe')
        ->set('rubrique_id', $r->id)
        ->set('source', 'lien')
        ->set('url', '')
        ->call('enregistrer')
        ->assertHasErrors('url');
});

it('supprime un document et son fichier', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bibliotheque/x.pdf', 'data');
    $d = Document::factory()->create(['source' => 'fichier', 'fichier_path' => 'bibliotheque/x.pdf']);

    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->call('supprimer', $d->id);

    expect(Document::find($d->id))->toBeNull();
    Storage::disk('public')->assertMissing('bibliotheque/x.pdf');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=GestionDocumentsTest` → FAIL.

- [ ] **Step 3: Composant**

`app/Livewire/Bibliotheque/Gestion/Documents.php` :

```php
<?php

namespace App\Livewire\Bibliotheque\Gestion;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\Chunker;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Documents extends Component
{
    use WithFileUploads, WithPagination;

    public ?int $editId = null;

    public string $titre = '';

    public ?int $rubrique_id = null;

    public string $type = 'autre';

    public string $reference = '';

    public ?string $date_document = null;

    public string $resume = '';

    public string $mots_cles = '';

    public string $source = 'texte';

    public string $contenu = '';

    public string $url = '';

    public $fichier = null;

    public string $recherche = '';

    protected function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'rubrique_id' => 'nullable|exists:rubriques,id',
            'type' => ['required', Rule::in(['loi', 'decret', 'reglement', 'rapport', 'circulaire', 'guide', 'archive', 'ordonnance', 'autre'])],
            'reference' => 'nullable|string|max:255',
            'date_document' => 'nullable|date',
            'resume' => 'nullable|string|max:1000',
            'mots_cles' => 'nullable|string|max:255',
            'source' => ['required', Rule::in(['fichier', 'lien', 'texte'])],
            'contenu' => 'nullable|string',
            'url' => 'nullable|url',
            'fichier' => 'nullable|file|mimes:pdf,docx|max:20480',
        ];
    }

    public function editer(int $id): void
    {
        $d = Document::findOrFail($id);
        $this->editId = $d->id;
        $this->titre = $d->titre;
        $this->rubrique_id = $d->rubrique_id;
        $this->type = $d->type;
        $this->reference = (string) $d->reference;
        $this->date_document = $d->date_document?->format('Y-m-d');
        $this->resume = (string) $d->resume;
        $this->mots_cles = (string) $d->mots_cles;
        $this->source = $d->source;
        $this->contenu = (string) $d->contenu;
        $this->url = (string) $d->url;
        $this->fichier = null;
    }

    public function annuler(): void
    {
        $this->reset(['editId', 'titre', 'rubrique_id', 'type', 'reference', 'date_document', 'resume', 'mots_cles', 'source', 'contenu', 'url', 'fichier']);
        $this->type = 'autre';
        $this->source = 'texte';
    }

    public function enregistrer(ExtracteurTexte $extracteur, Chunker $chunker): void
    {
        $this->validate();

        if ($this->source === 'lien' && trim($this->url) === '') {
            $this->addError('url', 'Une URL est requise pour la source lien.');

            return;
        }
        if ($this->source === 'fichier' && ! $this->fichier && ! $this->editId) {
            $this->addError('fichier', 'Un fichier est requis.');

            return;
        }

        $doc = Document::findOrNew($this->editId);
        $doc->fill([
            'titre' => $this->titre,
            'rubrique_id' => $this->rubrique_id,
            'type' => $this->type,
            'reference' => $this->reference ?: null,
            'date_document' => $this->date_document ?: null,
            'resume' => $this->resume ?: null,
            'mots_cles' => $this->mots_cles ?: null,
            'source' => $this->source,
            'publie_par' => auth()->id(),
            'actif' => $doc->actif ?? true,
        ]);

        $texteAChunker = null;

        if ($this->source === 'texte') {
            $doc->contenu = $this->contenu;
            $doc->url = null;
            $doc->fichier_path = null;
            $texteAChunker = $this->contenu;
        } elseif ($this->source === 'lien') {
            $doc->url = $this->url;
            $doc->contenu = null;
            $doc->fichier_path = null;
        } elseif ($this->source === 'fichier') {
            $doc->contenu = null;
            $doc->url = null;
            if ($this->fichier) {
                $doc->fichier_path = $this->fichier->store('bibliotheque', 'public');
            }
        }

        $doc->save();

        // Re-chunking
        if ($this->source === 'fichier' && $this->fichier) {
            $texte = $extracteur->extraire(Storage::disk('public')->path($doc->fichier_path));
            $texteAChunker = mb_strlen($texte) > 800 ? $texte : null;
        }

        if ($this->source !== 'lien') {
            $doc->chunks()->delete();
            if ($texteAChunker) {
                foreach ($chunker->decouper($texteAChunker) as $i => $morceau) {
                    $doc->chunks()->create(['ordre' => $i, 'contenu' => $morceau]);
                }
            }
        } else {
            $doc->chunks()->delete();
        }

        $this->annuler();
    }

    public function basculer(int $id): void
    {
        $d = Document::findOrFail($id);
        $d->update(['actif' => ! $d->actif]);
    }

    public function supprimer(int $id): void
    {
        $d = Document::findOrFail($id);
        if ($d->fichier_path) {
            Storage::disk('public')->delete($d->fichier_path);
        }
        $d->delete();
    }

    public function render()
    {
        $q = Document::query()->with('rubrique')->latest('id');
        if ($this->recherche !== '') {
            $q->where('titre', 'like', '%'.$this->recherche.'%');
        }

        return view('livewire.bibliotheque.gestion.documents', [
            'documents' => $q->paginate(15),
            'rubriques' => Rubrique::orderBy('ordre')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue**

`resources/views/livewire/bibliotheque/gestion/documents.blade.php` :

```blade
<div class="card" style="max-width:1040px;margin:0 auto;">
    <h1>Documents — gestion</h1>

    <form wire:submit="enregistrer" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:16px;background:var(--surface-2);border-radius:14px;">
        <label style="grid-column:1/-1">Titre <input class="field" wire:model="titre"></label>
        <label>Rubrique
            <select class="field" wire:model="rubrique_id">
                <option value="">—</option>
                @foreach ($rubriques as $r)<option value="{{ $r->id }}">{{ $r->nom }}</option>@endforeach
            </select>
        </label>
        <label>Type
            <select class="field" wire:model="type">
                @foreach (['loi','decret','reglement','rapport','circulaire','guide','archive','ordonnance','autre'] as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label>Référence <input class="field" wire:model="reference"></label>
        <label>Date <input class="field" type="date" wire:model="date_document"></label>
        <label style="grid-column:1/-1">Résumé <input class="field" wire:model="resume"></label>
        <label style="grid-column:1/-1">Mots-clés <input class="field" wire:model="mots_cles" placeholder="séparés par des virgules"></label>

        <label style="grid-column:1/-1">Source
            <select class="field" wire:model.live="source">
                <option value="texte">Texte (markdown)</option>
                <option value="fichier">Fichier (PDF/DOCX)</option>
                <option value="lien">Lien externe</option>
            </select>
        </label>

        @if ($source === 'texte')
            <label style="grid-column:1/-1">Contenu (markdown)
                <textarea class="field" wire:model="contenu" rows="8"></textarea>
            </label>
        @elseif ($source === 'fichier')
            <label style="grid-column:1/-1">Fichier <input class="field" type="file" wire:model="fichier" accept=".pdf,.docx"></label>
            @error('fichier') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @elseif ($source === 'lien')
            <label style="grid-column:1/-1">URL <input class="field" wire:model="url" placeholder="https://…"></label>
            @error('url') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @endif

        <div style="grid-column:1/-1;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">{{ $editId ? 'Mettre à jour' : 'Créer' }}</button>
            @if ($editId)<button class="btn btn-ghost" type="button" wire:click="annuler">Annuler</button>@endif
        </div>
        @error('titre') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
    </form>

    <input class="field" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un document…" style="margin-bottom:12px">

    <table style="width:100%;border-collapse:collapse">
        <thead><tr><th>Titre</th><th>Rubrique</th><th>Type</th><th>Source</th><th>Actif</th><th></th></tr></thead>
        <tbody>
        @foreach ($documents as $d)
            <tr style="border-top:1px solid var(--line)">
                <td>{{ $d->titre }}</td>
                <td>{{ $d->rubrique?->nom }}</td>
                <td>{{ $d->type }}</td>
                <td>{{ $d->source }}</td>
                <td><button class="btn btn-ghost" wire:click="basculer({{ $d->id }})">{{ $d->actif ? 'Oui' : 'Non' }}</button></td>
                <td style="display:flex;gap:6px">
                    <button class="btn btn-ghost" wire:click="editer({{ $d->id }})">Éditer</button>
                    <button class="btn btn-ghost" wire:click="supprimer({{ $d->id }})" wire:confirm="Supprimer ce document ?">Supprimer</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:12px">{{ $documents->links() }}</div>
</div>
```

- [ ] **Step 5: Route**

In `routes/web.php`, add `use App\Livewire\Bibliotheque\Gestion\Documents as GestionDocuments;` and add to the `role:archiviste` group created in Task 2:

```php
Route::get('/bibliotheque/gerer/documents', GestionDocuments::class)->name('bibliotheque.gerer.documents');
```

- [ ] **Step 6: Relancer (succès)**

Run: `php artisan test --filter=GestionDocumentsTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Bibliotheque/Gestion/Documents.php resources/views/livewire/bibliotheque/gestion/documents.blade.php routes/web.php tests/Feature/Bibliotheque/GestionDocumentsTest.php
git commit -m "feat(bibliotheque): gestion des documents (CRUD 3 sources + re-chunk)"
```

---

### Task 4: Navigation + accès

**Files:**
- Modify: `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Bibliotheque/AccesGestionTest.php`

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Bibliotheque/AccesGestionTest.php` :

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('archiviste accède à la gestion', function () {
    $u = User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/rubriques')->assertOk();
    $this->actingAs($u)->get('/bibliotheque/gerer/documents')->assertOk();
});

it('super-admin accède à la gestion (bypass)', function () {
    $u = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/rubriques')->assertOk();
});

it('un agent est refusé (403)', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/bibliotheque/gerer/documents')->assertForbidden();
});

it('le lien de gestion apparaît pour l’archiviste, pas pour un agent', function () {
    $arch = User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($arch)->get('/bibliotheque')->assertSee('Gérer la bibliothèque');

    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque')->assertDontSee('Gérer la bibliothèque');
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=AccesGestionTest` → FAIL.

- [ ] **Step 3: Nav**

In `resources/views/components/layouts/rh.blade.php`, **READ the sidebar first** and match the real `rh-nav a` / `on` / `$ico` convention. Add, visible only to archiviste + admin (`$__u->isArchiviste() || $__u->isAdmin()`), a group "Gérer la bibliothèque" linking to `bibliotheque.gerer.rubriques` and `bibliotheque.gerer.documents` (two links, or one section header + two). Use existing icons (`livre`, or add `gear`).

```blade
@if ($__u->isArchiviste() || $__u->isAdmin())
    <a href="{{ route('bibliotheque.gerer.rubriques') }}" class="{{ request()->routeIs('bibliotheque.gerer.rubriques') ? 'on' : '' }}">{!! $ico['livre'] ?? '' !!} <span>Gérer la bibliothèque</span></a>
    <a href="{{ route('bibliotheque.gerer.documents') }}" class="{{ request()->routeIs('bibliotheque.gerer.documents') ? 'on' : '' }}">{!! $ico['livre'] ?? '' !!} <span>Gérer les documents</span></a>
@endif
```

- [ ] **Step 4: Relancer + suite complète**

Run: `php artisan test --filter=AccesGestionTest` puis `php artisan test`
Expected: PASS (toute la suite verte)

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/layouts/rh.blade.php tests/Feature/Bibliotheque/AccesGestionTest.php
git commit -m "feat(bibliotheque): navigation + accès gestion (archiviste + admin)"
```

---

## Self-Review (effectuée)

- **Couverture** : photo couverture (Task 1), CRUD rubriques + upload (Task 2), CRUD documents 3 sources + re-chunk + suppression fichier (Task 3), nav + accès role:archiviste/admin bypass (Task 4).
- **Placeholders** : aucun — tests + implémentations complets.
- **Cohérence types** : `ExtracteurTexte::extraire(string):string` + `Chunker::decouper(string,int):array` réutilisés (Module A) ; `Rubrique::coverUrl()` défini Task 1, utilisé Task 1 (tuile) ; routes `bibliotheque.gerer.rubriques`/`.documents` définies Tasks 2/3, liées Task 4.
- **Point d'attention** : le composant Documents résout `ExtracteurTexte`/`Chunker` par injection de méthode sur `enregistrer()` (comme `AssistantIA` dans l'assistant) — les tests lient `FauxExtracteur` via `app()->instance` avant `->call('enregistrer')`. Le test « texte » n'utilise pas l'extracteur (chunk direct du contenu), donc pas besoin du faux ; le test « fichier » lie le faux.
- **Re-chunk** : suppression systématique des anciens chunks avant recréation ; lien → 0 chunk. Cohérent avec l'import.
