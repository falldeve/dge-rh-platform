<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\FauxAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

it('un agent ne peut pas lire la conversation privée d’un autre agent (IDOR)', function () {
    $agentA = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);
    $agentB = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    $conv = $agentA->conversations()->create([
        'titre' => 'Conversation privée de A',
        'modele' => config('assistant.modele_premium'),
        'niveau' => 'premium',
    ]);
    $conv->messages()->create([
        'role' => 'user',
        'contenu' => [['type' => 'text', 'text' => 'Secret confidentiel de A']],
    ]);

    $component = Livewire::actingAs($agentB)->test(Index::class);

    try {
        // Une propriété non protégée permettrait à B de forcer la lecture de
        // la conversation de A en manipulant simplement conversationId.
        $component->set('conversationId', $conv->id);
    } catch (\Throwable $e) {
        // Avec #[Locked], Livewire refuse la mise à jour côté client : c'est
        // une des deux défenses attendues, on continue pour vérifier l'autre
        // (le scoping de la requête dans render()).
    }

    $component->assertDontSee('Secret confidentiel de A')
        ->assertSee('Comment puis-je aider');
});

it('ne laisse aucune conversation ni message orphelins si l’appel réel à l’IA échoue', function () {
    $assistantEnPanne = new class implements AssistantIA
    {
        public function repondre(array $messages, string $modele, string $system, callable $onChunk): \App\Support\Assistant\ClaudeReponse
        {
            throw new \RuntimeException('Erreur réseau simulée');
        }
    };
    app()->instance(AssistantIA::class, $assistantEnPanne);
    $user = agentVerifie(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Explique le parrainage')
        ->call('envoyer')
        ->assertSet('erreur', fn ($erreur) => $erreur !== '');

    expect(Conversation::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(\App\Models\Message::query()->exists())->toBeFalse()
        ->and($user->fresh()->assistantCreditsConsommesMois())->toBe(0);
});
