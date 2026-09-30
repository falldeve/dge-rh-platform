# Plan 1 — Fondations (Plateforme RH DGE) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Poser les fondations de la plateforme RH DGE : projet Laravel, schéma de base (directions, agents, users) et import des 117 agents avec réaffectation du Service Informatique (SI).

**Architecture:** Laravel 12 + Livewire 3 + MySQL. Les données du personnel sont extraites une fois depuis le `.docx` officiel vers `database/data/personnel_dge.json` (déjà généré, 117 agents), puis chargées par une commande Artisan idempotente. Les directions sont seedées (DG, DOE, DRHF, DFC, SI). Aucune auth/écran dans ce plan — livrable testable = migrations + modèles + import fonctionnels.

**Tech Stack:** Laravel 12, Livewire 3, MySQL, Pest (tests), Tailwind (installé, non utilisé ici).

**Référence spec:** `docs/superpowers/specs/2026-07-11-plateforme-rh-dge-design.md` (§4 directions, §5 modèle de données, §7 import).

---

## Fichiers créés/modifiés dans ce plan

- `.` — projet Laravel scaffoldé (composer, artisan…).
- `database/data/personnel_dge.json` — **déjà présent** (source des 117 agents).
- `database/migrations/*_create_directions_table.php` — table directions.
- `database/migrations/*_create_agents_table.php` — table agents.
- `database/migrations/*_add_rh_fields_to_users_table.php` — colonnes role + matricule sur users.
- `app/Models/Direction.php`, `app/Models/Agent.php` — modèles + relations.
- `app/Models/User.php` — relation agent + cast role (modifié).
- `database/seeders/DirectionSeeder.php` — 5 directions.
- `app/Console/Commands/ImportPersonnel.php` — commande d'import.
- `tests/Feature/*` — tests Pest par tâche.

---

## Task 1: Scaffold du projet Laravel + Pest + Livewire

**Files:**
- Create: projet Laravel à la racine `/Users/admin/dge-rh-platform`.
- Test: `tests/Feature/SmokeTest.php`

- [ ] **Step 1: Créer le projet Laravel dans le dossier existant**

Le dossier contient déjà `docs/` et `database/data/`. Scaffolder dans un tmp puis rapatrier pour ne pas écraser ces fichiers.

Run:
```bash
cd /Users/admin/dge-rh-platform
composer create-project laravel/laravel _scaffold "^12.0"
# rapatrier le scaffold sans écraser docs/ et database/data/
rsync -a --ignore-existing _scaffold/ ./
cp -rn _scaffold/database/ ./database/ 2>/dev/null || true
rm -rf _scaffold
```
Expected: présence de `artisan`, `composer.json`, `app/`, `database/migrations/`.

- [ ] **Step 2: Installer Pest, Livewire**

Run:
```bash
cd /Users/admin/dge-rh-platform
composer require livewire/livewire "^3.0"
composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
php artisan pest:install --no-interaction || ./vendor/bin/pest --init
```
Expected: `tests/Pest.php` créé, `phpunit.xml` présent.

- [ ] **Step 3: Configurer la base MySQL**

Éditer `.env` :
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dge_rh
DB_USERNAME=root
DB_PASSWORD=
```
Run:
```bash
cd /Users/admin/dge-rh-platform
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS dge_rh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Expected: base `dge_rh` créée.

- [ ] **Step 4: Écrire un smoke test**

Create `tests/Feature/SmokeTest.php`:
```php
<?php

it('charge la page d’accueil', function () {
    $this->get('/')->assertStatus(200);
});
```

- [ ] **Step 5: Lancer le smoke test**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/SmokeTest.php`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
printf "\n/vendor\n/node_modules\n.env\n" >> .gitignore
git add -A
git commit -m "chore: scaffold Laravel 12 + Livewire + Pest"
```

---

## Task 2: Directions (migration + modèle + seeder)

