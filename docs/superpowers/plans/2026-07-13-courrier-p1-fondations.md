# Courrier — Plan 1 : Fondations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Poser les fondations du module Courrier : rôles `courrier` et `archiviste`, entités destinataires paramétrables (directions / services / divisions), et l'écran Admin RH de gestion des entités.

**Architecture:** Nouveau modèle `Entite` (unité routable du courrier, hiérarchique via `parent_id`, avec chef et secrétaire pour l'accès), distinct des `directions` RH. Deux nouveaux rôles ajoutés à l'enum `users.role`. Un écran Livewire de gestion (CRUD + désignation chef/secrétaire) réservé à l'Admin RH, dans la console à sidebar existante.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind. S'appuie sur Plans RH 1-7 (modèles `Agent`, `Direction`, `User` + helpers de rôle, middleware `role`, layout `components.layouts.rh`, commande `rh:promouvoir`, `Rh\AgentForm`).

**Prérequis d'exécution:** créer la branche `feat/courrier-p1` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/courrier-p1
```
Env local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-13-module-courrier-design.md` §4 (entités), §7 (rôles), §11 (découpage).

---

## Fichiers créés/modifiés dans ce plan

- `database/migrations/2026_07_13_000001_add_courrier_roles.php` — enum role + `courrier`, `archiviste`.
- `database/migrations/2026_07_13_000002_create_entites_table.php` — table entités.
- `app/Models/Entite.php` — modèle + relations.
- `database/seeders/EntiteSeeder.php` — directions/services/divisions ; `DatabaseSeeder.php` (modifié).
- `app/Models/User.php` — helpers `isCourrier()` / `isArchiviste()` (modifié).
- `app/Console/Commands/PromouvoirAgent.php` — liste blanche (modifié).
- `app/Livewire/Rh/AgentForm.php` + vue — rôles attribuables (modifié).
- `app/Livewire/Rh/EntitesManager.php` + `resources/views/livewire/rh/entites-manager.blade.php` — gestion.
- `routes/web.php` + `resources/views/components/layouts/rh.blade.php` (modifiés).
- `tests/Feature/Courrier/*`.

---

## Task 1: Rôles `courrier` et `archiviste`

**Files:**
- Create: `database/migrations/2026_07_13_000001_add_courrier_roles.php`
- Modify: `app/Models/User.php`, `app/Console/Commands/PromouvoirAgent.php`, `app/Livewire/Rh/AgentForm.php`, `resources/views/livewire/rh/agent-form.blade.php`
- Test: `tests/Feature/Courrier/RolesCourrierTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/RolesCourrierTest.php` (créer le dossier `tests/Feature/Courrier/`):
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepte les rôles courrier et archiviste + helpers', function () {
    $c = User::create(['name' => 'C', 'matricule' => 'C1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $a = User::create(['name' => 'A', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'archiviste']);

    expect($c->fresh()->role)->toBe('courrier');
    expect($c->isCourrier())->toBeTrue();
    expect($c->isArchiviste())->toBeFalse();
    expect($a->isArchiviste())->toBeTrue();
});

it('promeut en courrier via la commande', function () {
    User::create(['name' => 'X', 'matricule' => 'M1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->artisan('rh:promouvoir', ['matricule' => 'M1', 'role' => 'courrier'])->assertExitCode(0);

    expect(User::where('matricule', 'M1')->first()->role)->toBe('courrier');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/RolesCourrierTest.php`
Expected: FAIL (enum n'accepte pas `courrier` / helpers absents).

- [ ] **Step 3: Migration d'enum**

Create `database/migrations/2026_07_13_000001_add_courrier_roles.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire', 'courrier', 'archiviste'])
                ->default('agent')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire'])
                ->default('agent')->change();
        });
    }
};
```
Note : `->change()` fonctionne nativement (déjà utilisé pour ajouter `secretaire`). Sur SQLite il reconstruit la table.

- [ ] **Step 4: Helpers sur User**

Modify `app/Models/User.php` — ajouter dans la classe :
```php
public function isCourrier(): bool
{
    return $this->role === 'courrier';
}

public function isArchiviste(): bool
{
    return $this->role === 'archiviste';
}
```

- [ ] **Step 5: Liste blanche de `rh:promouvoir`**

Modify `app/Console/Commands/PromouvoirAgent.php` — mettre à jour la constante et la description :
```php
    protected $description = 'Attribue un rôle (agent|chef_direction|admin_rh|dg|secretaire|courrier|archiviste) à un compte existant';

    private const ROLES = ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire', 'courrier', 'archiviste'];
```

- [ ] **Step 6: Rôles attribuables depuis la fiche agent**

Modify `app/Livewire/Rh/AgentForm.php` — dans `rules()`, la règle `role` :
```php
            'role' => [
                'nullable',
                \Illuminate\Validation\Rule::in(['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire', 'courrier', 'archiviste']),
            ],
```
Modify `resources/views/livewire/rh/agent-form.blade.php` — dans le `<select wire:model="role">`, ajouter après l'option `secretaire` :
```blade
                    <option value="courrier">Agent courrier</option>
                    <option value="archiviste">Archiviste</option>
```

- [ ] **Step 7: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/RolesCourrierTest.php`
Expected: PASS (2). Vérifier aussi `./vendor/bin/pest tests/Feature/Rh` (non-régression rôles).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): roles courrier + archiviste"
```

---

## Task 2: Entités destinataires (table + modèle + seed)

**Files:**
- Create: `database/migrations/2026_07_13_000002_create_entites_table.php`
- Create: `app/Models/Entite.php`
- Create: `database/seeders/EntiteSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Courrier/EntiteTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/EntiteTest.php`:
```php
<?php

