# Plan 3 — Fiches & Gestion RH (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner à l'Admin RH (DRHF) un espace pour gérer le personnel (lister/rechercher/filtrer, créer, éditer la fiche complète d'un agent avec photo, ajuster le solde de congé, attribuer un rôle) et gérer les directions (CRUD + désignation d'un chef).

**Architecture:** Un groupe de routes `/rh/*` protégé par `auth` + `role:admin_rh` (middleware posé en Plan 2). Composants Livewire 3 pleine page sous `app/Livewire/Rh/`. La persistance des agents/directions se fait dans les composants (Eloquent direct, validation Livewire). L'upload de photo utilise `WithFileUploads` sur le disque `public`. L'attribution de rôle réutilise la liste blanche des 4 rôles.

**Tech Stack:** Laravel 12, Livewire 3 (`WithPagination`, `WithFileUploads`), Pest, Tailwind. S'appuie sur Plan 1 (modèles `Agent`, `Direction`, colonnes `solde_conge_jours`, `photo_path`, `telephone`, `email`, `date_naissance`, `date_prise_service`, `statut`) et Plan 2 (middleware `role`, helpers `User::hasRole`, `User::agent()`).

**Prérequis d'exécution:** créer la branche `feat/plan3-gestion-rh` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/plan3-gestion-rh
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif sur stderr — l'ignorer. Tests sur SQLite in-memory. Les tests HTTP qui rendent le layout doivent appeler `$this->withoutVite();` (pas de manifest Vite build dans cet env). Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-11-plateforme-rh-dge-design.md` §5 (fiche agent), §6 (Admin RH gère agents/soldes/directions, promeut rôles), §4 (directions + chef).

---

## Fichiers créés/modifiés dans ce plan

- `resources/views/components/layouts/rh.blade.php` — layout RH avec navigation.
- `app/Livewire/Rh/AgentsIndex.php` + `resources/views/livewire/rh/agents-index.blade.php` — liste + recherche + filtre direction.
- `app/Livewire/Rh/AgentForm.php` + `resources/views/livewire/rh/agent-form.blade.php` — création ET édition d'un agent (photo, solde, rôle).
- `app/Livewire/Rh/DirectionsManager.php` + `resources/views/livewire/rh/directions-manager.blade.php` — CRUD directions + désignation chef.
- `routes/web.php` — groupe de routes `/rh/*` (modifié).
- `tests/Feature/Rh/*` — tests Pest par tâche.

**Convention de test partagée** (utilisée par toutes les tâches ci-dessous) : un helper crée un Admin RH connecté. Il est défini **une seule fois** en Task 1 dans `tests/Feature/Rh/AgentsIndexTest.php` sous la forme d'une fonction globale `adminRh()`. Les tâches suivantes redéfinissent la même fonction dans leur propre fichier de test (Pest charge chaque fichier ; pour éviter « cannot redeclare function », **chaque fichier de test de ce plan déclare son helper avec un nom unique** : `adminRhIndex()`, `adminRhForm()`, `adminRhDirections()`). Le code exact est fourni dans chaque tâche.

---

## Task 1: Espace RH protégé + liste des agents (recherche + filtre)

**Files:**
- Create: `resources/views/components/layouts/rh.blade.php`
- Create: `app/Livewire/Rh/AgentsIndex.php`
- Create: `resources/views/livewire/rh/agents-index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Rh/AgentsIndexTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Rh/AgentsIndexTest.php`:
```php
<?php

use App\Livewire\Rh\AgentsIndex;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhIndex(): User
{
    return User::create([
        'name' => 'RH', 'matricule' => 'RH-1',
        'password' => bcrypt('secret'), 'role' => 'admin_rh',
    ]);
}

function seedDeuxAgents(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations Électorales']);
    $a = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dg->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $b = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => '700000/Z', 'direction_id' => $doe->id, 'statut' => 'police', 'solde_conge_jours' => 20]);

    return [$dg, $doe, $a, $b];
}

it('interdit l’accès aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agent)->get('/rh/agents')->assertForbidden();
});

it('redirige les invités vers login', function () {
    $this->get('/rh/agents')->assertRedirect('/login');
});

it('liste les agents pour l’admin RH', function () {
    $this->withoutVite();
    seedDeuxAgents();

    $this->actingAs(adminRhIndex())->get('/rh/agents')
        ->assertOk()
        ->assertSeeLivewire(AgentsIndex::class)
        ->assertSee('SENE')
        ->assertSee('DIOP');
});

it('filtre par recherche sur nom/matricule', function () {
    seedDeuxAgents();

    Livewire::actingAs(adminRhIndex())
        ->test(AgentsIndex::class)
        ->set('search', 'Biram')
        ->assertSee('SENE')
        ->assertDontSee('DIOP');
});

it('filtre par direction', function () {
    [$dg] = seedDeuxAgents();

    Livewire::actingAs(adminRhIndex())
        ->test(AgentsIndex::class)
        ->set('directionId', $dg->id)
        ->assertSee('SENE')
        ->assertDontSee('DIOP');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentsIndexTest.php`
