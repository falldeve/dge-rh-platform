# Auth : Création de compte par email + 2FA (façon OFNAC) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter à DGE l'inscription avec email + vérification d'email obligatoire, et une authentification à deux facteurs (2FA) par code envoyé par email à chaque connexion, avec option « appareil de confiance » — en répliquant le mécanisme de l'application OFNAC, adapté à Livewire.

**Architecture:** Connexion en deux temps : (1) le composant Livewire `Login` valide matricule+mot de passe **sans** connecter, puis (2) selon l'état du compte, redirige vers la capture d'email, la vérification d'email, ou le défi 2FA. Les codes 2FA (6 chiffres, hachés, 10 min) et les appareils de confiance (jeton haché, 30 j, cookie) sont stockés en base via des relations polymorphes sur `User`. La vérification d'email s'appuie sur le mécanisme natif Laravel (`MustVerifyEmail` + middleware `verified`).

**Tech Stack:** Laravel 12, Livewire 3, Pest, Mail (SMTP en prod), Illuminate Hash/Cookie/Mail.

## Global Constraints

- Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande. PHP émet `Warning: Module "swoole" is already loaded` — l'ignorer. Tests : `./vendor/bin/pest`.
- Base branch : créer `feat/auth-2fa` **depuis** `feat/plan2-auth` :
  ```bash
  cd /Users/admin/dge-rh-platform && git checkout feat/plan2-auth && git checkout -b feat/auth-2fa
  ```
