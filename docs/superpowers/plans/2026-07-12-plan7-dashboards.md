# Plan 7 — Tableaux de bord (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fournir des tableaux de bord adaptés à chaque rôle : synthèse globale pour la DRHF, vue de synthèse en lecture seule pour le DG, et widgets contextuels (solde/demandes en cours pour l'agent, file à valider pour le chef).

**Architecture:** Un service d'agrégats pur et testable `App\Support\TableauBord` calcule les compteurs (demandes par statut, par type, par direction, files d'attente, taux d'absence). Les écrans (Livewire RH et DG) et les widgets du dashboard consomment ce service. Le DG a une vue dédiée en **lecture seule** (aucune action), protégée par `role:dg`.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind. S'appuie sur Plans 1-6 : `Demande` (statut, type, dates, agent()), `Agent` (direction_id, solde_conge_jours, demandes()), `Direction`, `User` (helpers de rôle), middleware `role`, `EtatCongesQuery`.

**Prérequis d'exécution:** créer la branche `feat/plan7-dashboards` **à partir de** `feat/plan2-auth` (contient Plans 1-6) :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/plan7-dashboards
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; tests HTTP rendant un layout `@vite` → `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** §6 (DG lecture seule, synthèse par direction, taux d'absence), §10 (écrans).

**Définitions:**
- **En attente RH** = demandes au statut `validee_chef`.
- **En attente chef (d'une direction)** = demandes `soumise` d'agents de cette direction.
- **En congé à une date** = agent ayant un `conge_annuel` `validee_rh` dont la période `[date_debut, date_fin]` contient la date de référence (défaut : aujourd'hui).
- **Taux d'absence d'une direction** = `en_conge / agents` × 100 (0 si aucun agent).

---

## Fichiers créés/modifiés dans ce plan

- `app/Support/TableauBord.php` — service d'agrégats.
- `app/Livewire/Rh/TableauBord.php` + `resources/views/livewire/rh/tableau-bord.blade.php` — dashboard DRHF.
- `app/Livewire/Dg/TableauBord.php` + `resources/views/livewire/dg/tableau-bord.blade.php` — dashboard DG (lecture seule).
- `routes/web.php` (modifié), `resources/views/dashboard.blade.php` (modifié : widgets agent/chef + liens RH/DG), `resources/views/components/layouts/rh.blade.php` (modifié : lien nav).
- `tests/Feature/Dashboards/*`.

---

## Task 1: Service d'agrégats `TableauBord`

**Files:**
- Create: `app/Support/TableauBord.php`
- Test: `tests/Feature/Dashboards/TableauBordTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Dashboards/TableauBordTest.php` (créer le dossier `tests/Feature/Dashboards/`):
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Support\TableauBord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedStats(): array
{
    $dg = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $doe = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $a1 = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dg->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    $a2 = Agent::create(['prenoms' => 'Modou', 'noms' => 'FALL', 'matricule' => 'A2', 'direction_id' => $dg->id, 'statut' => 'autre', 'solde_conge_jours' => 5]);
    $a3 = Agent::create(['prenoms' => 'Bou', 'noms' => 'NDOYE', 'matricule' => 'A3', 'direction_id' => $doe->id, 'statut' => 'autre', 'solde_conge_jours' => 20]);

    // 2 soumise (DG), 1 validee_chef, 1 validee_rh (congé couvrant le 2026-08-05), 1 permission validee_rh
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'soumise']);
    Demande::create(['agent_id' => $a2->id, 'type' => 'permission', 'date_debut' => '2026-08-02', 'date_fin' => '2026-08-02', 'nb_jours' => 1, 'statut' => 'soumise']);
    Demande::create(['agent_id' => $a3->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_chef']);
    Demande::create(['agent_id' => $a1->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'nb_jours' => 10, 'statut' => 'validee_rh']);
    Demande::create(['agent_id' => $a2->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'validee_rh']);

    return compact('dg', 'doe', 'a1', 'a2', 'a3');
}

it('compte les demandes par statut', function () {
    seedStats();

    $s = TableauBord::parStatut();

    expect($s['soumise'])->toBe(2);
    expect($s['validee_chef'])->toBe(1);
    expect($s['validee_rh'])->toBe(2);
    expect($s['refusee'])->toBe(0);
    expect($s['emise'])->toBe(0);
});

it('compte les demandes par type', function () {
    seedStats();

    $t = TableauBord::parType();

    expect($t['conge_annuel'])->toBe(3);
    expect($t['permission'])->toBe(2);
    expect($t['ordre_mission'])->toBe(0);
});

it('compte les files d’attente', function () {
    ['dg' => $dg] = seedStats();

    expect(TableauBord::enAttenteRh())->toBe(1); // validee_chef
    expect(TableauBord::enAttenteChef($dg->id))->toBe(2); // 2 soumise en DG
});

it('calcule le taux d’absence par direction à une date donnée', function () {
    ['dg' => $dg, 'doe' => $doe] = seedStats();

    $lignes = TableauBord::parDirection('2026-08-05');
    $dgLigne = $lignes->firstWhere('code', 'DG');
    $doeLigne = $lignes->firstWhere('code', 'DOE');

    // DG : a1 en congé validé le 05/08 (1 sur 2 agents) => 50 %
    expect($dgLigne['agents'])->toBe(2);
    expect($dgLigne['en_conge'])->toBe(1);
    expect($dgLigne['taux'])->toBe(50.0);

    // DOE : le congé de a3 est validee_chef (pas validee_rh) => 0 en congé
    expect($doeLigne['agents'])->toBe(1);
    expect($doeLigne['en_conge'])->toBe(0);
    expect($doeLigne['taux'])->toBe(0.0);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/TableauBordTest.php`
Expected: FAIL (classe absente).

- [ ] **Step 3: Écrire le service**

Create `app/Support/TableauBord.php`:
```php
<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use Illuminate\Support\Collection;

class TableauBord
{
    /** @return array<string,int> */
    public static function parStatut(): array
    {
        $counts = Demande::selectRaw('statut, count(*) as c')->groupBy('statut')->pluck('c', 'statut')->toArray();

        $out = [];
        foreach (['soumise', 'validee_chef', 'validee_rh', 'refusee', 'emise'] as $s) {
            $out[$s] = (int) ($counts[$s] ?? 0);
        }

        return $out;
    }

    /** @return array<string,int> */
    public static function parType(): array
    {
        $counts = Demande::selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type')->toArray();

        $out = [];
        foreach (Demande::TYPES as $t) {
            $out[$t] = (int) ($counts[$t] ?? 0);
        }

        return $out;
    }

    public static function enAttenteRh(): int
    {
        return Demande::where('statut', Demande::STATUT_VALIDEE_CHEF)->count();
    }

    public static function enAttenteChef(int $directionId): int
    {
        return Demande::where('statut', Demande::STATUT_SOUMISE)
            ->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId))
            ->count();
    }

    /**
     * @return Collection<int, array{code:string, nom:string, agents:int, en_conge:int, taux:float}>
     */
    public static function parDirection(?string $refDate = null): Collection
    {
        $ref = $refDate ?? now()->toDateString();

        return Direction::orderBy('code')->get()->map(function (Direction $dir) use ($ref) {
            $agents = Agent::where('direction_id', $dir->id)->count();

            $enConge = Demande::where('type', 'conge_annuel')
                ->where('statut', Demande::STATUT_VALIDEE_RH)
                ->whereDate('date_debut', '<=', $ref)
                ->whereDate('date_fin', '>=', $ref)
                ->whereHas('agent', fn ($a) => $a->where('direction_id', $dir->id))
                ->distinct('agent_id')
                ->count('agent_id');

            $taux = $agents > 0 ? round($enConge / $agents * 100, 1) : 0.0;

            return [
                'code' => $dir->code,
                'nom' => $dir->nom,
                'agents' => $agents,
                'en_conge' => $enConge,
                'taux' => $taux,
            ];
        });
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/TableauBordTest.php`
Expected: PASS (4).

- [ ] **Step 5: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: service d'agregats TableauBord"
```

---

## Task 2: Dashboard DRHF

**Files:**
- Create: `app/Livewire/Rh/TableauBord.php`, `resources/views/livewire/rh/tableau-bord.blade.php`
- Modify: `routes/web.php`, `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Dashboards/DashboardRhTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Dashboards/DashboardRhTest.php`:
```php
<?php

use App\Livewire\Rh\TableauBord;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('interdit le tableau de bord RH aux non admin_rh (403)', function () {
    $this->withoutVite();
    $agentUser = User::create(['name' => 'X', 'matricule' => 'X1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->actingAs($agentUser)->get('/rh/tableau-bord')->assertForbidden();
});

it('affiche les agrégats à la DRHF', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_chef']);

    Livewire::actingAs($rh)
        ->test(TableauBord::class)
        ->assertSee('En attente DRHF')
        ->assertSee('DG'); // ligne direction
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardRhTest.php`
Expected: FAIL (route/composant absents).

- [ ] **Step 3: Composant**

Create `app/Livewire/Rh/TableauBord.php`:
```php
<?php

namespace App\Livewire\Rh;

use App\Support\TableauBord as Agregats;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class TableauBord extends Component
{
    public function render()
    {
        return view('livewire.rh.tableau-bord', [
            'parStatut' => Agregats::parStatut(),
            'parType' => Agregats::parType(),
            'parDirection' => Agregats::parDirection(),
            'enAttenteRh' => Agregats::enAttenteRh(),
        ]);
    }
}
```

- [ ] **Step 4: Vue**

Create `resources/views/livewire/rh/tableau-bord.blade.php`:
```blade
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Tableau de bord — DRHF</h1>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="text-sm text-gray-500">En attente DRHF</div>
            <div class="text-2xl font-bold">{{ $enAttenteRh }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="text-sm text-gray-500">Soumises (attente chef)</div>
            <div class="text-2xl font-bold">{{ $parStatut['soumise'] }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="text-sm text-gray-500">Congés validés</div>
            <div class="text-2xl font-bold">{{ $parStatut['validee_rh'] }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="text-sm text-gray-500">Refusées</div>
            <div class="text-2xl font-bold">{{ $parStatut['refusee'] }}</div>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="mb-3 font-semibold">Par type de demande</h2>
            <ul class="space-y-1 text-sm">
                <li>Congé annuel : <strong>{{ $parType['conge_annuel'] }}</strong></li>
                <li>Permission : <strong>{{ $parType['permission'] }}</strong></li>
                <li>Ordre de mission : <strong>{{ $parType['ordre_mission'] }}</strong></li>
            </ul>
        </div>

        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="mb-3 font-semibold">Absences par direction (aujourd'hui)</h2>
            <table class="w-full text-left text-sm">
                <thead class="text-gray-500"><tr><th class="py-1">Direction</th><th>Agents</th><th>En congé</th><th>Taux</th></tr></thead>
                <tbody>
                    @foreach ($parDirection as $d)
                        <tr class="border-t">
                            <td class="py-1">{{ $d['code'] }}</td>
                            <td>{{ $d['agents'] }}</td>
                            <td>{{ $d['en_conge'] }}</td>
                            <td>{{ $d['taux'] }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Route + lien nav**

Modify `routes/web.php` — dans un groupe `['auth','role:admin_rh']`, ajouter :
```php
    Route::get('/rh/tableau-bord', \App\Livewire\Rh\TableauBord::class)->name('rh.tableau-bord');
```
Modify `resources/views/components/layouts/rh.blade.php` — dans la `<nav>`, ajouter en premier lien :
```blade
                <a href="{{ route('rh.tableau-bord') }}" class="hover:underline">Tableau de bord</a>
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardRhTest.php`
Expected: PASS (2).

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: tableau de bord DRHF"
```

---

## Task 3: Dashboard DG (lecture seule)

**Files:**
- Create: `app/Livewire/Dg/TableauBord.php`, `resources/views/livewire/dg/tableau-bord.blade.php`
- Modify: `routes/web.php`, `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Dashboards/DashboardDgTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Dashboards/DashboardDgTest.php`:
```php
<?php

use App\Livewire\Dg\TableauBord;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('interdit la vue DG aux non dg (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/dg')->assertForbidden();
});

it('affiche la synthèse au DG en lecture seule', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $dg = User::create(['name' => 'DG', 'matricule' => 'DG1', 'password' => bcrypt('s'), 'role' => 'dg']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => 'validee_rh']);

    Livewire::actingAs($dg)
        ->test(TableauBord::class)
        ->assertSee('Synthèse')
        ->assertSee('DG')
        ->assertDontSee('Valider'); // aucune action de validation
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardDgTest.php`
Expected: FAIL (route/composant absents).

- [ ] **Step 3: Composant**

Create `app/Livewire/Dg/TableauBord.php`:
```php
<?php

namespace App\Livewire\Dg;

use App\Support\TableauBord as Agregats;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class TableauBord extends Component
{
    public function render()
    {
        return view('livewire.dg.tableau-bord', [
            'parStatut' => Agregats::parStatut(),
            'parType' => Agregats::parType(),
            'parDirection' => Agregats::parDirection(),
        ]);
    }
}
```

- [ ] **Step 4: Vue (lecture seule, sans bouton d'action)**

Create `resources/views/livewire/dg/tableau-bord.blade.php`:
```blade
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Synthèse — Direction Générale</h1>
    <p class="text-sm text-gray-500">Vue de consultation (lecture seule).</p>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow"><div class="text-sm text-gray-500">Congés validés</div><div class="text-2xl font-bold">{{ $parStatut['validee_rh'] }}</div></div>
        <div class="rounded-lg bg-white p-4 shadow"><div class="text-sm text-gray-500">En attente DRHF</div><div class="text-2xl font-bold">{{ $parStatut['validee_chef'] }}</div></div>
        <div class="rounded-lg bg-white p-4 shadow"><div class="text-sm text-gray-500">En attente chef</div><div class="text-2xl font-bold">{{ $parStatut['soumise'] }}</div></div>
        <div class="rounded-lg bg-white p-4 shadow"><div class="text-sm text-gray-500">Ordres de mission</div><div class="text-2xl font-bold">{{ $parType['ordre_mission'] }}</div></div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-3 font-semibold">Absences par direction (aujourd'hui)</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-gray-500"><tr><th class="py-1">Direction</th><th>Agents</th><th>En congé</th><th>Taux</th></tr></thead>
            <tbody>
                @foreach ($parDirection as $d)
                    <tr class="border-t">
                        <td class="py-1">{{ $d['code'] }} — {{ $d['nom'] }}</td>
                        <td>{{ $d['agents'] }}</td>
                        <td>{{ $d['en_conge'] }}</td>
                        <td>{{ $d['taux'] }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Route + lien dashboard**

Modify `routes/web.php` — ajouter un groupe :
```php
Route::middleware(['auth', 'role:dg'])->group(function () {
    Route::get('/dg', \App\Livewire\Dg\TableauBord::class)->name('dg.tableau-bord');
});
```
Modify `resources/views/dashboard.blade.php` — dans le bloc des liens, ajouter :
```blade
                @if (auth()->user()->isDg())
                    <a href="{{ route('dg.tableau-bord') }}" class="rounded bg-emerald-700 px-4 py-2 text-white">Synthèse (DG)</a>
                @endif
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardDgTest.php`
Expected: PASS (2).

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: tableau de bord DG (lecture seule)"
```

---

## Task 4: Widgets agent & chef sur le dashboard

Enrichit le dashboard commun : l'agent voit son solde et le nombre de ses demandes en cours ; le chef voit le nombre de demandes à valider dans sa direction. Les liens RH/DRHF/chef existent déjà (Plan 4) ; on ajoute les compteurs.

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Dashboards/DashboardWidgetsTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Dashboards/DashboardWidgetsTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('affiche le solde et le nombre de demandes en cours pour l’agent', function () {
    $this->withoutVite();
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 17, 'user_id' => $user->id]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Solde de congé')
        ->assertSee('17')
        ->assertSee('Demandes en cours');
});

it('affiche le nombre de demandes à valider pour le chef', function () {
    $this->withoutVite();
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 10]);
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $this->actingAs($chefUser)->get('/dashboard')
        ->assertOk()
        ->assertSee('À valider');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardWidgetsTest.php`
Expected: FAIL (widgets absents).

- [ ] **Step 3: Enrichir la vue dashboard**

Modify `resources/views/dashboard.blade.php` — insérer ce bloc de widgets juste APRÈS le paragraphe `Rôle : ...` et AVANT le bloc des liens (`<div class="mt-4 flex flex-wrap gap-3 ...">`). Le code utilise l'agent lié et le service d'agrégats :
```blade
        @php($__agent = auth()->user()->agent)
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @if ($__agent)
                <div class="rounded-lg border p-4">
                    <div class="text-sm text-gray-500">Solde de congé</div>
                    <div class="text-xl font-bold">{{ $__agent->solde_conge_jours }} jours</div>
                </div>
                <div class="rounded-lg border p-4">
                    <div class="text-sm text-gray-500">Demandes en cours</div>
                    <div class="text-xl font-bold">{{ $__agent->demandes()->whereIn('statut', ['soumise', 'validee_chef'])->count() }}</div>
                </div>
            @endif

            @if (auth()->user()->isChefDirection() && $__agent)
                <div class="rounded-lg border p-4">
                    <div class="text-sm text-gray-500">À valider (ma direction)</div>
                    <div class="text-xl font-bold">{{ \App\Support\TableauBord::enAttenteChef($__agent->direction_id) }}</div>
                </div>
            @endif
        </div>
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Dashboards/DashboardWidgetsTest.php`
Expected: PASS (2).

- [ ] **Step 5: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plans 1-7).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: widgets dashboard agent (solde/demandes) + chef (a valider)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §6 DG lecture seule (Task 3, vue sans action) ✓ ; synthèse par direction + taux d'absence (Task 1 `parDirection`, exposé Tasks 2-3) ✓ ; §10 dashboards agent/chef/RH/DG (Tasks 2-4) ✓. Portée par rôle : `role:admin_rh` (Task 2), `role:dg` (Task 3), widgets agent/chef sur le dashboard commun (Task 4).
- **Placeholders :** aucun. Chaque étape contient le code réel.
- **Cohérence types :** `TableauBord::parStatut(): array`, `parType(): array`, `enAttenteRh(): int`, `enAttenteChef(int): int`, `parDirection(?string): Collection` — signatures identiques entre service (Task 1) et consommateurs (Tasks 2-4). Statuts/types = constantes `Demande::*`. Routes `rh.tableau-bord`, `dg.tableau-bord` nommées identiquement (vues, tests, dashboard). Deux composants homonymes distincts par namespace : `App\Livewire\Rh\TableauBord` et `App\Livewire\Dg\TableauBord`.

## Clôture v1

Ce plan termine la roadmap v1 (Plans 1-7). Suites possibles (hors périmètre) : notifications email/SMS, congés exceptionnels (maternité, événements familiaux, maladie), UI de configuration du DG (`dge_nom`/`dge_fonction`), gestion fine du reliquat de congé.
