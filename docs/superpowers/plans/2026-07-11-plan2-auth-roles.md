# Plan 2 — Authentification & Rôles (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à chaque agent de créer son compte (auto-inscription contrôlée), de se connecter par matricule, et poser l'infrastructure de rôles (agent / chef_direction / admin_rh / dg) avec protection des routes.

**Architecture:** L'auto-inscription est une action isolée (`RegisterAgentAccount`) qui n'accepte un agent que si `matricule + noms + prénoms` correspondent à une fiche importée **non encore réclamée** (`user_id` null). La connexion utilise le guard session natif de Laravel avec le **matricule** comme identifiant (`Auth::attempt(['matricule'=>…])`). Les rôles sont des chaînes sur `users.role` (déjà en base depuis Plan 1) exposées via des helpers et un middleware `role`. UI en Livewire 3.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind. S'appuie sur Plan 1 (tables `users`/`agents`, `User::agent()`, `Agent::nomComplet()`).

**Prérequis d'exécution:** créer la branche `feat/plan2-auth` **à partir de** `feat/plan1-fondations` (Plan 1 non mergé) :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan1-fondations
git checkout -b feat/plan2-auth
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif sur stderr — l'ignorer. Tests sur SQLite in-memory (phpunit.xml par défaut). Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-11-plateforme-rh-dge-design.md` §6 (rôles), §7 (comptes/inscription).

---

## Fichiers créés/modifiés dans ce plan

- `app/Actions/RegisterAgentAccount.php` — logique d'auto-inscription (validation match + création user + liaison agent).
- `app/Livewire/Auth/Register.php` + `resources/views/livewire/auth/register.blade.php` — écran inscription.
- `app/Livewire/Auth/Login.php` + `resources/views/livewire/auth/login.blade.php` — écran connexion.
- `resources/views/components/layouts/app.blade.php` — layout minimal pour les composants Livewire pleine page.
- `resources/views/dashboard.blade.php` — page d'accueil connectée.
- `routes/web.php` — routes login/register/logout/dashboard (modifié).
- `app/Models/User.php` — helpers de rôle (modifié).
- `app/Http/Middleware/EnsureRole.php` — middleware de contrôle de rôle.
- `bootstrap/app.php` — enregistrement de l'alias middleware `role` (modifié).
- `app/Console/Commands/PromouvoirAgent.php` — commande CLI de promotion de rôle.
- `tests/Feature/*` — tests Pest par tâche.

---

## Task 1: Action d'auto-inscription (`RegisterAgentAccount`)

Logique pure, sans HTTP. Un agent ne peut créer un compte que si `matricule + noms + prénoms` (comparaison insensible à la casse et aux espaces) correspondent à une fiche agent importée dont `user_id` est null. Sinon, `ValidationException`. Les agents sans matricule ne peuvent pas s'auto-inscrire (activés manuellement par la RH — hors périmètre).

**Files:**
- Create: `app/Actions/RegisterAgentAccount.php`
- Test: `tests/Feature/RegisterAgentAccountTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/RegisterAgentAccountTest.php`:
```php
<?php

use App\Actions\RegisterAgentAccount;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function agentSansCompte(): Agent
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    return Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('crée un compte quand matricule + nom + prénom correspondent', function () {
    $agent = agentSansCompte();

    $user = app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1');

    expect($user)->toBeInstanceOf(User::class);
    expect($user->matricule)->toBe('636324/D');
    expect($user->role)->toBe('agent');
    expect($user->name)->toBe('Biram SENE');
    expect($agent->fresh()->user_id)->toBe($user->id);
    expect(\Illuminate\Support\Facades\Hash::check('motdepasse1', $user->password))->toBeTrue();
});

it('accepte une casse et des espaces différents', function () {
    agentSansCompte();

    $user = app(RegisterAgentAccount::class)->handle(' 636324/D ', 'sene', 'biram', 'motdepasse1');

    expect($user->matricule)->toBe('636324/D');
});

it('refuse un matricule inconnu', function () {
    agentSansCompte();

    expect(fn () => app(RegisterAgentAccount::class)->handle('000000/X', 'SENE', 'Biram', 'motdepasse1'))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(0);
});

it('refuse si le nom ne correspond pas au matricule', function () {
    agentSansCompte();

    expect(fn () => app(RegisterAgentAccount::class)->handle('636324/D', 'DIOP', 'Biram', 'motdepasse1'))
        ->toThrow(ValidationException::class);
});

it('refuse un agent déjà réclamé', function () {
    $agent = agentSansCompte();
    $existing = User::create(['name' => 'X', 'matricule' => 'ZZZ', 'password' => bcrypt('x'), 'role' => 'agent']);
    $agent->update(['user_id' => $existing->id]);

    expect(fn () => app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1'))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(1); // aucun nouveau compte
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/RegisterAgentAccountTest.php`
Expected: FAIL (classe `RegisterAgentAccount` introuvable).

