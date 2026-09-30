# Plan 4 — Demandes & Workflow (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à un agent de soumettre une demande (congé annuel, permission, ordre de mission) et la faire circuler dans un workflow à 2 niveaux (Chef de direction → DRHF), avec décompte du solde de congé à la validation finale et notifications in-app.

**Architecture:** La logique métier vit dans deux actions testables sans HTTP : `SoumettreDemande` (calcul des jours calendaires, contrôle du solde, routage initial du statut) et `DeciderDemande` (transitions d'état, contrôle d'autorisation, décrément du solde, journal des validations, notification de l'agent). Les composants Livewire sont fins et délèguent à ces actions. Les notifications utilisent le canal `database` natif de Laravel.

**Tech Stack:** Laravel 12, Livewire 3, Pest, Tailwind, notifications `database`. S'appuie sur Plans 1-3 : modèles `Agent` (`solde_conge_jours`, `direction`), `Direction` (`chef_id`), `User` (`role`, helpers `isAdminRh/isChefDirection`, `agent()`), middleware `role`.

**Prérequis d'exécution:** créer la branche `feat/plan4-demandes` **à partir de** `feat/plan2-auth` :
```bash
cd /Users/admin/dge-rh-platform
git checkout feat/plan2-auth
git checkout -b feat/plan4-demandes
```
Environnement local : PHP 8.5.5, Composer 2.9.5, MySQL 9.6 (root, sans mot de passe), base `dge_rh`. `php` émet un `Warning: Module "swoole" is already loaded` inoffensif sur stderr — l'ignorer. Tests sur SQLite in-memory. Les tests HTTP qui rendent un layout `@vite` appellent `$this->withoutVite();`. Toujours `cd /Users/admin/dge-rh-platform` en tête de chaque commande.

**Référence spec:** `docs/superpowers/specs/2026-07-11-plateforme-rh-dge-design.md` §8 (workflow), §9 (calcul congé), §5 (tables demandes/validations).

**Règles métier verrouillées:**
- Types (colonne) : `conge_annuel`, `permission`, `ordre_mission`. **En libre-service agent, seuls `conge_annuel` et `permission`** sont disponibles dans ce plan. `ordre_mission` est réservé au **secrétaire de direction** (rôle et flux traités en **Plan 5**, sans workflow) ; on prévoit juste sa place dans le schéma (enum type + statut `emise`).
- Statuts : `brouillon`, `soumise`, `validee_chef`, `validee_rh`, `refusee`, `emise`. Le statut `emise` est destiné aux ordres de mission (Plan 5) ; congé/permission n'utilisent que `soumise`/`validee_chef`/`validee_rh`/`refusee`.
- `nb_jours` = jours **calendaires** inclusifs = `date_debut..date_fin` (week-ends/fériés compris).
- Circuit : agent soumet → `soumise` → chef valide → `validee_chef` → DRHF valide → `validee_rh` (finale). Refus à tout niveau → `refusee`.
- **Saut N1** : si l'agent est le chef de sa direction, OU si sa direction n'a pas de chef assigné, la demande part directement en `validee_chef` (file DRHF).
- **Congé annuel** : `nb_jours` doit être ≤ `solde_conge_jours` à la soumission ; le solde est décrémenté **uniquement** à `validee_rh`. Permission ne touche pas le solde.
- Chaque décision (validation/refus) notifie l'agent (in-app).

---

## Fichiers créés/modifiés dans ce plan

- `database/migrations/*_create_demandes_table.php`, `*_create_validations_table.php`, `*_create_notifications_table.php`.
- `app/Models/Demande.php`, `app/Models/Validation.php` ; `app/Models/Agent.php` (relation `demandes`, modifié).
- `app/Actions/SoumettreDemande.php`, `app/Actions/DeciderDemande.php`.
- `app/Notifications/DemandeDecisionNotification.php`.
- `app/Livewire/Demandes/NouvelleDemande.php` + vue ; `app/Livewire/Demandes/MesDemandes.php` + vue.
- `app/Livewire/Validation/FileChef.php` + vue ; `app/Livewire/Validation/FileRh.php` + vue.
- `app/Livewire/Notifications/Cloche.php` + vue (affichage + marquage lu).
- `routes/web.php` (modifié), `resources/views/dashboard.blade.php` (modifié : liens + cloche).
- `tests/Feature/Demandes/*`, `tests/Feature/Validation/*`.

---

## Task 1: Schéma & modèles (demandes, validations, notifications)

**Files:**
- Create: `database/migrations/2026_07_12_000001_create_demandes_table.php`
- Create: `database/migrations/2026_07_12_000002_create_validations_table.php`
- Create: `app/Models/Demande.php`, `app/Models/Validation.php`
- Modify: `app/Models/Agent.php`
- Test: `tests/Feature/Demandes/ModelsTest.php`

- [ ] **Step 1: Générer la table des notifications**

Run:
```bash
cd /Users/admin/dge-rh-platform
php artisan make:notifications-table
```
Expected: une migration `*_create_notifications_table.php` est créée. (Si la commande n'existe pas dans cette version, créer manuellement la migration standard Laravel des notifications : table `notifications` avec `uuid id` primary, `string type`, `morphs notifiable`, `text data`, `timestamp read_at nullable`, `timestamps`.)

- [ ] **Step 2: Écrire le test qui échoue**

Create `tests/Feature/Demandes/ModelsTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\Validation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unAgent(): Agent
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    return Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30,
    ]);
}

it('crée une demande liée à un agent', function () {
    $agent = unAgent();

    $demande = Demande::create([
        'agent_id' => $agent->id,
        'type' => 'conge_annuel',
        'date_debut' => '2026-08-01',
        'date_fin' => '2026-08-05',
        'nb_jours' => 5,
        'statut' => 'soumise',
    ]);

    expect($demande->agent->id)->toBe($agent->id);
    expect($agent->demandes()->count())->toBe(1);
    expect($demande->date_debut->format('Y-m-d'))->toBe('2026-08-01');
    expect($demande->meta)->toBeNull();
});

it('stocke meta en tableau JSON', function () {
    $agent = unAgent();
    $demande = Demande::create([
        'agent_id' => $agent->id, 'type' => 'ordre_mission',
        'date_debut' => '2026-08-01', 'date_fin' => '2026-08-02',
        'nb_jours' => 2, 'statut' => 'soumise',
        'meta' => ['destination' => 'Saint-Louis', 'objet' => 'Supervision'],
    ]);

    expect($demande->fresh()->meta['destination'])->toBe('Saint-Louis');
});

it('journalise une validation', function () {
    $agent = unAgent();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise']);

    $v = Validation::create([
        'demande_id' => $demande->id, 'validateur_id' => $agent->id,
        'niveau' => 'chef', 'decision' => 'ok', 'commentaire' => 'OK',
    ]);

    expect($demande->validations()->count())->toBe(1);
    expect($v->demande->id)->toBe($demande->id);
    expect($v->validateur->id)->toBe($agent->id);
});
```

- [ ] **Step 3: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/ModelsTest.php`
Expected: FAIL (modèles/migrations absents).

- [ ] **Step 4: Migration demandes**

Create `database/migrations/2026_07_12_000001_create_demandes_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->enum('type', ['conge_annuel', 'permission', 'ordre_mission']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('nb_jours');
            $table->text('motif')->nullable();
            $table->enum('statut', ['brouillon', 'soumise', 'validee_chef', 'validee_rh', 'refusee', 'emise'])->default('brouillon');
            $table->string('piece_jointe_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
```

- [ ] **Step 5: Migration validations**

Create `database/migrations/2026_07_12_000002_create_validations_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes')->cascadeOnDelete();
            $table->foreignId('validateur_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->enum('niveau', ['chef', 'rh']);
            $table->enum('decision', ['ok', 'refus']);
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};
```

- [ ] **Step 6: Modèle Demande**

Create `app/Models/Demande.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demande extends Model
{
    public const TYPES = ['conge_annuel', 'permission', 'ordre_mission'];
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_SOUMISE = 'soumise';
    public const STATUT_VALIDEE_CHEF = 'validee_chef';
    public const STATUT_VALIDEE_RH = 'validee_rh';
    public const STATUT_REFUSEE = 'refusee';
    public const STATUT_EMISE = 'emise'; // ordres de mission (Plan 5)

    /** Types disponibles en libre-service agent (hors ordre de mission). */
    public const TYPES_SELF_SERVICE = ['conge_annuel', 'permission'];

    protected $fillable = [
        'agent_id', 'type', 'date_debut', 'date_fin', 'nb_jours',
        'motif', 'statut', 'piece_jointe_path', 'meta',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'nb_jours' => 'integer',
        'meta' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }

    public function estCongeAnnuel(): bool
    {
        return $this->type === 'conge_annuel';
    }
}
```

- [ ] **Step 7: Modèle Validation**

Create `app/Models/Validation.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validation extends Model
{
    protected $fillable = [
        'demande_id', 'validateur_id', 'niveau', 'decision', 'commentaire',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'validateur_id');
    }
}
```

- [ ] **Step 8: Relation `demandes` sur Agent**

Modify `app/Models/Agent.php` — ajouter la relation dans la classe :
```php
public function demandes(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Demande::class);
}
```

- [ ] **Step 9: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/ModelsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 10: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: schema et modeles demandes/validations + notifications table"
```

---

## Task 2: Action `SoumettreDemande`

Calcule `nb_jours` (jours calendaires inclusifs), contrôle le solde pour un congé annuel, route le statut initial (saut N1 si l'agent est chef de sa direction ou si la direction n'a pas de chef).

**Files:**
- Create: `app/Actions/SoumettreDemande.php`
- Test: `tests/Feature/Demandes/SoumettreDemandeTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Demandes/SoumettreDemandeTest.php`:
```php
<?php

use App\Actions\SoumettreDemande;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Demande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function directionAvecChef(?Agent &$chef = null): Direction
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $dir->update(['chef_id' => $chef->id]);

    return $dir->fresh();
}

function agentDe(Direction $dir): Agent
{
    return Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 30]);
}

it('calcule nb_jours en jours calendaires inclusifs', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    $demande = app(SoumettreDemande::class)->handle($agent, 'conge_annuel', '2026-08-01', '2026-08-05');

    expect($demande->nb_jours)->toBe(5); // 1,2,3,4,5 août inclus
    expect($demande->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('route directement en validee_chef quand l’agent est le chef', function () {
    $chef = null;
    $dir = directionAvecChef($chef);

    $demande = app(SoumettreDemande::class)->handle($chef, 'permission', '2026-08-10', '2026-08-10');

    expect($demande->nb_jours)->toBe(1);
    expect($demande->statut)->toBe(Demande::STATUT_VALIDEE_CHEF);
});

it('route en validee_chef quand la direction n’a pas de chef', function () {
    $dir = Direction::create(['code' => 'DOE', 'nom' => 'Opérations']); // chef_id null
    $agent = agentDe($dir);

    $demande = app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-01', '2026-08-02');

    expect($demande->statut)->toBe(Demande::STATUT_VALIDEE_CHEF);
});

it('refuse une date de fin antérieure au début', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-05', '2026-08-01'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});

it('refuse un congé annuel dépassant le solde', function () {
    $dir = directionAvecChef();
    $agent = Agent::create(['prenoms' => 'Petit', 'noms' => 'SOLDE', 'matricule' => 'P1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 3]);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'conge_annuel', '2026-08-01', '2026-08-10'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});

it('n’applique pas le contrôle de solde à une permission', function () {
    $dir = directionAvecChef();
    $agent = Agent::create(['prenoms' => 'Petit', 'noms' => 'SOLDE', 'matricule' => 'P1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    $demande = app(SoumettreDemande::class)->handle($agent, 'permission', '2026-08-01', '2026-08-10', 'raison');

    expect($demande->nb_jours)->toBe(10);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(0.0); // solde intact
});

it('refuse un ordre de mission en libre-service (réservé au secrétaire)', function () {
    $dir = directionAvecChef();
    $agent = agentDe($dir);

    expect(fn () => app(SoumettreDemande::class)->handle($agent, 'ordre_mission', '2026-08-01', '2026-08-02'))
        ->toThrow(ValidationException::class);
    expect(Demande::count())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/SoumettreDemandeTest.php`
Expected: FAIL (action absente).

- [ ] **Step 3: Écrire l'action**

Create `app/Actions/SoumettreDemande.php`:
```php
<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\Demande;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SoumettreDemande
{
    public function handle(
        Agent $agent,
        string $type,
        string $dateDebut,
        string $dateFin,
        ?string $motif = null,
        array $meta = [],
        ?string $pieceJointePath = null,
    ): Demande {
        if (! in_array($type, Demande::TYPES_SELF_SERVICE, true)) {
            throw ValidationException::withMessages([
                'type' => "Ce type n'est pas disponible en libre-service. Les ordres de mission sont initiés par le secrétaire de direction.",
            ]);
        }

        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();

        if ($fin->lt($debut)) {
            throw ValidationException::withMessages([
                'date_fin' => "La date de fin doit être postérieure ou égale à la date de début.",
            ]);
        }

        $nbJours = $debut->diffInDays($fin) + 1; // jours calendaires inclusifs

        if ($type === 'conge_annuel' && $nbJours > (float) $agent->solde_conge_jours) {
            throw ValidationException::withMessages([
                'nb_jours' => "Solde de congé insuffisant ({$agent->solde_conge_jours} j disponibles).",
            ]);
        }

        $direction = $agent->direction;
        $estChef = $direction && $direction->chef_id === $agent->id;
        $sansChef = ! $direction || $direction->chef_id === null;

        $statut = ($estChef || $sansChef)
            ? Demande::STATUT_VALIDEE_CHEF
            : Demande::STATUT_SOUMISE;

        return Demande::create([
            'agent_id' => $agent->id,
            'type' => $type,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'nb_jours' => $nbJours,
            'motif' => $motif,
            'statut' => $statut,
            'piece_jointe_path' => $pieceJointePath,
            'meta' => $meta ?: null,
        ]);
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/SoumettreDemandeTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: action SoumettreDemande (jours calendaires, solde, routage N1)"
```

---

## Task 3: Action `DeciderDemande` + notification

Applique une décision (chef ou RH), contrôle l'autorisation et la transition d'état, décrémente le solde à la validation RH d'un congé annuel, journalise la validation, notifie l'agent.

**Files:**
- Create: `app/Notifications/DemandeDecisionNotification.php`
- Create: `app/Actions/DeciderDemande.php`
- Test: `tests/Feature/Demandes/DeciderDemandeTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Demandes/DeciderDemandeTest.php`:
```php
<?php

use App\Actions\DeciderDemande;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contexte(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);

    $rhUser = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);

    $agentUser = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $agentUser->id]);

    return compact('dir', 'chefUser', 'chefAgent', 'rhUser', 'agentUser', 'agent');
}

function demandeConge(Agent $agent, int $jours = 5): Demande
{
    return Demande::create([
        'agent_id' => $agent->id, 'type' => 'conge_annuel',
        'date_debut' => '2026-08-01', 'date_fin' => '2026-08-0'.$jours,
        'nb_jours' => $jours, 'statut' => Demande::STATUT_SOUMISE,
    ]);
}

it('le chef valide : soumise -> validee_chef + notif agent', function () {
    ['chefUser' => $chef, 'agent' => $agent, 'agentUser' => $agentUser] = contexte();
    $demande = demandeConge($agent);

    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_CHEF);
    expect($demande->validations()->where('niveau', 'chef')->where('decision', 'ok')->count())->toBe(1);
    expect($agentUser->fresh()->notifications()->count())->toBe(1);
});

it('un non-chef ne peut pas valider au niveau chef', function () {
    ['rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent);

    expect(fn () => app(DeciderDemande::class)->handle($demande, $rh, 'chef', 'ok'))
        ->toThrow(AuthorizationException::class);
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('la RH valide un congé : validee_chef -> validee_rh + décrément solde', function () {
    ['chefUser' => $chef, 'rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent, 5);
    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    app(DeciderDemande::class)->handle($demande->fresh(), $rh, 'rh', 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(15.0); // 20 - 5
});

it('une permission validée RH ne touche pas le solde', function () {
    ['chefUser' => $chef, 'rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-03', 'nb_jours' => 3, 'statut' => Demande::STATUT_SOUMISE]);
    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'ok');

    app(DeciderDemande::class)->handle($demande->fresh(), $rh, 'rh', 'ok');

    expect((float) $agent->fresh()->solde_conge_jours)->toBe(20.0);
});

it('un refus chef passe en refusee et notifie l’agent', function () {
    ['chefUser' => $chef, 'agent' => $agent, 'agentUser' => $agentUser] = contexte();
    $demande = demandeConge($agent);

    app(DeciderDemande::class)->handle($demande, $chef, 'chef', 'refus', 'Effectif insuffisant');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_REFUSEE);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(20.0); // pas de décrément
    expect($agentUser->fresh()->notifications()->count())->toBe(1);
});

it('la RH ne peut pas agir tant que le chef n’a pas validé', function () {
    ['rhUser' => $rh, 'agent' => $agent] = contexte();
    $demande = demandeConge($agent); // statut soumise

    expect(fn () => app(DeciderDemande::class)->handle($demande, $rh, 'rh', 'ok'))
        ->toThrow(\DomainException::class);
    expect($demande->fresh()->statut)->toBe(Demande::STATUT_SOUMISE);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/DeciderDemandeTest.php`
Expected: FAIL (action/notification absentes).

- [ ] **Step 3: Créer la notification**

Create `app/Notifications/DemandeDecisionNotification.php`:
```php
<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Notifications\Notification;

class DemandeDecisionNotification extends Notification
{
    public function __construct(
        public Demande $demande,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'demande_id' => $this->demande->id,
            'type' => $this->demande->type,
            'statut' => $this->demande->statut,
            'message' => $this->message,
        ];
    }
}
```

- [ ] **Step 4: Créer l'action**

Create `app/Actions/DeciderDemande.php`:
```php
<?php

namespace App\Actions;

use App\Models\Demande;
use App\Models\User;
use App\Models\Validation;
use App\Notifications\DemandeDecisionNotification;
use Illuminate\Auth\Access\AuthorizationException;

class DeciderDemande
{
    /**
     * @param  string  $niveau  'chef' | 'rh'
     * @param  string  $decision  'ok' | 'refus'
     */
    public function handle(Demande $demande, User $acteur, string $niveau, string $decision, ?string $commentaire = null): Demande
    {
        if (! in_array($decision, ['ok', 'refus'], true)) {
            throw new \InvalidArgumentException("Décision invalide.");
        }

        $demande->loadMissing('agent.direction', 'agent.user');

        if ($niveau === 'chef') {
            $this->autoriserChef($demande, $acteur);
            $this->exigerStatut($demande, Demande::STATUT_SOUMISE);
            $nouveauStatut = $decision === 'ok' ? Demande::STATUT_VALIDEE_CHEF : Demande::STATUT_REFUSEE;
        } elseif ($niveau === 'rh') {
            $this->autoriserRh($acteur);
            $this->exigerStatut($demande, Demande::STATUT_VALIDEE_CHEF);
            $nouveauStatut = $decision === 'ok' ? Demande::STATUT_VALIDEE_RH : Demande::STATUT_REFUSEE;
        } else {
            throw new \InvalidArgumentException("Niveau invalide.");
        }

        Validation::create([
            'demande_id' => $demande->id,
            'validateur_id' => $acteur->agent?->id,
            'niveau' => $niveau,
            'decision' => $decision,
            'commentaire' => $commentaire,
        ]);

        $demande->update(['statut' => $nouveauStatut]);

        // Décrément du solde uniquement à la validation RH finale d'un congé annuel
        if ($nouveauStatut === Demande::STATUT_VALIDEE_RH && $demande->estCongeAnnuel()) {
            $agent = $demande->agent;
            $agent->decrement('solde_conge_jours', $demande->nb_jours);
        }

        $this->notifierAgent($demande, $nouveauStatut);

        return $demande;
    }

    private function autoriserChef(Demande $demande, User $acteur): void
    {
        $direction = $demande->agent->direction;
        $estChef = $acteur->agent
            && $direction
            && $direction->chef_id === $acteur->agent->id;

        if (! $estChef) {
            throw new AuthorizationException("Vous n'êtes pas le chef de cette direction.");
        }
    }

    private function autoriserRh(User $acteur): void
    {
        if (! $acteur->isAdminRh()) {
            throw new AuthorizationException("Réservé à la DRHF.");
        }
    }

    private function exigerStatut(Demande $demande, string $statut): void
    {
        if ($demande->statut !== $statut) {
            throw new \DomainException("Transition invalide depuis le statut « {$demande->statut} ».");
        }
    }

    private function notifierAgent(Demande $demande, string $statut): void
    {
        $user = $demande->agent->user;
        if (! $user) {
            return;
        }

        $message = match ($statut) {
            Demande::STATUT_VALIDEE_CHEF => "Votre demande a été validée par votre chef de direction.",
            Demande::STATUT_VALIDEE_RH => "Votre demande a été validée par la DRHF.",
            Demande::STATUT_REFUSEE => "Votre demande a été refusée.",
            default => "Votre demande a changé de statut.",
        };

        $user->notify(new DemandeDecisionNotification($demande, $message));
    }
}
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/DeciderDemandeTest.php`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: action DeciderDemande (workflow 2 niveaux, solde, notif)"
```

---

## Task 4: UI Agent — nouvelle demande + mes demandes

**Files:**
- Create: `app/Livewire/Demandes/NouvelleDemande.php`, `resources/views/livewire/demandes/nouvelle-demande.blade.php`
- Create: `app/Livewire/Demandes/MesDemandes.php`, `resources/views/livewire/demandes/mes-demandes.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Demandes/DemandeUiTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Demandes/DemandeUiTest.php`:
```php
<?php

use App\Livewire\Demandes\MesDemandes;
use App\Livewire\Demandes\NouvelleDemande;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentConnecte(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chef = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);
    $dir->update(['chef_id' => $chef->id]);
    $user = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $user->id]);

    return [$user, $agent];
}

