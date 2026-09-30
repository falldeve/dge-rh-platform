# Cycle de vie des comptes (provisioning admin/RH + mot de passe provisoire + reset) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Remplacer l'auto-inscription par des comptes **provisionnés** : un **super-admin** crée les comptes RH/courrier/archiviste ; la **RH** crée les comptes agents en saisissant juste l'email. Chaque compte reçoit par email un **mot de passe provisoire** à **changer obligatoirement à la première connexion**, et tout utilisateur peut **réinitialiser son mot de passe** (mot de passe oublié) — façon OFNAC.

**Architecture:** Nouveau rôle `admin` (super-admin). Une action `ProvisionAccount` crée l'utilisateur (email vérifié d'office, mot de passe aléatoire, `must_change_password=true`) et envoie le mail `AccountProvisioned`. Un middleware `RequirePasswordChange` force le changement avant tout accès. La réinitialisation utilise le mécanisme natif Laravel (`password_reset_tokens` + notification). L'inscription publique est supprimée.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Mail (Gmail SMTP en prod).

## Global Constraints

- `cd /Users/admin/dge-rh-platform` en tête de chaque commande. Ignorer `Warning: Module "swoole"...`. Tests: `./vendor/bin/pest`.
- Branche : `git checkout main && git checkout -b feat/account-lifecycle`.
- Politique déjà en place (à préserver): email+2FA (services `TwoFactorChallenge`/`TrustedDeviceManager`, `Login` valide sans connecter, `TwoFactor`, `EmailRequis`), middleware `verified` sur les routes applicatives, `App\Support\Accueil::pour(User)`. GOTCHA: `email_verified_at` DOIT rester dans `User::$fillable`. `wire:click="$reset(...)"` interdit → méthode. `Livewire::test()` → `assertForbidden()` pas `toThrow`. Chemins relatifs, jamais `asset()`.
- Rôle enum users actuel: `['agent','chef_direction','admin_rh','dg','secretaire','courrier','archiviste']`. `password_reset_tokens` existe déjà ; `User extends Authenticatable` inclut déjà le trait `CanResetPassword`.
- Réplique OFNAC: `~/ofnac-platform/app/Http/Middleware/RequirePasswordChange.php`, `app/Http/Controllers/Assujetti/{PasswordController,PasswordResetLinkController,NewPasswordController}.php`, `app/Notifications/AssujettiResetPassword.php`, migration `..._add_must_change_password_to_assujettis.php`.

---

## Task 1: Rôle super-admin + provisioning (mot de passe provisoire par email)

**Files:**
- Create: `database/migrations/2026_08_05_100000_add_admin_role_to_users.php`, `database/migrations/2026_08_05_100001_add_must_change_password_to_users.php`
- Create: `app/Actions/ProvisionAccount.php`, `app/Mail/AccountProvisioned.php`, `resources/views/emails/account-provisioned.blade.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Auth/ProvisionAccountTest.php`

**Interfaces produites:**
- `ProvisionAccount::handle(array $data): User` où `$data` = `['name','matricule'=>?, 'email','role', 'agent_id'=>?]`. Crée l'utilisateur (email vérifié, mot de passe aléatoire, `must_change_password=true`), lie l'agent si `agent_id`, envoie `AccountProvisioned`. Retourne le User.
- `User::isAdmin(): bool` ; colonne `must_change_password` (bool, fillable, cast).

- [ ] **Step 1: Test qui échoue**

Create `tests/Feature/Auth/ProvisionAccountTest.php`:
```php
<?php

use App\Actions\ProvisionAccount;
use App\Mail\AccountProvisioned;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('provisionne un compte avec mot de passe provisoire et envoie l’email', function () {
    Mail::fake();

    $user = app(ProvisionAccount::class)->handle([
        'name' => 'Awa DIOP', 'matricule' => 'COURRIER', 'email' => 'awa@dge.sn', 'role' => 'courrier',
    ]);

    expect($user->role)->toBe('courrier');
    expect($user->must_change_password)->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeTrue();          // vérifié d'office (admin vouche)
    Mail::assertSent(AccountProvisioned::class, fn ($m) => $m->hasTo('awa@dge.sn'));
});

it('refuse un email déjà utilisé', function () {
    User::create(['name' => 'X', 'matricule' => 'Z', 'email' => 'awa@dge.sn', 'password' => bcrypt('x'), 'role' => 'agent']);

    expect(fn () => app(ProvisionAccount::class)->handle(['name' => 'Y', 'email' => 'awa@dge.sn', 'role' => 'agent']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
```

