# Courrier — Plan 2 : Enregistrement & Ventilation DG Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à l'agent du bureau courrier d'enregistrer un courrier arrivée (avec scan), de saisir la **fiche de ventilation** du DG (destinataires + mentions), et de consulter/rechercher le registre.

**Architecture:** Deux tables : `courriers` (le courrier + scan) et `imputations` (une fiche de ventilation, à un niveau `dg` ou `direction`), reliées aux entités destinataires par un pivot `entite_imputation`. Les mentions « Soit transmis » sont une constante. Écrans Livewire réservés au rôle `courrier`, dans la console à sidebar.

**Tech Stack:** Laravel 12, Livewire 3 (`WithFileUploads`, `WithPagination`), Pest, Tailwind. S'appuie sur Courrier P1 (modèle `Entite`, rôle `courrier`, helper `isCourrier()`), + `User`, disque `public` (scans), accesseur d'URL relative (comme les photos agents).

**Prérequis d'exécution:** créer la branche `feat/courrier-p2` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/courrier-p2
```
Env local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-13-module-courrier-design.md` §5 (mentions), §6 (données), §8 (workflow), §10 (écrans).

---

## Fichiers créés/modifiés dans ce plan

- `database/migrations/2026_07_13_010001_create_courriers_table.php`
- `database/migrations/2026_07_13_010002_create_imputations_table.php`
- `database/migrations/2026_07_13_010003_create_entite_imputation_table.php`
- `app/Models/Courrier.php`, `app/Models/Imputation.php`
- `app/Livewire/Courrier/Registre.php` + vue ; `app/Livewire/Courrier/NouveauCourrier.php` + vue ; `app/Livewire/Courrier/FicheCourrier.php` + vue.
- `routes/web.php` (modifié) ; `resources/views/components/layouts/rh.blade.php` (nav courrier, modifié).
- `tests/Feature/Courrier/*`.

---

## Task 1: Schéma & modèles (courriers, imputations, pivot)

**Files:**
- Create: `database/migrations/2026_07_13_010001_create_courriers_table.php`
- Create: `database/migrations/2026_07_13_010002_create_imputations_table.php`
- Create: `database/migrations/2026_07_13_010003_create_entite_imputation_table.php`
- Create: `app/Models/Courrier.php`, `app/Models/Imputation.php`
- Test: `tests/Feature/Courrier/CourrierModelsTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/CourrierModelsTest.php`:
```php
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
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/CourrierModelsTest.php`
Expected: FAIL (modèles/tables absents).

- [ ] **Step 3: Migration courriers**

Create `database/migrations/2026_07_13_010001_create_courriers_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('objet');
            $table->string('expediteur');
            $table->date('date_arrivee');
            $table->date('date_depart')->nullable();
            $table->string('scan_path')->nullable();
            $table->foreignId('enregistre_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
```

- [ ] **Step 4: Migration imputations**

Create `database/migrations/2026_07_13_010002_create_imputations_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->enum('niveau', ['dg', 'direction']);
            $table->foreignId('entite_source_id')->nullable()->constrained('entites')->nullOnDelete();
            $table->json('mentions')->nullable();
            $table->text('observations')->nullable();
            $table->string('signataire_nom')->nullable();
            $table->string('scan_path')->nullable();
            $table->foreignId('saisi_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imputations');
    }
};
```

- [ ] **Step 5: Migration pivot**

Create `database/migrations/2026_07_13_010003_create_entite_imputation_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entite_imputation', function (Blueprint $table) {
            $table->foreignId('imputation_id')->constrained('imputations')->cascadeOnDelete();
            $table->foreignId('entite_id')->constrained('entites')->cascadeOnDelete();
            $table->primary(['imputation_id', 'entite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entite_imputation');
    }
};
```

- [ ] **Step 6: Modèle Courrier**