**Files:**
- Create: `database/migrations/2026_07_11_000001_create_directions_table.php`
- Create: `app/Models/Direction.php`
- Create: `database/seeders/DirectionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/DirectionTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/DirectionTest.php`:
```php
<?php

use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seed les 5 directions dont SI', function () {
    $this->seed(\Database\Seeders\DirectionSeeder::class);

    expect(Direction::count())->toBe(5);
    expect(Direction::pluck('code')->sort()->values()->all())
        ->toBe(['DFC', 'DG', 'DOE', 'DRHF', 'SI']);
    expect(Direction::where('code', 'SI')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/DirectionTest.php`
Expected: FAIL (classe `Direction` / seeder introuvable).

- [ ] **Step 3: Créer la migration**

Create `database/migrations/2026_07_11_000001_create_directions_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('directions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->foreignId('chef_id')->nullable(); // fk agents ajoutée après création agents
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directions');
    }
};
```

- [ ] **Step 4: Créer le modèle**

Create `app/Models/Direction.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    protected $fillable = ['code', 'nom', 'chef_id'];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
```

- [ ] **Step 5: Créer le seeder**

Libellés `nom` éditables ensuite par la RH. **DFC : libellé à confirmer avec le client.**

Create `database/seeders/DirectionSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Direction;
use Illuminate\Database\Seeder;

class DirectionSeeder extends Seeder
{
    public function run(): void
    {
        $directions = [
            ['code' => 'DG',   'nom' => 'Direction Générale'],
            ['code' => 'DOE',  'nom' => 'Direction des Opérations Électorales'],
            ['code' => 'DRHF', 'nom' => 'Direction des Ressources Humaines et des Finances'],
            ['code' => 'DFC',  'nom' => 'Direction de la Formation et de la Communication'],
            ['code' => 'SI',   'nom' => 'Service Informatique'],
        ];

        foreach ($directions as $d) {
            Direction::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}
```

- [ ] **Step 6: Enregistrer le seeder**

Modify `database/seeders/DatabaseSeeder.php` — dans `run()`, ajouter :
```php
$this->call(DirectionSeeder::class);
```

- [ ] **Step 7: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/DirectionTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: directions (migration, modele, seeder + SI)"
```

---

## Task 3: Agents (migration + modèle + relations)

**Files:**
- Create: `database/migrations/2026_07_11_000002_create_agents_table.php`
- Create: `app/Models/Agent.php`
- Test: `tests/Feature/AgentTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/AgentTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un agent rattaché à une direction', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);

    $agent = Agent::create([
        'prenoms' => 'Biram',
        'noms' => 'SENE',
        'matricule' => '636324/D',
        'profession' => 'Magistrat',
        'fonction' => 'Directeur général des Elections',
        'direction_id' => $dir->id,
        'statut' => 'fonctionnaire',
        'solde_conge_jours' => 30,
    ]);

    expect($agent->direction->code)->toBe('DG');
    expect($dir->agents()->count())->toBe(1);
    expect($agent->user_id)->toBeNull();
});