- [ ] **Step 3: Écrire l'action**

Create `app/Actions/RegisterAgentAccount.php`:
```php
<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterAgentAccount
{
    public function handle(string $matricule, string $noms, string $prenoms, string $password): User
    {
        $agent = Agent::query()
            ->whereRaw('LOWER(TRIM(matricule)) = ?', [strtolower(trim($matricule))])
            ->whereRaw('LOWER(TRIM(noms)) = ?', [strtolower(trim($noms))])
            ->whereRaw('LOWER(TRIM(prenoms)) = ?', [strtolower(trim($prenoms))])
            ->first();

        if (! $agent) {
            throw ValidationException::withMessages([
                'matricule' => "Aucun agent ne correspond à ce matricule et à ce nom. Contactez la DRHF.",
            ]);
        }

        if ($agent->user_id !== null) {
            throw ValidationException::withMessages([
                'matricule' => "Un compte existe déjà pour cet agent.",
            ]);
        }

        $user = User::create([
            'name' => $agent->nomComplet(),
            'matricule' => $agent->matricule,
            'password' => Hash::make($password),
            'role' => 'agent',
        ]);

        $agent->update(['user_id' => $user->id]);

        return $user;
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/RegisterAgentAccountTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: action auto-inscription agent (match matricule+nom)"
```

---

## Task 2: Connexion par matricule

Vérifie que le guard natif authentifie par `matricule`. Pas de nouveau code applicatif ici (le guard session standard suffit) : la tâche verrouille ce comportement par un test avant de construire l'UI.

**Files:**
- Test: `tests/Feature/LoginMatriculeTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/LoginMatriculeTest.php`:
```php
<?php

use App\Actions\RegisterAgentAccount;
use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
    app(RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1');
});

it('authentifie avec le matricule et le bon mot de passe', function () {
    expect(Auth::attempt(['matricule' => '636324/D', 'password' => 'motdepasse1']))->toBeTrue();
    expect(Auth::check())->toBeTrue();
});

it('rejette un mauvais mot de passe', function () {
    expect(Auth::attempt(['matricule' => '636324/D', 'password' => 'faux']))->toBeFalse();
});

it('rejette un matricule inconnu', function () {
    expect(Auth::attempt(['matricule' => '999999/Z', 'password' => 'motdepasse1']))->toBeFalse();
});
```

- [ ] **Step 2: Lancer le test**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/LoginMatriculeTest.php`
Expected: PASS directement (le guard session natif gère `Auth::attempt` sur n'importe quelle colonne de `users` + `password`). Si un test échoue, NE PAS affaiblir l'assertion : vérifier que la colonne `matricule` existe bien sur `users` (migration Plan 1) et que `password` est bien hashé par l'action Task 1.

- [ ] **Step 3: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "test: verrouille la connexion par matricule (guard natif)"
```

---

## Task 3: Écrans Livewire inscription / connexion / déconnexion

Composants Livewire pleine page + layout + routes. Après inscription ou connexion réussie, redirection vers `/dashboard`.

