# Assistant Claude DGE — Plan Phase 1 (cœur) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer un assistant Claude conversationnel intégré à la plateforme DGE, ouvert à tous les agents, à deux niveaux (gratuit Haiku / premium Sonnet 5), avec conversations persistées, comptage des crédits + quota mensuel, et un écran admin de répartition des crédits.

**Architecture:** Un service `AssistantClaude` (derrière l'interface `AssistantIA`, remplaçable par un faux en test) encapsule le SDK PHP Anthropic et le streaming. Un composant Livewire `Assistant\Index` gère les conversations et l'envoi de messages ; un composant `Admin\Assistant\Gestion` gère drapeau premium, pool de crédits et réallocation. Les crédits sont une abstraction des jetons, plafonnés par mois via la table `assistant_usage`.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, MySQL, SDK `anthropic-ai/sdk` ^0.41, Guzzle.

**Périmètre Phase 1 (ce plan) :** chat texte (gratuit + premium), crédits + quota, écran admin, nav, accès.
**Hors Phase 1 (plan suivant) :** analyse de fichiers, recherche web, ancrage bibliothèque (`document_chunks`), passe design ui-ux-pro-max. Spec complète : `docs/superpowers/specs/2026-08-06-assistant-claude-dge-design.md`.

## Global Constraints

- PHP 8.3 (plateforme figée `composer.json` `config.platform.php=8.3.32`) — ne pas exiger une lib PHP ≥ 8.4.
- Laravel 12 + Livewire 3 ; tests **Pest** (`php artisan test`).
- **Jamais** de HTTP brut vers Anthropic ni de shim d'un autre fournisseur : uniquement le SDK `Anthropic\Client`.
- **Aucun test ne doit appeler l'API réelle** : les tests lient un faux `AssistantIA` dans le conteneur.
- Modèles : premium `claude-sonnet-5`, gratuit `claude-haiku-4-5` (via `config/assistant.php`).
- Clé API dans `.env` (`ANTHROPIC_API_KEY`), jamais commitée. `.env.example` reçoit les clés vides.
- Middleware d'accès agent : `['auth','verified','password.change']`. Admin : ajouter `'role:admin'` (le bypass `EnsureRole` laisse déjà passer `admin`).
- Crédit = abstraction : `credits = ceil((jetons_input + jetons_output) / config('assistant.jetons_par_credit'))`.
- Nommage SDK : arguments nommés **camelCase** (`maxTokens`, `messages`, `model`, `system`) ; clés des blocs de contenu **snake_case** (`media_type`).
- Convention existante : composants Livewire sous `app/Livewire/...`, vues `resources/views/livewire/...`, layout `components.layouts.rh`.

---

### Task 1: Installer le SDK Anthropic + configuration

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `config/assistant.php`
- Modify: `.env.example`

**Interfaces:**
- Produces: `config('assistant.jetons_par_credit')` (int), `config('assistant.modele_premium')` (string), `config('assistant.modele_gratuit')` (string), `config('assistant.forfait_gratuit_credits')` (int), `config('assistant.max_upload_mo')` (int).

- [ ] **Step 1: Installer le SDK + Guzzle**

```bash
cd /Users/admin/dge-rh-platform
composer require "anthropic-ai/sdk:^0.41.0" guzzlehttp/guzzle
```

- [ ] **Step 2: Créer le fichier de config**

Create `config/assistant.php` :

```php
<?php

return [
    'cle_api' => env('ANTHROPIC_API_KEY'),
    'modele_premium' => env('ANTHROPIC_MODEL_PREMIUM', 'claude-sonnet-5'),
    'modele_gratuit' => env('ANTHROPIC_MODEL_GRATUIT', 'claude-haiku-4-5'),
    'jetons_par_credit' => (int) env('ASSISTANT_JETONS_PAR_CREDIT', 1000),
    'forfait_gratuit_credits' => (int) env('ASSISTANT_FORFAIT_GRATUIT', 50),
    'max_upload_mo' => (int) env('ASSISTANT_MAX_UPLOAD_MO', 20),
    'max_tokens_reponse' => (int) env('ASSISTANT_MAX_TOKENS', 4096),
];
```

- [ ] **Step 3: Ajouter les variables à `.env.example`**

Append to `.env.example` :

```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL_PREMIUM=claude-sonnet-5
ANTHROPIC_MODEL_GRATUIT=claude-haiku-4-5
ASSISTANT_JETONS_PAR_CREDIT=1000
ASSISTANT_FORFAIT_GRATUIT=50
```

- [ ] **Step 4: Vérifier que la config se charge**

Run: `php artisan config:clear && php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config('assistant.modele_premium');"`
Expected: affiche `claude-sonnet-5`

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/assistant.php .env.example
git commit -m "feat(assistant): install Anthropic PHP SDK + config"
```

---

### Task 2: Migrations + modèles + factories

**Files:**
- Create: `database/migrations/xxxx_create_assistant_conversations_table.php`
- Create: `database/migrations/xxxx_create_assistant_messages_table.php`
- Create: `database/migrations/xxxx_create_assistant_usage_table.php`
- Create: `database/migrations/xxxx_create_assistant_parametres_table.php`
- Create: `database/migrations/xxxx_add_assistant_columns_to_users_table.php`
- Create: `app/Models/Conversation.php`, `app/Models/Message.php`, `app/Models/AssistantUsage.php`, `app/Models/AssistantParametre.php`
- Modify: `app/Models/User.php` (fillable + casts + relation)
- Create: `database/factories/ConversationFactory.php`, `database/factories/MessageFactory.php`
- Test: `tests/Feature/Assistant/ModelesAssistantTest.php`

**Interfaces:**
- Produces: `Conversation` (belongsTo user, hasMany messages ; colonnes `user_id,titre,modele,niveau,archivee_le`), `Message` (belongsTo conversation ; colonnes `conversation_id,role,contenu(json),jetons_input,jetons_output,credits`), `AssistantUsage` (`user_id,annee_mois,credits_consommes,jetons_input,jetons_output`), `AssistantParametre` (ligne unique `pool_credits`). `User` : colonnes `assistant_ia_actif(bool)`, `assistant_quota_credits(int)`.

- [ ] **Step 1: Écrire le test des modèles (échec attendu)**

Create `tests/Feature/Assistant/ModelesAssistantTest.php` :

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

it('crée une conversation avec messages', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->for($user)->create(['niveau' => 'premium']);
    $conv->messages()->create([
        'role' => 'user',
        'contenu' => [['type' => 'text', 'text' => 'Bonjour']],
    ]);

    expect($conv->messages)->toHaveCount(1)
        ->and($conv->messages->first()->contenu[0]['text'])->toBe('Bonjour')
        ->and($conv->user->is($user))->toBeTrue();
});

it('a des colonnes assistant sur users avec valeurs par défaut', function () {
    $user = User::factory()->create();
    expect($user->assistant_ia_actif)->toBeFalse()
        ->and($user->assistant_quota_credits)->toBe(0);
});
```

- [ ] **Step 2: Lancer le test (échec)**

Run: `php artisan test --filter=ModelesAssistantTest`
Expected: FAIL (classes/tables manquantes)

- [ ] **Step 3: Écrire la migration conversations**

`database/migrations/xxxx_create_assistant_conversations_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titre')->nullable();
            $table->string('modele');
            $table->enum('niveau', ['gratuit', 'premium']);
            $table->timestamp('archivee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

- [ ] **Step 4: Écrire la migration messages**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->json('contenu');
            $table->unsignedInteger('jetons_input')->nullable();
            $table->unsignedInteger('jetons_output')->nullable();
            $table->unsignedInteger('credits')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

- [ ] **Step 5: Écrire la migration assistant_usage**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('annee_mois', 7); // YYYY-MM
            $table->unsignedInteger('credits_consommes')->default(0);
            $table->unsignedBigInteger('jetons_input')->default(0);
            $table->unsignedBigInteger('jetons_output')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'annee_mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_usage');
    }
};
```

- [ ] **Step 6: Écrire la migration assistant_parametres (ligne unique de pool)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_parametres', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pool_credits')->default(0);
            $table->timestamps();
        });

        DB::table('assistant_parametres')->insert([
            'pool_credits' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_parametres');
    }
};
```

- [ ] **Step 7: Écrire la migration des colonnes users**

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
            $table->boolean('assistant_ia_actif')->default(false);
            $table->unsignedInteger('assistant_quota_credits')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['assistant_ia_actif', 'assistant_quota_credits']);
        });
    }
};
```