it('soumet une demande de congé via le composant', function () {
    [$user, $agent] = agentConnecte();

    Livewire::actingAs($user)
        ->test(NouvelleDemande::class)
        ->set('type', 'conge_annuel')
        ->set('date_debut', '2026-08-01')
        ->set('date_fin', '2026-08-05')
        ->set('motif', 'Congé annuel')
        ->call('soumettre')
        ->assertRedirect(route('demandes.mes'));

    $demande = Demande::where('agent_id', $agent->id)->first();
    expect($demande)->not->toBeNull();
    expect($demande->nb_jours)->toBe(5);
    expect($demande->statut)->toBe(Demande::STATUT_SOUMISE);
});

it('affiche une erreur si solde insuffisant', function () {
    [$user, $agent] = agentConnecte();
    $agent->update(['solde_conge_jours' => 2]);

    Livewire::actingAs($user)
        ->test(NouvelleDemande::class)
        ->set('type', 'conge_annuel')
        ->set('date_debut', '2026-08-01')
        ->set('date_fin', '2026-08-10')
        ->call('soumettre')
        ->assertHasErrors('nb_jours');

    expect(Demande::count())->toBe(0);
});

it('liste seulement mes demandes', function () {
    [$user, $agent] = agentConnecte();
    Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise', 'motif' => 'MAPERM']);
    // demande d'un autre agent
    $autre = Agent::create(['prenoms' => 'Autre', 'noms' => 'AGENT', 'matricule' => 'B1', 'direction_id' => $agent->direction_id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $autre->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'soumise', 'motif' => 'AUTREPERM']);

    Livewire::actingAs($user)
        ->test(MesDemandes::class)
        ->assertSee('MAPERM')
        ->assertDontSee('AUTREPERM');
});