- [ ] **Step 2: Lancer — échoue** — `./vendor/bin/pest tests/Feature/Auth/ProvisionAccountTest.php`

- [ ] **Step 3: Migrations**

Create `database/migrations/2026_08_05_100000_add_admin_role_to_users.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('agent','chef_direction','admin_rh','dg','secretaire','courrier','archiviste','admin') NOT NULL DEFAULT 'agent'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('agent','chef_direction','admin_rh','dg','secretaire','courrier','archiviste') NOT NULL DEFAULT 'agent'");
    }
};
```
(Vérifier le défaut réel de la colonne dans la dernière migration `add_courrier_roles` et le reproduire ; défaut `agent` supposé.)

Create `database/migrations/2026_08_05_100001_add_must_change_password_to_users.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
```

- [ ] **Step 4: User — fillable/cast + helper**

Modify `app/Models/User.php` : ajouter `'must_change_password'` à `$fillable` ; dans `casts()` ajouter `'must_change_password' => 'boolean'` ; ajouter :
```php
public function isAdmin(): bool
{
    return $this->role === 'admin';
}
```

- [ ] **Step 5: Action + Mail + vue**

Create `app/Actions/ProvisionAccount.php`:
```php
<?php

namespace App\Actions;

use App\Mail\AccountProvisioned;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionAccount
{
    /** @param array{name:string,email:string,role:string,matricule?:string,agent_id?:int} $data */
    public function handle(array $data): User
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string'],
        ])->validate();

        $temp = Str::password(12); // mot de passe provisoire aléatoire

        $user = User::create([
            'name' => $data['name'],
            'matricule' => $data['matricule'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($temp),
            'role' => $data['role'],
            'must_change_password' => true,
        ]);
        $user->markEmailAsVerified(); // l'admin garantit l'adresse ; le mail provisoire prouve sa validité

        if (! empty($data['agent_id'])) {
            Agent::whereKey($data['agent_id'])->update(['user_id' => $user->id]);
        }

        Mail::to($user->email)->send(new AccountProvisioned($user, $temp));

        return $user;
    }
}
```

Create `app/Mail/AccountProvisioned.php`:
```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountProvisioned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte DGE — accès');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-provisioned', with: [
            'user' => $this->user,
            'password' => $this->temporaryPassword,
            'url' => route('login'),
        ]);
    }
}
```

Create `resources/views/emails/account-provisioned.blade.php`:
```blade
<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#1c2b3a">
    <h2 style="color:#193761">Direction générale des Élections</h2>
    <p>Bonjour {{ $user->name }},</p>
    <p>Un compte vous a été créé sur la plateforme RH de la DGE.</p>
    <p><strong>Matricule / identifiant :</strong> {{ $user->matricule ?? $user->email }}<br>
       <strong>Mot de passe provisoire :</strong>
       <span style="font-family:monospace;font-size:16px;background:#eef2f6;padding:2px 6px;border-radius:4px">{{ $password }}</span></p>
    <p>Connectez-vous ici : <a href="{{ $url }}" style="color:#309966">{{ $url }}</a></p>
    <p style="color:#4f5e6e;font-size:14px">À votre première connexion, il vous sera demandé de définir un nouveau mot de passe. Un code de vérification vous sera aussi envoyé par email (double authentification).</p>
</div>
```

- [ ] **Step 6: Lancer — passe** (2 tests) ; puis `php artisan migrate --force`.

- [ ] **Step 7: Commit** — `feat(auth): role super-admin + provisioning de comptes (mot de passe provisoire par email)`