Expected: FAIL (route `/rh/agents` et composant absents).

- [ ] **Step 3: Créer le layout RH**

Create `resources/views/components/layouts/rh.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Espace RH — DGE' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
    <header class="bg-emerald-700 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between p-4">
            <span class="font-semibold">RH — DGE</span>
            <nav class="flex gap-4 text-sm">
                <a href="{{ route('rh.agents.index') }}" class="hover:underline">Agents</a>
                <a href="{{ route('rh.directions.index') }}" class="hover:underline">Directions</a>
                <a href="{{ route('dashboard') }}" class="hover:underline">Tableau de bord</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-6xl p-6">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
```

- [ ] **Step 4: Créer le composant AgentsIndex**

Create `app/Livewire/Rh/AgentsIndex.php`:
```php
<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Direction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class AgentsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $directionId = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'directionId'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $agents = Agent::query()
            ->with('direction')
            ->when($this->search !== '', function ($q) {
                $terme = '%'.$this->search.'%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('prenoms', 'like', $terme)
                        ->orWhere('noms', 'like', $terme)
                        ->orWhere('matricule', 'like', $terme);
                });
            })
            ->when($this->directionId, fn ($q) => $q->where('direction_id', $this->directionId))
            ->orderBy('noms')
            ->paginate(20);

        return view('livewire.rh.agents-index', [
            'agents' => $agents,
            'directions' => Direction::orderBy('code')->get(),
        ]);
    }
}
```

- [ ] **Step 5: Créer la vue**

Create `resources/views/livewire/rh/agents-index.blade.php`:
```blade
<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Agents ({{ $agents->total() }})</h1>
        <a href="{{ route('rh.agents.create') }}" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">+ Nouvel agent</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher nom / matricule…" class="rounded border-gray-300">
        <select wire:model.live="directionId" class="rounded border-gray-300">
            <option value="">Toutes les directions</option>
            @foreach ($directions as $d)
                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="p-3">Nom</th><th class="p-3">Matricule</th>
                    <th class="p-3">Fonction</th><th class="p-3">Direction</th>
                    <th class="p-3">Solde</th><th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $agent->prenoms }} {{ $agent->noms }}</td>
                        <td class="p-3">{{ $agent->matricule ?? '—' }}</td>
                        <td class="p-3">{{ $agent->fonction ?? '—' }}</td>
                        <td class="p-3">{{ $agent->direction?->code }}</td>
                        <td class="p-3">{{ $agent->solde_conge_jours }} j</td>
                        <td class="p-3"><a href="{{ route('rh.agents.edit', $agent) }}" class="text-emerald-700 underline">Modifier</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Aucun agent.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $agents->links() }}</div>
</div>
```

- [ ] **Step 6: Déclarer le groupe de routes RH**

Modify `routes/web.php` — ajouter, à l'intérieur du `Route::middleware('auth')->group(...)` existant (ou juste après lui), ce bloc :
```php
Route::middleware(['auth', 'role:admin_rh'])->prefix('rh')->name('rh.')->group(function () {
    Route::get('/agents', \App\Livewire\Rh\AgentsIndex::class)->name('agents.index');
    Route::get('/agents/nouveau', \App\Livewire\Rh\AgentForm::class)->name('agents.create');
    Route::get('/agents/{agent}/modifier', \App\Livewire\Rh\AgentForm::class)->name('agents.edit');
    Route::get('/directions', \App\Livewire\Rh\DirectionsManager::class)->name('directions.index');
});
```
Note : les composants `AgentForm` et `DirectionsManager` n'existent pas encore (Tasks 2-5). Les routes qui les référencent ne sont pas exercées par les tests de Task 1, donc l'app fonctionne ; mais pour éviter une erreur au chargement des routes, ces classes doivent exister comme fichiers. **Créer immédiatement des stubs minimaux** pour que le fichier de routes se charge :