- Politique **stricte** : email + vérification obligatoires pour tous ; 2FA à chaque connexion (sauf appareil de confiance) ; un compte existant **sans** email doit en ajouter un (et le vérifier) à sa prochaine connexion.
- **Ne jamais** utiliser `asset()`/`Storage::url()` (APP_URL casse sur :8000) — chemins relatifs.
- GOTCHA Livewire 3 : `wire:click="$reset(...)"` NE marche PAS ; passer par une méthode. `Livewire::test()` intercepte AuthorizationException→403 (tester avec `assertForbidden()`).
- Mail : le driver/SMTP se configure dans `.env` (fait par l'utilisateur). Les tests utilisent `Mail::fake()`.
- Réplique de l'implémentation OFNAC : `~/ofnac-platform/app/Services/TwoFactorChallenge.php`, `TrustedDeviceManager.php`, `app/Mail/TwoFactorCode.php`, migrations `two_factor_codes` / `two_factor_trusted_devices`. Adaptée à un seul guard `web`, login par **matricule**, et composants **Livewire**.

**Faits vérifiés dans le code DGE :**
- `users` a déjà `email` (unique, rendu nullable) + `email_verified_at` (migrations `0001_01_01_000000_create_users_table`, `2026_07_11_000003_add_rh_fields_to_users_table`).
- `App\Models\User` : `$fillable` contient `email`, `matricule`, `password`, `role` ; cast `email_verified_at=>datetime`, `password=>hashed`. Helpers rôle `isAdminRh()` etc. + `agentEntitesGereesIds()`/`gereEntite()`.
- `App\Livewire\Auth\Login` : `login()` fait `Auth::attempt(['matricule'=>…,'password'=>…])` puis `redirect()->to($this->accueil(Auth::user()))`. Méthode privée `accueil(User)` (match rôle → route, défaut `mes-courriers` si gereEntite sinon `dashboard`).
- `App\Livewire\Auth\Register` : champs matricule/noms/prenoms/password ; appelle `RegisterAgentAccount::handle(...)` (lie un `Agent` existant par matricule+noms+prenoms, crée `User` role=agent), puis `Auth::login` + redirect dashboard.
- Groupes de routes authentifiés dans `routes/web.php` utilisent `['auth', 'role:…']` (middleware alias `role` = `EnsureRole`).

---

## Fichiers créés/modifiés

- Migrations : `…_create_two_factor_codes_table.php`, `…_create_two_factor_trusted_devices_table.php`.
- `app/Models/User.php` (MustVerifyEmail + relations), `app/Models/TwoFactorCode.php`, `app/Models/TwoFactorTrustedDevice.php`.
- `app/Services/TwoFactorChallenge.php`, `app/Services/TrustedDeviceManager.php`, `app/Support/Accueil.php`.
- `app/Mail/TwoFactorCode.php` + `resources/views/emails/two-factor-code.blade.php`.
- `app/Livewire/Auth/Login.php` (refonte), `app/Livewire/Auth/TwoFactor.php` + vue, `app/Livewire/Auth/EmailRequis.php` + vue, `app/Livewire/Auth/Register.php` (+ email), `app/Actions/RegisterAgentAccount.php` (+ email).
- `app/Http/Controllers/Auth/EmailVerificationController.php` (+ notice/verify/resend) OU routes natives.
- `routes/web.php` (routes 2FA / email / verification + middleware `verified`).
- `tests/Feature/Auth/*`.

---

## Task 1: Base de données, modèles, services 2FA

**Files:**
- Create: `database/migrations/2026_07_19_100000_create_two_factor_codes_table.php`, `database/migrations/2026_07_19_100001_create_two_factor_trusted_devices_table.php`
- Create: `app/Models/TwoFactorCode.php`, `app/Models/TwoFactorTrustedDevice.php`, `app/Services/TwoFactorChallenge.php`, `app/Services/TrustedDeviceManager.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Auth/TwoFactorServiceTest.php`

**Interfaces produites :**
- `TwoFactorChallenge::issueFor(User): string` (code clair), `::verify(User, string): bool`. Constantes `CODE_LENGTH=6`, `TTL_MINUTES=10`, `MAX_ATTEMPTS=5`.
- `TrustedDeviceManager::remember(User, ?string ua): string` (jeton clair), `::matches(User, ?string token): bool`. Constante `TTL_DAYS=30`.
- `User::twoFactorCodes()`, `User::trustedDevices()` (morphMany). `User implements MustVerifyEmail`.

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Auth/TwoFactorServiceTest.php`:
```php
<?php

use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userTfa(): User
{
    return User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'x@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);
}

it('émet un code 2FA vérifiable une seule fois', function () {
    $u = userTfa();
    $svc = app(TwoFactorChallenge::class);

    $code = $svc->issueFor($u);

    expect($code)->toHaveLength(6);
    expect($svc->verify($u, '000000'))->toBeFalse();      // mauvais code
    expect($svc->verify($u, $code))->toBeTrue();          // bon code
    expect($svc->verify($u, $code))->toBeFalse();         // déjà consommé
});

it('bloque après trop de tentatives', function () {
    $u = userTfa();
    $svc = app(TwoFactorChallenge::class);
    $svc->issueFor($u);

    for ($i = 0; $i < 5; $i++) {
        $svc->verify($u, '999999');
    }
    // 5 tentatives ratées → même un bon code est refusé
    expect($svc->verify($u, '111111'))->toBeFalse();
});

it('reconnaît un appareil de confiance non expiré', function () {
    $u = userTfa();
    $mgr = app(TrustedDeviceManager::class);

    $token = $mgr->remember($u, 'UA-test');

    expect($mgr->matches($u, $token))->toBeTrue();
    expect($mgr->matches($u, 'jeton-inconnu'))->toBeFalse();
    expect($mgr->matches($u, null))->toBeFalse();
});
```

- [ ] **Step 2: Lancer — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/TwoFactorServiceTest.php`
Expected: FAIL (classes absentes).

- [ ] **Step 3: Migrations**

Create `database/migrations/2026_07_19_100000_create_two_factor_codes_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable', 'tfc_auth_index');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_codes');
    }
};
```

Create `database/migrations/2026_07_19_100001_create_two_factor_trusted_devices_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable', 'tftd_auth_index');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
```

- [ ] **Step 4: Modèles**

Create `app/Models/TwoFactorCode.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactorCode extends Model
{
    protected $fillable = ['code_hash', 'expires_at', 'attempts', 'consumed_at'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Create `app/Models/TwoFactorTrustedDevice.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactorTrustedDevice extends Model
{
    protected $fillable = ['token_hash', 'expires_at', 'last_used_at', 'user_agent'];

    protected $casts = ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: User implements MustVerifyEmail + relations**

Modify `app/Models/User.php` :
- Ajouter l'import `use Illuminate\Contracts\Auth\MustVerifyEmail;` et `use Illuminate\Database\Eloquent\Relations\MorphMany;`.
- Déclarer l'interface : `class User extends Authenticatable implements MustVerifyEmail`.
- Ajouter les relations :
```php
public function twoFactorCodes(): MorphMany
{
    return $this->morphMany(TwoFactorCode::class, 'authenticatable');
}

public function trustedDevices(): MorphMany
{
    return $this->morphMany(TwoFactorTrustedDevice::class, 'authenticatable');
}
```
(User utilise déjà le trait `Notifiable` — requis pour l'envoi de la notification de vérification d'email.)

- [ ] **Step 6: Services (répliqués d'OFNAC, typés User)**

Create `app/Services/TwoFactorChallenge.php`:
```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class TwoFactorChallenge
{
    public const CODE_LENGTH = 6;
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /** Crée un code, le stocke haché, retourne le code en clair (pour l'e-mail). */
    public function issueFor(Model $user): string
    {
        $user->twoFactorCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $user->twoFactorCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'attempts' => 0,
        ]);

        return $code;
    }

    /** Vérifie le code actif : correct, non expiré, non consommé, < MAX_ATTEMPTS. */
    public function verify(Model $user, string $code): bool
    {
        $row = $user->twoFactorCodes()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $row || $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return false;
        }

        $row->update(['consumed_at' => now()]);

        return true;
    }
}
```

Create `app/Services/TrustedDeviceManager.php`:
```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrustedDeviceManager
{
    public const TTL_DAYS = 30;

    public function remember(Model $user, ?string $userAgent = null): string
    {
        $token = Str::random(48);

        $user->trustedDevices()->create([
            'token_hash' => $this->hash($token),
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'user_agent' => $userAgent,
        ]);

        return $token;
    }

    public function matches(Model $user, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $device = $user->trustedDevices()
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
```

- [ ] **Step 7: Lancer — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/TwoFactorServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(auth): base 2FA (tables, modeles, services challenge + trusted device)"
```

---

## Task 2: Inscription avec email + vérification d'email obligatoire

**Files:**
- Modify: `app/Actions/RegisterAgentAccount.php`, `app/Livewire/Auth/Register.php`, `resources/views/livewire/auth/register.blade.php`
- Create: `app/Http/Controllers/Auth/EmailVerificationController.php`, `resources/views/auth/verify-email.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/RegistrationEmailTest.php`

**Interfaces produites :**
- `RegisterAgentAccount::handle(string $matricule, string $noms, string $prenoms, string $email, string $password): User` (crée le compte avec email, non vérifié).
- Routes nommées : `verification.notice` (GET `/verifier-email`), `verification.verify` (GET `/email/verify/{id}/{hash}` signée), `verification.send` (POST `/email/verification-notification`, throttlée).
- Middleware `verified` ajouté aux groupes de routes applicatifs.

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Auth/RegistrationEmailTest.php`:
```php
<?php

use App\Livewire\Auth\Register;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentSansCompte(): Agent
{
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);

    return Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
}

it('inscrit un agent avec email et envoie la vérification', function () {
    Notification::fake();
    $this->withoutVite();
    agentSansCompte();

    Livewire::test(Register::class)
        ->set('matricule', 'A1')->set('noms', 'DIOP')->set('prenoms', 'Awa')
        ->set('email', 'awa@dge.sn')
        ->set('password', 'motdepasse')->set('password_confirmation', 'motdepasse')
        ->call('register');

    $user = User::where('email', 'awa@dge.sn')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('refuse un email déjà utilisé', function () {
    $this->withoutVite();
    agentSansCompte();
    User::create(['name' => 'Y', 'matricule' => 'Z9', 'email' => 'awa@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);

    Livewire::test(Register::class)
        ->set('matricule', 'A1')->set('noms', 'DIOP')->set('prenoms', 'Awa')
        ->set('email', 'awa@dge.sn')
        ->set('password', 'motdepasse')->set('password_confirmation', 'motdepasse')
        ->call('register')
        ->assertHasErrors('email');
});

it('un utilisateur non vérifié est bloqué hors de l’app par le middleware verified', function () {
    $this->withoutVite();
    $u = User::create(['name' => 'NV', 'matricule' => 'NV1', 'email' => 'nv@dge.sn', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $this->actingAs($u)->get('/rh/tableau-bord')->assertRedirect(route('verification.notice'));
});

it('le lien signé vérifie l’email', function () {
    $u = User::create(['name' => 'NV', 'matricule' => 'NV2', 'email' => 'nv2@dge.sn', 'password' => bcrypt('s'), 'role' => 'agent']);
    Event::fake();

    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify', now()->addMinutes(60),
        ['id' => $u->id, 'hash' => sha1($u->email)]
    );

    $this->actingAs($u)->get($url)->assertRedirect();
    expect($u->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});
```

- [ ] **Step 2: Lancer — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/RegistrationEmailTest.php`
Expected: FAIL.

- [ ] **Step 3: RegisterAgentAccount + email**

Modify `app/Actions/RegisterAgentAccount.php` — nouvelle signature et création avec email :
```php
public function handle(string $matricule, string $noms, string $prenoms, string $email, string $password): User
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
        'email' => strtolower(trim($email)),
        'password' => Hash::make($password),
        'role' => 'agent',
    ]);

    $agent->update(['user_id' => $user->id]);

    return $user;
}
```

- [ ] **Step 4: Register component + email + envoi vérification**

Modify `app/Livewire/Auth/Register.php` :
- Ajouter `public string $email = '';`.
- Dans `register()` : ajouter `'email' => ['required', 'email', 'max:255', 'unique:users,email']` aux règles ; appeler `$action->handle($this->matricule, $this->noms, $this->prenoms, $this->email, $this->password)` ; puis :
```php
$user->sendEmailVerificationNotification();
Auth::login($user);
session()->regenerate();