use App\Models\Entite;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seed les entités (directions, services, divisions)', function () {
    $this->seed(\Database\Seeders\EntiteSeeder::class);

    expect(Entite::where('type', 'direction')->count())->toBe(5);
    expect(Entite::where('type', 'service')->count())->toBeGreaterThanOrEqual(4);
    expect(Entite::where('type', 'division')->count())->toBeGreaterThanOrEqual(1);

    // Une division est rattachée à sa direction
    $doe = Entite::where('code', 'DOE')->first();
    $division = Entite::where('type', 'division')->where('parent_id', $doe->id)->first();
    expect($division)->not->toBeNull();
    expect($division->parent->code)->toBe('DOE');
    expect($doe->enfants()->count())->toBeGreaterThanOrEqual(1);
});

it('relie chef et secrétaire à des agents', function () {
    $dir = App\Models\Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = App\Models\Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    $sec = App\Models\Agent::create(['prenoms' => 'C', 'noms' => 'D', 'matricule' => 'X2', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    $e = Entite::create(['code' => 'TEST', 'nom' => 'Test', 'type' => 'service', 'chef_agent_id' => $chef->id, 'secretaire_agent_id' => $sec->id]);

    expect($e->chef->id)->toBe($chef->id);
    expect($e->secretaire->id)->toBe($sec->id);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EntiteTest.php`
Expected: FAIL (modèle/table/seeder absents).

- [ ] **Step 3: Migration**

Create `database/migrations/2026_07_13_000002_create_entites_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->enum('type', ['direction', 'service', 'division']);
            $table->foreignId('parent_id')->nullable()->constrained('entites')->nullOnDelete();
            $table->foreignId('chef_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('secretaire_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entites');
    }
};
```

- [ ] **Step 4: Modèle**

Create `app/Models/Entite.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entite extends Model
{
    protected $fillable = [
        'code', 'nom', 'type', 'parent_id', 'chef_agent_id', 'secretaire_agent_id', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entite::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Entite::class, 'parent_id');
    }

    public function chef(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'chef_agent_id');
    }

    public function secretaire(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'secretaire_agent_id');
    }
}
```

- [ ] **Step 5: Seeder**

Create `database/seeders/EntiteSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Entite;
use Illuminate\Database\Seeder;