Create `app/Models/Courrier.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courrier extends Model
{
    protected $fillable = [
        'numero', 'objet', 'expediteur', 'date_arrivee', 'date_depart', 'scan_path', 'enregistre_par',
    ];

    protected $casts = [
        'date_arrivee' => 'date',
        'date_depart' => 'date',
    ];

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class)->latest();
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function scanUrl(): ?string
    {
        return $this->scan_path ? '/storage/'.ltrim($this->scan_path, '/') : null;
    }
}
```

- [ ] **Step 7: Modèle Imputation**

Create `app/Models/Imputation.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Imputation extends Model
{
    /** Mentions « Soit transmis » de la fiche de ventilation. */
    public const MENTIONS = [
        'urgent' => 'Urgent',
        'men_parler' => "M'en parler",
        'etude_reponse' => 'Pour étude et réponse',
        'accord' => 'Accord',
        'information' => 'Pour information',
        'attribution' => 'Pour attribution',
        'exploitation' => 'Pour exploitation',
        'execution' => 'Pour exécution',
        'suite_a_donner' => 'Pour suite à donner',
        'a_suivre' => 'À suivre',
        'diffusion' => 'Pour diffusion',
        'a_classer' => 'À classer',
    ];

    protected $fillable = [
        'courrier_id', 'niveau', 'entite_source_id', 'mentions', 'observations', 'signataire_nom', 'scan_path', 'saisi_par',
    ];

    protected $casts = [
        'mentions' => 'array',
    ];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function entiteSource(): BelongsTo
    {
        return $this->belongsTo(Entite::class, 'entite_source_id');
    }

    public function destinataires(): BelongsToMany
    {
        return $this->belongsToMany(Entite::class, 'entite_imputation');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/CourrierModelsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): schema courriers + imputations + pivot"
```

---

## Task 2: Enregistrement + registre (bureau courrier)

**Files:**
- Create: `app/Livewire/Courrier/Registre.php`, `resources/views/livewire/courrier/registre.blade.php`
- Create: `app/Livewire/Courrier/NouveauCourrier.php`, `resources/views/livewire/courrier/nouveau-courrier.blade.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Courrier/EnregistrementTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/EnregistrementTest.php`:
```php
<?php

use App\Livewire\Courrier\NouveauCourrier;
use App\Livewire\Courrier\Registre;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function courrierUser(): User
{
    return User::create(['name' => 'Bureau Courrier', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
}

it('interdit le registre aux non courrier (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agent)->get('/courriers')->assertForbidden();
});

it('enregistre un courrier', function () {
    $u = courrierUser();

    Livewire::actingAs($u)
        ->test(NouveauCourrier::class)
        ->set('numero', '000145')
        ->set('objet', 'Convocation réunion')
        ->set('expediteur', 'Préfecture de Dakar')
        ->set('date_arrivee', '2026-07-07')
        ->call('enregistrer')
        ->assertRedirect(route('courriers.registre'));

    $c = Courrier::where('numero', '000145')->first();
    expect($c)->not->toBeNull();
    expect($c->objet)->toBe('Convocation réunion');
    expect($c->enregistre_par)->toBe($u->id);
});

it('refuse un numéro dupliqué', function () {
    $u = courrierUser();
    Courrier::create(['numero' => 'DUP', 'objet' => 'x', 'expediteur' => 'y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    Livewire::actingAs($u)
        ->test(NouveauCourrier::class)
        ->set('numero', 'DUP')
        ->set('objet', 'z')
        ->set('expediteur', 'w')
        ->set('date_arrivee', '2026-07-08')
        ->call('enregistrer')
        ->assertHasErrors('numero');
});

it('recherche dans le registre par objet', function () {
    $u = courrierUser();
    Courrier::create(['numero' => 'A1', 'objet' => 'CONVOCATION', 'expediteur' => 'P', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);
    Courrier::create(['numero' => 'A2', 'objet' => 'FACTURE', 'expediteur' => 'F', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    Livewire::actingAs($u)
        ->test(Registre::class)
        ->set('search', 'CONVOCATION')
        ->assertSee('CONVOCATION')
        ->assertDontSee('FACTURE');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EnregistrementTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Composant NouveauCourrier**

Create `app/Livewire/Courrier/NouveauCourrier.php`:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class NouveauCourrier extends Component
{
    use WithFileUploads;

    public string $numero = '';
    public string $objet = '';
    public string $expediteur = '';
    public ?string $date_arrivee = null;
    public ?string $date_depart = null;
    public $scan = null;

    public function enregistrer()
    {
        $data = $this->validate([
            'numero' => ['required', 'string', 'max:50', 'unique:courriers,numero'],
            'objet' => ['required', 'string', 'max:255'],
            'expediteur' => ['required', 'string', 'max:255'],
            'date_arrivee' => ['required', 'date'],
            'date_depart' => ['nullable', 'date'],
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        unset($data['scan']);
        if ($this->scan) {
            $data['scan_path'] = $this->scan->store('courriers-scans', 'public');
        }
        $data['enregistre_par'] = auth()->id();

        Courrier::create($data);
        session()->flash('ok', 'Courrier enregistré.');

        return redirect()->route('courriers.registre');
    }

    public function render()
    {
        return view('livewire.courrier.nouveau-courrier');
    }
}
```