- [ ] **Step 8: Créer les modèles**

`app/Models/Conversation.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'titre', 'modele', 'niveau', 'archivee_le'];

    protected function casts(): array
    {
        return ['archivee_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }
}
```

`app/Models/Message.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'role', 'contenu', 'jetons_input', 'jetons_output', 'credits'];

    protected function casts(): array
    {
        return ['contenu' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
```

`app/Models/AssistantUsage.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantUsage extends Model
{
    protected $table = 'assistant_usage';

    protected $fillable = ['user_id', 'annee_mois', 'credits_consommes', 'jetons_input', 'jetons_output'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Models/AssistantParametre.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantParametre extends Model
{
    protected $table = 'assistant_parametres';

    protected $fillable = ['pool_credits'];

    /** La ligne unique de paramètres (créée à la migration). */
    public static function courant(): self
    {
        return static::query()->firstOrCreate([], ['pool_credits' => 0]);
    }
}
```

- [ ] **Step 9: Mettre à jour le modèle User**

In `app/Models/User.php`, add to `$fillable`: `'assistant_ia_actif'`, `'assistant_quota_credits'`. Add to `casts()`: `'assistant_ia_actif' => 'boolean'`. Add relation:

```php
public function conversations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\Conversation::class);
}
```

- [ ] **Step 10: Créer les factories**