class EntiteSeeder extends Seeder
{
    public function run(): void
    {
        // Directions (racines)
        $directions = [
            'DG' => 'Direction Générale',
            'DOE' => 'Direction des Opérations Électorales',
            'DFC' => 'Direction de la Formation et de la Communication',
            'DRHF' => 'Direction des Ressources Humaines et des Finances',
            'SI' => 'Service Informatique',
        ];
        foreach ($directions as $code => $nom) {
            Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'direction', 'parent_id' => null]);
        }

        // Services / bureaux (racines)
        $services = [
            'SP' => 'Secrétariat Particulier',
            'BDA' => 'Bureau de la Documentation et des Archives',
            'BCI' => 'Bureau de la Coopération internationale',
            'BC' => 'Bureau du Courrier',
        ];
        foreach ($services as $code => $nom) {
            Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'service', 'parent_id' => null]);
        }

        // Divisions rattachées (exemples issus des fonctions du personnel)
        $divisions = [
            'DOE' => [
                'DOE-CARTE' => 'Division Carte électorale et fichiers',
                'DOE-JUR' => 'Division des Études et des Affaires juridiques',
                'DOE-SUIVI' => 'Division Suivi Opérations et Missions',
                'DOE-LOG' => 'Division logistique',
            ],
            'DFC' => [
                'DFC-FORM' => 'Division de la Formation permanente',
                'DFC-RP' => 'Division des Relations publiques et de la Communication',
            ],
        ];
        foreach ($divisions as $parentCode => $items) {
            $parent = Entite::where('code', $parentCode)->first();
            foreach ($items as $code => $nom) {
                Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'division', 'parent_id' => $parent?->id]);
            }
        }
    }
}
```

- [ ] **Step 6: Enregistrer le seeder**

Modify `database/seeders/DatabaseSeeder.php` — dans `run()`, ajouter après le `DirectionSeeder` :
```php
$this->call(EntiteSeeder::class);
```

- [ ] **Step 7: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EntiteTest.php`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): entites (table, modele, seeder)"
```

---

## Task 3: Écran de gestion des entités (Admin RH)

**Files:**
- Create: `app/Livewire/Rh/EntitesManager.php`, `resources/views/livewire/rh/entites-manager.blade.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Courrier/EntitesManagerTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/EntitesManagerTest.php`:
```php
<?php

use App\Livewire\Rh\EntitesManager;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhEntites(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
}

it('interdit la gestion des entités aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agent)->get('/rh/entites')->assertForbidden();
});

it('crée une entité de type service', function () {
    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'SP')
        ->set('nom', 'Secrétariat Particulier')
        ->set('type', 'service')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entite::where('code', 'SP')->exists())->toBeTrue();
});

it('crée une division rattachée à une direction', function () {
    $doe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'DOE-CARTE')
        ->set('nom', 'Division Carte électorale')
        ->set('type', 'division')
        ->set('parent_id', $doe->id)
        ->call('save')
        ->assertHasNoErrors();

    $div = Entite::where('code', 'DOE-CARTE')->first();
    expect($div->parent_id)->toBe($doe->id);
});