return redirect()->route('verification.notice');
```

Modify `resources/views/livewire/auth/register.blade.php` — ajouter un champ email (après le champ noms/prenoms, avant le mot de passe), même style que les autres champs :
```blade
<div>
    <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Email professionnel</label>
    <input type="email" wire:model="email" class="field" placeholder="prenom.nom@dge.sn">
    @error('email') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
</div>
```

- [ ] **Step 5: Contrôleur de vérification + vue notice**

Create `app/Http/Controllers/Auth/EmailVerificationController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** Page « vérifiez votre email ». */
    public function notice(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->to($this->home($request))
            : view('auth.verify-email');
    }

    /** Lien signé cliqué depuis l'email. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill(); // marque vérifié + dispatch Verified
        }

        return redirect()->to($this->home($request))->with('ok', 'Email vérifié. Bienvenue.');
    }

    /** Renvoi du lien. */
    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('ok', 'Lien de vérification renvoyé.');
    }

    private function home(Request $request): string
    {
        return \App\Support\Accueil::pour($request->user());
    }
}
```
Note : `App\Support\Accueil::pour(User): string` est créé au Task 3. Pour que Task 2 soit testable seul, créer d'abord un stub minimal :

Create `app/Support/Accueil.php` (version initiale — enrichie au Task 3) :
```php
<?php