`database/factories/ConversationFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titre' => $this->faker->sentence(3),
            'modele' => 'claude-haiku-4-5',
            'niveau' => 'gratuit',
        ];
    }
}
```

`database/factories/MessageFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => 'user',
            'contenu' => [['type' => 'text', 'text' => $this->faker->sentence()]],
        ];
    }
}
```

- [ ] **Step 11: Migrer + relancer le test (succès)**

Run: `php artisan migrate && php artisan test --filter=ModelesAssistantTest`
Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add database/migrations database/factories app/Models tests/Feature/Assistant/ModelesAssistantTest.php
git commit -m "feat(assistant): data model (conversations, messages, usage, params)"
```

---

### Task 3: Helpers de crédits sur User

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Assistant/CreditsUserTest.php`

**Interfaces:**
- Consumes: `AssistantUsage`, `config('assistant.forfait_gratuit_credits')`.
- Produces: `User::assistantEstPremium(): bool`, `User::assistantAllocationCredits(): int`, `User::assistantCreditsConsommesMois(): int`, `User::assistantCreditsRestants(): int`, `User::assistantModele(): string`, `User::enregistrerConsommation(int $jetonsIn, int $jetonsOut, int $credits): void`.

- [ ] **Step 1: Écrire le test des crédits (échec attendu)**

Create `tests/Feature/Assistant/CreditsUserTest.php` :

```php
<?php

use App\Models\User;

it('un agent sans drapeau est gratuit avec le forfait config', function () {
    config()->set('assistant.forfait_gratuit_credits', 50);
    $u = User::factory()->create(['assistant_ia_actif' => false]);

    expect($u->assistantEstPremium())->toBeFalse()
        ->and($u->assistantAllocationCredits())->toBe(50)
        ->and($u->assistantModele())->toBe(config('assistant.modele_gratuit'));
});

it('un agent avec drapeau est premium avec son quota', function () {
    $u = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 300]);

    expect($u->assistantEstPremium())->toBeTrue()
        ->and($u->assistantAllocationCredits())->toBe(300)
        ->and($u->assistantModele())->toBe(config('assistant.modele_premium'));
});

it('décompte la consommation du mois et calcule le restant', function () {
    $u = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    $u->enregistrerConsommation(2000, 500, 3);
    $u->enregistrerConsommation(1000, 1000, 2);

    expect($u->assistantCreditsConsommesMois())->toBe(5)
        ->and($u->assistantCreditsRestants())->toBe(95);
});

it('le super-admin est premium', function () {
    $u = User::factory()->create(['role' => 'admin', 'assistant_ia_actif' => false]);
    expect($u->assistantEstPremium())->toBeTrue();
});
```

- [ ] **Step 2: Lancer le test (échec)**

Run: `php artisan test --filter=CreditsUserTest`
Expected: FAIL (méthodes manquantes)

- [ ] **Step 3: Implémenter les helpers dans User**

Add to `app/Models/User.php` :

```php
public function assistantEstPremium(): bool
{
    return $this->assistant_ia_actif || $this->isAdmin();
}

public function assistantModele(): string
{
    return $this->assistantEstPremium()
        ? config('assistant.modele_premium')
        : config('assistant.modele_gratuit');
}

public function assistantAllocationCredits(): int
{
    return $this->assistantEstPremium()
        ? (int) $this->assistant_quota_credits
        : (int) config('assistant.forfait_gratuit_credits');
}

public function assistantCreditsConsommesMois(): int
{
    return (int) \App\Models\AssistantUsage::query()
        ->where('user_id', $this->id)
        ->where('annee_mois', now()->format('Y-m'))
        ->value('credits_consommes') ?? 0;
}

public function assistantCreditsRestants(): int
{
    return max(0, $this->assistantAllocationCredits() - $this->assistantCreditsConsommesMois());
}

public function enregistrerConsommation(int $jetonsIn, int $jetonsOut, int $credits): void
{
    $ligne = \App\Models\AssistantUsage::query()->firstOrCreate(
        ['user_id' => $this->id, 'annee_mois' => now()->format('Y-m')],
        ['credits_consommes' => 0, 'jetons_input' => 0, 'jetons_output' => 0],
    );
    $ligne->increment('credits_consommes', $credits);
    $ligne->increment('jetons_input', $jetonsIn);
    $ligne->increment('jetons_output', $jetonsOut);
}
```

- [ ] **Step 4: Relancer le test (succès)**

Run: `php artisan test --filter=CreditsUserTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Assistant/CreditsUserTest.php
git commit -m "feat(assistant): helpers crédits/niveau sur User"
```

---

### Task 4: Interface `AssistantIA` + implémentation Claude + faux

**Files:**
- Create: `app/Support/Assistant/ClaudeReponse.php`
- Create: `app/Support/Assistant/AssistantIA.php` (interface)
- Create: `app/Support/Assistant/AssistantClaude.php` (impl SDK)
- Create: `app/Support/Assistant/FauxAssistant.php` (test double)
- Create: `app/Providers/AssistantServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Feature/Assistant/AssistantClaudeTest.php`

