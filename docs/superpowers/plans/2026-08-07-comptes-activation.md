# Activation / désactivation des comptes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Permettre à la RH et au super-admin de **désactiver / réactiver** un compte utilisateur. Un compte désactivé ne peut plus se connecter et est déconnecté s'il l'était. Boutons dans la liste **Agents** (comptes agents) et **Comptes système** (RH/courrier/archiviste/DG). Garde-fous : impossible de désactiver le super-admin ni son propre compte. Soft-disable réversible (pas de suppression).

**Architecture:** Colonne `users.compte_actif` (bool, défaut vrai). Le composant `Auth\Login` refuse un compte désactivé avant toute connexion. Un middleware `EnsureCompteActif` (ajouté au groupe `web`) déconnecte immédiatement un utilisateur désactivé en pleine session. Les composants `Rh\AgentsIndex` et `Admin\ComptesSysteme` exposent un basculement gardé.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, MySQL (SQLite in-memory en test).

## Global Constraints

- PHP 8.3 ; Livewire 3 ; Pest ; SQLite in-memory en test.
- `users.compte_actif` bool, **défaut true** ; ajouté au `$fillable` + cast boolean de `User`.
- **Login bloqué** si `! $user->compte_actif` (message « Votre compte a été désactivé. Contactez la RH. »), placé APRÈS la vérification mot de passe, AVANT les branches email/2FA (jamais de `Auth::login` pour un compte désactivé).
- **Middleware `EnsureCompteActif`** (alias `compte.actif`) ajouté au groupe `web` : si `$request->user()` existe et `! ->compte_actif` → `Auth::logout()` + invalidation session + redirect `login` avec erreur. Doit ignorer les invités (`$user` null).
- **Garde-fous** (composants) : `abort_if($user->isAdmin() || $user->id === auth()->id(), 403)` avant tout basculement.
- Accès : AgentsIndex = `role:admin_rh` (admin bypass) ; ComptesSysteme = `role:admin`. Inchangé.
- Tests existants (auth, 2FA, comptes) doivent rester verts ; `UserFactory` a `compte_actif` true par défaut (colonne défaut).

---

### Task 1: Colonne + User + blocage Login + middleware session

**Files:**
- Create: migration `..._add_compte_actif_to_users_table.php`, `app/Http/Middleware/EnsureCompteActif.php`
- Modify: `app/Models/User.php`, `app/Livewire/Auth/Login.php`, `bootstrap/app.php`
- Test: `tests/Feature/Auth/CompteActifTest.php`

**Interfaces:**
- Produces: `users.compte_actif` (bool, défaut true) ; `User::estActif(): bool` ; middleware alias `compte.actif` + ajout au groupe `web`.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Auth/CompteActifTest.php` :

```php
<?php

use App\Models\User;
use App\Livewire\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('refuse la connexion d’un compte désactivé', function () {
    $u = User::factory()->create([
        'matricule' => 'X001', 'password' => Hash::make('secret'),
        'email' => 'x@dge.sn', 'email_verified_at' => now(),
        'compte_actif' => false,
    ]);

    Livewire::test(Login::class)
        ->set('matricule', 'X001')
        ->set('password', 'secret')
        ->call('login')
        ->assertHasErrors('matricule');

    expect(auth()->check())->toBeFalse();
});

it('déconnecte un utilisateur désactivé en pleine session (middleware)', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'compte_actif' => false]);

    $this->actingAs($u)->get('/dashboard')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('un compte actif accède normalement', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'compte_actif' => true]);
    $this->actingAs($u)->get('/dashboard')->assertOk();
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=CompteActifTest` → FAIL.

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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('compte_actif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('compte_actif');
        });
    }
};
```

- [ ] **Step 4: User**

Add `'compte_actif'` to `$fillable`; add `'compte_actif' => 'boolean'` to `casts()`; add:

```php
public function estActif(): bool
{
    return (bool) $this->compte_actif;
}
```

- [ ] **Step 5: Login — blocage**

In `app/Livewire/Auth/Login.php`, after `RateLimiter::clear($key);` and `session()->regenerate();` (before the "1) Compte sans email" branch), add:

```php
        if (! $user->compte_actif) {
            throw ValidationException::withMessages([
                'matricule' => 'Votre compte a été désactivé. Contactez la RH.',
            ]);
        }
```

- [ ] **Step 6: Middleware**