---

## Task 2: Changement de mot de passe obligatoire à la première connexion

**Files:**
- Create: `app/Http/Middleware/RequirePasswordChange.php`, `app/Livewire/Auth/ChangerMotDePasse.php`, `resources/views/livewire/auth/changer-mot-de-passe.blade.php`
- Modify: `bootstrap/app.php` (alias middleware), `routes/web.php`
- Test: `tests/Feature/Auth/ForcedPasswordChangeTest.php`

**Interfaces produites:** middleware alias `password.change` ; route `password.change` (GET `/changer-mot-de-passe`) ; composant `ChangerMotDePasse` avec `changer()`.

- [ ] **Step 1: Test qui échoue**

Create `tests/Feature/Auth/ForcedPasswordChangeTest.php`:
```php
<?php

use App\Livewire\Auth\ChangerMotDePasse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userAChanger(): User
{
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('provisoire'), 'role' => 'admin_rh', 'must_change_password' => true]);

    return $u;
}

it('redirige un utilisateur must_change vers le changement de mot de passe', function () {
    $this->withoutVite();
    $u = userAChanger();

    $this->actingAs($u)->get('/rh/tableau-bord')->assertRedirect(route('password.change'));
});

it('le changement de mot de passe lève le drapeau et connecte à l’app', function () {
    $u = userAChanger();

    Livewire::actingAs($u)->test(ChangerMotDePasse::class)
        ->set('password', 'nouveauMotDePasse1')
        ->set('password_confirmation', 'nouveauMotDePasse1')
        ->call('changer')
        ->assertHasNoErrors();

    $u->refresh();
    expect($u->must_change_password)->toBeFalse();
    expect(Hash::check('nouveauMotDePasse1', $u->password))->toBeTrue();
});
```

- [ ] **Step 2: Lancer — échoue**

- [ ] **Step 3: Middleware**

Create `app/Http/Middleware/RequirePasswordChange.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Alias middleware**

Modify `bootstrap/app.php` — dans `->withMiddleware(function (Middleware $middleware) { ... })`, ajouter l'alias :
```php
$middleware->alias([
    // ... alias existants (dont 'role' => EnsureRole::class) ...
    'password.change' => \App\Http\Middleware\RequirePasswordChange::class,
]);
```
(READ `bootstrap/app.php` d'abord ; l'alias `role` y est déjà — ajouter à la même liste.)

- [ ] **Step 5: Composant + vue + route**

Create `app/Livewire/Auth/ChangerMotDePasse.php`:
```php
<?php

namespace App\Livewire\Auth;

use App\Support\Accueil;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangerMotDePasse extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function changer()
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth()->user();
        $user->update([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ]);

        session()->flash('ok', 'Mot de passe mis à jour.');

        return redirect()->to(Accueil::pour($user));
    }

    public function render()
    {
        return view('livewire.auth.changer-mot-de-passe');
    }
}
```

Create `resources/views/livewire/auth/changer-mot-de-passe.blade.php`:
```blade
<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Définir votre mot de passe</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Choisissez un nouveau mot de passe pour sécuriser votre compte.</p>
    <form wire:submit="changer" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Nouveau mot de passe</label>
            <input type="password" wire:model="password" class="field">
            @error('password') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Confirmer</label>
            <input type="password" wire:model="password_confirmation" class="field">
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</div>
```

Modify `routes/web.php` — dans le groupe `Route::middleware('auth')->group(...)` (sans `verified`/`role`/`password.change`), ajouter :
```php
    Route::get('/changer-mot-de-passe', \App\Livewire\Auth\ChangerMotDePasse::class)->name('password.change');