namespace App\Support;

use App\Models\User;

class Accueil
{
    /** Route d'accueil selon le rôle (et la gestion d'entité pour les agents). */
    public static function pour(User $user): string
    {
        return match ($user->role) {
            'admin_rh' => route('rh.tableau-bord'),
            'dg' => route('dg.tableau-bord'),
            'chef_direction' => route('validation.chef'),
            'secretaire' => route('missions.mes'),
            'courrier' => route('courriers.registre'),
            'archiviste' => route('archives'),
            default => $user->gereEntite() ? route('mes-courriers') : route('dashboard'),
        };
    }
}
```

Create `resources/views/auth/verify-email.blade.php` (utilise le layout `app`) :
```blade
<x-layouts.app>
    <div class="card" style="max-width:460px;margin:60px auto;padding:28px">
        <h1 style="font-size:22px;font-weight:700;margin:0 0 8px">Vérifiez votre email</h1>
        <p style="color:var(--muted);font-size:14px;margin:0 0 16px">
            Un lien de vérification a été envoyé à <strong>{{ auth()->user()->email }}</strong>.
            Cliquez-le pour activer votre compte. Pensez à vérifier vos spams.
        </p>
        @if (session('ok'))
            <div style="background:var(--green-soft);color:var(--green-deep);padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:14px">{{ session('ok') }}</div>
        @endif
        <div style="display:flex;gap:10px;align-items:center">
            <form method="POST" action="{{ route('verification.send') }}">@csrf
                <button type="submit" class="btn btn-primary">Renvoyer le lien</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button type="submit" class="btn btn-ghost">Déconnexion</button>
            </form>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Routes de vérification + middleware `verified`**

Modify `routes/web.php` :
- Dans le groupe `Route::middleware('auth')->group(...)`, ajouter :
```php
    Route::get('/verifier-email', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
```
- Ajouter `'verified'` au middleware de **tous les groupes de routes applicatifs** (ceux avec `role:…` + le groupe `auth` des pages métier : demandes, agents/profil, documents). Exemple : `['auth', 'verified', 'role:admin_rh']`. NE PAS ajouter `verified` aux routes de connexion/2FA/vérification (elles doivent rester accessibles à un compte non vérifié). Le middleware `verified` redirige vers `route('verification.notice')` par défaut.