Create `app/Livewire/Rh/AgentForm.php` (stub — sera complété en Tasks 2-4) :
```php
<?php

namespace App\Livewire\Rh;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class AgentForm extends Component
{
    public function render()
    {
        return view('livewire.rh.agent-form');
    }
}
```
Create `resources/views/livewire/rh/agent-form.blade.php` (stub) :
```blade
<div>Formulaire agent (à venir).</div>
```
Create `app/Livewire/Rh/DirectionsManager.php` (stub — sera complété en Task 5) :
```php
<?php

namespace App\Livewire\Rh;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class DirectionsManager extends Component
{
    public function render()
    {
        return view('livewire.rh.directions-manager');
    }
}
```
Create `resources/views/livewire/rh/directions-manager.blade.php` (stub) :
```blade
<div>Gestion des directions (à venir).</div>
```

- [ ] **Step 7: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentsIndexTest.php`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: espace RH protege + liste agents (recherche/filtre)"
```

---

## Task 2: Création d'un agent

Complète `AgentForm` pour le mode création (route `rh.agents.create`, sans paramètre `agent`). Valide et crée un agent, puis redirige vers la liste.

**Files:**
- Modify: `app/Livewire/Rh/AgentForm.php`
- Modify: `resources/views/livewire/rh/agent-form.blade.php`
- Test: `tests/Feature/Rh/AgentCreateTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Rh/AgentCreateTest.php`:
```php
<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhCreate(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('crée un agent avec les champs saisis', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', 'Fatou')
        ->set('noms', 'NDIAYE')
        ->set('matricule', '800000/A')
        ->set('profession', 'Secrétaire')
        ->set('fonction', 'Assistante')
        ->set('direction_id', $dir->id)
        ->set('statut', 'contractuel_pav')
        ->set('solde_conge_jours', 25)
        ->set('telephone', '770000000')
        ->set('email', 'fatou@example.sn')
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent = Agent::where('matricule', '800000/A')->first();
    expect($agent)->not->toBeNull();
    expect($agent->prenoms)->toBe('Fatou');
    expect($agent->direction_id)->toBe($dir->id);
    expect($agent->statut)->toBe('contractuel_pav');
    expect((float) $agent->solde_conge_jours)->toBe(25.0);
});

it('refuse un agent sans prénom/nom/direction', function () {
    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', '')
        ->set('noms', '')
        ->call('save')
        ->assertHasErrors(['prenoms', 'noms', 'direction_id']);

    expect(Agent::count())->toBe(0);
});

it('refuse un matricule déjà utilisé', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'DUP1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    Livewire::actingAs(adminRhCreate())
        ->test(AgentForm::class)
        ->set('prenoms', 'C')
        ->set('noms', 'D')
        ->set('matricule', 'DUP1')
        ->set('direction_id', $dir->id)
        ->call('save')
        ->assertHasErrors('matricule');

    expect(Agent::count())->toBe(1);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentCreateTest.php`