- [ ] **Step 4: Vue NouveauCourrier**

Create `resources/views/livewire/courrier/nouveau-courrier.blade.php`:
```blade
<div style="max-width:640px;margin:0 auto">
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($err='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:26px;font-weight:600;margin:0 0 18px">Nouveau courrier</h1>

    <form wire:submit="enregistrer" class="card" style="padding:24px;display:flex;flex-direction:column;gap:16px">
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px">
            <div><label style="{{ $lbl }}">N° d'arrivée</label>
                <input type="text" wire:model="numero" class="field" placeholder="000145">
                @error('numero') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
            <div><label style="{{ $lbl }}">Expéditeur</label>
                <input type="text" wire:model="expediteur" class="field">
                @error('expediteur') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        </div>
        <div><label style="{{ $lbl }}">Objet</label>
            <input type="text" wire:model="objet" class="field">
            @error('objet') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label style="{{ $lbl }}">Date d'arrivée</label>
                <input type="date" wire:model="date_arrivee" class="field">
                @error('date_arrivee') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
            <div><label style="{{ $lbl }}">Date de départ (optionnel)</label>
                <input type="date" wire:model="date_depart" class="field"></div>
        </div>
        <div><label style="{{ $lbl }}">Scan du courrier (PDF ou image)</label>
            <input type="file" wire:model="scan" accept=".pdf,image/*" style="font-size:13px">
            <div wire:loading wire:target="scan" style="font-size:12px;color:var(--muted)">Téléversement…</div>
            @error('scan') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="display:flex;justify-content:flex-end;gap:10px">
            <a href="{{ route('courriers.registre') }}" class="btn btn-ghost" style="text-decoration:none">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
```

- [ ] **Step 5: Composant Registre**