**Interfaces:**
- Produces:
  - `ClaudeReponse` (readonly) : `string $texte`, `int $jetonsInput`, `int $jetonsOutput`.
  - `AssistantIA::repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse` — `$messages` = tableau `[['role'=>'user'|'assistant','content'=>string], ...]` ; `$onChunk(string $delta)` appelé pour chaque fragment de texte.
- Consumes: `Anthropic\Client`, `config('assistant.*')`.

- [ ] **Step 1: Écrire le test (avec faux lié) (échec attendu)**

Create `tests/Feature/Assistant/AssistantClaudeTest.php` :

```php
<?php

use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\ClaudeReponse;
use App\Support\Assistant\FauxAssistant;

it('le faux assistant renvoie une réponse et streame les fragments', function () {
    $faux = new FauxAssistant('Bonjour, je peux aider.', jetonsInput: 10, jetonsOutput: 5);
    app()->instance(AssistantIA::class, $faux);

    $fragments = [];
    $rep = app(AssistantIA::class)->repondre(
        messages: [['role' => 'user', 'content' => 'Salut']],
        modele: 'claude-haiku-4-5',
        system: 'Assistant DGE',
        onChunk: function (string $d) use (&$fragments) { $fragments[] = $d; },
    );

    expect($rep)->toBeInstanceOf(ClaudeReponse::class)
        ->and($rep->texte)->toBe('Bonjour, je peux aider.')
        ->and($rep->jetonsInput)->toBe(10)
        ->and($rep->jetonsOutput)->toBe(5)
        ->and(implode('', $fragments))->toBe('Bonjour, je peux aider.');
});
```

- [ ] **Step 2: Lancer le test (échec)**

Run: `php artisan test --filter=AssistantClaudeTest`
Expected: FAIL (classes manquantes)

- [ ] **Step 3: Créer le DTO ClaudeReponse**

```php
<?php

namespace App\Support\Assistant;

final readonly class ClaudeReponse
{
    public function __construct(
        public string $texte,
        public int $jetonsInput,
        public int $jetonsOutput,
    ) {}
}
```

- [ ] **Step 4: Créer l'interface**

```php
<?php

namespace App\Support\Assistant;

interface AssistantIA
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  callable(string):void  $onChunk
     */
    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse;
}
```

- [ ] **Step 5: Créer le faux (pour les tests)**

```php
<?php

namespace App\Support\Assistant;

final class FauxAssistant implements AssistantIA
{
    public function __construct(
        private string $reponse = 'Réponse simulée.',
        private int $jetonsInput = 10,
        private int $jetonsOutput = 5,
    ) {}

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        foreach (str_split($this->reponse, 8) as $fragment) {
            $onChunk($fragment);
        }

        return new ClaudeReponse($this->reponse, $this->jetonsInput, $this->jetonsOutput);
    }
}
```

- [ ] **Step 6: Créer l'implémentation Claude (SDK, streaming)**

```php
<?php

namespace App\Support\Assistant;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;

final class AssistantClaude implements AssistantIA
{
    public function __construct(private Client $client) {}

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        $stream = $this->client->messages->createStream(
            maxTokens: (int) config('assistant.max_tokens_reponse'),
            messages: $messages,
            model: $modele,
            system: $system,
        );

        $accumulator = MessageAccumulator::forMessages();
        foreach ($stream as $event) {
            $accumulator->accumulate($event);
            // Émettre le delta de texte au fur et à mesure.
            if (($event->type ?? null) === 'content_block_delta'
                && ($event->delta->type ?? null) === 'text_delta') {
                $onChunk($event->delta->text);
            }
        }

        $message = $accumulator->message();
        $texte = '';
        foreach ($message->content as $bloc) {
            if (($bloc->type ?? null) === 'text') {
                $texte .= $bloc->text;
            }
        }

        return new ClaudeReponse(
            texte: $texte,
            jetonsInput: (int) $message->usage->inputTokens,
            jetonsOutput: (int) $message->usage->outputTokens,
        );
    }
}
```

> Note d'implémentation : si un nom d'événement/propriété du SDK diffère (`$event->type`, `$event->delta`), l'ajuster contre `vendor/anthropic-ai/sdk` — ne pas deviner. La forme de `MessageAccumulator`, `usage->inputTokens/outputTokens` est confirmée par la doc SDK.

- [ ] **Step 7: Créer le provider (bind interface → impl, avec client SDK)**

```php
<?php

namespace App\Providers;

use Anthropic\Client;
use App\Support\Assistant\AssistantClaude;
use App\Support\Assistant\AssistantIA;
use Illuminate\Support\ServiceProvider;

class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: (string) config('assistant.cle_api'),
        ));

        $this->app->bind(AssistantIA::class, AssistantClaude::class);
    }
}
```

Register it in `bootstrap/providers.php` (add `App\Providers\AssistantServiceProvider::class` to the returned array).

- [ ] **Step 8: Relancer le test (succès)**