Expected: FAIL (le stub `AgentForm` n'a ni propriétés ni `save`).

- [ ] **Step 3: Remplacer `AgentForm` par la version création**

Replace `app/Livewire/Rh/AgentForm.php` with:
```php
<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class AgentForm extends Component
{
    public ?Agent $agent = null;

    public string $prenoms = '';
    public string $noms = '';
    public ?string $matricule = null;
    public ?string $profession = null;
    public ?string $fonction = null;
    public ?int $direction_id = null;
    public string $statut = 'autre';
    public float $solde_conge_jours = 0;
    public ?string $telephone = null;
    public ?string $email = null;
    public ?string $date_naissance = null;
    public ?string $date_prise_service = null;

    public function mount(?Agent $agent = null): void
    {
        if ($agent && $agent->exists) {
            $this->agent = $agent;
            $this->fill($agent->only([
                'prenoms', 'noms', 'matricule', 'profession', 'fonction',
                'direction_id', 'statut', 'solde_conge_jours', 'telephone',
                'email', 'date_naissance', 'date_prise_service',
            ]));
        }
    }

    protected function rules(): array
    {
        $ignore = $this->agent?->id;

        return [
            'prenoms' => ['required', 'string', 'max:255'],
            'noms' => ['required', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:255', Rule::unique('agents', 'matricule')->ignore($ignore)],
            'profession' => ['nullable', 'string', 'max:255'],
            'fonction' => ['nullable', 'string', 'max:255'],
            'direction_id' => ['required', 'exists:directions,id'],
            'statut' => ['required', Rule::in(['fonctionnaire', 'police', 'contractuel_pav', 'autre'])],
            'solde_conge_jours' => ['required', 'numeric', 'min:0'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'date_prise_service' => ['nullable', 'date'],
        ];
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->agent) {
            $this->agent->update($data);
        } else {
            Agent::create($data);
        }

        session()->flash('ok', 'Agent enregistré.');

        return redirect()->route('rh.agents.index');
    }

    public function render()
    {
        return view('livewire.rh.agent-form', [
            'directions' => Direction::orderBy('code')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Remplacer la vue du formulaire**

Replace `resources/views/livewire/rh/agent-form.blade.php` with:
```blade
<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 text-xl font-semibold">{{ $agent ? 'Modifier' : 'Nouvel' }} agent</h1>

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Prénom(s)</label>
                <input type="text" wire:model="prenoms" class="mt-1 w-full rounded border-gray-300">
                @error('prenoms') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Nom</label>
                <input type="text" wire:model="noms" class="mt-1 w-full rounded border-gray-300">
                @error('noms') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Matricule / NIN</label>
                <input type="text" wire:model="matricule" class="mt-1 w-full rounded border-gray-300">
                @error('matricule') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Direction</label>
                <select wire:model="direction_id" class="mt-1 w-full rounded border-gray-300">
                    <option value="">— choisir —</option>
                    @foreach ($directions as $d)
                        <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
                    @endforeach
                </select>
                @error('direction_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Profession</label>
                <input type="text" wire:model="profession" class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Fonction</label>
                <input type="text" wire:model="fonction" class="mt-1 w-full rounded border-gray-300">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium">Statut</label>
                <select wire:model="statut" class="mt-1 w-full rounded border-gray-300">
                    <option value="fonctionnaire">Fonctionnaire</option>
                    <option value="police">Police</option>
                    <option value="contractuel_pav">Contractuel / PAV</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">Solde congé (jours)</label>
                <input type="number" step="0.5" wire:model="solde_conge_jours" class="mt-1 w-full rounded border-gray-300">
                @error('solde_conge_jours') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Téléphone</label>
                <input type="text" wire:model="telephone" class="mt-1 w-full rounded border-gray-300">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded border-gray-300">
                @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Date de naissance</label>
                <input type="date" wire:model="date_naissance" class="mt-1 w-full rounded border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Prise de service</label>
                <input type="date" wire:model="date_prise_service" class="mt-1 w-full rounded border-gray-300">
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('rh.agents.index') }}" class="rounded border px-4 py-2 text-sm">Annuler</a>
            <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Enregistrer</button>
        </div>
    </form>
</div>
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentCreateTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: creation d'un agent (formulaire RH)"
```

---

## Task 3: Édition d'un agent + ajustement solde + attribution de rôle

Le mode édition est déjà géré par `mount(?Agent $agent)` (Task 2). Cette tâche ajoute : la mise à jour effective (déjà dans `save()`), et un contrôle de **rôle** visible uniquement si l'agent a un compte utilisateur lié.

**Files:**
- Modify: `app/Livewire/Rh/AgentForm.php`
- Modify: `resources/views/livewire/rh/agent-form.blade.php`
- Test: `tests/Feature/Rh/AgentEditTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Rh/AgentEditTest.php`:
```php
<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhForm(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('met à jour la fiche et le solde d’un agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->assertSet('prenoms', 'Biram')
        ->set('fonction', 'Chef de bureau')
        ->set('solde_conge_jours', 42)
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent->refresh();
    expect($agent->fonction)->toBe('Chef de bureau');
    expect((float) $agent->solde_conge_jours)->toBe(42.0);
});