it('impose un matricule unique quand présent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    Agent::create(['prenoms' => 'A', 'noms' => 'B', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]);

    expect(fn () => Agent::create(['prenoms' => 'C', 'noms' => 'D', 'matricule' => 'X1', 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/AgentTest.php`
Expected: FAIL (modèle `Agent` absent).

- [ ] **Step 3: Créer la migration**

Create `database/migrations/2026_07_11_000002_create_agents_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('prenoms');
            $table->string('noms');
            $table->string('matricule')->nullable()->unique();
            $table->string('profession')->nullable();
            $table->string('fonction')->nullable();
            $table->foreignId('direction_id')->constrained('directions');
            $table->enum('statut', ['fonctionnaire', 'police', 'contractuel_pav', 'autre'])->default('autre');
            $table->decimal('solde_conge_jours', 6, 1)->default(0);
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('date_naissance')->nullable();
            $table->date('date_prise_service')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
```

- [ ] **Step 4: Créer le modèle**

Create `app/Models/Agent.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    protected $fillable = [
        'prenoms', 'noms', 'matricule', 'profession', 'fonction',
        'direction_id', 'statut', 'solde_conge_jours', 'telephone',
        'email', 'photo_path', 'date_naissance', 'date_prise_service', 'user_id',
    ];

    protected $casts = [
        'solde_conge_jours' => 'decimal:1',
        'date_naissance' => 'date',
        'date_prise_service' => 'date',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenoms} {$this->noms}");
    }
}
```

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/AgentTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: agents (migration, modele, relations)"
```

---

## Task 4: Champs RH sur users + relation agent

**Files:**
- Create: `database/migrations/2026_07_11_000003_add_rh_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/UserRoleTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/UserRoleTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un user avec rôle et le relie à un agent', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $user = User::create([
        'name' => 'Biram SENE',
        'matricule' => '636324/D',
        'password' => bcrypt('secret'),
        'role' => 'admin_rh',
    ]);
    $agent = Agent::create([
        'prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D',
        'direction_id' => $dir->id, 'statut' => 'fonctionnaire',
        'solde_conge_jours' => 30, 'user_id' => $user->id,
    ]);

    expect($user->role)->toBe('admin_rh');
    expect($user->agent->id)->toBe($agent->id);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/UserRoleTest.php`
Expected: FAIL (colonne `role`/`matricule` inexistante, relation `agent` absente).

- [ ] **Step 3: Créer la migration**

Create `database/migrations/2026_07_11_000003_add_rh_fields_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule')->nullable()->unique()->after('name');
            $table->enum('role', ['agent', 'chef_direction', 'admin_rh', 'dg'])
                ->default('agent')->after('matricule');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['matricule', 'role']);
        });
    }
};
```
Note : si `->change()` échoue, exécuter `composer require doctrine/dbal` d'abord.

- [ ] **Step 4: Modifier le modèle User**

Modify `app/Models/User.php` — ajouter `matricule` et `role` à `$fillable`, et la relation :
```php
// dans la classe User :
public function agent()
{
    return $this->hasOne(\App\Models\Agent::class);
}
```
Ajouter `'matricule'` et `'role'` dans le tableau `$fillable`.

- [ ] **Step 5: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/UserRoleTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: champs role/matricule sur users + relation agent"
```

---

## Task 5: Commande d'import du personnel + réaffectation SI

Charge `database/data/personnel_dge.json` (117 agents). Idempotente (rejouable). Rattache chaque agent à sa direction par `code`. Les agents dont la `fonction` ou la `profession` contient « informatique » sont réaffectés à la direction **SI** (règle spec §4). Statut par défaut `autre` ; solde par défaut 30 (ajustable ensuite par la RH). Matricule manquant → `null`.

**Files:**
- Create: `app/Console/Commands/ImportPersonnel.php`
- Test: `tests/Feature/ImportPersonnelTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

Create `tests/Feature/ImportPersonnelTest.php`:
```php
<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\DirectionSeeder::class);
});

it('importe les 117 agents du fichier source', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::count())->toBe(117);
});

it('réaffecte les agents informatiques à la direction SI', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    $si = Direction::where('code', 'SI')->first();
    // Cheikh Tidiane DIALLO (Responsable Service informatique) doit être en SI
    $diallo = Agent::where('noms', 'DIALLO')->where('prenoms', 'Cheikh Tidiane')->first();
    expect($diallo)->not->toBeNull();
    expect($diallo->direction_id)->toBe($si->id);
    expect($si->agents()->count())->toBeGreaterThanOrEqual(2);
});

it('est idempotente (pas de doublons)', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::count())->toBe(117);
});

it('gère les agents sans matricule', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::whereNull('matricule')->count())->toBe(15);
});
```

- [ ] **Step 2: Lancer le test — doit échouer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/ImportPersonnelTest.php`
Expected: FAIL (commande `rh:import-personnel` inexistante).

- [ ] **Step 3: Créer la commande**

Create `app/Console/Commands/ImportPersonnel.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Console\Command;

class ImportPersonnel extends Command
{
    protected $signature = 'rh:import-personnel {--file=database/data/personnel_dge.json}';
    protected $description = 'Importe le personnel DGE depuis le fichier JSON source';

    public function handle(): int
    {
        $path = base_path($this->option('file'));
        if (! file_exists($path)) {
            $this->error("Fichier introuvable : {$path}");
            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($path), true);
        if (! is_array($records)) {
            $this->error('JSON invalide.');
            return self::FAILURE;
        }

        $directions = Direction::pluck('id', 'code');
        $siId = $directions['SI'] ?? null;
        $imported = 0;

        foreach ($records as $r) {
            $code = $r['direction'] ?? null;
            $directionId = $directions[$code] ?? null;
            if (! $directionId) {
                $this->warn("Direction inconnue « {$code} » — agent ignoré : {$r['prenoms']} {$r['noms']}");
                continue;
            }

            // Réaffectation SI : fonction ou profession contient « informatique »
            $haystack = strtolower(($r['fonction'] ?? '').' '.($r['profession'] ?? ''));
            if ($siId && str_contains($haystack, 'informatique')) {
                $directionId = $siId;
            }

            // Clé d'idempotence : matricule si présent, sinon prenoms+noms
            $key = ! empty($r['matricule'])
                ? ['matricule' => $r['matricule']]
                : ['prenoms' => $r['prenoms'], 'noms' => $r['noms']];

            Agent::updateOrCreate($key, [
                'prenoms' => $r['prenoms'],
                'noms' => $r['noms'],
                'matricule' => $r['matricule'] ?? null,
                'profession' => $r['profession'] ?? null,
                'fonction' => $r['fonction'] ?? null,
                'direction_id' => $directionId,
                'statut' => 'autre',
                'solde_conge_jours' => 30,
            ]);
            $imported++;
        }

        $this->info("Import terminé : {$imported} agents.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Lancer le test — doit passer**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest tests/Feature/ImportPersonnelTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Lancer l'import réel + vérifier**

Run:
```bash
cd /Users/admin/dge-rh-platform
php artisan migrate --force
php artisan db:seed --class=DirectionSeeder --force
php artisan rh:import-personnel
```
Expected: « Import terminé : 117 agents. »

- [ ] **Step 6: Lancer toute la suite de tests**

Run: `cd /Users/admin/dge-rh-platform && ./vendor/bin/pest`
Expected: tous verts.

- [ ] **Step 7: Commit**

```bash
cd /Users/admin/dge-rh-platform
git add -A && git commit -m "feat: import personnel DGE + reaffectation SI"
```

---

## Self-Review (effectué)

- **Couverture spec :** §4 directions (Task 2, incl. SI) ✓ ; §5 modèle directions/agents/users (Tasks 2-4) ✓ ; §7 import 117 agents + réaffectation SI + gestion sans matricule (Task 5) ✓. Auth/inscription, workflow, écrans, exports = **hors périmètre Plan 1** (Plans 2-6).
- **Placeholders :** aucun. Seule incertitude assumée : libellé `nom` de DFC (marqué « à confirmer »), non bloquant — éditable par la RH.
- **Cohérence types :** `Direction`, `Agent`, `User`, colonnes (`direction_id`, `matricule`, `role`, `solde_conge_jours`) et signature commande `rh:import-personnel` identiques d'une tâche à l'autre. `Agent::nomComplet()` défini Task 3, non requis par les tests de ce plan.

## Prochains plans

Plan 2 (Auth & rôles) s'appuiera sur `users.matricule`/`role` et `agents.user_id` posés ici.