`app/Http/Middleware/EnsureCompteActif.php` :

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->compte_actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'matricule' => 'Votre compte a été désactivé. Contactez la RH.',
            ]);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php` inside `withMiddleware` — add the alias AND append to the `web` group:

```php
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'password.change' => \App\Http\Middleware\RequirePasswordChange::class,
            'compte.actif' => \App\Http\Middleware\EnsureCompteActif::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureCompteActif::class);
```

- [ ] **Step 7: Migrer + relancer (succès)**

Run: `php artisan migrate && php artisan test --filter=CompteActifTest`
Expected: PASS. Puis full `php artisan test` — report totals (les tests auth existants doivent rester verts).

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Http/Middleware/EnsureCompteActif.php app/Models/User.php app/Livewire/Auth/Login.php bootstrap/app.php tests/Feature/Auth/CompteActifTest.php
git commit -m "feat(auth): compte_actif — blocage login + middleware déconnexion"
```

---

### Task 2: Basculement dans la liste Agents

**Files:**
- Modify: `app/Livewire/Rh/AgentsIndex.php`, `resources/views/livewire/rh/agents-index.blade.php`
- Test: `tests/Feature/Rh/BasculerCompteAgentTest.php`

**Interfaces:**
- Consumes: `User`, `Agent`.
- Produces: `AgentsIndex::basculerCompte(int $userId)`.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Rh/BasculerCompteAgentTest.php` :

```php
<?php