Run: `php artisan test --filter=AssistantClaudeTest`
Expected: PASS (le test utilise le faux, jamais le vrai client)

- [ ] **Step 9: Commit**

```bash
git add app/Support/Assistant app/Providers/AssistantServiceProvider.php bootstrap/providers.php tests/Feature/Assistant/AssistantClaudeTest.php
git commit -m "feat(assistant): interface AssistantIA + impl Claude SDK + faux"
```

---

### Task 5: Composant chat `Assistant\Index` (envoi + persistance + crédits)

**Files:**
- Create: `app/Livewire/Assistant/Index.php`
- Create: `resources/views/livewire/assistant/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Assistant/ChatAssistantTest.php`

**Interfaces:**
- Consumes: `AssistantIA`, `Conversation`, `Message`, `User` helpers (Task 3), `Accueil` layout `components.layouts.rh`.
- Produces: route nommée `assistant` (`/assistant`).

- [ ] **Step 1: Écrire les tests du chat (échec attendu)**

Create `tests/Feature/Assistant/ChatAssistantTest.php` :

```php
<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\FauxAssistant;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('assistant.jetons_par_credit', 1000);
    config()->set('assistant.forfait_gratuit_credits', 50);
});

function agentVerifie(array $attrs = []): User
{
    return User::factory()->create(array_merge(['email_verified_at' => now(), 'must_change_password' => false], $attrs));
}

it('envoie un message, persiste la conversation et la réponse', function () {
    app()->instance(AssistantIA::class, new FauxAssistant('Voici la réponse.', 1200, 800));
    $user = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Explique le parrainage')
        ->call('envoyer');

    $conv = Conversation::where('user_id', $user->id)->firstOrFail();
    expect($conv->niveau)->toBe('premium')
        ->and($conv->modele)->toBe(config('assistant.modele_premium'))
        ->and($conv->messages)->toHaveCount(2)
        ->and($conv->messages[0]->role)->toBe('user')
        ->and($conv->messages[1]->role)->toBe('assistant')
        ->and($conv->messages[1]->contenu[0]['text'])->toBe('Voici la réponse.');
});

it('décompte les crédits après une réponse', function () {
    // 1200 + 800 = 2000 jetons ⇒ ceil(2000/1000) = 2 crédits
    app()->instance(AssistantIA::class, new FauxAssistant('ok', 1200, 800));
    $user = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'test')
        ->call('envoyer');

    expect($user->fresh()->assistantCreditsConsommesMois())->toBe(2);
});

it('bloque l’envoi quand les crédits sont épuisés', function () {
    app()->instance(AssistantIA::class, new FauxAssistant('ne devrait pas répondre', 1000, 1000));
    $user = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'test')
        ->call('envoyer')
        ->assertSee('crédits'); // message d'épuisement rendu

    expect(Conversation::where('user_id', $user->id)->exists())->toBeFalse();
});

it('un agent sans drapeau utilise le modèle gratuit', function () {
    app()->instance(AssistantIA::class, new FauxAssistant('salut', 100, 50));
    $user = agentVerifie(['assistant_ia_actif' => false]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'bonjour')
        ->call('envoyer');

    $conv = Conversation::where('user_id', $user->id)->firstOrFail();
    expect($conv->niveau)->toBe('gratuit')
        ->and($conv->modele)->toBe(config('assistant.modele_gratuit'));
});
```

- [ ] **Step 2: Lancer les tests (échec)**

Run: `php artisan test --filter=ChatAssistantTest`
Expected: FAIL (composant manquant)

- [ ] **Step 3: Écrire le composant Livewire**

`app/Livewire/Assistant/Index.php` :