Create `app/Livewire/Courrier/Registre.php`:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Registre extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $courriers = Courrier::query()
            ->withCount('imputations')
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('numero', 'like', $t)->orWhere('objet', 'like', $t)->orWhere('expediteur', 'like', $t));
            })
            ->latest('date_arrivee')
            ->latest('id')
            ->paginate(15);

        return view('livewire.courrier.registre', ['courriers' => $courriers]);
    }
}
```

- [ ] **Step 6: Vue Registre**

Create `resources/views/livewire/courrier/registre.blade.php`:
```blade
<div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px">
        <div>
            <h1 style="font-size:28px;font-weight:600;margin:0">Registre du courrier</h1>
            <p style="color:var(--muted);font-size:14px;margin:4px 0 0">{{ $courriers->total() }} courrier(s)</p>
        </div>
        <a href="{{ route('courriers.nouveau') }}" class="btn btn-primary" style="text-decoration:none">+ Nouveau courrier</a>
    </div>

    <div class="card" style="padding:12px 14px;margin:18px 0">
        <div style="position:relative">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher n° / objet / expéditeur…" class="field" style="padding-left:36px">
        </div>
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">N°</th><th style="padding:12px 12px;font-weight:600">Objet</th>
                    <th style="padding:12px 12px;font-weight:600">Expéditeur</th><th style="padding:12px 12px;font-weight:600">Arrivée</th>
                    <th style="padding:12px 12px;font-weight:600">Ventilé</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->numero }}</td>
                        <td style="padding:11px 12px">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">
                            @if ($c->imputations_count > 0)
                                <span class="badge" style="background:#e7efe8;color:#1c6b45">Oui</span>
                            @else
                                <span class="badge" style="background:#f6ecdd;color:#8a5a1e">À ventiler</span>
                            @endif
                        </td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('courriers.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucun courrier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
```

- [ ] **Step 7: Routes + nav**

Modify `routes/web.php` — ajouter un groupe :
```php
Route::middleware(['auth', 'role:courrier'])->group(function () {
    Route::get('/courriers', \App\Livewire\Courrier\Registre::class)->name('courriers.registre');
    Route::get('/courriers/nouveau', \App\Livewire\Courrier\NouveauCourrier::class)->name('courriers.nouveau');
    Route::get('/courriers/{courrier}', \App\Livewire\Courrier\FicheCourrier::class)->name('courriers.fiche');
});
```
Note : `FicheCourrier` (route `courriers.fiche`) est créé en Task 3 ; comme le fichier de routes le référence, créer d'abord un **stub** minimal pour que les routes se chargent :
```php
// app/Livewire/Courrier/FicheCourrier.php (stub — remplacé en Task 3)
<?php
namespace App\Livewire\Courrier;
use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.rh')]
class FicheCourrier extends Component
{
    public Courrier $courrier;
    public function mount(Courrier $courrier): void { $this->courrier = $courrier; }
    public function render() { return view('livewire.courrier.fiche-courrier'); }
}
```
et la vue stub `resources/views/livewire/courrier/fiche-courrier.blade.php` :
```blade
<div>Fiche courrier (à venir).</div>
```

Modify `resources/views/components/layouts/rh.blade.php` — dans la `<nav>`, ajouter un bloc pour le rôle courrier (après le bloc `@if ($__u->isSecretaire())`) :
```blade
                @if ($__u->isCourrier())
                    <a href="{{ route('courriers.registre') }}" class="{{ request()->routeIs('courriers.*') ? 'on' : '' }}">{!! $ico['cal'] !!} Registre courrier</a>
                @endif
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EnregistrementTest.php`
Expected: PASS (4 tests).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): enregistrement + registre (bureau courrier)"
```

---

## Task 3: Ventilation DG (fiche courrier)

Complète `FicheCourrier` : afficher le courrier + son historique d'imputations, et créer la **fiche de ventilation niveau `dg`** (destinataires parmi les entités direction/service + mentions + observations + signataire).

**Files:**
- Modify: `app/Livewire/Courrier/FicheCourrier.php`
- Modify: `resources/views/livewire/courrier/fiche-courrier.blade.php`
- Test: `tests/Feature/Courrier/VentilationTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/VentilationTest.php`:
```php
<?php

use App\Livewire\Courrier\FicheCourrier;
use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxVentilation(): array
{
    $u = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);
    $si = Entite::create(['code' => 'SI', 'nom' => 'Informatique', 'type' => 'direction']);
    $c = Courrier::create(['numero' => '000145', 'objet' => 'Convocation', 'expediteur' => 'Préfecture', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $u->id]);

    return compact('u', 'doe', 'si', 'c');
}

it('le bureau courrier ventile un courrier (destinataires + mentions)', function () {
    ['u' => $u, 'doe' => $doe, 'si' => $si, 'c' => $c] = ctxVentilation();

    Livewire::actingAs($u)
        ->test(FicheCourrier::class, ['courrier' => $c])
        ->set('destinataires', [$doe->id, $si->id])
        ->set('mentions', ['etude_reponse', 'information'])
        ->set('observations', 'Traiter en urgence')
        ->set('signataire_nom', 'Le Directeur Général')
        ->call('ventiler')
        ->assertHasNoErrors();

    $imp = $c->imputations()->first();
    expect($imp)->not->toBeNull();
    expect($imp->niveau)->toBe('dg');
    expect($imp->mentions)->toBe(['etude_reponse', 'information']);
    expect($imp->destinataires()->count())->toBe(2);
    expect($imp->saisi_par)->toBe($u->id);
});

it('exige au moins un destinataire', function () {
    ['u' => $u, 'c' => $c] = ctxVentilation();

    Livewire::actingAs($u)
        ->test(FicheCourrier::class, ['courrier' => $c])
        ->set('destinataires', [])
        ->set('mentions', ['information'])
        ->call('ventiler')
        ->assertHasErrors('destinataires');

    expect(Imputation::count())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/VentilationTest.php`
Expected: FAIL (le stub `FicheCourrier` n'a ni `ventiler` ni propriétés).

- [ ] **Step 3: Remplacer `FicheCourrier`**

Replace `app/Livewire/Courrier/FicheCourrier.php` with:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FicheCourrier extends Component
{
    public Courrier $courrier;

    public array $destinataires = [];
    public array $mentions = [];
    public ?string $observations = null;
    public ?string $signataire_nom = null;

    public function mount(Courrier $courrier): void
    {
        $this->courrier = $courrier;
        $this->signataire_nom = config('dge.dg_nom');
    }

    public function ventiler()
    {
        $this->validate([
            'destinataires' => ['array', 'min:1'],
            'destinataires.*' => ['exists:entites,id'],
            'mentions' => ['array'],
            'mentions.*' => [Rule::in(array_keys(Imputation::MENTIONS))],
            'observations' => ['nullable', 'string', 'max:2000'],
            'signataire_nom' => ['nullable', 'string', 'max:255'],
        ], [], ['destinataires' => 'destinataires']);

        $imp = Imputation::create([
            'courrier_id' => $this->courrier->id,
            'niveau' => 'dg',
            'entite_source_id' => null,
            'mentions' => array_values($this->mentions),
            'observations' => $this->observations,
            'signataire_nom' => $this->signataire_nom,
            'saisi_par' => auth()->id(),
        ]);
        $imp->destinataires()->sync($this->destinataires);

        if (! $this->courrier->date_depart) {
            $this->courrier->update(['date_depart' => now()->toDateString()]);
        }

        $this->reset(['destinataires', 'mentions', 'observations']);
        session()->flash('ok', 'Ventilation enregistrée.');
    }

    public function render()
    {
        return view('livewire.courrier.fiche-courrier', [
            'entites' => Entite::whereIn('type', ['direction', 'service'])->where('actif', true)->orderBy('type')->orderBy('code')->get(),
            'imputations' => $this->courrier->imputations()->with('destinataires', 'entiteSource')->get(),
            'mentionsListe' => Imputation::MENTIONS,
        ]);
    }
}
```

- [ ] **Step 4: Remplacer la vue**

Replace `resources/views/livewire/courrier/fiche-courrier.blade.php` with:
```blade
<div style="max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:20px">
    <a href="{{ route('courriers.registre') }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Registre</a>

    {{-- En-tête courrier --}}
    <div class="card" style="padding:22px">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="font-size:24px;font-weight:600;margin:0">Courrier N° {{ $courrier->numero }}</h1>
                <div style="color:var(--muted);font-size:14px;margin-top:4px">{{ $courrier->objet }}</div>
                <div style="font-size:13px;margin-top:8px">Expéditeur : <strong>{{ $courrier->expediteur }}</strong> · Arrivée : {{ $courrier->date_arrivee->format('d/m/Y') }}</div>
            </div>
            @if ($courrier->scanUrl())
                <a href="{{ $courrier->scanUrl() }}" target="_blank" class="btn btn-ghost" style="text-decoration:none">Voir le scan</a>
            @endif
        </div>
    </div>

    {{-- Fiche de ventilation --}}
    <div class="card" style="padding:22px">
        <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Fiche de ventilation</h2>
        <form wire:submit="ventiler" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Destinataires</span>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                    @foreach ($entites as $e)
                        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                            <input type="checkbox" wire:model="destinataires" value="{{ $e->id }}"> {{ $e->code }} — {{ $e->nom }}
                        </label>
                    @endforeach
                </div>
                @error('destinataires') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">Sélectionnez au moins un destinataire.</span> @enderror
            </div>

            <div>
                <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Soit transmis (mentions)</span>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
                    @foreach ($mentionsListe as $code => $libelle)
                        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                            <input type="checkbox" wire:model="mentions" value="{{ $code }}"> {{ $libelle }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
                <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Observations</label>
                    <textarea wire:model="observations" rows="2" class="field"></textarea></div>
                <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Signataire</label>
                    <input type="text" wire:model="signataire_nom" class="field"></div>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Enregistrer la ventilation</button>
            </div>
        </form>
    </div>

    {{-- Historique --}}
    <div class="card" style="padding:22px">
        <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Historique d'imputation</h2>
        @forelse ($imputations as $imp)
            <div style="padding:12px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                <div style="font-weight:600;font-size:14px">
                    {{ $imp->niveau === 'dg' ? 'Ventilation DG' : 'Cascade '.($imp->entiteSource?->code) }}
                    <span style="color:var(--muted);font-weight:400">· {{ $imp->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div style="font-size:13px;margin-top:4px">→ {{ $imp->destinataires->pluck('code')->join(', ') }}</div>
                @if ($imp->mentions)
                    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px">
                        @foreach ($imp->mentions as $m)
                            <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ \App\Models\Imputation::MENTIONS[$m] ?? $m }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($imp->observations)
                    <div style="font-size:13px;color:#41504a;margin-top:6px">« {{ $imp->observations }} »</div>
                @endif
                <div style="font-size:12px;color:var(--muted);margin-top:4px">Signataire : {{ $imp->signataire_nom ?? '—' }}</div>
            </div>
        @empty
            <p style="color:var(--muted);font-size:13px;margin:0">Aucune imputation. Ventilez le courrier ci-dessus.</p>
        @endforelse
    </div>
</div>
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/VentilationTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts.

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): ventilation DG (fiche de ventilation)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §6 tables courriers/imputations/pivot (Task 1) ✓ ; §5 mentions (constante 12, Task 1) ✓ ; §8/§10 enregistrement + scan + registre + recherche (Task 2) ✓ ; ventilation niveau `dg` (destinataires direction/service + mentions + observations + signataire) (Task 3) ✓. Cascade direction→divisions + consultation service = **Plan 3** ; archives + PDF = **Plan 4** (hors périmètre).
- **Placeholders :** aucun. Le stub `FicheCourrier` (Task 2) est un composant réel temporaire, explicitement remplacé en Task 3.
- **Cohérence types :** `Courrier` (numero/objet/expediteur/date_arrivee/date_depart/scan_path/enregistre_par ; `imputations()`, `enregistrePar()`, `scanUrl()`) et `Imputation` (niveau/entite_source_id/mentions/observations/signataire_nom/saisi_par ; `MENTIONS`, `destinataires()` via pivot `entite_imputation`, `entiteSource()`, `saisiPar()`) identiques entre migrations, modèles, composants, tests. Routes `courriers.registre`/`courriers.nouveau`/`courriers.fiche` nommées identiquement (nav, vues, tests). Ventilation `niveau='dg'`, `saisi_par=auth()->id()`.

## Dépendances pour les plans suivants

Plan 3 (cascade + consultation) : imputation `niveau='direction'` avec `entite_source_id` = la direction ; destinataires = divisions ; policy d'accès via `Entite.chef_agent_id`/`secretaire_agent_id`. Plan 4 (PDF) : rend une `Imputation` en Fiche de Ventilation.
