# Plan 5 — Ordres de mission & Secrétaire (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Doter chaque direction d'un rôle **secrétaire** qui initie des ordres de mission pour les agents de sa direction (sans workflow de validation) et les imprime en PDF conforme au modèle officiel, avec un ou deux signataires (Directeur Général et/ou Directeur de la direction).

**Architecture:** Nouveau rôle `secretaire` ajouté à l'enum `users.role`. La logique d'initiation vit dans une action testable `InitierMission` (contrôle d'autorisation : le secrétaire ne peut créer que pour un agent de **sa** direction ; statut `emise` ; pas de workflow). Les champs spécifiques du modèle (destination, moyen de transport, indice, groupe, imputation, chapitre, article) et le choix des signataires sont stockés dans `demandes.meta`. Le PDF est rendu par `barryvdh/laravel-dompdf` depuis une vue Blade calquée sur le modèle ; les signataires sont résolus à l'impression (DG depuis la config, Directeur depuis le chef de la direction).

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind, **barryvdh/laravel-dompdf**. S'appuie sur Plans 1-4 : `Demande` (type `ordre_mission`, statut `emise`, meta json), `Agent`/`Direction` (`chef_id`), `User` (`role`, helpers), middleware `role`, commande `rh:promouvoir`, `Rh\AgentForm` (attribution de rôle).

**Prérequis d'exécution:** créer la branche `feat/plan5-missions` **à partir de** `feat/plan2-auth` (qui contient Plans 1-4 mergés) :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/plan5-missions
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif — l'ignorer. Tests sur SQLite in-memory ; les tests HTTP qui rendent un layout `@vite` appellent `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence:** modèle `docs/references/modeles/Ordre de Mission DGE.docx` + mapping `docs/references/modeles/README.md`. Spec §11.

**Règles métier verrouillées:**
- Rôle `secretaire` (5e rôle), rattaché à **sa** direction (`agent.direction_id`). La RH l'attribue via la fiche agent (`Rh\AgentForm`) ou `rh:promouvoir`.
- Le secrétaire initie un ordre de mission **pour un agent de sa direction** ; statut direct `emise` ; **pas de workflow**.
- Signataires : 1 ou 2 parmi `dg` (Directeur Général) et `directeur` (chef de la direction concernée). Stockés dans `meta['signataires']` (tableau de codes).
- Nom/fonction du DG : config `dge.dg_nom` / `dge.dg_fonction` (env `DGE_DG_NOM` / `DGE_DG_FONCTION`).
- PDF conforme au modèle ; imprimable uniquement par le secrétaire de la direction de la mission.

---

## Fichiers créés/modifiés dans ce plan

- `database/migrations/*_add_secretaire_to_users_role.php` — enum role + `secretaire`.
- `config/dge.php` — nom/fonction du DG.
- `app/Models/User.php` — helper `isSecretaire()` (modifié).
- `app/Console/Commands/PromouvoirAgent.php` — ajouter `secretaire` à la liste blanche (modifié).
- `app/Livewire/Rh/AgentForm.php` + vue — ajouter `secretaire` aux rôles attribuables (modifié).
- `app/Actions/InitierMission.php` — création d'un ordre de mission.
- `app/Livewire/Missions/NouvelleMission.php` + vue ; `app/Livewire/Missions/MesMissions.php` + vue.
- `app/Http/Controllers/OrdreMissionPdfController.php` + `resources/views/pdf/ordre-mission.blade.php` — impression PDF.
- `routes/web.php` (modifié), `resources/views/dashboard.blade.php` (modifié : lien secrétaire).
- `tests/Feature/Missions/*`.

---

## Task 1: Rôle `secretaire` activé dans toute l'app + config DG

**Files:**
- Create: `database/migrations/2026_07_12_020000_add_secretaire_to_users_role.php`
- Create: `config/dge.php`
- Modify: `app/Models/User.php`, `app/Console/Commands/PromouvoirAgent.php`, `app/Livewire/Rh/AgentForm.php`, `resources/views/livewire/rh/agent-form.blade.php`
- Test: `tests/Feature/Missions/RoleSecretaireTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Missions/RoleSecretaireTest.php` (créer le dossier `tests/Feature/Missions/`):
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepte le rôle secretaire et son helper', function () {
    $user = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);

    expect($user->fresh()->role)->toBe('secretaire');
    expect($user->isSecretaire())->toBeTrue();
    expect($user->isAgent())->toBeFalse();
});