```
Puis ajouter le middleware `'password.change'` à **tous les groupes applicatifs** (ceux qui ont déjà `verified`) : `['auth', 'verified', 'password.change', 'role:…']` etc. Ne PAS l'ajouter aux routes login/2fa/verification/logout/password.change elle-même.

- [ ] **Step 6: Lancer — passe** ; puis suite complète `./vendor/bin/pest` et réparer les tests dont les users `actingAs` frappent des routes protégées : s'assurer que `must_change_password` est `false` (défaut) — les `User::create` de test ne le mettent pas, donc OK par défaut. Vérifier aucun test cassé.

- [ ] **Step 7: Commit** — `feat(auth): changement de mot de passe force a la premiere connexion`

---

## Task 3: UIs de provisioning + retrait de l'auto-inscription

**Files:**
- Create: `app/Livewire/Admin/ComptesSysteme.php` + vue
- Modify: `app/Livewire/Rh/AgentsIndex.php` (+ action créer compte) + sa vue, `resources/views/components/layouts/rh.blade.php` (nav), `routes/web.php`, `resources/views/livewire/auth/login.blade.php` (retirer lien inscription)
- Delete: route `/register` + usage `Register` (le composant peut rester mais non routé)
- Test: `tests/Feature/Auth/ProvisioningUiTest.php`

- [ ] **Step 1: Test qui échoue**

Create `tests/Feature/Auth/ProvisioningUiTest.php`:
```php
<?php

use App\Livewire\Admin\ComptesSysteme;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function superAdmin(): User
{
    $u = User::create(['name' => 'Super', 'matricule' => 'ADMIN', 'email' => 'admin@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin']);

    return $u;
}

it('le super-admin crée un compte courrier', function () {
    Mail::fake();
    Livewire::actingAs(superAdmin())->test(ComptesSysteme::class)
        ->set('name', 'Bureau Courrier')->set('matricule', 'COURRIER')->set('email', 'courrier@dge.sn')->set('role', 'courrier')
        ->call('creer')->assertHasNoErrors();

    expect(User::where('email', 'courrier@dge.sn')->where('role', 'courrier')->exists())->toBeTrue();
});

it('la page comptes système est interdite aux non-admin (403)', function () {
    $this->withoutVite();
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'email' => 'rh@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin_rh']);

    $this->actingAs($rh)->get('/admin/comptes')->assertForbidden();
});