use App\Livewire\Rh\AgentsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function rhUser(): User
{
    return User::factory()->create(['role' => 'admin_rh', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('la RH désactive puis réactive un compte agent', function () {
    $agentUser = User::factory()->create(['role' => 'agent', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $agentUser->id);
    expect($agentUser->fresh()->compte_actif)->toBeFalse();

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $agentUser->id);
    expect($agentUser->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver le super-admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'compte_actif' => true]);

    Livewire::actingAs(rhUser())->test(AgentsIndex::class)
        ->call('basculerCompte', $admin->id)
        ->assertStatus(403);

    expect($admin->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver son propre compte', function () {
    $rh = rhUser();

    Livewire::actingAs($rh)->test(AgentsIndex::class)
        ->call('basculerCompte', $rh->id)
        ->assertStatus(403);

    expect($rh->fresh()->compte_actif)->toBeTrue();
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=BasculerCompteAgentTest` → FAIL.

- [ ] **Step 3: Composant — méthode + eager-load user**

In `app/Livewire/Rh/AgentsIndex.php`, add:

```php
public function basculerCompte(int $userId): void
{
    $user = \App\Models\User::findOrFail($userId);
    abort_if($user->isAdmin() || $user->id === auth()->id(), 403);
    $user->update(['compte_actif' => ! $user->compte_actif]);
    session()->flash('ok', $user->compte_actif ? 'Compte réactivé.' : 'Compte désactivé.');
}
```

And in `render()`, eager-load the agent's user: change `->with('direction')` to `->with('direction', 'user')`.

- [ ] **Step 4: Vue — bouton dans la colonne compte**

In `resources/views/livewire/rh/agents-index.blade.php`, replace the `@if ($agent->user_id)` "Compte actif" badge (around line 82-83) so it shows the status + toggle (keep the `@else` "Créer le compte" block unchanged):

```blade
@if ($agent->user_id)
    @php($u = $agent->user)
    @if ($u && $u->compte_actif)
        <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">Compte actif</span>
    @else
        <span class="badge" style="background:#fdecec;color:#b3261e">Désactivé</span>
    @endif
    @if ($u && ! $u->isAdmin() && $u->id !== auth()->id())
        <button type="button" class="btn btn-ghost" style="padding:5px 10px;font-size:12px"
                wire:click="basculerCompte({{ $u->id }})"
                wire:confirm="{{ $u->compte_actif ? 'Désactiver ce compte ? L’agent ne pourra plus se connecter.' : 'Réactiver ce compte ?' }}">
            {{ $u->compte_actif ? 'Désactiver' : 'Réactiver' }}
        </button>
    @endif
@else
    {{-- bloc "Créer le compte" existant, inchangé --}}
@endif
```

> Garder le bloc `@else` (Créer le compte + dropdown email) tel quel.

- [ ] **Step 5: Relancer (succès)**

Run: `php artisan test --filter=BasculerCompteAgentTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Rh/AgentsIndex.php resources/views/livewire/rh/agents-index.blade.php tests/Feature/Rh/BasculerCompteAgentTest.php
git commit -m "feat(rh): activer/désactiver un compte agent (gardes admin + soi-même)"
```

---

### Task 3: Basculement dans Comptes système + suite complète

**Files:**
- Modify: `app/Livewire/Admin/ComptesSysteme.php`, `resources/views/livewire/admin/comptes-systeme.blade.php`
- Test: `tests/Feature/Admin/BasculerCompteSystemeTest.php`

**Interfaces:**
- Produces: `ComptesSysteme::basculer(int $userId)`.

- [ ] **Step 1: Tests (échec attendu)**

Create `tests/Feature/Admin/BasculerCompteSystemeTest.php` :

```php
<?php

use App\Livewire\Admin\ComptesSysteme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('le super-admin désactive un compte système (courrier)', function () {
    $courrier = User::factory()->create(['role' => 'courrier', 'compte_actif' => true]);

    Livewire::actingAs(adminUser())->test(ComptesSysteme::class)
        ->call('basculer', $courrier->id);

    expect($courrier->fresh()->compte_actif)->toBeFalse();
});

it('interdit de désactiver un autre super-admin', function () {
    $autreAdmin = User::factory()->create(['role' => 'admin', 'compte_actif' => true]);

    Livewire::actingAs(adminUser())->test(ComptesSysteme::class)
        ->call('basculer', $autreAdmin->id)
        ->assertStatus(403);

    expect($autreAdmin->fresh()->compte_actif)->toBeTrue();
});

it('interdit de désactiver son propre compte', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)->test(ComptesSysteme::class)
        ->call('basculer', $admin->id)
        ->assertStatus(403);

    expect($admin->fresh()->compte_actif)->toBeTrue();
});
```

- [ ] **Step 2: Lancer (échec)**

Run: `php artisan test --filter=BasculerCompteSystemeTest` → FAIL.

- [ ] **Step 3: Composant**

In `app/Livewire/Admin/ComptesSysteme.php`, add:

```php
public function basculer(int $userId): void
{
    $user = User::findOrFail($userId);
    abort_if($user->isAdmin() || $user->id === auth()->id(), 403);
    $user->update(['compte_actif' => ! $user->compte_actif]);
    session()->flash('ok', $user->compte_actif ? 'Compte réactivé.' : 'Compte désactivé.');
}
```

- [ ] **Step 4: Vue — bouton dans la liste des comptes**

In `resources/views/livewire/admin/comptes-systeme.blade.php`, in the `$comptes` list row, add a status + toggle (except for `admin` role and self):

```blade
@if ($compte->compte_actif)
    <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">Actif</span>
@else
    <span class="badge" style="background:#fdecec;color:#b3261e">Désactivé</span>
@endif
@if (! $compte->isAdmin() && $compte->id !== auth()->id())
    <button type="button" class="btn btn-ghost" style="padding:5px 10px;font-size:12px"
            wire:click="basculer({{ $compte->id }})"
            wire:confirm="{{ $compte->compte_actif ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' }}">
        {{ $compte->compte_actif ? 'Désactiver' : 'Réactiver' }}
    </button>
@endif
```

> Adapter au markup réel de la ligne (`$compte` est la variable de boucle de `$comptes`). READ the blade first to place it in the row correctly.

- [ ] **Step 5: Relancer + suite complète**

Run: `php artisan test --filter=BasculerCompteSystemeTest` puis `php artisan test`
Expected: PASS (toute la suite verte)

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Admin/ComptesSysteme.php resources/views/livewire/admin/comptes-systeme.blade.php tests/Feature/Admin/BasculerCompteSystemeTest.php
git commit -m "feat(admin): activer/désactiver un compte système (gardes admin + soi-même)"
```

---

## Self-Review (effectuée)

- **Couverture** : colonne + User (Task 1), blocage login (Task 1), middleware session (Task 1), toggle agents + gardes (Task 2), toggle comptes système + gardes (Task 3).
- **Placeholders** : aucun — tests + code fournis.
- **Cohérence** : `compte_actif` ajouté au fillable/cast (Task 1) puis utilisé partout ; `estActif()` défini Task 1 ; gardes identiques (isAdmin || self → 403) Tasks 2/3 ; middleware `web` global gère la désactivation en session.
- **Sécurité** : jamais de `Auth::login` pour un compte désactivé (blocage avant les branches) ; middleware déconnecte en session ; gardes empêchent de neutraliser le super-admin ou soi-même.
- **Rétro-compat** : colonne défaut true → comptes existants restent actifs ; tests auth existants inchangés.
- **Point d'attention** : le middleware ajouté au groupe `web` s'exécute sur toutes les requêtes ; il ignore les invités (`$user` null) et n'agit que sur un utilisateur connecté désactivé — ne casse pas le flux login/2FA (pending non connecté).