it('promeut un compte en secretaire via la commande', function () {
    User::create(['name' => 'X', 'matricule' => 'M1', 'password' => bcrypt('s'), 'role' => 'agent']);

    $this->artisan('rh:promouvoir', ['matricule' => 'M1', 'role' => 'secretaire'])->assertExitCode(0);

    expect(User::where('matricule', 'M1')->first()->role)->toBe('secretaire');
});

it('expose la configuration du DG', function () {
    expect(config('dge.dg_nom'))->not->toBeNull();
    expect(config('dge.dg_fonction'))->not->toBeNull();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/RoleSecretaireTest.php`
Expected: FAIL (enum n'accepte pas `secretaire` / helper absent / config absente).

- [ ] **Step 3: Migration d'ajout de la valeur d'enum**

Create `database/migrations/2026_07_12_020000_add_secretaire_to_users_role.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire'])
                ->default('agent')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg'])
                ->default('agent')->change();
        });
    }
};
```
Note : `->change()` fonctionne nativement en Laravel 12 (déjà utilisé en Plan 2). Sur SQLite (tests) il reconstruit la table avec la nouvelle contrainte ; sur MySQL il émet un `MODIFY`.

- [ ] **Step 4: Config DG**

Create `config/dge.php`:
```php
<?php

return [
    'dg_nom' => env('DGE_DG_NOM', 'Le Directeur Général'),
    'dg_fonction' => env('DGE_DG_FONCTION', 'Directeur Général des Élections'),
];
```

- [ ] **Step 5: Helper `isSecretaire()` sur User**

Modify `app/Models/User.php` — ajouter dans la classe :
```php
public function isSecretaire(): bool
{
    return $this->role === 'secretaire';
}
```

- [ ] **Step 6: Liste blanche de `rh:promouvoir`**

Modify `app/Console/Commands/PromouvoirAgent.php` — mettre à jour la constante :
```php
    private const ROLES = ['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire'];
```

- [ ] **Step 7: Rôle attribuable depuis la fiche agent**

Modify `app/Livewire/Rh/AgentForm.php` — dans `rules()`, la règle `role` doit inclure `secretaire` :
```php
            'role' => [
                'nullable',
                \Illuminate\Validation\Rule::in(['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire']),
            ],
```
Modify `resources/views/livewire/rh/agent-form.blade.php` — dans le `<select wire:model="role">`, ajouter l'option :
```blade
                    <option value="secretaire">Secrétaire</option>
```
(la placer après l'option `dg`).

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/RoleSecretaireTest.php`
Expected: PASS (3). Lancer aussi `./vendor/bin/pest tests/Feature/Rh tests/Feature/PromouvoirAgentTest.php` pour vérifier la non-régression des tests RH.

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: role secretaire (enum, helper, promotion, config DG)"
```

---

## Task 2: Action `InitierMission`

Le secrétaire crée un ordre de mission (`type = ordre_mission`, `statut = emise`) pour un agent de **sa** direction. Champs template dans `meta`, signataires validés (1-2 parmi `dg`/`directeur`).

**Files:**
- Create: `app/Actions/InitierMission.php`
- Test: `tests/Feature/Missions/InitierMissionTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Missions/InitierMissionTest.php`:
```php
<?php

use App\Actions\InitierMission;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function ctxMission(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $agent = Agent::create(['prenoms' => 'Papa Ibrahima', 'noms' => 'NIANG', 'matricule' => '710.231/F', 'direction_id' => $dir->id, 'statut' => 'police', 'fonction' => 'Agent DGE', 'solde_conge_jours' => 20]);

    return compact('dir', 'secUser', 'secAgent', 'agent');
}