```php
<?php

namespace App\Livewire\Assistant;

use App\Models\Conversation;
use App\Support\Assistant\AssistantIA;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class Index extends Component
{
    public ?int $conversationId = null;

    public string $saisie = '';

    public string $erreur = '';

    public function nouvelleConversation(): void
    {
        $this->conversationId = null;
        $this->saisie = '';
        $this->erreur = '';
    }

    public function choisir(int $id): void
    {
        $conv = auth()->user()->conversations()->findOrFail($id);
        $this->conversationId = $conv->id;
        $this->erreur = '';
    }

    public function envoyer(AssistantIA $assistant): void
    {
        $this->erreur = '';
        $texte = trim($this->saisie);
        if ($texte === '') {
            return;
        }

        $user = auth()->user();

        if ($user->assistantCreditsRestants() <= 0) {
            $this->erreur = 'Vos crédits sont épuisés pour ce mois. Demandez un rechargement à l’administrateur.';

            return;
        }

        $conv = $this->conversationId
            ? $user->conversations()->findOrFail($this->conversationId)
            : $user->conversations()->create([
                'titre' => Str::limit($texte, 60),
                'modele' => $user->assistantModele(),
                'niveau' => $user->assistantEstPremium() ? 'premium' : 'gratuit',
            ]);
        $this->conversationId = $conv->id;

        $conv->messages()->create([
            'role' => 'user',
            'contenu' => [['type' => 'text', 'text' => $texte]],
        ]);
        $this->saisie = '';

        // Historique pour l'API
        $messages = $conv->messages()->get()->map(fn ($m) => [
            'role' => $m->role,
            'content' => collect($m->contenu)->pluck('text')->implode(''),
        ])->all();

        $rep = $assistant->repondre(
            messages: $messages,
            modele: $conv->modele,
            system: $this->promptSysteme(),
            onChunk: fn (string $d) => $this->stream(to: 'reponse-en-cours', content: $d),
        );

        $credits = (int) ceil(($rep->jetonsInput + $rep->jetonsOutput) / (int) config('assistant.jetons_par_credit'));

        $conv->messages()->create([
            'role' => 'assistant',
            'contenu' => [['type' => 'text', 'text' => $rep->texte]],
            'jetons_input' => $rep->jetonsInput,
            'jetons_output' => $rep->jetonsOutput,
            'credits' => $credits,
        ]);

        $user->enregistrerConsommation($rep->jetonsInput, $rep->jetonsOutput, $credits);
    }

    private function promptSysteme(): string
    {
        return "Tu es l'assistant IA de la Direction Générale des Élections (DGE) du Sénégal. "
            ."Réponds en français, de façon professionnelle et concise, pour aider l'agent dans ses tâches. "
            ."N'invente jamais de référence juridique ; si tu n'es pas sûr, dis-le.";
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.assistant.index', [
            'conversations' => $user->conversations()->latest()->get(),
            'conversation' => $this->conversationId
                ? Conversation::with('messages')->find($this->conversationId)
                : null,
            'creditsRestants' => $user->assistantCreditsRestants(),
            'allocation' => $user->assistantAllocationCredits(),
            'estPremium' => $user->assistantEstPremium(),
        ]);
    }
}
```

- [ ] **Step 4: Écrire la vue (minimale ; le design vient en Phase 2)**

`resources/views/livewire/assistant/index.blade.php` :

```blade
<div class="assistant" style="display:grid;grid-template-columns:260px 1fr;gap:1rem;">
    <aside class="card">
        <button class="btn" wire:click="nouvelleConversation">+ Nouvelle conversation</button>
        <div style="margin-top:.5rem;">
            <span class="badge">{{ $estPremium ? 'Premium' : 'Gratuit' }}</span>
            <span>Crédits : {{ $creditsRestants }} / {{ $allocation }}</span>
        </div>
        <ul style="list-style:none;padding:0;margin-top:1rem;">
            @foreach ($conversations as $c)
                <li>
                    <button class="btn" wire:click="choisir({{ $c->id }})">{{ $c->titre ?? 'Sans titre' }}</button>
                </li>
            @endforeach
        </ul>
    </aside>

    <section class="card">
        @if ($erreur)
            <div class="alerte" style="color:#b91c1c;">{{ $erreur }}</div>
        @endif

        <div class="fil" style="min-height:300px;">
            @if ($conversation)
                @foreach ($conversation->messages as $m)
                    <div class="msg msg-{{ $m->role }}">
                        <strong>{{ $m->role === 'user' ? 'Vous' : 'Assistant' }} :</strong>
                        <div>{{ collect($m->contenu)->pluck('text')->implode('') }}</div>
                    </div>
                @endforeach
            @else
                <p>Pose une question pour démarrer.</p>
            @endif
            <div wire:stream="reponse-en-cours"></div>
        </div>

        <form wire:submit="envoyer" style="margin-top:1rem;">
            <textarea wire:model="saisie" rows="3" style="width:100%;" placeholder="Écris ta demande…"></textarea>
            <button class="btn" type="submit" wire:loading.attr="disabled">Envoyer</button>
        </form>
    </section>
</div>
```

- [ ] **Step 5: Ajouter la route**

In `routes/web.php`, add `use App\Livewire\Assistant\Index as AssistantIndex;` and inside the `['auth','verified','password.change']` group (lines 67–81):

```php
Route::get('/assistant', AssistantIndex::class)->name('assistant');
```

- [ ] **Step 6: Relancer les tests (succès)**

Run: `php artisan test --filter=ChatAssistantTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Assistant resources/views/livewire/assistant routes/web.php tests/Feature/Assistant/ChatAssistantTest.php
git commit -m "feat(assistant): composant chat (envoi, persistance, crédits, niveaux)"
```

---

### Task 6: Écran admin `Admin\Assistant\Gestion` (drapeau, pool, réallocation, usage)

**Files:**
- Create: `app/Livewire/Admin/Assistant/Gestion.php`
- Create: `resources/views/livewire/admin/assistant/gestion.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Assistant/AdminAssistantTest.php`

**Interfaces:**
- Consumes: `User`, `AssistantParametre`, `assistantCreditsConsommesMois()`.
- Produces: route nommée `admin.assistant` (`/admin/assistant`).

- [ ] **Step 1: Écrire les tests admin (échec attendu)**