- [ ] **Step 7: Lancer — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/RegistrationEmailTest.php`
Expected: PASS (4 tests). Puis lancer toute la suite `./vendor/bin/pest` et **réparer les tests existants** qui atteignent des routes désormais `verified` : les users de test doivent avoir `email_verified_at` renseigné. Ajouter dans les factories/helpers concernés `'email_verified_at' => now()` (ou marquer via `$user->markEmailAsVerified()`), ou utiliser `actingAs` sur un user vérifié. Corriger jusqu'au vert.

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(auth): inscription avec email + verification obligatoire (middleware verified)"
```

---

## Task 3: Connexion 2FA par email + appareil de confiance + capture email legacy

**Files:**
- Modify: `app/Livewire/Auth/Login.php`
- Create: `app/Mail/TwoFactorCode.php`, `resources/views/emails/two-factor-code.blade.php`
- Create: `app/Livewire/Auth/TwoFactor.php`, `resources/views/livewire/auth/two-factor.blade.php`
- Create: `app/Livewire/Auth/EmailRequis.php`, `resources/views/livewire/auth/email-requis.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/LoginTwoFactorTest.php`

**Interfaces consommées :** `TwoFactorChallenge`, `TrustedDeviceManager`, `App\Support\Accueil::pour(User)`, `App\Mail\TwoFactorCode`.

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Auth/LoginTwoFactorTest.php`:
```php
<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactor;
use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userVerifie(string $mat = 'M1'): User
{
    $u = User::create(['name' => 'X', 'matricule' => $mat, 'email' => strtolower($mat).'@dge.sn', 'password' => bcrypt('secret123'), 'role' => 'admin_rh']);
    $u->markEmailAsVerified();

    return $u;
}

it('un login valide déclenche le 2FA (pas de connexion immédiate)', function () {
    Mail::fake();
    $u = userVerifie();

    Livewire::test(Login::class)
        ->set('matricule', 'M1')->set('password', 'secret123')
        ->call('login')
        ->assertRedirect(route('two-factor.show'));

    expect(auth()->check())->toBeFalse();
    Mail::assertSent(TwoFactorCode::class);
    expect(session()->has('pending_2fa'))->toBeTrue();
});

it('rejette un mauvais mot de passe sans 2FA', function () {
    Mail::fake();
    userVerifie();

    Livewire::test(Login::class)
        ->set('matricule', 'M1')->set('password', 'mauvais')
        ->call('login')
        ->assertHasErrors('matricule');

    Mail::assertNothingSent();
});

it('le code 2FA correct connecte l’utilisateur', function () {
    $u = userVerifie();
    // simule l'état pending après login
    session()->put('pending_2fa', ['id' => $u->id]);
    $code = app(TwoFactorChallenge::class)->issueFor($u);

    Livewire::test(TwoFactor::class)
        ->set('code', $code)
        ->call('verifier')
        ->assertHasNoErrors();

    expect(auth()->id())->toBe($u->id);
    expect(session()->has('pending_2fa'))->toBeFalse();
});

it('un code 2FA erroné est refusé', function () {
    $u = userVerifie();
    session()->put('pending_2fa', ['id' => $u->id]);
    app(TwoFactorChallenge::class)->issueFor($u);

    Livewire::test(TwoFactor::class)
        ->set('code', '000000')
        ->call('verifier')
        ->assertHasErrors('code');

    expect(auth()->check())->toBeFalse();
});

it('un compte sans email est redirigé vers la capture d’email', function () {
    Mail::fake();
    $u = User::create(['name' => 'Legacy', 'matricule' => 'LEG', 'email' => null, 'password' => bcrypt('secret123'), 'role' => 'agent']);

    Livewire::test(Login::class)
        ->set('matricule', 'LEG')->set('password', 'secret123')
        ->call('login')
        ->assertRedirect(route('email.requis'));

    expect(session('pending_email_user'))->toBe($u->id);
});
```

- [ ] **Step 2: Lancer — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/LoginTwoFactorTest.php`
Expected: FAIL.

- [ ] **Step 3: Mail 2FA + vue email**