**Files:**
- Create: `resources/views/components/layouts/app.blade.php`
- Create: `app/Livewire/Auth/Register.php`, `resources/views/livewire/auth/register.blade.php`
- Create: `app/Livewire/Auth/Login.php`, `resources/views/livewire/auth/login.blade.php`
- Create: `resources/views/dashboard.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AuthFlowTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/AuthFlowTest.php`:
```php
<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedAgent(): void
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('affiche les pages login et register', function () {
    $this->get('/login')->assertOk()->assertSeeLivewire(Login::class);
    $this->get('/register')->assertOk()->assertSeeLivewire(Register::class);
});

it('inscrit un agent via le composant et le connecte', function () {
    seedAgent();

    Livewire::test(Register::class)
        ->set('matricule', '636324/D')
        ->set('noms', 'SENE')
        ->set('prenoms', 'Biram')
        ->set('password', 'motdepasse1')
        ->set('password_confirmation', 'motdepasse1')
        ->call('register')
        ->assertRedirect('/dashboard');

    expect(User::where('matricule', '636324/D')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('affiche une erreur de validation sur matricule inconnu', function () {
    seedAgent();

    Livewire::test(Register::class)
        ->set('matricule', '000000/X')
        ->set('noms', 'SENE')
        ->set('prenoms', 'Biram')
        ->set('password', 'motdepasse1')
        ->set('password_confirmation', 'motdepasse1')
        ->call('register')
        ->assertHasErrors('matricule');

    $this->assertGuest();
});

it('connecte un agent existant via le composant', function () {
    seedAgent();
    app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1');

    Livewire::test(Login::class)
        ->set('matricule', '636324/D')
        ->set('password', 'motdepasse1')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});

it('refuse la connexion avec un mauvais mot de passe', function () {
    seedAgent();
    app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1');

    Livewire::test(Login::class)
        ->set('matricule', '636324/D')
        ->set('password', 'faux')
        ->call('login')
        ->assertHasErrors('matricule');

    $this->assertGuest();
});

it('déconnecte un utilisateur', function () {
    seedAgent();
    $user = app(\App\Actions\RegisterAgentAccount::class)->handle('636324/D', 'SENE', 'Biram', 'motdepasse1');

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/AuthFlowTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Créer le layout**

Create `resources/views/components/layouts/app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'RH DGE' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
    <main class="mx-auto max-w-3xl p-6">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