Create `tests/Feature/Assistant/AdminAssistantTest.php` :

```php
<?php

use App\Livewire\Admin\Assistant\Gestion;
use App\Models\AssistantParametre;
use App\Models\User;
use Livewire\Livewire;

function admin(): User
{
    return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('un non-admin ne peut pas accéder à l’écran admin', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/admin/assistant')->assertForbidden();
});

it('l’admin bascule le drapeau premium d’un agent', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => false]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->call('basculerDrapeau', $agent->id);

    expect($agent->fresh()->assistant_ia_actif)->toBeTrue();
});

it('l’admin règle le pool et alloue des crédits', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);
    AssistantParametre::courant()->update(['pool_credits' => 1000]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->set('pool', 1000)
        ->call('enregistrerPool')
        ->set("allocations.{$agent->id}", 300)
        ->call('enregistrerAllocation', $agent->id);

    expect($agent->fresh()->assistant_quota_credits)->toBe(300);
});

it('refuse une allocation qui dépasse le pool', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);
    AssistantParametre::courant()->update(['pool_credits' => 100]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->set("allocations.{$agent->id}", 500)
        ->call('enregistrerAllocation', $agent->id)
        ->assertHasErrors("allocations.{$agent->id}");

    expect($agent->fresh()->assistant_quota_credits)->toBe(0);
});
```

- [ ] **Step 2: Lancer les tests (échec)**

Run: `php artisan test --filter=AdminAssistantTest`
Expected: FAIL

- [ ] **Step 3: Écrire le composant admin**

`app/Livewire/Admin/Assistant/Gestion.php` :

```php
<?php

namespace App\Livewire\Admin\Assistant;

use App\Models\AssistantParametre;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class Gestion extends Component
{
    public int $pool = 0;

    /** @var array<int,int> */
    public array $allocations = [];

    public function mount(): void
    {
        $this->pool = (int) AssistantParametre::courant()->pool_credits;
        $this->allocations = User::where('assistant_ia_actif', true)
            ->pluck('assistant_quota_credits', 'id')->map(fn ($v) => (int) $v)->all();
    }

    public function basculerDrapeau(int $userId): void
    {
        $u = User::findOrFail($userId);
        $u->update(['assistant_ia_actif' => ! $u->assistant_ia_actif]);
        if ($u->assistant_ia_actif) {
            $this->allocations[$u->id] = (int) $u->assistant_quota_credits;
        } else {
            unset($this->allocations[$u->id]);
        }
    }

    public function enregistrerPool(): void
    {
        $this->validate(['pool' => 'required|integer|min:0']);
        AssistantParametre::courant()->update(['pool_credits' => $this->pool]);
    }

    public function enregistrerAllocation(int $userId): void
    {
        $valeur = (int) ($this->allocations[$userId] ?? 0);

        $totalAutres = User::where('assistant_ia_actif', true)
            ->where('id', '!=', $userId)
            ->sum('assistant_quota_credits');

        if ($totalAutres + $valeur > $this->pool) {
            $this->addError("allocations.$userId", "Dépasse le pool disponible ({$this->pool} crédits).");

            return;
        }

        User::whereKey($userId)->update(['assistant_quota_credits' => $valeur]);
    }

    public function render()
    {
        $agents = User::orderBy('name')->get();
        $totalAlloue = User::where('assistant_ia_actif', true)->sum('assistant_quota_credits');

        return view('livewire.admin.assistant.gestion', [
            'agents' => $agents,
            'totalAlloue' => (int) $totalAlloue,
        ]);
    }
}
```

- [ ] **Step 4: Écrire la vue admin (minimale)**

`resources/views/livewire/admin/assistant/gestion.blade.php` :

```blade
<div class="card">
    <h1>Assistant IA — gestion</h1>

    <form wire:submit="enregistrerPool" style="margin:1rem 0;">
        <label>Pool mensuel (crédits premium)</label>
        <input type="number" wire:model="pool" min="0">
        <button class="btn" type="submit">Enregistrer le pool</button>
        <p>Alloué : {{ $totalAlloue }} / {{ $pool }}</p>
        @error('pool') <span style="color:#b91c1c;">{{ $message }}</span> @enderror
    </form>

    <table>
        <thead><tr><th>Agent</th><th>Premium</th><th>Allocation</th><th>Consommé (mois)</th></tr></thead>
        <tbody>
        @foreach ($agents as $agent)
            <tr>
                <td>{{ $agent->name }}</td>
                <td>
                    <button class="btn" wire:click="basculerDrapeau({{ $agent->id }})">
                        {{ $agent->assistant_ia_actif ? 'Oui' : 'Non' }}
                    </button>
                </td>
                <td>
                    @if ($agent->assistant_ia_actif)
                        <input type="number" min="0" wire:model="allocations.{{ $agent->id }}">
                        <button class="btn" wire:click="enregistrerAllocation({{ $agent->id }})">OK</button>
                        @error("allocations.{$agent->id}") <span style="color:#b91c1c;">{{ $message }}</span> @enderror
                    @else — @endif
                </td>
                <td>{{ $agent->assistantCreditsConsommesMois() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
```