it('attribue un rôle au compte lié de l’agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Biram SENE', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->assertSet('role', 'agent')
        ->set('role', 'chef_direction')
        ->call('save');

    expect($user->fresh()->role)->toBe('chef_direction');
});

it('refuse un rôle invalide', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'B S', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhForm())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('role', 'roi')
        ->call('save')
        ->assertHasErrors('role');

    expect($user->fresh()->role)->toBe('agent');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentEditTest.php`
Expected: FAIL (propriété `role` et sa logique absentes).

- [ ] **Step 3: Ajouter la gestion du rôle dans `AgentForm`**

Modify `app/Livewire/Rh/AgentForm.php` :

(a) Ajouter la propriété après `public ?string $date_prise_service = null;` :
```php
    public ?string $role = null;
```

(b) À la fin de `mount()`, après le bloc `if ($agent && $agent->exists) { ... }`, ajouter (à l'intérieur du `if`, après `$this->fill(...)`) :
```php
            $this->role = $agent->user?->role;
```

(c) Dans `rules()`, ajouter une règle conditionnelle sur `role` (seulement si un compte est lié) :
```php
            'role' => [
                'nullable',
                \Illuminate\Validation\Rule::in(['agent', 'chef_direction', 'admin_rh', 'dg']),
            ],
```

(d) Dans `save()`, APRÈS le bloc `if ($this->agent) { $this->agent->update($data); } else { ... }` et AVANT le `session()->flash(...)`, ajouter :
```php
        if ($this->agent && $this->agent->user && $this->role !== null) {
            $this->agent->user->update(['role' => $this->role]);
        }
```
Note : `$data = $this->validate()` valide déjà `role` (présent dans `rules()`), donc un rôle invalide échoue avant toute écriture. Comme `role` n'est PAS une colonne de `agents`, retirer `role` de `$data` avant `update()`/`create()` pour éviter une erreur « colonne inconnue » : juste après `$data = $this->validate();`, ajouter `unset($data['role']);`.

- [ ] **Step 4: Ajouter le contrôle de rôle dans la vue**

Modify `resources/views/livewire/rh/agent-form.blade.php` — insérer ce bloc juste avant la `<div class="flex justify-end gap-3">` finale :
```blade
        @if ($agent && $agent->user)
            <div class="border-t pt-4">
                <label class="block text-sm font-medium">Rôle du compte</label>
                <select wire:model="role" class="mt-1 w-64 rounded border-gray-300">
                    <option value="agent">Agent</option>
                    <option value="chef_direction">Chef de direction</option>
                    <option value="admin_rh">Admin RH</option>
                    <option value="dg">Directeur Général</option>
                </select>
                @error('role') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        @endif
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentEditTest.php`
Expected: PASS (3 tests). Vérifier aussi que `tests/Feature/Rh/AgentCreateTest.php` passe toujours (la propriété `role` nullable ne casse pas la création, car `unset($data['role'])` et le contrôle `$this->agent && ...`).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: edition agent + ajustement solde + attribution de role"
```

---

## Task 4: Upload de la photo d'identité

Ajoute l'upload d'une photo sur la fiche agent (`WithFileUploads`), stockée sur le disque `public`, chemin dans `agents.photo_path`.

**Files:**
- Modify: `app/Livewire/Rh/AgentForm.php`
- Modify: `resources/views/livewire/rh/agent-form.blade.php`
- Test: `tests/Feature/Rh/AgentPhotoTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Rh/AgentPhotoTest.php`:
```php
<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhPhoto(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('téléverse et stocke la photo d’un agent', function () {
    Storage::fake('public');
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhPhoto())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent->refresh();
    expect($agent->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($agent->photo_path);
});

it('refuse un fichier non image', function () {
    Storage::fake('public');
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhPhoto())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photo');

    expect($agent->fresh()->photo_path)->toBeNull();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentPhotoTest.php`
Expected: FAIL (propriété `photo` / trait absent).

