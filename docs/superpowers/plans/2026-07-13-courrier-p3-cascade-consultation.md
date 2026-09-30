# Courrier — Plan 3 : Cascade & Consultation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à un chef/secrétaire d'entité de consulter les courriers imputés à son entité, et au secrétaire d'une direction de **cascader** un courrier vers les divisions de sa direction (recopie de l'imputation du directeur).

**Architecture:** Une policy d'accès `CourrierPolicy` + un scope `Courrier::pourEntites()` basés sur les entités qu'un utilisateur gère (chef ou secrétaire). Écran « Mes courriers » (liste) + fiche de consultation ; le secrétaire d'une direction destinataire y trouve un formulaire de cascade créant une imputation `niveau=direction` vers les divisions.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind. S'appuie sur Courrier P1 (`Entite` chef/secrétaire, rôles) + P2 (`Courrier`, `Imputation`, pivot, MENTIONS).

**Prérequis d'exécution:** créer la branche `feat/courrier-p3` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/courrier-p3
```
Env local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-13-module-courrier-design.md` §7 (accès), §8 (cascade), §10 (écrans).

---

## Fichiers créés/modifiés dans ce plan

- `app/Models/User.php` (helper `agentEntitesGereesIds`, modifié) ; `app/Models/Courrier.php` (scope `pourEntites`, modifié).
- `app/Policies/CourrierPolicy.php`.
- `app/Livewire/Courrier/MesCourriers.php` + vue ; `app/Livewire/Courrier/CourrierEntite.php` + vue.
- `routes/web.php` + `resources/views/components/layouts/rh.blade.php` (nav, modifiés).
- `tests/Feature/Courrier/*`.

---

## Task 1: Accès (entités gérées + policy + scope)

**Files:**
- Modify: `app/Models/User.php`, `app/Models/Courrier.php`
- Create: `app/Policies/CourrierPolicy.php`
- Test: `tests/Feature/Courrier/AccesCourrierTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/AccesCourrierTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function ctxAcces(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $autreU = User::create(['name' => 'Autre', 'matricule' => 'AU1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $autreA = Agent::create(['prenoms' => 'Au', 'noms' => 'TR', 'matricule' => 'AU1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $autreU->id]);

    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);
    $eSi = Entite::create(['code' => 'SI', 'nom' => 'Informatique', 'type' => 'direction']);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $courrier = Courrier::create(['numero' => '1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    $imp = Imputation::create(['courrier_id' => $courrier->id, 'niveau' => 'dg', 'saisi_par' => $bc->id]);
    $imp->destinataires()->sync([$eDoe->id]); // imputé à DOE

    return compact('chefU', 'autreU', 'bc', 'courrier', 'eDoe');
}

it('liste les entités gérées par un utilisateur', function () {
    ['chefU' => $chef, 'eDoe' => $eDoe] = ctxAcces();

    expect($chef->agentEntitesGereesIds())->toBe([$eDoe->id]);
});

it('scope pourEntites filtre les courriers imputés à ces entités', function () {
    ['courrier' => $c, 'eDoe' => $eDoe] = ctxAcces();

    expect(Courrier::pourEntites([$eDoe->id])->pluck('id')->all())->toBe([$c->id]);
    expect(Courrier::pourEntites([999])->count())->toBe(0);
});

it('la policy autorise le chef de l’entité destinataire, refuse les autres', function () {
    ['chefU' => $chef, 'autreU' => $autre, 'bc' => $bc, 'courrier' => $c] = ctxAcces();

    expect(Gate::forUser($chef)->allows('voir', $c))->toBeTrue();   // chef de DOE (destinataire)
    expect(Gate::forUser($autre)->allows('voir', $c))->toBeFalse(); // ne gère aucune entité destinataire
    expect(Gate::forUser($bc)->allows('voir', $c))->toBeTrue();     // bureau courrier voit tout
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/AccesCourrierTest.php`
Expected: FAIL (helper/scope/policy absents).

- [ ] **Step 3: Helper sur User**