it('refuse un code dupliqué', function () {
    Entite::create(['code' => 'SP', 'nom' => 'X', 'type' => 'service']);

    Livewire::actingAs(adminRhEntites())
        ->test(EntitesManager::class)
        ->set('code', 'SP')
        ->set('nom', 'Y')
        ->set('type', 'service')
        ->call('save')
        ->assertHasErrors('code');

    expect(Entite::where('code', 'SP')->count())->toBe(1);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EntitesManagerTest.php`
Expected: FAIL (route/composant absents).

- [ ] **Step 3: Composant**

Create `app/Livewire/Rh/EntitesManager.php`:
```php
<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Entite;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class EntitesManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $nom = '';
    public string $type = 'service';
    public ?int $parent_id = null;
    public ?int $chef_agent_id = null;
    public ?int $secretaire_agent_id = null;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('entites', 'code')->ignore($this->editingId)],
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['direction', 'service', 'division'])],
            'parent_id' => ['nullable', 'exists:entites,id'],
            'chef_agent_id' => ['nullable', 'exists:agents,id'],
            'secretaire_agent_id' => ['nullable', 'exists:agents,id'],
        ];
    }

    public function edit(int $id): void
    {
        $e = Entite::findOrFail($id);
        $this->editingId = $e->id;
        $this->code = $e->code;
        $this->nom = $e->nom;
        $this->type = $e->type;
        $this->parent_id = $e->parent_id;
        $this->chef_agent_id = $e->chef_agent_id;
        $this->secretaire_agent_id = $e->secretaire_agent_id;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Entite::findOrFail($this->editingId)->update($data);
        } else {
            Entite::create($data);
        }

        $this->reset(['editingId', 'code', 'nom', 'parent_id', 'chef_agent_id', 'secretaire_agent_id']);
        $this->type = 'service';
        session()->flash('ok', 'Entité enregistrée.');
    }

    public function render()
    {
        return view('livewire.rh.entites-manager', [
            'entites' => Entite::with('parent')->orderBy('type')->orderBy('code')->get(),
            'directions' => Entite::where('type', 'direction')->orderBy('code')->get(),
            'agents' => Agent::orderBy('noms')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue**

Create `resources/views/livewire/rh/entites-manager.blade.php`:
```blade
<div>
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($err='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:28px;font-weight:600;margin:0 0 18px">Entités destinataires (courrier)</h1>

    <form wire:submit="save" class="card" style="padding:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
        <div><label style="{{ $lbl }}">Code</label>
            <input type="text" wire:model="code" class="field">
            @error('code') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="grid-column:span 2"><label style="{{ $lbl }}">Nom</label>
            <input type="text" wire:model="nom" class="field">
            @error('nom') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div><label style="{{ $lbl }}">Type</label>
            <select wire:model.live="type" class="field">
                <option value="direction">Direction</option>
                <option value="service">Service / Bureau</option>
                <option value="division">Division</option>
            </select></div>
        <div><label style="{{ $lbl }}">Direction parente (si division)</label>
            <select wire:model="parent_id" class="field" @disabled($type !== 'division')>
                <option value="">—</option>
                @foreach ($directions as $d)
                    <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
                @endforeach
            </select></div>
        <div><label style="{{ $lbl }}">Chef</label>
            <select wire:model="chef_agent_id" class="field">
                <option value="">—</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }}</option>
                @endforeach
            </select></div>
        <div><label style="{{ $lbl }}">Secrétaire</label>
            <select wire:model="secretaire_agent_id" class="field">
                <option value="">—</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }}</option>
                @endforeach
            </select></div>
        <div style="display:flex;align-items:flex-end;gap:10px">
            <button type="submit" class="btn btn-primary">{{ $editingId ? 'Mettre à jour' : 'Ajouter' }}</button>
            @if ($editingId)
                <button type="button" wire:click="$reset('editingId','code','nom','parent_id','chef_agent_id','secretaire_agent_id')" class="btn btn-ghost">Annuler</button>
            @endif
        </div>
    </form>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Code</th><th style="padding:12px 12px;font-weight:600">Nom</th>
                    <th style="padding:12px 12px;font-weight:600">Type</th><th style="padding:12px 12px;font-weight:600">Parent</th>
                    <th style="padding:12px 12px;font-weight:600">Chef</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entites as $e)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px"><span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ $e->code }}</span></td>
                        <td style="padding:11px 12px;font-weight:600">{{ $e->nom }}</td>
                        <td style="padding:11px 12px">{{ ucfirst($e->type) }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $e->parent?->code ?? '—' }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ optional($e->chef)->noms ?? '—' }}</td>
                        <td style="padding:11px 18px;text-align:right"><button wire:click="edit({{ $e->id }})" class="btn btn-ghost" style="padding:6px 12px;font-size:13px">Éditer</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Route + lien nav**

Modify `routes/web.php` — dans un groupe `['auth','role:admin_rh']`, ajouter :
```php
    Route::get('/rh/entites', \App\Livewire\Rh\EntitesManager::class)->name('rh.entites');
```
Modify `resources/views/components/layouts/rh.blade.php` — dans le bloc `@if ($__u->isAdminRh())` de la `<nav>`, ajouter après le lien Directions :
```blade
                    <a href="{{ route('rh.entites') }}" class="{{ request()->routeIs('rh.entites') ? 'on' : '' }}">{!! $ico['bank'] !!} Entités courrier</a>
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/EntitesManagerTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (RH + courrier fondations).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): gestion des entites (Admin RH)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §7 rôles `courrier`/`archiviste` (Task 1) ✓ ; §4 entités (table/modèle/seed directions+services+divisions, hiérarchie, chef/secrétaire) (Task 2) ✓ ; gestion paramétrable par l'Admin RH (Task 3) ✓. Les courriers/imputations/consultation/PDF = Plans 2-4, hors périmètre de ce plan.
- **Placeholders :** aucun. Les divisions seedées sont des exemples réels (fonctions du personnel) et complétables via l'écran de gestion (Task 3), pas des TODO.
- **Cohérence types :** modèle `Entite` (relations `parent`/`enfants`/`chef`/`secretaire`, champs `code/nom/type/parent_id/chef_agent_id/secretaire_agent_id/actif`) identique entre migration, seeder, tests et `EntitesManager`. Rôles : 7 valeurs cohérentes entre migration, `PromouvoirAgent::ROLES`, `AgentForm` rule+vue, helpers `isCourrier/isArchiviste`. Route `rh.entites` nommée identiquement (nav, test).

## Dépendances pour les plans suivants

Plan 2 (Enregistrement + ventilation) : `courriers` + `imputations` référencent `entites` (destinataires) et `users` (saisi_par). Plan 3 (cascade + consultation) : policy d'accès basée sur `entites.chef_agent_id` / `secretaire_agent_id`.