Create `app/Mail/TwoFactorCode.php`:
```php
<?php

namespace App\Mail;

use App\Services\TwoFactorChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Model $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion — DGE');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.two-factor-code', with: [
            'code' => $this->code,
            'minutes' => TwoFactorChallenge::TTL_MINUTES,
        ]);
    }
}
```

Create `resources/views/emails/two-factor-code.blade.php`:
```blade
<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#1c2b3a">
    <h2 style="color:#193761">Direction générale des Élections</h2>
    <p>Votre code de connexion à usage unique :</p>
    <p style="font-size:30px;font-weight:700;letter-spacing:6px;color:#309966;margin:18px 0">{{ $code }}</p>
    <p style="color:#4f5e6e;font-size:14px">Ce code expire dans {{ $minutes }} minutes. Si vous n'êtes pas à l'origine de cette connexion, ignorez cet email.</p>
</div>
```

- [ ] **Step 4: Refonte du composant Login**

Replace `app/Livewire/Auth/Login.php` with:
```php
<?php

namespace App\Livewire\Auth;

use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use App\Support\Accueil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        $user = User::where('matricule', trim($this->matricule))->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou mot de passe invalide.',
            ]);
        }

        // 1) Compte sans email → capture obligatoire.
        if (blank($user->email)) {
            session()->put('pending_email_user', $user->id);

            return redirect()->route('email.requis');
        }

        // 2) Email non vérifié → connecter puis renvoyer vers la notice (middleware verified bloque l'app).
        if (! $user->hasVerifiedEmail()) {
            Auth::login($user);
            session()->regenerate();

            return redirect()->route('verification.notice');
        }

        // 3) Appareil de confiance → connexion directe, sinon défi 2FA.
        if (app(TrustedDeviceManager::class)->matches($user, request()->cookie('dge_td'))) {
            Auth::login($user);
            session()->regenerate();

            return redirect()->to(Accueil::pour($user));
        }

        $code = app(TwoFactorChallenge::class)->issueFor($user);
        Mail::to($user->email)->send(new TwoFactorCode($user, $code));

        session()->put('pending_2fa', ['id' => $user->id]);

        return redirect()->route('two-factor.show');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
```

- [ ] **Step 5: Composant TwoFactor (défi)**

Create `app/Livewire/Auth/TwoFactor.php`:
```php
<?php

namespace App\Livewire\Auth;

use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use App\Support\Accueil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $code = '';
    public bool $remember_device = false;

    private function pendingUser(): ?User
    {
        $id = session('pending_2fa.id');

        return $id ? User::find($id) : null;
    }

    public function mount()
    {
        if (! session()->has('pending_2fa')) {
            return redirect()->route('login');
        }
    }

    public function verifier()
    {
        $user = $this->pendingUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $this->validate(['code' => ['required', 'string']]);

        if (! app(TwoFactorChallenge::class)->verify($user, trim($this->code))) {
            $this->addError('code', 'Code invalide ou expiré.');

            return;
        }

        if ($this->remember_device) {
            $token = app(TrustedDeviceManager::class)->remember($user, request()->userAgent());
            Cookie::queue('dge_td', $token, 60 * 24 * TrustedDeviceManager::TTL_DAYS);
        }

        Auth::login($user);
        session()->forget('pending_2fa');
        session()->regenerate();

        return redirect()->to(Accueil::pour($user));
    }

    public function renvoyer()
    {
        $user = $this->pendingUser();
        if ($user) {
            $code = app(TwoFactorChallenge::class)->issueFor($user);
            Mail::to($user->email)->send(new TwoFactorCode($user, $code));
            session()->flash('ok', 'Un nouveau code vous a été envoyé.');
        }
    }

    public function render()
    {
        return view('livewire.auth.two-factor');
    }
}
```

Create `resources/views/livewire/auth/two-factor.blade.php`:
```blade
<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Vérification en deux étapes</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Saisissez le code à 6 chiffres envoyé à votre adresse email.</p>

    @if (session('ok'))
        <div style="background:var(--green-soft);color:var(--green-deep);padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:14px">{{ session('ok') }}</div>
    @endif

    <form wire:submit="verifier" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="field"
                   style="letter-spacing:8px;text-align:center;font-size:22px" placeholder="______">
            @error('code') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
            <input type="checkbox" wire:model="remember_device"> Se souvenir de cet appareil (30 jours)
        </label>
        <button type="submit" wire:loading.attr="disabled" wire:target="verifier" class="btn btn-primary">
            <span wire:loading.remove wire:target="verifier">Vérifier</span>
            <span wire:loading wire:target="verifier">Vérification…</span>
        </button>
    </form>

    <div style="margin-top:14px;font-size:13px">
        <button type="button" wire:click="renvoyer" style="background:none;border:none;color:var(--green);cursor:pointer;padding:0">Renvoyer le code</button>
    </div>
</div>
```