it('crée un ordre de mission emise pour un agent de la direction', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    $meta = ['destination' => 'Dakar – Mbour – Dakar', 'moyen_transport' => 'AD 31057', 'indice' => '', 'groupe' => '', 'imputation' => '', 'chapitre' => '', 'article' => ''];

    $demande = app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', 'Mission DGE', $meta, ['dg']);

    expect($demande->type)->toBe('ordre_mission');
    expect($demande->statut)->toBe(Demande::STATUT_EMISE);
    expect($demande->agent_id)->toBe($agent->id);
    expect($demande->meta['destination'])->toBe('Dakar – Mbour – Dakar');
    expect($demande->meta['signataires'])->toBe(['dg']);
});

it('accepte deux signataires', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    $demande = app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', 'Mission', [], ['dg', 'directeur']);

    expect($demande->meta['signataires'])->toBe(['dg', 'directeur']);
});

it('refuse un secrétaire pour un agent d’une autre direction', function () {
    ['secUser' => $sec] = ctxMission();
    $autre = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $agentAutre = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autre->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    expect(fn () => app(InitierMission::class)->handle($sec, $agentAutre, '2026-06-14', '2026-06-16', null, [], ['dg']))
        ->toThrow(AuthorizationException::class);
    expect(Demande::count())->toBe(0);
});

it('refuse un utilisateur non secrétaire', function () {
    ['agent' => $agent] = ctxMission();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    expect(fn () => app(InitierMission::class)->handle($rh, $agent, '2026-06-14', '2026-06-16', null, [], ['dg']))
        ->toThrow(AuthorizationException::class);
});