- [ ] **Step 3: Ajouter l'upload dans `AgentForm`**

Modify `app/Livewire/Rh/AgentForm.php` :

(a) Ajouter l'import et le trait :
```php
use Livewire\WithFileUploads;
```
et dans la déclaration de classe : `class AgentForm extends Component` → utiliser le trait :
```php
    use WithFileUploads;
```
(placer `use WithFileUploads;` comme première ligne dans le corps de la classe).

(b) Ajouter la propriété (après `public ?string $role = null;`) :
```php
    public $photo = null;
```

(c) Dans `rules()`, ajouter :
```php
            'photo' => ['nullable', 'image', 'max:4096'],
```

(d) Dans `save()`, juste après `unset($data['role']);` et AVANT l'écriture (`if ($this->agent) { ... }`), gérer la photo. Remplacer le bloc d'écriture par :
```php
        unset($data['photo']);

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('agents-photos', 'public');
        }

        if ($this->agent) {
            $this->agent->update($data);
        } else {
            $this->agent = Agent::create($data);
        }
```
(Le `unset($data['role'])` de Task 3 reste juste avant ce bloc. On retire aussi `photo` de `$data` car ce n'est pas une colonne. On stocke le fichier et on met le chemin dans `photo_path`.)

- [ ] **Step 4: Ajouter le champ photo dans la vue**

Modify `resources/views/livewire/rh/agent-form.blade.php` — insérer, juste avant le bloc rôle ajouté en Task 3 (ou avant la div des boutons si pas de compte) :
```blade
        <div class="border-t pt-4">
            <label class="block text-sm font-medium">Photo d'identité</label>
            @if ($agent && $agent->photo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($agent->photo_path) }}" alt="photo" class="mb-2 h-24 w-24 rounded object-cover">
            @endif
            <input type="file" wire:model="photo" accept="image/*" class="mt-1 block text-sm">
            <div wire:loading wire:target="photo" class="text-sm text-gray-500">Téléversement…</div>
            @error('photo') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/AgentPhotoTest.php`
Expected: PASS (2 tests). Vérifier que `AgentCreateTest` et `AgentEditTest` passent toujours.

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: upload photo d'identite agent"
```

---

## Task 5: Gestion des directions + désignation d'un chef

Complète `DirectionsManager` : lister les directions, créer/éditer (code + nom), et désigner un chef parmi les agents de la direction. Désigner un chef renseigne `directions.chef_id` et, si l'agent désigné a un compte, promeut son rôle à `chef_direction`.

**Files:**
- Modify: `app/Livewire/Rh/DirectionsManager.php`
- Modify: `resources/views/livewire/rh/directions-manager.blade.php`
- Test: `tests/Feature/Rh/DirectionsManagerTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Rh/DirectionsManagerTest.php`:
```php
<?php

use App\Livewire\Rh\DirectionsManager;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhDirections(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('crée une direction', function () {
    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->set('code', 'DFC')
        ->set('nom', 'Direction Formation Communication')
        ->call('save')
        ->assertHasNoErrors();

    expect(Direction::where('code', 'DFC')->exists())->toBeTrue();
});

it('refuse un code de direction dupliqué', function () {
    Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->set('code', 'DG')
        ->set('nom', 'Autre')
        ->call('save')
        ->assertHasErrors('code');

    expect(Direction::where('code', 'DG')->count())->toBe(1);
});

it('désigne un chef et promeut son compte', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Biram SENE', 'matricule' => '636324/D', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $user->id]);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->call('designerChef', $dir->id, $agent->id);

    expect($dir->fresh()->chef_id)->toBe($agent->id);
    expect($user->fresh()->role)->toBe('chef_direction');
});

it('désigne un chef sans compte sans erreur', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Sans', 'noms' => 'COMPTE', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    Livewire::actingAs(adminRhDirections())
        ->test(DirectionsManager::class)
        ->call('designerChef', $dir->id, $agent->id);

    expect($dir->fresh()->chef_id)->toBe($agent->id);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/DirectionsManagerTest.php`
Expected: FAIL (stub sans propriétés ni méthodes).

- [ ] **Step 3: Remplacer `DirectionsManager`**

Replace `app/Livewire/Rh/DirectionsManager.php` with:
```php
<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class DirectionsManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $nom = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('directions', 'code')->ignore($this->editingId)],
            'nom' => ['required', 'string', 'max:255'],
        ];
    }

    public function edit(int $id): void
    {
        $dir = Direction::findOrFail($id);
        $this->editingId = $dir->id;
        $this->code = $dir->code;
        $this->nom = $dir->nom;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Direction::findOrFail($this->editingId)->update($data);
        } else {
            Direction::create($data);
        }

        $this->reset(['editingId', 'code', 'nom']);
    }

    public function designerChef(int $directionId, int $agentId): void
    {
        $direction = Direction::findOrFail($directionId);
        $agent = Agent::findOrFail($agentId);

        $direction->update(['chef_id' => $agent->id]);

        if ($agent->user) {
            $agent->user->update(['role' => 'chef_direction']);
        }
    }

    public function render()
    {
        return view('livewire.rh.directions-manager', [
            'directions' => Direction::withCount('agents')->orderBy('code')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Remplacer la vue**

Replace `resources/views/livewire/rh/directions-manager.blade.php` with:
```blade
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Directions</h1>

    <form wire:submit="save" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow">
        <div>
            <label class="block text-sm font-medium">Code</label>
            <input type="text" wire:model="code" class="mt-1 w-32 rounded border-gray-300">
            @error('code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium">Nom</label>
            <input type="text" wire:model="nom" class="mt-1 w-full rounded border-gray-300">
            @error('nom') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">
            {{ $editingId ? 'Mettre à jour' : 'Ajouter' }}
        </button>
        @if ($editingId)
            <button type="button" wire:click="$reset('editingId','code','nom')" class="rounded border px-4 py-2 text-sm">Annuler</button>
        @endif
    </form>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Code</th><th class="p-3">Nom</th><th class="p-3">Agents</th><th class="p-3">Chef</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
                @foreach ($directions as $d)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $d->code }}</td>
                        <td class="p-3">{{ $d->nom }}</td>
                        <td class="p-3">{{ $d->agents_count }}</td>
                        <td class="p-3">{{ optional(\App\Models\Agent::find($d->chef_id))->noms ?? '—' }}</td>
                        <td class="p-3"><button wire:click="edit({{ $d->id }})" class="text-emerald-700 underline">Éditer</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Rh/DirectionsManagerTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plans 1, 2, 3).

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: gestion des directions + designation d'un chef"
```

---

## Self-Review (effectué)

- **Couverture spec :** §6 « Admin RH gère le personnel (CRUD agents), ajuste soldes, gère directions, promeut des rôles » → Task 1 (liste), Task 2 (création), Task 3 (édition + solde + rôle), Task 4 (photo), Task 5 (directions + chef) ✓. §5 fiche agent enrichie (contact, photo, dates, statut) → Tasks 2-4 ✓. §4 direction a un chef → Task 5 ✓. Accès réservé `admin_rh` via middleware `role` (Task 1) ✓. Le scoping « chef voit sa direction » et « DG lecture » concernent les demandes/dashboards → Plans 4-5, hors périmètre.
- **Placeholders :** aucun. Les stubs de Task 1 sont des fichiers réels et fonctionnels (pas des TODO) ; ils sont explicitement remplacés en Tasks 2-5.
- **Cohérence types :** composant `AgentForm` : propriétés (`prenoms`, `noms`, `matricule`, `direction_id`, `statut`, `solde_conge_jours`, `role`, `photo`) cohérentes entre Tasks 2/3/4 ; `mount(?Agent $agent)` unique ; `save()` enrichi de façon additive (unset `role`/`photo` avant écriture Eloquent). Routes `rh.agents.index/create/edit`, `rh.directions.index` nommées identiquement dans layout, composants, tests. Rôles : mêmes 4 valeurs que l'enum Plan 1 partout (`AgentForm::rules`, `DirectionsManager::designerChef` → `chef_direction`). Middleware `role:admin_rh` = alias posé Plan 2.

## Dépendances pour les plans suivants

Plan 4 (Demandes & workflow) : s'appuiera sur `directions.chef_id` (validateur N1) et les rôles posés ici, plus le solde éditable (`solde_conge_jours`) décrémenté à la validation RH.