Modify `app/Models/User.php` — ajouter dans la classe :
```php
/** IDs des entités dont l'agent lié est chef ou secrétaire. */
public function agentEntitesGereesIds(): array
{
    if (! $this->agent) {
        return [];
    }

    return \App\Models\Entite::where('chef_agent_id', $this->agent->id)
        ->orWhere('secretaire_agent_id', $this->agent->id)
        ->pluck('id')->all();
}
```

- [ ] **Step 4: Scope sur Courrier**

Modify `app/Models/Courrier.php` — ajouter dans la classe :
```php
/**
 * Courriers ayant au moins une imputation dont un destinataire est dans $entiteIds.
 *
 * @param  \Illuminate\Database\Eloquent\Builder  $query
 * @param  array<int>  $entiteIds
 */
public function scopePourEntites($query, array $entiteIds)
{
    return $query->whereHas('imputations.destinataires', function ($q) use ($entiteIds) {
        $q->whereIn('entites.id', $entiteIds);
    });
}
```
Note : `Imputation` doit exposer `destinataires()` (déjà fait au Plan 2) et `Courrier::imputations()` (déjà fait). Le `whereHas('imputations.destinataires', ...)` traverse les deux.

- [ ] **Step 5: Policy**

Create `app/Policies/CourrierPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    /**
     * Peut consulter un courrier :
     * - bureau courrier + archiviste : tous
     * - chef/secrétaire d'une entité : si le courrier est imputé à une entité qu'il gère
     */
    public function voir(User $user, Courrier $courrier): bool
    {
        if ($user->isCourrier() || $user->isArchiviste()) {
            return true;
        }

        $ids = $user->agentEntitesGereesIds();
        if (empty($ids)) {
            return false;
        }

        return $courrier->imputations()
            ->whereHas('destinataires', fn ($q) => $q->whereIn('entites.id', $ids))
            ->exists();
    }
}
```
(Laravel 12 auto-découvre `CourrierPolicy` pour `Courrier` — pas d'enregistrement manuel.)

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/AccesCourrierTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): acces (entites gerees + policy + scope)"
```

---

## Task 2: Consultation — Mes courriers + fiche entité

**Files:**
- Create: `app/Livewire/Courrier/MesCourriers.php`, `resources/views/livewire/courrier/mes-courriers.blade.php`
- Create: `app/Livewire/Courrier/CourrierEntite.php`, `resources/views/livewire/courrier/courrier-entite.blade.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Courrier/ConsultationTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/ConsultationTest.php`:
```php
<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Livewire\Courrier\MesCourriers;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxConsult(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $chefU = User::create(['name' => 'Chef DOE', 'matricule' => 'CH1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefA = Agent::create(['prenoms' => 'Ch', 'noms' => 'EF', 'matricule' => 'CH1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $chefU->id]);
    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'chef_agent_id' => $chefA->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $mien = Courrier::create(['numero' => 'MIEN', 'objet' => 'POUR DOE', 'expediteur' => 'P', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $mien->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);
    $autre = Courrier::create(['numero' => 'AUTRE', 'objet' => 'PAS POUR MOI', 'expediteur' => 'Q', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);

    return compact('chefU', 'eDoe', 'mien', 'autre');
}

it('interdit mes-courriers aux non chef/secrétaire (403)', function () {
    $this->withoutVite();
    $agent = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agent)->get('/mes-courriers')->assertForbidden();
});

it('liste seulement les courriers de mon entité', function () {
    ['chefU' => $chef] = ctxConsult();

    Livewire::actingAs($chef)
        ->test(MesCourriers::class)
        ->assertSee('POUR DOE')
        ->assertDontSee('PAS POUR MOI');
});

it('la fiche entité respecte la policy', function () {
    ['chefU' => $chef, 'mien' => $mien, 'autre' => $autre] = ctxConsult();

    Livewire::actingAs($chef)->test(CourrierEntite::class, ['courrier' => $mien])->assertOk();

    // Livewire::test() intercepte AuthorizationException et rend un 403 (RequestBroker
    // l'exclut du re-throw) → on assert sur la réponse, pas via toThrow.
    Livewire::actingAs($chef)->test(CourrierEntite::class, ['courrier' => $autre])->assertForbidden();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/ConsultationTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Composant MesCourriers**

Create `app/Livewire/Courrier/MesCourriers.php`:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class MesCourriers extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $ids = auth()->user()->agentEntitesGereesIds();

        $courriers = Courrier::query()
            ->when(empty($ids), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(! empty($ids), fn ($q) => $q->pourEntites($ids))
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('numero', 'like', $t)->orWhere('objet', 'like', $t)->orWhere('expediteur', 'like', $t));
            })
            ->latest('date_arrivee')->latest('id')
            ->paginate(15);

        return view('livewire.courrier.mes-courriers', ['courriers' => $courriers]);
    }
}
```

- [ ] **Step 4: Vue MesCourriers**

Create `resources/views/livewire/courrier/mes-courriers.blade.php`:
```blade
<div>
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Mes courriers</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Courriers imputés à votre entité</p>

    <div class="card" style="padding:12px 14px;margin-bottom:18px">
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
                    <th style="padding:12px 12px;font-weight:600">Expéditeur</th><th style="padding:12px 12px;font-weight:600">Arrivée</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->numero }}</td>
                        <td style="padding:11px 12px">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('mes-courriers.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">Aucun courrier imputé à votre entité.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
```

- [ ] **Step 5: Composant CourrierEntite (consultation)**

Create `app/Livewire/Courrier/CourrierEntite.php`:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class CourrierEntite extends Component
{
    public Courrier $courrier;

    public function mount(Courrier $courrier): void
    {
        Gate::authorize('voir', $courrier);
        $this->courrier = $courrier;
    }

    public function render()
    {
        return view('livewire.courrier.courrier-entite', [
            'imputations' => $this->courrier->imputations()->with('destinataires', 'entiteSource')->get(),
        ]);
    }
}
```

- [ ] **Step 6: Vue CourrierEntite**

Create `resources/views/livewire/courrier/courrier-entite.blade.php`:
```blade
<div style="max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:20px">
    <a href="{{ route('mes-courriers') }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Mes courriers</a>

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

    <div class="card" style="padding:22px">
        <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Historique d'imputation</h2>
        @foreach ($imputations as $imp)
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
                @if ($imp->observations)<div style="font-size:13px;color:#41504a;margin-top:6px">« {{ $imp->observations }} »</div>@endif
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 7: Routes + nav**

Modify `routes/web.php` — ajouter un groupe :
```php
Route::middleware(['auth', 'role:chef_direction,secretaire'])->group(function () {
    Route::get('/mes-courriers', \App\Livewire\Courrier\MesCourriers::class)->name('mes-courriers');
    Route::get('/mes-courriers/{courrier}', \App\Livewire\Courrier\CourrierEntite::class)->name('mes-courriers.fiche');
});
```
Modify `resources/views/components/layouts/rh.blade.php` — dans la `<nav>`, ajouter (après le bloc `@if ($__u->isSecretaire())`) :
```blade
                @if ($__u->isChefDirection() || $__u->isSecretaire())
                    <a href="{{ route('mes-courriers') }}" class="{{ request()->routeIs('mes-courriers*') ? 'on' : '' }}">{!! $ico['cal'] !!} Mes courriers</a>
                @endif
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/ConsultationTest.php`
Expected: PASS (3 tests).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): consultation par service (mes courriers + fiche entite)"
```

---

## Task 3: Cascade direction → divisions (secrétaire)

Ajoute sur `CourrierEntite` un formulaire de cascade : si l'utilisateur est **secrétaire d'une direction** destinataire du courrier, il peut imputer vers les **divisions** de cette direction.

**Files:**
- Modify: `app/Livewire/Courrier/CourrierEntite.php`, `resources/views/livewire/courrier/courrier-entite.blade.php`
- Test: `tests/Feature/Courrier/CascadeTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Courrier/CascadeTest.php`:
```php
<?php

use App\Livewire\Courrier\CourrierEntite;
use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Direction;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxCascade(): array
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $secU = User::create(['name' => 'Sec DOE', 'matricule' => 'SE1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secA = Agent::create(['prenoms' => 'Se', 'noms' => 'CR', 'matricule' => 'SE1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secU->id]);

    $eDoe = Entite::create(['code' => 'DOE', 'nom' => 'Opérations', 'type' => 'direction', 'secretaire_agent_id' => $secA->id]);
    $div1 = Entite::create(['code' => 'DOE-CARTE', 'nom' => 'Carte', 'type' => 'division', 'parent_id' => $eDoe->id]);
    $div2 = Entite::create(['code' => 'DOE-JUR', 'nom' => 'Juridique', 'type' => 'division', 'parent_id' => $eDoe->id]);

    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    $c = Courrier::create(['numero' => '1', 'objet' => 'X', 'expediteur' => 'Y', 'date_arrivee' => '2026-07-07', 'enregistre_par' => $bc->id]);
    Imputation::create(['courrier_id' => $c->id, 'niveau' => 'dg', 'saisi_par' => $bc->id])->destinataires()->sync([$eDoe->id]);

    return compact('secU', 'eDoe', 'div1', 'div2', 'c');
}

it('le secrétaire cascade vers les divisions de sa direction', function () {
    ['secU' => $sec, 'eDoe' => $eDoe, 'div1' => $div1, 'div2' => $div2, 'c' => $c] = ctxCascade();

    Livewire::actingAs($sec)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('divisions', [$div1->id, $div2->id])
        ->set('mentions_cascade', ['execution'])
        ->set('observations_cascade', 'À traiter')
        ->call('cascader')
        ->assertHasNoErrors();

    $cascade = $c->imputations()->where('niveau', 'direction')->first();
    expect($cascade)->not->toBeNull();
    expect($cascade->entite_source_id)->toBe($eDoe->id);
    expect($cascade->mentions)->toBe(['execution']);
    expect($cascade->destinataires()->count())->toBe(2);
    expect($cascade->saisi_par)->toBe($sec->id);
});

it('exige au moins une division', function () {
    ['secU' => $sec, 'c' => $c] = ctxCascade();

    Livewire::actingAs($sec)
        ->test(CourrierEntite::class, ['courrier' => $c])
        ->set('divisions', [])
        ->call('cascader')
        ->assertHasErrors('divisions');

    expect(Imputation::where('niveau', 'direction')->count())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/CascadeTest.php`
Expected: FAIL (propriétés/`cascader` absents).

- [ ] **Step 3: Étendre `CourrierEntite`**

Replace `app/Livewire/Courrier/CourrierEntite.php` with:
```php
<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class CourrierEntite extends Component
{
    public Courrier $courrier;

    public array $divisions = [];
    public array $mentions_cascade = [];
    public ?string $observations_cascade = null;

    public function mount(Courrier $courrier): void
    {
        Gate::authorize('voir', $courrier);
        $this->courrier = $courrier;
    }

    /** La direction dont l'utilisateur est secrétaire ET qui est destinataire du courrier. */
    private function directionCascade(): ?Entite
    {
        $agentId = auth()->user()->agent?->id;
        if (! $agentId) {
            return null;
        }

        $dir = Entite::where('type', 'direction')->where('secretaire_agent_id', $agentId)->first();
        if (! $dir) {
            return null;
        }

        $estDestinataire = $this->courrier->imputations()
            ->whereHas('destinataires', fn ($q) => $q->where('entites.id', $dir->id))
            ->exists();

        return $estDestinataire ? $dir : null;
    }

    public function cascader()
    {
        $dir = $this->directionCascade();
        abort_unless($dir !== null, 403);

        $divisionsValides = Entite::where('type', 'division')->where('parent_id', $dir->id)->pluck('id')->all();

        $this->validate([
            'divisions' => ['array', 'min:1'],
            'divisions.*' => [Rule::in($divisionsValides)],
            'mentions_cascade' => ['array'],
            'mentions_cascade.*' => [Rule::in(array_keys(Imputation::MENTIONS))],
            'observations_cascade' => ['nullable', 'string', 'max:2000'],
        ], [], ['divisions' => 'divisions']);

        $imp = Imputation::create([
            'courrier_id' => $this->courrier->id,
            'niveau' => 'direction',
            'entite_source_id' => $dir->id,
            'mentions' => array_values($this->mentions_cascade),
            'observations' => $this->observations_cascade,
            'signataire_nom' => $dir->chef?->nomComplet(),
            'saisi_par' => auth()->id(),
        ]);
        $imp->destinataires()->sync($this->divisions);

        $this->reset(['divisions', 'mentions_cascade', 'observations_cascade']);
        session()->flash('ok', 'Cascade enregistrée.');
    }

    public function render()
    {
        $dir = $this->directionCascade();

        return view('livewire.courrier.courrier-entite', [
            'imputations' => $this->courrier->imputations()->with('destinataires', 'entiteSource')->get(),
            'directionCascade' => $dir,
            'divisionsDispo' => $dir ? Entite::where('type', 'division')->where('parent_id', $dir->id)->where('actif', true)->orderBy('code')->get() : collect(),
            'mentionsListe' => Imputation::MENTIONS,
        ]);
    }
}
```

- [ ] **Step 4: Ajouter le formulaire de cascade à la vue**

Modify `resources/views/livewire/courrier/courrier-entite.blade.php` — insérer ce bloc juste AVANT la carte « Historique d'imputation » :
```blade
    @if ($directionCascade && $divisionsDispo->isNotEmpty())
        <div class="card" style="padding:22px">
            <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 4px">Imputer aux divisions ({{ $directionCascade->code }})</h2>
            <p style="color:var(--muted);font-size:13px;margin:0 0 14px">Recopiez l'imputation de votre directeur vers les divisions.</p>
            <form wire:submit="cascader" style="display:flex;flex-direction:column;gap:16px">
                <div>
                    <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Divisions destinataires</span>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                        @foreach ($divisionsDispo as $d)
                            <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                                <input type="checkbox" wire:model="divisions" value="{{ $d->id }}"> {{ $d->code }} — {{ $d->nom }}
                            </label>
                        @endforeach
                    </div>
                    @error('divisions') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">Sélectionnez au moins une division.</span> @enderror
                </div>
                <div>
                    <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Soit transmis (mentions)</span>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
                        @foreach ($mentionsListe as $code => $libelle)
                            <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                                <input type="checkbox" wire:model="mentions_cascade" value="{{ $code }}"> {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Observations</label>
                    <textarea wire:model="observations_cascade" rows="2" class="field"></textarea></div>
                <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary">Enregistrer la cascade</button></div>
            </form>
        </div>
    @endif
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Courrier/CascadeTest.php`
Expected: PASS (2 tests). Vérifier aussi `./vendor/bin/pest tests/Feature/Courrier/ConsultationTest.php` (non-régression).

- [ ] **Step 6: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts.

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(courrier): cascade direction -> divisions (secretaire)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §7 accès (chef/secrétaire d'entité voient leurs courriers ; bureau/archiviste tout) via `CourrierPolicy` + `agentEntitesGereesIds` + scope `pourEntites` (Task 1) ✓ ; §10 écran « Mes courriers » + fiche (Task 2) ✓ ; §8 cascade direction→divisions par le secrétaire (imputation `niveau=direction`, `entite_source_id`, destinataires = divisions, signataire = chef de la direction) (Task 3) ✓. Archives + PDF = Plan 4.
- **Placeholders :** aucun.
- **Cohérence types :** `agentEntitesGereesIds(): array`, `Courrier::scopePourEntites(array)`, `CourrierPolicy::voir(User, Courrier)`, propriétés `divisions`/`mentions_cascade`/`observations_cascade` de `CourrierEntite`, imputation `niveau='direction'` + `entite_source_id` = la direction. Routes `mes-courriers`/`mes-courriers.fiche` cohérentes (nav, vues, tests). Réutilise `Imputation::MENTIONS`, `destinataires()`, `Entite` chef/secrétaire de P1/P2.

## Dépendances pour les plans suivants

Plan 4 (Archives + PDF) : rôle `archiviste` (recherche globale sur `Courrier`/`Imputation`) + génération PDF d'une `Imputation` en Fiche de Ventilation (niveaux dg/direction).