- [ ] **Step 5: Ajouter la route admin**

In `routes/web.php`, add `use App\Livewire\Admin\Assistant\Gestion as AssistantGestion;` and inside the `role:admin` group (lines 140–142):

```php
Route::get('/admin/assistant', AssistantGestion::class)->name('admin.assistant');
```

- [ ] **Step 6: Relancer les tests (succès)**

Run: `php artisan test --filter=AdminAssistantTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Admin/Assistant resources/views/livewire/admin/assistant routes/web.php tests/Feature/Assistant/AdminAssistantTest.php
git commit -m "feat(assistant): écran admin (drapeau, pool, réallocation, usage)"
```

---

### Task 7: Navigation + accès de bout en bout

**Files:**
- Modify: `resources/views/components/layouts/rh.blade.php`
- Test: `tests/Feature/Assistant/AccesAssistantTest.php`

**Interfaces:**
- Consumes: routes `assistant`, `admin.assistant` ; helpers `User`.

- [ ] **Step 1: Écrire les tests d'accès (échec attendu)**

Create `tests/Feature/Assistant/AccesAssistantTest.php` :

```php
<?php

use App\Models\User;

it('tout agent connecté et vérifié accède à l’assistant', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/assistant')->assertOk();
});

it('un invité est redirigé vers le login', function () {
    $this->get('/assistant')->assertRedirect('/login');
});

it('le lien Assistant IA apparaît dans la nav pour un agent', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($u)->get('/assistant')->assertSee('Assistant IA');
});

it('le lien de gestion assistant apparaît pour l’admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($admin)->get('/assistant')->assertSee('Gérer l’assistant');
});
```

- [ ] **Step 2: Lancer les tests (échec)**

Run: `php artisan test --filter=AccesAssistantTest`
Expected: FAIL (liens absents de la nav)

- [ ] **Step 3: Ajouter les liens dans la barre latérale**

In `resources/views/components/layouts/rh.blade.php`, add in the sidebar nav (section Personnel, visible par tous) :

```blade
<a href="{{ route('assistant') }}" class="nav-link @if(request()->routeIs('assistant')) actif @endif">Assistant IA</a>

@if (auth()->user()?->isAdmin())
    <a href="{{ route('admin.assistant') }}" class="nav-link @if(request()->routeIs('admin.assistant')) actif @endif">Gérer l’assistant</a>
@endif
```

> Suivre le style/markup des liens `nav-link` déjà présents dans ce layout (classes exactes à copier depuis les liens voisins).

- [ ] **Step 4: Relancer les tests (succès)**

Run: `php artisan test --filter=AccesAssistantTest`
Expected: PASS

- [ ] **Step 5: Lancer toute la suite**

Run: `php artisan test`
Expected: PASS (toute la suite verte, y compris les 228+ tests existants)

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/layouts/rh.blade.php tests/Feature/Assistant/AccesAssistantTest.php
git commit -m "feat(assistant): navigation + accès (agent + admin)"
```

---

## Phase 2 (plan suivant, hors ce document)

- **Analyse de fichiers** : upload `WithFileUploads` (PDF/image natif, Excel/Word → `pieces_jointes.texte_extrait` via PhpSpreadsheet/PhpWord), blocs `document`/`image` ; premium seulement.
- **Recherche web** : outil serveur `web_search` (vérifier la déclaration exacte dans `vendor/anthropic-ai/sdk` — non couverte par la doc README) ; premium seulement.
- **Ancrage bibliothèque** : Module A (spec 2026-08-05) + table `document_chunks` (FULLTEXT) + outil personnalisé `rechercher_bibliotheque` (boucle agentique) + citations.
- **Passe design ui-ux-pro-max** : interface chat type claude.ai, jauge crédits, rendu Markdown/streaming soigné, états vides, charte DGB.

## Self-Review (effectuée)

- **Couverture spec** : niveaux (Task 3/5), crédits+quota (Task 3/5), pool+réallocation (Task 6), accès ouvert + admin (Task 7), persistance (Task 2/5), modèle par niveau (Task 3/5), compte/facturation (Task 1 config + `.env`). Fichiers/web/ancrage/design → Phase 2 (déclaré).
- **Placeholders** : aucun — tests et implémentations fournis intégralement.
- **Cohérence des types** : `AssistantIA::repondre(...)` identique entre interface (Task 4), faux (Task 4), et appelant (Task 5) ; `ClaudeReponse->{texte,jetonsInput,jetonsOutput}` ; `enregistrerConsommation(int,int,int)` défini Task 3, appelé Task 5 ; `assistantCreditsRestants()` défini Task 3, utilisé Task 5.
- **Point à vérifier en exécution** (noté dans le code) : noms d'événements de streaming du SDK (`$event->type`, `$event->delta->text`) — ajuster contre `vendor/anthropic-ai/sdk` sans deviner.