it('interdit la page demandes aux invités', function () {
    $this->get('/demandes')->assertRedirect('/login');
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/DemandeUiTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Composant NouvelleDemande**

Create `app/Livewire/Demandes/NouvelleDemande.php`:
```php
<?php

namespace App\Livewire\Demandes;

use App\Actions\SoumettreDemande;
use App\Models\Demande;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class NouvelleDemande extends Component
{
    public string $type = 'conge_annuel';
    public ?string $date_debut = null;
    public ?string $date_fin = null;
    public ?string $motif = null;

    public function soumettre(SoumettreDemande $action)
    {
        $this->validate([
            'type' => ['required', Rule::in(Demande::TYPES_SELF_SERVICE)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent = auth()->user()->agent;

        if (! $agent) {
            $this->addError('type', "Aucune fiche agent n'est liée à votre compte. Contactez la DRHF.");

            return;
        }

        try {
            $action->handle($agent, $this->type, $this->date_debut, $this->date_fin, $this->motif);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ, $messages[0]);
            }

            return;
        }

        session()->flash('ok', 'Demande soumise.');

        return redirect()->route('demandes.mes');
    }

    public function render()
    {
        return view('livewire.demandes.nouvelle-demande');
    }
}
```

- [ ] **Step 4: Vue NouvelleDemande**

Create `resources/views/livewire/demandes/nouvelle-demande.blade.php`:
```blade
<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-xl font-semibold">Nouvelle demande</h1>

    <form wire:submit="soumettre" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium">Type</label>
            <select wire:model="type" class="mt-1 w-full rounded border-gray-300">
                <option value="conge_annuel">Congé annuel</option>
                <option value="permission">Permission d'absence</option>
            </select>
            @error('type') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Du</label>
                <input type="date" wire:model="date_debut" class="mt-1 w-full rounded border-gray-300">
                @error('date_debut') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Au</label>
                <input type="date" wire:model="date_fin" class="mt-1 w-full rounded border-gray-300">
                @error('date_fin') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                @error('nb_jours') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Motif</label>
            <textarea wire:model="motif" rows="3" class="mt-1 w-full rounded border-gray-300"></textarea>
            @error('motif') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('demandes.mes') }}" class="rounded border px-4 py-2 text-sm">Annuler</a>
            <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Soumettre</button>
        </div>
    </form>
</div>
```

- [ ] **Step 5: Composant MesDemandes**

Create `app/Livewire/Demandes/MesDemandes.php`:
```php
<?php

namespace App\Livewire\Demandes;

use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MesDemandes extends Component
{
    public function render()
    {
        $agent = auth()->user()->agent;

        $demandes = $agent
            ? $agent->demandes()->latest()->get()
            : collect();

        return view('livewire.demandes.mes-demandes', [
            'demandes' => $demandes,
            'agent' => $agent,
        ]);
    }
}
```

- [ ] **Step 6: Vue MesDemandes**

Create `resources/views/livewire/demandes/mes-demandes.blade.php`:
```blade
<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Mes demandes</h1>
        <a href="{{ route('demandes.nouvelle') }}" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">+ Nouvelle demande</a>
    </div>

    @if ($agent)
        <p class="mb-4 text-sm text-gray-600">Solde de congé : <strong>{{ $agent->solde_conge_jours }} jours</strong></p>
    @endif

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Type</th><th class="p-3">Période</th><th class="p-3">Jours</th><th class="p-3">Motif</th><th class="p-3">Statut</th></tr>
            </thead>
            <tbody>
                @forelse ($demandes as $d)
                    <tr class="border-t">
                        <td class="p-3">{{ str_replace('_', ' ', $d->type) }}</td>
                        <td class="p-3">{{ $d->date_debut->format('d/m/Y') }} → {{ $d->date_fin->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $d->nb_jours }}</td>
                        <td class="p-3">{{ $d->motif ?? '—' }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $d->statut) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Aucune demande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 7: Routes agent**

Modify `routes/web.php` — dans le groupe `Route::middleware('auth')->group(...)`, ajouter :
```php
    Route::get('/demandes', \App\Livewire\Demandes\MesDemandes::class)->name('demandes.mes');
    Route::get('/demandes/nouvelle', \App\Livewire\Demandes\NouvelleDemande::class)->name('demandes.nouvelle');
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/DemandeUiTest.php`
Expected: PASS (4 tests).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: UI agent nouvelle demande + mes demandes"
```

---

## Task 5: UI validation — file chef + file RH

Deux composants : la file du chef (demandes `soumise` de **sa** direction) et la file DRHF (demandes `validee_chef`). Chacun protégé par le rôle adéquat ; les décisions passent par `DeciderDemande` (qui re-vérifie l'autorisation).

**Files:**
- Create: `app/Livewire/Validation/FileChef.php`, `resources/views/livewire/validation/file-chef.blade.php`
- Create: `app/Livewire/Validation/FileRh.php`, `resources/views/livewire/validation/file-rh.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Validation/FilesTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Validation/FilesTest.php`:
```php
<?php

use App\Livewire\Validation\FileChef;
use App\Livewire\Validation\FileRh;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxValidation(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $chefUser = User::create(['name' => 'Chef', 'matricule' => 'CHEF1', 'password' => bcrypt('s'), 'role' => 'chef_direction']);
    $chefAgent = Agent::create(['prenoms' => 'Chef', 'noms' => 'DIR', 'matricule' => 'CHEF1', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30, 'user_id' => $chefUser->id]);
    $dir->update(['chef_id' => $chefAgent->id]);
    $rhUser = User::create(['name' => 'RH', 'matricule' => 'RH1', 'password' => bcrypt('s'), 'role' => 'admin_rh']);
    $agentUser = User::create(['name' => 'Awa DIOP', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $agentUser->id]);

    return compact('dir', 'chefUser', 'rhUser', 'agent');
}

it('interdit la file chef aux non chefs (403)', function () {
    $this->withoutVite();
    ['rhUser' => $rh] = ctxValidation();

    $this->actingAs($rh)->get('/validation/chef')->assertForbidden();
});

it('le chef voit et valide une demande soumise de sa direction', function () {
    ['chefUser' => $chef, 'agent' => $agent] = ctxValidation();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => Demande::STATUT_SOUMISE, 'motif' => 'PERMTEST']);

    Livewire::actingAs($chef)
        ->test(FileChef::class)
        ->assertSee('PERMTEST')
        ->call('decider', $demande->id, 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_CHEF);
});

it('le chef ne voit pas les demandes d’une autre direction', function () {
    ['chefUser' => $chef] = ctxValidation();
    $autreDir = Direction::create(['code' => 'DOE', 'nom' => 'Autre']);
    $autreAgent = Agent::create(['prenoms' => 'X', 'noms' => 'Y', 'matricule' => 'Z9', 'direction_id' => $autreDir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);
    Demande::create(['agent_id' => $autreAgent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => Demande::STATUT_SOUMISE, 'motif' => 'AILLEURS']);

    Livewire::actingAs($chef)->test(FileChef::class)->assertDontSee('AILLEURS');
});

it('la RH voit et valide une demande validee_chef', function () {
    ['rhUser' => $rh, 'agent' => $agent] = ctxValidation();
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'conge_annuel', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-05', 'nb_jours' => 5, 'statut' => Demande::STATUT_VALIDEE_CHEF, 'motif' => 'CONGRH']);

    Livewire::actingAs($rh)
        ->test(FileRh::class)
        ->assertSee('CONGRH')
        ->call('decider', $demande->id, 'ok');

    expect($demande->fresh()->statut)->toBe(Demande::STATUT_VALIDEE_RH);
    expect((float) $agent->fresh()->solde_conge_jours)->toBe(15.0);
});

it('interdit la file RH aux non admin_rh (403)', function () {
    $this->withoutVite();
    ['chefUser' => $chef] = ctxValidation();

    $this->actingAs($chef)->get('/validation/rh')->assertForbidden();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Validation/FilesTest.php`
Expected: FAIL (composants/routes absents).

- [ ] **Step 3: Composant FileChef**

Create `app/Livewire/Validation/FileChef.php`:
```php
<?php

namespace App\Livewire\Validation;

use App\Actions\DeciderDemande;
use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FileChef extends Component
{
    public function decider(int $demandeId, string $decision, DeciderDemande $action): void
    {
        $demande = Demande::findOrFail($demandeId);
        $action->handle($demande, auth()->user(), 'chef', $decision);
        session()->flash('ok', 'Décision enregistrée.');
    }

    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        $demandes = Demande::query()
            ->with('agent')
            ->where('statut', Demande::STATUT_SOUMISE)
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when(! $directionId, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return view('livewire.validation.file-chef', ['demandes' => $demandes]);
    }
}
```

- [ ] **Step 4: Vue FileChef**

Create `resources/views/livewire/validation/file-chef.blade.php`:
```blade
<div>
    <h1 class="mb-4 text-xl font-semibold">Demandes à valider — Chef de direction</h1>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Agent</th><th class="p-3">Type</th><th class="p-3">Période</th><th class="p-3">Jours</th><th class="p-3">Motif</th><th class="p-3">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($demandes as $d)
                    <tr class="border-t">
                        <td class="p-3">{{ $d->agent->prenoms }} {{ $d->agent->noms }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $d->type) }}</td>
                        <td class="p-3">{{ $d->date_debut->format('d/m/Y') }} → {{ $d->date_fin->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $d->nb_jours }}</td>
                        <td class="p-3">{{ $d->motif ?? '—' }}</td>
                        <td class="p-3 space-x-2">
                            <button wire:click="decider({{ $d->id }}, 'ok')" class="rounded bg-emerald-600 px-3 py-1 text-white">Valider</button>
                            <button wire:click="decider({{ $d->id }}, 'refus')" class="rounded bg-red-600 px-3 py-1 text-white">Refuser</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Aucune demande en attente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Composant FileRh**

Create `app/Livewire/Validation/FileRh.php`:
```php
<?php

namespace App\Livewire\Validation;

use App\Actions\DeciderDemande;
use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FileRh extends Component
{
    public function decider(int $demandeId, string $decision, DeciderDemande $action): void
    {
        $demande = Demande::findOrFail($demandeId);
        $action->handle($demande, auth()->user(), 'rh', $decision);
        session()->flash('ok', 'Décision enregistrée.');
    }

    public function render()
    {
        $demandes = Demande::query()
            ->with('agent')
            ->where('statut', Demande::STATUT_VALIDEE_CHEF)
            ->latest()
            ->get();

        return view('livewire.validation.file-rh', ['demandes' => $demandes]);
    }
}
```

- [ ] **Step 6: Vue FileRh**

Create `resources/views/livewire/validation/file-rh.blade.php`:
```blade
<div>
    <h1 class="mb-4 text-xl font-semibold">Demandes à valider — DRHF</h1>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="p-3">Agent</th><th class="p-3">Type</th><th class="p-3">Période</th><th class="p-3">Jours</th><th class="p-3">Motif</th><th class="p-3">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($demandes as $d)
                    <tr class="border-t">
                        <td class="p-3">{{ $d->agent->prenoms }} {{ $d->agent->noms }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $d->type) }}</td>
                        <td class="p-3">{{ $d->date_debut->format('d/m/Y') }} → {{ $d->date_fin->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $d->nb_jours }}</td>
                        <td class="p-3">{{ $d->motif ?? '—' }}</td>
                        <td class="p-3 space-x-2">
                            <button wire:click="decider({{ $d->id }}, 'ok')" class="rounded bg-emerald-600 px-3 py-1 text-white">Valider</button>
                            <button wire:click="decider({{ $d->id }}, 'refus')" class="rounded bg-red-600 px-3 py-1 text-white">Refuser</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-gray-500">Aucune demande en attente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 7: Routes validation**

Modify `routes/web.php` — ajouter, après le groupe RH ou dans un nouveau groupe :
```php
Route::middleware(['auth', 'role:chef_direction'])->group(function () {
    Route::get('/validation/chef', \App\Livewire\Validation\FileChef::class)->name('validation.chef');
});

Route::middleware(['auth', 'role:admin_rh'])->group(function () {
    Route::get('/validation/rh', \App\Livewire\Validation\FileRh::class)->name('validation.rh');
});
```

- [ ] **Step 8: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Validation/FilesTest.php`
Expected: PASS (5 tests).

- [ ] **Step 9: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: UI validation file chef + file RH"
```

---

## Task 6: Cloche de notifications + liens dashboard

Affiche les notifications non lues de l'utilisateur et permet de les marquer comme lues. Ajoute les liens de navigation contextuels au dashboard.

**Files:**
- Create: `app/Livewire/Notifications/Cloche.php`, `resources/views/livewire/notifications/cloche.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Demandes/ClocheTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/Demandes/ClocheTest.php`:
```php
<?php

use App\Livewire\Notifications\Cloche;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use App\Models\User;
use App\Notifications\DemandeDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userAvecNotif(): array
{
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create(['name' => 'Awa', 'matricule' => 'A1', 'password' => bcrypt('s'), 'role' => 'agent']);
    $agent = Agent::create(['prenoms' => 'Awa', 'noms' => 'DIOP', 'matricule' => 'A1', 'direction_id' => $dir->id, 'statut' => 'police', 'solde_conge_jours' => 20, 'user_id' => $user->id]);
    $demande = Demande::create(['agent_id' => $agent->id, 'type' => 'permission', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-01', 'nb_jours' => 1, 'statut' => 'refusee']);
    $user->notify(new DemandeDecisionNotification($demande, 'Votre demande a été refusée.'));

    return [$user->fresh(), $demande];
}

it('affiche le nombre de notifications non lues', function () {
    [$user] = userAvecNotif();

    Livewire::actingAs($user)
        ->test(Cloche::class)
        ->assertSee('1')
        ->assertSee('refusée');
});

it('marque toutes les notifications comme lues', function () {
    [$user] = userAvecNotif();

    Livewire::actingAs($user)
        ->test(Cloche::class)
        ->call('toutMarquerLu');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/ClocheTest.php`
Expected: FAIL (composant absent).

- [ ] **Step 3: Composant Cloche**

Create `app/Livewire/Notifications/Cloche.php`:
```php
<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class Cloche extends Component
{
    public function toutMarquerLu(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications.cloche', [
            'nonLues' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->take(10)->get(),
        ]);
    }
}
```

- [ ] **Step 4: Vue Cloche**

Create `resources/views/livewire/notifications/cloche.blade.php`:
```blade
<div class="rounded-lg bg-white p-4 shadow">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-semibold">Notifications <span class="ml-1 rounded-full bg-red-600 px-2 text-xs text-white">{{ $nonLues }}</span></h2>
        @if ($nonLues > 0)
            <button wire:click="toutMarquerLu" class="text-sm text-emerald-700 underline">Tout marquer lu</button>
        @endif
    </div>

    <ul class="space-y-2 text-sm">
        @forelse ($notifications as $n)
            <li class="rounded border-l-4 {{ $n->read_at ? 'border-gray-200' : 'border-emerald-500' }} bg-gray-50 p-2">
                {{ $n->data['message'] ?? 'Notification' }}
                <span class="block text-xs text-gray-500">{{ $n->created_at->diffForHumans() }}</span>
            </li>
        @empty
            <li class="text-gray-500">Aucune notification.</li>
        @endforelse
    </ul>
</div>
```

- [ ] **Step 5: Intégrer au dashboard**

Replace `resources/views/dashboard.blade.php` with:
```blade
<x-layouts.app :title="'Tableau de bord'">
    <div class="space-y-6">
        <div class="rounded-lg bg-white p-8 shadow">
            <h1 class="text-xl font-semibold">Bonjour {{ auth()->user()->name }}</h1>
            <p class="mt-2 text-sm text-gray-600">Rôle : {{ auth()->user()->role }}</p>

            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                <a href="{{ route('demandes.mes') }}" class="rounded bg-emerald-600 px-4 py-2 text-white">Mes demandes</a>
                @if (auth()->user()->isChefDirection())
                    <a href="{{ route('validation.chef') }}" class="rounded bg-emerald-700 px-4 py-2 text-white">File de validation (chef)</a>
                @endif
                @if (auth()->user()->isAdminRh())
                    <a href="{{ route('validation.rh') }}" class="rounded bg-emerald-700 px-4 py-2 text-white">File de validation (DRHF)</a>
                    <a href="{{ route('rh.agents.index') }}" class="rounded bg-gray-800 px-4 py-2 text-white">Espace RH</a>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Se déconnecter</button>
            </form>
        </div>

        <livewire:notifications.cloche />
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/Demandes/ClocheTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Lancer TOUTE la suite**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts (Plans 1-4).

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: cloche notifications in-app + liens dashboard"
```

---

## Self-Review (effectué)

- **Couverture spec :** §8 workflow 2 niveaux (Task 3, transitions + refus + saut N1 en Task 2) ✓ ; refus notifie l'agent (Task 3) ✓ ; congé décrémenté à `validee_rh` uniquement (Task 3) ✓ ; permission ne touche pas le solde (Tasks 2-3) ✓. §9 jours calendaires inclusifs + contrôle solde à la soumission (Task 2) ✓. §5 tables demandes/validations + meta json (Task 1) ✓. UI agent/chef/RH (Tasks 4-5) + notifications in-app (Tasks 3, 6) ✓. **Ordre de mission exclu du libre-service** (rejeté par `SoumettreDemande`, absent de l'UI agent) : rôle `secretaire`, initiation et PDF traités en **Plan 5**. Le schéma prévoit déjà le type `ordre_mission` et le statut `emise`.
- **Placeholders :** aucun. Chaque étape contient le code réel.
- **Cohérence types :** constantes de statut `Demande::STATUT_*` utilisées de façon identique dans actions, composants et tests. `DeciderDemande::handle(Demande, User, string $niveau, string $decision, ?string)` appelée pareil en Tasks 3/5. `SoumettreDemande::handle(Agent, type, dateDebut, dateFin, motif, meta, pieceJointe)` cohérente Tasks 2/4. Autorisation : chef = `direction->chef_id === acteur->agent->id`, RH = `isAdminRh()` (helpers Plan 2). Routes `demandes.mes/nouvelle`, `validation.chef/rh` nommées identiquement (vues, composants, tests, dashboard). `validateur_id` nullable → un RH sans fiche agent journalise `null`.

## Dépendances pour les plans suivants

- **Plan 5 (Ordres de mission & secrétaire)** : nouveau rôle `secretaire` (migration ALTER de l'enum `users.role`), action `InitierMission` (le secrétaire crée un `ordre_mission` `statut = emise` pour un agent de sa direction, champs template dans `meta` : destination, motif, moyen_transport, indice, groupe, imputation, chapitre, article), UI secrétaire + génération PDF de l'ordre de mission (modèle `docs/references/modeles/`, signataire DG configurable).
- **Plan 6 (Attestation congé + état congés)** : PDF « Attestation de cessation de service » après `validee_rh` (nb jours en lettres, date reprise = `date_fin` + 1 j, signataire DG configurable) + export état des congés (Excel/PDF).
- **Plan 7 (Dashboards)** : agrégats sur `demandes.statut`/`type` par direction + taux d'absence.

Réf. modèles de documents : `docs/references/modeles/README.md`.