it('la RH crée le compte d’un agent avec son email', function () {
    Mail::fake();
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    $rh = User::create(['name' => 'RH', 'matricule' => 'RH1', 'email' => 'rh@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'admin_rh']);

    Livewire::actingAs($rh)->test(\App\Livewire\Rh\AgentsIndex::class)
        ->call('creerCompte', $agent->id, 'awa@dge.sn');

    $agent->refresh();
    expect($agent->user_id)->not->toBeNull();
    expect(User::find($agent->user_id)->role)->toBe('agent');
});

it('la route d’inscription publique est supprimée (404)', function () {
    $this->withoutVite();
    $this->get('/register')->assertNotFound();
});
```

- [ ] **Step 2: Lancer — échoue**

- [ ] **Step 3: ComptesSysteme (super-admin)**

Create `app/Livewire/Admin/ComptesSysteme.php`:
```php
<?php

namespace App\Livewire\Admin;

use App\Actions\ProvisionAccount;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class ComptesSysteme extends Component
{
    public string $name = '';
    public ?string $matricule = null;
    public string $email = '';
    public string $role = 'admin_rh';

    /** Rôles système provisionnables par le super-admin (pas 'agent' — géré par la RH). */
    public array $rolesDispo = ['admin_rh', 'courrier', 'archiviste', 'dg'];

    public function creer(ProvisionAccount $action)
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin_rh,courrier,archiviste,dg'],
        ]);

        $action->handle([
            'name' => $this->name,
            'matricule' => $this->matricule,
            'email' => $this->email,
            'role' => $this->role,
        ]);

        $this->reset(['name', 'matricule', 'email']);
        $this->role = 'admin_rh';
        session()->flash('ok', 'Compte créé — mot de passe provisoire envoyé par email.');
    }

    public function render()
    {
        return view('livewire.admin.comptes-systeme', [
            'comptes' => User::whereIn('role', ['admin', 'admin_rh', 'courrier', 'archiviste', 'dg'])->orderBy('role')->get(),
        ]);
    }
}
```

Create `resources/views/livewire/admin/comptes-systeme.blade.php` (formulaire name/matricule/email/role + tableau des comptes système ; réutiliser les classes `.card/.field/.btn`, style cohérent avec `entites-manager.blade.php`). Bouton submit avec état loading (`wire:target="creer"`).

- [ ] **Step 4: RH crée le compte agent**

Modify `app/Livewire/Rh/AgentsIndex.php` — ajouter :
```php
public function creerCompte(int $agentId, string $email): void
{
    $agent = \App\Models\Agent::findOrFail($agentId);
    abort_if($agent->user_id !== null, 422);

    app(\App\Actions\ProvisionAccount::class)->handle([
        'name' => $agent->nomComplet(),
        'matricule' => $agent->matricule,
        'email' => $email,
        'role' => 'agent',
        'agent_id' => $agent->id,
    ]);

    session()->flash('ok', 'Compte créé — identifiants envoyés à '.$email);
}
```
Dans `resources/views/livewire/rh/agents-index.blade.php` — pour chaque agent **sans compte** (`$agent->user_id === null`), ajouter un petit formulaire inline (Alpine) « Créer le compte » : un champ email + bouton qui appelle `creerCompte({{ $agent->id }}, email)`. Pour les agents **avec** compte, afficher un badge « Compte actif ». (Charger `user_id` : la requête `render()` sélectionne déjà l'agent ; s'assurer que `user_id` est disponible.)

- [ ] **Step 5: Routes + nav + retrait inscription**

Modify `routes/web.php` :
- **Supprimer** la route `Route::get('/register', Register::class)->name('register');` (et l'import `use App\Livewire\Auth\Register;` s'il n'est plus utilisé).
- Ajouter le groupe super-admin :
```php
Route::middleware(['auth', 'verified', 'password.change', 'role:admin'])->group(function () {
    Route::get('/admin/comptes', \App\Livewire\Admin\ComptesSysteme::class)->name('admin.comptes');
});
```
Modify `resources/views/components/layouts/rh.blade.php` : bloc `@if ($__u->isAdmin())` avec lien « Comptes système » (`route('admin.comptes')`). (Ajouter le helper `isAdmin()` est fait au Task 1.)
Modify `resources/views/livewire/auth/login.blade.php` : **retirer** le lien « Pas encore de compte ? Créer un compte » et le remplacer par « Mot de passe oublié ? » (route `password.request`, créée au Task 4 — pour l'instant mettre le lien, la route arrive au Task 4).

- [ ] **Step 6: Lancer — passe (4 tests)** + suite complète, réparer les tests qui utilisaient `/register` ou le composant `Register` (les adapter à `ProvisionAccount` ou les retirer).

- [ ] **Step 7: Commit** — `feat(auth): UIs de provisioning (super-admin comptes systeme + RH comptes agents) + retrait auto-inscription`

---

## Task 4: Réinitialisation du mot de passe (mot de passe oublié)

**Files:**
- Create: `app/Livewire/Auth/MotDePasseOublie.php` + vue, `app/Livewire/Auth/ReinitialiserMotDePasse.php` + vue
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces produites:** routes `password.request` (GET `/mot-de-passe-oublie`), `password.email` (via composant), `password.reset` (GET `/reinitialiser-mot-de-passe/{token}`), `password.update` (via composant). Utilise la façade `Password` + notification native `ResetPassword`.

- [ ] **Step 1: Test qui échoue**

Create `tests/Feature/Auth/PasswordResetTest.php`:
```php
<?php

use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\ReinitialiserMotDePasse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('envoie le lien de réinitialisation', function () {
    Notification::fake();
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'agent']);

    Livewire::test(MotDePasseOublie::class)->set('email', 'm1@dge.sn')->call('envoyer')->assertHasNoErrors();

    Notification::assertSentTo($u, ResetPassword::class);
});