```

- [ ] **Step 4: Créer le composant Register**

Create `app/Livewire/Auth/Register.php`:
```php
<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterAgentAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Register extends Component
{
    public string $matricule = '';
    public string $noms = '';
    public string $prenoms = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(RegisterAgentAccount $action)
    {
        $this->validate([
            'matricule' => ['required', 'string'],
            'noms' => ['required', 'string'],
            'prenoms' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $action->handle($this->matricule, $this->noms, $this->prenoms, $this->password);
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
```

Create `resources/views/livewire/auth/register.blade.php`:
```blade
<div class="mx-auto mt-10 max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-xl font-semibold">Création de compte — Agent DGE</h1>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Matricule / NIN</label>
            <input type="text" wire:model="matricule" class="mt-1 w-full rounded border-gray-300">
            @error('matricule') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Nom</label>
            <input type="text" wire:model="noms" class="mt-1 w-full rounded border-gray-300">
            @error('noms') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Prénom(s)</label>
            <input type="text" wire:model="prenoms" class="mt-1 w-full rounded border-gray-300">
            @error('prenoms') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Mot de passe</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded border-gray-300">
            @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Confirmer le mot de passe</label>
            <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border-gray-300">
        </div>
        <button type="submit" class="w-full rounded bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
            Créer mon compte
        </button>
    </form>

    <p class="mt-4 text-sm">Déjà un compte ? <a href="{{ route('login') }}" class="text-emerald-700 underline">Se connecter</a></p>
</div>
```

- [ ] **Step 5: Créer le composant Login**

Create `app/Livewire/Auth/Login.php`:
```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $matricule = '';
    public string $password = '';

    public function login()
    {
        $this->validate([
            'matricule' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['matricule' => trim($this->matricule), 'password' => $this->password])) {
            throw ValidationException::withMessages([
                'matricule' => "Matricule ou mot de passe invalide.",
            ]);
        }

        request()->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
```

Create `resources/views/livewire/auth/login.blade.php`:
```blade
<div class="mx-auto mt-10 max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-xl font-semibold">Connexion — RH DGE</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Matricule / NIN</label>
            <input type="text" wire:model="matricule" class="mt-1 w-full rounded border-gray-300">
            @error('matricule') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Mot de passe</label>
            <input type="password" wire:model="password" class="mt-1 w-full rounded border-gray-300">
            @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="w-full rounded bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
            Se connecter
        </button>
    </form>

    <p class="mt-4 text-sm">Pas encore de compte ? <a href="{{ route('register') }}" class="text-emerald-700 underline">Créer un compte</a></p>
</div>
```

- [ ] **Step 6: Créer la vue dashboard**

Create `resources/views/dashboard.blade.php`:
```blade
<x-layouts.app :title="'Tableau de bord'">
    <div class="rounded-lg bg-white p-8 shadow">
        <h1 class="text-xl font-semibold">Bonjour {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-gray-600">Rôle : {{ auth()->user()->role }}</p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Se déconnecter</button>
        </form>
    </div>
</x-layouts.app>
```

- [ ] **Step 7: Déclarer les routes**

Replace the contents of `routes/web.php` with:
```php
<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
```
Note : la route `/` de Plan 1 (welcome) est remplacée par une redirection vers `/login`. Le smoke test de Plan 1 (`GET /` → 200) suivra la redirection ? Non : `assertStatus(200)` échouera car `/` renvoie 302. **Mettre à jour** `tests/Feature/SmokeTest.php` pour :
```php
<?php

it('redirige la racine vers la connexion', function () {
    $this->get('/')->assertRedirect('/login');
});
```

- [ ] **Step 8: Lancer les tests concernés — doivent passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/AuthFlowTest.php tests/Feature/SmokeTest.php`
Expected: PASS (6 + 1 tests). Si `@vite` provoque une erreur en test (manifest manquant), ce n'est pas déclenché ici car les vues Livewire testées via `Livewire::test()` ne rendent pas le layout complet, et `assertSeeLivewire` ne compile pas Vite. Si `GET /login` échoue sur le manifest Vite, exécuter `npm install && npm run build` une fois, ou ajouter `$this->withoutVite();` en tête des tests HTTP concernés.

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: ecrans Livewire inscription/connexion/deconnexion"
```

---

## Task 4: Helpers de rôle + middleware `role` + protection dashboard

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Http/Middleware/EnsureRole.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/RoleAccessTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/RoleAccessTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function userAvecRole(string $role): User
{
    return User::create([
        'name' => 'Test', 'matricule' => 'M-'.$role,
        'password' => bcrypt('secret'), 'role' => $role,
    ]);
}

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin_rh'])->get('/_zone-rh', fn () => 'ok');
});

it('expose des helpers de rôle', function () {
    $rh = userAvecRole('admin_rh');
    expect($rh->isAdminRh())->toBeTrue();
    expect($rh->isAgent())->toBeFalse();
    expect($rh->hasRole('admin_rh', 'dg'))->toBeTrue();
    expect(userAvecRole('agent')->isAgent())->toBeTrue();
    expect(userAvecRole('chef_direction')->isChefDirection())->toBeTrue();
    expect(userAvecRole('dg')->isDg())->toBeTrue();
});

it('laisse passer le bon rôle', function () {
    $this->actingAs(userAvecRole('admin_rh'))->get('/_zone-rh')->assertOk();
});

it('bloque un rôle non autorisé avec 403', function () {
    $this->actingAs(userAvecRole('agent'))->get('/_zone-rh')->assertForbidden();
});

it('protège le dashboard derrière auth', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->actingAs(userAvecRole('agent'))->get('/dashboard')->assertOk();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/RoleAccessTest.php`
Expected: FAIL (helpers absents / alias `role` inconnu).

- [ ] **Step 3: Ajouter les helpers de rôle sur User**

Modify `app/Models/User.php` — ajouter ces méthodes dans la classe :
```php
public function isAgent(): bool
{
    return $this->role === 'agent';
}

public function isChefDirection(): bool
{
    return $this->role === 'chef_direction';
}

public function isAdminRh(): bool
{
    return $this->role === 'admin_rh';
}

public function isDg(): bool
{
    return $this->role === 'dg';
}

public function hasRole(string ...$roles): bool
{
    return in_array($this->role, $roles, true);
}
```

- [ ] **Step 4: Créer le middleware**

Create `app/Http/Middleware/EnsureRole.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Enregistrer l'alias middleware**

Modify `bootstrap/app.php` — dans `->withMiddleware(function (Middleware $middleware) { ... })`, ajouter :
```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureRole::class,
]);
```
(Si le closure `withMiddleware` est vide, y placer ce bloc. Conserver les autres réglages existants.)

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/RoleAccessTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: helpers de role + middleware role + protection dashboard"
```

---

## Task 5: Commande de promotion de rôle (`rh:promouvoir`)

Permet à la DRHF (via CLI, pour bootstrap) d'attribuer un rôle à un compte existant. Le compte doit déjà exister (l'agent s'inscrit d'abord). Rôle validé contre la liste autorisée.

**Files:**
- Create: `app/Console/Commands/PromouvoirAgent.php`
- Test: `tests/Feature/PromouvoirAgentTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/PromouvoirAgentTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function compte(string $matricule): User
{
    return User::create([
        'name' => 'Test', 'matricule' => $matricule,
        'password' => bcrypt('secret'), 'role' => 'agent',
    ]);
}

it('promeut un compte existant', function () {
    compte('636324/D');

    $this->artisan('rh:promouvoir', ['matricule' => '636324/D', 'role' => 'admin_rh'])
        ->assertExitCode(0);

    expect(User::where('matricule', '636324/D')->first()->role)->toBe('admin_rh');
});

it('refuse un rôle invalide', function () {
    compte('636324/D');

    $this->artisan('rh:promouvoir', ['matricule' => '636324/D', 'role' => 'roi'])
        ->assertExitCode(1);

    expect(User::where('matricule', '636324/D')->first()->role)->toBe('agent');
});

it('refuse un matricule sans compte', function () {
    $this->artisan('rh:promouvoir', ['matricule' => '000000/X', 'role' => 'admin_rh'])
        ->assertExitCode(1);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/PromouvoirAgentTest.php`
Expected: FAIL (commande inexistante).

- [ ] **Step 3: Créer la commande**

Create `app/Console/Commands/PromouvoirAgent.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromouvoirAgent extends Command
{
    protected $signature = 'rh:promouvoir {matricule} {role}';
    protected $description = 'Attribue un rôle (agent|chef_direction|admin_rh|dg) à un compte existant';

    private const ROLES = ['agent', 'chef_direction', 'admin_rh', 'dg'];

    public function handle(): int
    {
        $role = $this->argument('role');
        if (! in_array($role, self::ROLES, true)) {
            $this->error("Rôle invalide « {$role} ». Valeurs : ".implode(', ', self::ROLES));
            return self::FAILURE;
        }

        $matricule = trim($this->argument('matricule'));
        $user = User::where('matricule', $matricule)->first();
        if (! $user) {
            $this->error("Aucun compte pour le matricule « {$matricule} ». L'agent doit d'abord créer son compte.");
            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("{$user->name} ({$matricule}) est maintenant : {$role}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/PromouvoirAgentTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plan 1 + Plan 2). Le smoke test mis à jour (Task 3 step 7) et tous les tests d'auth passent.

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: commande rh:promouvoir (attribution de role)"
```

---

## Self-Review (effectué)

- **Couverture spec :** §7 auto-inscription par matricule+nom+prénom d'un agent non réclamé (Task 1) ✓ ; connexion (Tasks 2-3) ✓ ; rôle par défaut `agent` (Task 1) ✓ ; promotion des rôles par la RH (Task 5, via CLI pour bootstrap ; UI de promotion = Plan 3) ✓. §6 rôles agent/chef_direction/admin_rh/dg + contrôle d'accès (Task 4 helpers + middleware) ✓. Le scoping fin « chef ne voit que sa direction » et « DG lecture seule » s'appliquera sur les ressources concernées (agents en Plan 3, demandes en Plan 4) — hors périmètre Plan 2, noté explicitement.
- **Placeholders :** aucun. Chaque étape contient le code réel.
- **Cohérence types :** `RegisterAgentAccount::handle(matricule, noms, prenoms, password): User` appelée identiquement en Tasks 1/2/3. Helpers `isAgent/isChefDirection/isAdminRh/isDg/hasRole` définis Task 4 et utilisés par `EnsureRole`. Colonnes `users.matricule`/`users.role` proviennent de Plan 1. Routes nommées `login`/`register`/`dashboard`/`logout` cohérentes entre vues, composants et tests. Commande `rh:promouvoir` : mêmes 4 rôles que l'enum Plan 1.

## Dépendances pour les plans suivants

Plan 3 (Gestion RH) : UI de promotion de rôle (remplace/complète `rh:promouvoir`), CRUD agents, gestion soldes/directions — protégés par le middleware `role:admin_rh` posé ici. Plan 4 (Demandes) : policies de scoping par direction s'appuieront sur `User::hasRole()` et la relation `User::agent()->direction`.