- [ ] **Step 6: Composant EmailRequis (comptes legacy sans email)**

Create `app/Livewire/Auth/EmailRequis.php`:
```php
<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmailRequis extends Component
{
    public string $email = '';

    private function pendingUser(): ?User
    {
        $id = session('pending_email_user');

        return $id ? User::find($id) : null;
    }

    public function mount()
    {
        if (! $this->pendingUser()) {
            return redirect()->route('login');
        }
    }

    public function enregistrer()
    {
        $user = $this->pendingUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $this->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update(['email' => strtolower(trim($this->email))]);
        $user->sendEmailVerificationNotification();

        Auth::login($user);
        session()->forget('pending_email_user');
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.email-requis');
    }
}
```

Create `resources/views/livewire/auth/email-requis.blade.php`:
```blade
<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Ajoutez votre email</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Pour sécuriser votre compte, renseignez une adresse email. Un lien de vérification vous y sera envoyé.</p>
    <form wire:submit="enregistrer" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <input type="email" wire:model="email" class="field" placeholder="prenom.nom@dge.sn">
            @error('email') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer et vérifier</button>
    </form>
</div>
```

- [ ] **Step 7: Routes (guest) pour 2FA + email requis**

Modify `routes/web.php` — dans le groupe `Route::middleware('guest')->group(...)`, ajouter :
```php
    Route::get('/two-factor', \App\Livewire\Auth\TwoFactor::class)->name('two-factor.show');
    Route::get('/email-requis', \App\Livewire\Auth\EmailRequis::class)->name('email.requis');
```
(Ces pages sont accessibles avant la connexion effective ; l'accès est gardé par la présence des clés de session `pending_2fa` / `pending_email_user`, gérée dans les composants.)

- [ ] **Step 8: Lancer — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Auth/LoginTwoFactorTest.php`
Expected: PASS (5 tests).

- [ ] **Step 9: Lancer TOUTE la suite + réparer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Réparer tout test cassé par le nouveau flux (ex : anciens tests de login qui supposaient une connexion immédiate — les faire passer par un user vérifié + trusted device, ou tester la nouvelle redirection). Vert obligatoire.

- [ ] **Step 10: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat(auth): connexion 2FA par email + appareil de confiance + capture email legacy"
```

---

## Self-Review (effectué)

- **Couverture :** inscription avec email + vérification obligatoire (Task 2) ; 2FA par code email à chaque connexion sauf appareil de confiance (Task 3) ; comptes legacy sans email forcés d'en ajouter (Task 3, `EmailRequis`) ; vérification d'email native + middleware `verified` (Task 2). Réplique OFNAC (services, mail, trusted device) adaptée Livewire/guard web/matricule.
- **Placeholders :** aucun. Toutes les vues et méthodes ont leur code.
- **Cohérence types :** `RegisterAgentAccount::handle(matricule,noms,prenoms,email,password)` (Task 2) appelé avec 5 args par `Register` (Task 2). `Accueil::pour(User): string` (Task 2 stub, utilisé Task 2+3). Sessions : `pending_2fa.id`, `pending_email_user`. Cookie `dge_td`. Services et constantes définis Task 1, consommés Task 3. Mail `TwoFactorCode(Model,string)` défini Task 3, utilisé Login/TwoFactor.
- **Point d'attention (Task 2 Step 7 & Task 3 Step 9) :** l'ajout du middleware `verified` casse les tests existants qui frappent des routes protégées avec des users non vérifiés → renseigner `email_verified_at`/`markEmailAsVerified()` dans les helpers de test concernés. C'est explicitement prévu dans les steps.

## Dépendances / suites

- **SMTP** : configurer `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST/PORT/USERNAME/PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME="DGE"`) — fait par l'utilisateur. En local sans SMTP : `MAIL_MAILER=log` (le lien/code apparaît dans `storage/logs/laravel.log`).
- Après ce plan : nettoyage optionnel (page « mes appareils de confiance », révocation), et déploiement VPS.