it('réinitialise le mot de passe avec un token valide', function () {
    $u = User::create(['name' => 'X', 'matricule' => 'M1', 'email' => 'm1@dge.sn', 'email_verified_at' => now(), 'password' => bcrypt('ancien'), 'role' => 'agent']);
    $token = Password::createToken($u);

    Livewire::test(ReinitialiserMotDePasse::class, ['token' => $token, 'email' => 'm1@dge.sn'])
        ->set('email', 'm1@dge.sn')
        ->set('password', 'nouveau1234')->set('password_confirmation', 'nouveau1234')
        ->call('reinitialiser')
        ->assertRedirect(route('login'));

    expect(Illuminate\Support\Facades\Hash::check('nouveau1234', $u->fresh()->password))->toBeTrue();
});
```

- [ ] **Step 2: Lancer — échoue**

- [ ] **Step 3: Composant « mot de passe oublié »**

Create `app/Livewire/Auth/MotDePasseOublie.php`:
```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Component;

class MotDePasseOublie extends Component
{
    public string $email = '';

    public function envoyer()
    {
        $this->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => strtolower(trim($this->email))]);

        // Message neutre (pas d'énumération d'emails).
        session()->flash('ok', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
    }

    public function render()
    {
        return view('livewire.auth.mot-de-passe-oublie');
    }
}
```

Create `resources/views/livewire/auth/mot-de-passe-oublie.blade.php` (champ email + bouton « Envoyer le lien » + message flash + lien retour login).

- [ ] **Step 4: Composant « réinitialiser »**

Create `app/Livewire/Auth/ReinitialiserMotDePasse.php`:
```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReinitialiserMotDePasse extends Component
{
    public string $token = '';
    #[Url]
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function reinitialiser()
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            ['email' => strtolower(trim($this->email)), 'password' => $this->password, 'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('ok', 'Mot de passe réinitialisé. Connectez-vous.');

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.reinitialiser-mot-de-passe');
    }
}
```

Create `resources/views/livewire/auth/reinitialiser-mot-de-passe.blade.php` (email + password + confirm + bouton).

- [ ] **Step 5: Routes (guest)**

Modify `routes/web.php` — dans le groupe `Route::middleware('guest')->group(...)` :
```php
    Route::get('/mot-de-passe-oublie', \App\Livewire\Auth\MotDePasseOublie::class)->name('password.request');
    Route::get('/reinitialiser-mot-de-passe/{token}', \App\Livewire\Auth\ReinitialiserMotDePasse::class)->name('password.reset');
```

- [ ] **Step 6: Lancer — passe (2 tests)** + suite complète verte.

- [ ] **Step 7: Commit** — `feat(auth): reinitialisation du mot de passe (mot de passe oublie)`

---

## Self-Review (effectué)

- **Couverture spec:** super-admin crée RH/courrier/archiviste (Task 1+3) ; RH crée agents par email (Task 3) ; mot de passe provisoire par email (Task 1) ; changement forcé 1re connexion (Task 2) ; reset mot de passe façon OFNAC (Task 4) ; auto-inscription retirée (Task 3).
- **Types:** `ProvisionAccount::handle(array): User` (Task 1) consommé par ComptesSysteme + AgentsIndex (Task 3). `must_change_password` (Task 1) lu par middleware (Task 2). `Accueil::pour` réutilisé. `isAdmin()` (Task 1) utilisé nav+route (Task 3).
- **Ordre d'exécution du login (déjà en place) :** `Login` → 2FA → `Auth::login`. Le middleware `password.change` s'applique APRÈS connexion sur les routes app → un compte provisionné passe 2FA puis est renvoyé vers `password.change`. Cohérent.
- **Placeholders:** vues listées « à créer » ont un contenu décrit + classes précises ; l'implémenteur suit le style de `entites-manager`/`two-factor` existants.

## Déploiement (après merge)
`git push` puis sur le VPS : `git pull && composer install --no-dev -o && php artisan migrate --force && php artisan config:cache route:cache view:cache && npm ci && npm run build`. Promouvoir le compte ADMIN existant en super-admin : `php artisan tinker --execute="App\Models\User::where('matricule','ADMIN')->update(['role'=>'admin']);"`.