it('refuse une liste de signataires vide ou invalide', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxMission();

    expect(fn () => app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', null, [], []))
        ->toThrow(ValidationException::class);
    expect(fn () => app(InitierMission::class)->handle($sec, $agent, '2026-06-14', '2026-06-16', null, [], ['roi']))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/InitierMissionTest.php`
Expected: FAIL (action absente).

- [ ] **Step 3: Écrire l'action**

Create `app/Actions/InitierMission.php`:
```php
<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InitierMission
{
    public const SIGNATAIRES = ['dg', 'directeur'];

    public function handle(
        User $secretaire,
        Agent $agent,
        string $dateDebut,
        string $dateFin,
        ?string $motif,
        array $meta,
        array $signataires,
    ): Demande {
        if (! $secretaire->isSecretaire() || ! $secretaire->agent) {
            throw new AuthorizationException("Réservé au secrétaire de direction.");
        }

        if ($agent->direction_id !== $secretaire->agent->direction_id) {
            throw new AuthorizationException("Cet agent n'appartient pas à votre direction.");
        }

        $signataires = array_values(array_unique($signataires));
        if ($signataires === [] || count($signataires) > 2 || array_diff($signataires, self::SIGNATAIRES) !== []) {
            throw ValidationException::withMessages([
                'signataires' => "Choisissez 1 ou 2 signataires parmi : Directeur Général, Directeur de la direction.",
            ]);
        }

        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();
        if ($fin->lt($debut)) {
            throw ValidationException::withMessages([
                'date_fin' => "La date de retour doit être postérieure ou égale à la date de départ.",
            ]);
        }

        $meta['signataires'] = $signataires;

        return Demande::create([
            'agent_id' => $agent->id,
            'type' => 'ordre_mission',
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'nb_jours' => $debut->diffInDays($fin) + 1,
            'motif' => $motif,
            'statut' => Demande::STATUT_EMISE,
            'meta' => $meta,
        ]);
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/InitierMissionTest.php`
Expected: PASS (5).

- [ ] **Step 5: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: action InitierMission (ordre de mission par secretaire)"
```

---

## Task 3: UI secrétaire — nouvelle mission + liste

**Files:**
- Create: `app/Livewire/Missions/NouvelleMission.php`, `resources/views/livewire/missions/nouvelle-mission.blade.php`
- Create: `app/Livewire/Missions/MesMissions.php`, `resources/views/livewire/missions/mes-missions.blade.php`
- Modify: `routes/web.php`, `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Missions/MissionUiTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Missions/MissionUiTest.php`:
```php
<?php

use App\Livewire\Missions\MesMissions;
use App\Livewire\Missions\NouvelleMission;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxUiMission(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $agent = Agent::create(['prenoms' => 'Papa', 'noms' => 'NIANG', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20]);

    return compact('dir', 'secUser', 'agent');
}

it('interdit l’espace missions aux non secrétaires (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/missions')->assertForbidden();
});

it('le secrétaire crée un ordre de mission', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();

    Livewire::actingAs($sec)
        ->test(NouvelleMission::class)
        ->set('agent_id', $agent->id)
        ->set('date_debut', '2026-06-14')
        ->set('date_fin', '2026-06-16')
        ->set('motif', 'Mission DGE')
        ->set('destination', 'Dakar – Mbour – Dakar')
        ->set('moyen_transport', 'AD 31057')
        ->set('signataires', ['dg'])
        ->call('creer')
        ->assertRedirect(route('missions.mes'));

    $demande = Demande::where('agent_id', $agent->id)->first();
    expect($demande->statut)->toBe(Demande::STATUT_EMISE);
    expect($demande->meta['destination'])->toBe('Dakar – Mbour – Dakar');
});

it('exige au moins un signataire', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();

    Livewire::actingAs($sec)
        ->test(NouvelleMission::class)
        ->set('agent_id', $agent->id)
        ->set('date_debut', '2026-06-14')
        ->set('date_fin', '2026-06-16')
        ->set('signataires', [])
        ->call('creer')
        ->assertHasErrors('signataires');

    expect(Demande::count())->toBe(0);
});

it('liste les missions de la direction du secrétaire', function () {
    ['secUser' => $sec, 'agent' => $agent] = ctxUiMission();
    Demande::create(['agent_id' => $agent->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'MAMISSION', 'meta' => ['signataires' => ['dg']]]);
    // mission d'une autre direction
    $autre = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $agentAutre = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autre->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $agentAutre->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'AUTREMISSION', 'meta' => ['signataires' => ['dg']]]);

    Livewire::actingAs($sec)
        ->test(MesMissions::class)
        ->assertSee('MAMISSION')
        ->assertDontSee('AUTREMISSION');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/MissionUiTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Composant NouvelleMission**

Create `app/Livewire/Missions/NouvelleMission.php`:
```php
<?php

namespace App\Livewire\Missions;

use App\Actions\InitierMission;
use App\Models\Agent;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class NouvelleMission extends Component
{
    public ?int $agent_id = null;
    public ?string $date_debut = null;
    public ?string $date_fin = null;
    public ?string $motif = null;
    public ?string $destination = null;
    public ?string $moyen_transport = null;
    public ?string $indice = null;
    public ?string $groupe = null;
    public ?string $imputation = null;
    public ?string $chapitre = null;
    public ?string $article = null;
    public array $signataires = [];

    public function creer(InitierMission $action)
    {
        $this->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
            'destination' => ['nullable', 'string', 'max:255'],
            'moyen_transport' => ['nullable', 'string', 'max:255'],
            'indice' => ['nullable', 'string', 'max:50'],
            'groupe' => ['nullable', 'string', 'max:50'],
            'imputation' => ['nullable', 'string', 'max:255'],
            'chapitre' => ['nullable', 'string', 'max:50'],
            'article' => ['nullable', 'string', 'max:50'],
            'signataires' => ['array'],
        ]);

        $agent = Agent::findOrFail($this->agent_id);

        $meta = array_filter([
            'destination' => $this->destination,
            'moyen_transport' => $this->moyen_transport,
            'indice' => $this->indice,
            'groupe' => $this->groupe,
            'imputation' => $this->imputation,
            'chapitre' => $this->chapitre,
            'article' => $this->article,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $action->handle(auth()->user(), $agent, $this->date_debut, $this->date_fin, $this->motif, $meta, $this->signataires);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ, $messages[0]);
            }

            return;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->addError('agent_id', $e->getMessage());

            return;
        }

        session()->flash('ok', 'Ordre de mission créé.');

        return redirect()->route('missions.mes');
    }

    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        return view('livewire.missions.nouvelle-mission', [
            'agents' => Agent::where('direction_id', $directionId)->orderBy('noms')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue NouvelleMission**

Create `resources/views/livewire/missions/nouvelle-mission.blade.php`:
```blade
<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 text-xl font-semibold">Nouvel ordre de mission</h1>

    <form wire:submit="creer" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium">Agent (missionnaire)</label>
            <select wire:model="agent_id" class="mt-1 w-full rounded border-gray-300">
                <option value="">— choisir —</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }} — {{ $a->matricule ?? 'sans matricule' }}</option>
                @endforeach
            </select>
            @error('agent_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium">Date de départ</label>
                <input type="date" wire:model="date_debut" class="mt-1 w-full rounded border-gray-300">
                @error('date_debut') <span class="text-sm text-red-600">{{ $message }}</span> @enderror</div>
            <div><label class="block text-sm font-medium">Date de retour</label>
                <input type="date" wire:model="date_fin" class="mt-1 w-full rounded border-gray-300">
                @error('date_fin') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                @error('date_fin') @enderror</div>
        </div>

        <div><label class="block text-sm font-medium">Se rendre à (destination)</label>
            <input type="text" wire:model="destination" class="mt-1 w-full rounded border-gray-300"></div>

        <div><label class="block text-sm font-medium">Motif</label>
            <textarea wire:model="motif" rows="2" class="mt-1 w-full rounded border-gray-300"></textarea></div>

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium">Moyen de transport</label>
                <input type="text" wire:model="moyen_transport" class="mt-1 w-full rounded border-gray-300"></div>
            <div><label class="block text-sm font-medium">Imputation budgétaire</label>
                <input type="text" wire:model="imputation" class="mt-1 w-full rounded border-gray-300"></div>
        </div>

        <div class="grid grid-cols-4 gap-4">
            <div><label class="block text-sm font-medium">Indice</label>
                <input type="text" wire:model="indice" class="mt-1 w-full rounded border-gray-300"></div>
            <div><label class="block text-sm font-medium">Groupe</label>
                <input type="text" wire:model="groupe" class="mt-1 w-full rounded border-gray-300"></div>
            <div><label class="block text-sm font-medium">Chapitre</label>
                <input type="text" wire:model="chapitre" class="mt-1 w-full rounded border-gray-300"></div>
            <div><label class="block text-sm font-medium">Article</label>
                <input type="text" wire:model="article" class="mt-1 w-full rounded border-gray-300"></div>
        </div>

        <div>
            <span class="block text-sm font-medium">Signataire(s)</span>
            <label class="mr-4 inline-flex items-center gap-2"><input type="checkbox" wire:model="signataires" value="dg"> Directeur Général</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="signataires" value="directeur"> Directeur de la direction</label>
            @error('signataires') <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('missions.mes') }}" class="rounded border px-4 py-2 text-sm">Annuler</a>
            <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Créer</button>
        </div>
    </form>
</div>
```

- [ ] **Step 5: Composant MesMissions**

Create `app/Livewire/Missions/MesMissions.php`:
```php
<?php

namespace App\Livewire\Missions;

use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class MesMissions extends Component
{
    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        $missions = Demande::query()
            ->with('agent')
            ->where('type', 'ordre_mission')
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when(! $directionId, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return view('livewire.missions.mes-missions', ['missions' => $missions]);
    }
}
```

- [ ] **Step 6: Vue MesMissions**

Create `resources/views/livewire/missions/mes-missions.blade.php`:
```blade
<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Ordres de mission</h1>
        <a href="{{ route('missions.nouvelle') }}" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">+ Nouvel ordre</a>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Agent</th><th class="p-3">Destination</th><th class="p-3">Période</th><th class="p-3">Motif</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($missions as $m)
                    <tr class="border-t">
                        <td class="p-3">{{ $m->agent->prenoms }} {{ $m->agent->noms }}</td>
                        <td class="p-3">{{ $m->meta['destination'] ?? '—' }}</td>
                        <td class="p-3">{{ $m->date_debut->format('d/m/Y') }} → {{ $m->date_fin->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $m->motif ?? '—' }}</td>
                        <td class="p-3"><a href="{{ route('missions.imprimer', $m) }}" target="_blank" class="text-emerald-700 underline">Imprimer (PDF)</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Aucun ordre de mission.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 7: Routes secrétaire + lien dashboard**

Modify `routes/web.php` — ajouter un groupe :
```php
Route::middleware(['auth', 'role:secretaire'])->group(function () {
    Route::get('/missions', \App\Livewire\Missions\MesMissions::class)->name('missions.mes');
    Route::get('/missions/nouvelle', \App\Livewire\Missions\NouvelleMission::class)->name('missions.nouvelle');
});
```
(La route `missions.imprimer` est ajoutée en Task 4.)

Modify `resources/views/dashboard.blade.php` — dans le bloc des liens, ajouter :
```blade
                @if (auth()->user()->isSecretaire())
                    <a href="{{ route('missions.mes') }}" class="rounded bg-emerald-700 px-4 py-2 text-white">Ordres de mission</a>
                @endif
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/MissionUiTest.php`
Expected: PASS (4). Note : `missions.imprimer` est référencée dans la vue `mes-missions` mais Task 3 ne la teste pas via un rendu de layout complet (les tests `Livewire::test` ne rendent pas les liens vers des routes inexistantes de façon bloquante) — si `assertSee` déclenche une `RouteNotFoundException` sur `route('missions.imprimer', ...)`, créer d'abord un stub de route dans le groupe secrétaire :
```php
    Route::get('/missions/{demande}/imprimer', fn () => abort(501))->name('missions.imprimer');
```
puis le remplacer par le vrai contrôleur en Task 4. Faire ce stub maintenant pour que le rendu de `mes-missions` fonctionne.

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: UI secretaire nouvelle mission + liste"
```

---

## Task 4: Impression PDF de l'ordre de mission

**Files:**
- Create: `app/Http/Controllers/OrdreMissionPdfController.php`
- Create: `resources/views/pdf/ordre-mission.blade.php`
- Modify: `routes/web.php` (remplacer le stub `missions.imprimer`)
- Test: `tests/Feature/Missions/OrdreMissionPdfTest.php`

- [ ] **Step 1: Installer dompdf**

Run:
```bash
cd /Users/admin/dge-rh-platform
composer require barryvdh/laravel-dompdf
```
Expected: package installé (facade `Barryvdh\DomPDF\Facade\Pdf`, auto-découvert).

- [ ] **Step 2: Écrire le test qui échoue**

Create `tests/Feature/Missions/OrdreMissionPdfTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctxPdf(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $secUser = User::create(['name' => 'Sec', 'matricule' => 'SEC1', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    $secAgent = Agent::create(['prenoms' => 'Sec', 'noms' => 'RETARY', 'matricule' => 'SEC1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $secUser->id]);
    $chef = Agent::create(['prenoms' => 'Le', 'noms' => 'DIRECTEUR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'fonction' => 'Directeur', 'solde_conge_jours' => 0]);
    $dir->update(['chef_id' => $chef->id]);
    $agent = Agent::create(['prenoms' => 'Papa Ibrahima', 'noms' => 'NIANG', 'matricule' => '710.231/F', 'direction_id' => $dir->id, 'statut' => 'police', 'fonction' => 'Agent DGE', 'solde_conge_jours' => 20]);
    $mission = Demande::create(['agent_id' => $agent->id, 'type' => 'ordre_mission', 'date_debut' => '2026-06-14', 'date_fin' => '2026-06-16', 'nb_jours' => 3, 'statut' => 'emise', 'motif' => 'Mission DGE', 'meta' => ['destination' => 'Dakar – Mbour – Dakar', 'moyen_transport' => 'AD 31057', 'signataires' => ['dg', 'directeur']]]);

    return compact('dir', 'secUser', 'agent', 'chef', 'mission');
}

it('le secrétaire télécharge le PDF de l’ordre de mission', function () {
    ['secUser' => $sec, 'mission' => $mission] = ctxPdf();

    $response = $this->actingAs($sec)->get(route('missions.imprimer', $mission));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('interdit l’impression à un secrétaire d’une autre direction', function () {
    ['mission' => $mission] = ctxPdf();
    $autreDir = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $autreSecU = User::create(['name' => 'Sec2', 'matricule' => 'SEC2', 'password' => bcrypt('s'), 'role' => 'secretaire']);
    Agent::create(['prenoms' => 'Sec', 'noms' => 'DEUX', 'matricule' => 'SEC2', 'direction_id' => $autreDir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'user_id' => $autreSecU->id]);

    $this->actingAs($autreSecU)->get(route('missions.imprimer', $mission))->assertForbidden();
});

it('la vue PDF contient les champs et les signataires résolus', function () {
    ['mission' => $mission, 'agent' => $agent, 'chef' => $chef] = ctxPdf();

    $signataires = app(\App\Http\Controllers\OrdreMissionPdfController::class)->resoudreSignataires($mission);
    $html = view('pdf.ordre-mission', ['m' => $mission, 'agent' => $agent, 'signataires' => $signataires])->render();

    expect($html)->toContain('Papa Ibrahima')->toContain('NIANG');
    expect($html)->toContain('Dakar – Mbour – Dakar');
    expect($html)->toContain(config('dge.dg_fonction')); // signataire DG
    expect($html)->toContain('DIRECTEUR'); // signataire directeur (chef)
});
```

- [ ] **Step 3: Contrôleur PDF**

Create `app/Http/Controllers/OrdreMissionPdfController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class OrdreMissionPdfController extends Controller
{
    public function __invoke(Demande $demande)
    {
        abort_unless($demande->type === 'ordre_mission', 404);

        $demande->loadMissing('agent.direction.chef', 'agent');

        // Le secrétaire ne peut imprimer que les missions de sa direction
        $directionId = Auth::user()->agent?->direction_id;
        abort_unless($directionId && $demande->agent->direction_id === $directionId, 403);

        $pdf = Pdf::loadView('pdf.ordre-mission', [
            'm' => $demande,
            'agent' => $demande->agent,
            'signataires' => $this->resoudreSignataires($demande),
        ]);

        return $pdf->stream("ordre-mission-{$demande->id}.pdf");
    }

    /**
     * Résout les codes de signataires en nom + fonction.
     *
     * @return array<int, array{nom: string, fonction: string}>
     */
    public function resoudreSignataires(Demande $demande): array
    {
        $codes = $demande->meta['signataires'] ?? [];
        $resolus = [];

        foreach ($codes as $code) {
            if ($code === 'dg') {
                $resolus[] = [
                    'nom' => config('dge.dg_nom'),
                    'fonction' => config('dge.dg_fonction'),
                ];
            } elseif ($code === 'directeur') {
                $chef = $demande->agent->direction?->chef;
                $resolus[] = [
                    'nom' => $chef ? $chef->nomComplet() : '',
                    'fonction' => $chef?->fonction ?: ('Directeur de '.($demande->agent->direction?->nom ?? '')),
                ];
            }
        }

        return $resolus;
    }
}
```

- [ ] **Step 4: Vue PDF (calquée sur le modèle)**

Create `resources/views/pdf/ordre-mission.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #000; }
        .entete { text-align: center; margin-bottom: 8px; }
        .titre { text-align: center; font-weight: bold; font-size: 16px; letter-spacing: 1px; margin: 18px 0; }
        .ligne { margin: 10px 0; border-bottom: 1px dotted #333; padding-bottom: 2px; }
        .label { font-weight: bold; }
        .signatures { margin-top: 50px; width: 100%; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding-top: 10px; }
        .lieu { margin-top: 40px; text-align: right; }
    </style>
</head>
<body>
    <div class="entete">
        RÉPUBLIQUE DU SÉNÉGAL<br>
        Un Peuple – Un But – Une Foi<br>
        MINISTÈRE DE L'INTÉRIEUR ET DE LA SÉCURITÉ PUBLIQUE<br>
        DIRECTION GÉNÉRALE DES ÉLECTIONS
    </div>

    <div class="titre">ORDRE DE MISSION</div>

    <div class="ligne"><span class="label">Prénom et nom :</span> {{ $agent->prenoms }} {{ $agent->noms }}
        &nbsp;&nbsp;<span class="label">Matricule :</span> {{ $agent->matricule ?? '' }}</div>
    <div class="ligne"><span class="label">Fonction :</span> {{ $agent->fonction ?? '' }}</div>
    <div class="ligne"><span class="label">Indice :</span> {{ $m->meta['indice'] ?? '' }}
        &nbsp;&nbsp;<span class="label">Groupe :</span> {{ $m->meta['groupe'] ?? '' }}</div>
    <div class="ligne"><span class="label">Se rendre à :</span> {{ $m->meta['destination'] ?? '' }}</div>
    <div class="ligne"><span class="label">Motif :</span> {{ $m->motif ?? '' }}</div>
    <div class="ligne"><span class="label">Date de départ :</span> {{ $m->date_debut->format('d/m/Y') }}
        &nbsp;&nbsp;<span class="label">Date de retour :</span> {{ $m->date_fin->format('d/m/Y') }}</div>
    <div class="ligne"><span class="label">Moyen de transport :</span> {{ $m->meta['moyen_transport'] ?? '' }}</div>
    <div class="ligne"><span class="label">Imputation budgétaire des frais de transport / indemnités :</span> {{ $m->meta['imputation'] ?? '' }}</div>
    <div class="ligne"><span class="label">Chapitre :</span> {{ $m->meta['chapitre'] ?? '' }}
        &nbsp;&nbsp;<span class="label">Article :</span> {{ $m->meta['article'] ?? '' }}</div>

    <div class="lieu">Dakar, le {{ now()->format('d/m/Y') }}</div>

    <table class="signatures">
        <tr>
            @foreach ($signataires as $s)
                <td>
                    <div>{{ $s['fonction'] }}</div>
                    <div style="height:40px;"></div>
                    <div><strong>{{ $s['nom'] }}</strong></div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 5: Route (remplacer le stub)**

Modify `routes/web.php` — dans le groupe `role:secretaire`, remplacer la route stub `missions.imprimer` par :
```php
    Route::get('/missions/{demande}/imprimer', \App\Http\Controllers\OrdreMissionPdfController::class)->name('missions.imprimer');
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Missions/OrdreMissionPdfTest.php`
Expected: PASS (3).

- [ ] **Step 7: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plans 1-5).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: PDF ordre de mission (dompdf, signataires DG/directeur)"
```

---

## Self-Review (effectué)

- **Couverture spec/exigences :** rôle `secretaire` activé partout (Task 1) ✓ ; le secrétaire initie une mission pour un agent de sa direction, sans workflow, statut `emise` (Task 2) ✓ ; UI secrétaire + scoping direction (Task 3) ✓ ; PDF conforme au modèle avec 1-2 signataires (DG et/ou Directeur), DG configurable (Task 4) ✓ ; impression réservée au secrétaire de la direction de la mission (Task 4 autorisation) ✓.
- **Placeholders :** aucun. Le stub `missions.imprimer` (Task 3) est une route réelle temporaire, explicitement remplacée en Task 4.
- **Cohérence types :** `InitierMission::handle(User, Agent, dateDebut, dateFin, ?motif, array $meta, array $signataires)` cohérente Tasks 2/3 ; `signataires` = sous-ensemble de `['dg','directeur']` partout (`InitierMission::SIGNATAIRES`, vue, résolution PDF) ; statut `emise` = `Demande::STATUT_EMISE` (Plan 4) ; rôle `secretaire` = 5 rôles cohérents entre migration, `PromouvoirAgent::ROLES`, `AgentForm` rules+vue, helper `isSecretaire()`, middleware `role:secretaire`. `resoudreSignataires()` publique (testée directement + utilisée par `__invoke`).

## Dépendances pour les plans suivants

Plan 6 (Attestation congé + état congés) : réutilisera dompdf + `config('dge.*')` pour le signataire DG de l'attestation de cessation de service, et une jointure demandes/agents/directions pour l'état des congés. Plan 7 (Dashboards) : agrégats incluant les ordres de mission `emise`.
