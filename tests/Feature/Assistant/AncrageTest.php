<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\ClaudeReponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Faux qui capture le prompt système reçu (pour vérifier l'injection d'extraits).
class FauxCapteur implements AssistantIA
{
    public static string $systemRecu = '';

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        self::$systemRecu = $system;

        return new ClaudeReponse('Réponse ancrée.', 500, 200);
    }
}

function agentAncrage(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);
}

function seedDocParrainage(): Document
{
    $d = Document::factory()->for(Rubrique::factory())->create(['titre' => 'Code électoral — Parrainage', 'reference' => 'Loi 2021-35', 'actif' => true]);
    $d->chunks()->create(['ordre' => 0, 'contenu' => "Le parrainage citoyen est requis pour toute candidature à l'élection présidentielle au Sénégal."]);

    return $d;
}

it('injecte les extraits de la bibliothèque dans le prompt système', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'Explique le parrainage des candidats')
        ->call('envoyer');

    expect(FauxCapteur::$systemRecu)->toContain('parrainage citoyen')
        ->and(FauxCapteur::$systemRecu)->toContain('Loi 2021-35')
        ->and(FauxCapteur::$systemRecu)->toContain('Sénégal');
});

it('persiste les sources sur le message assistant', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    $doc = seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'parrainage des candidats')
        ->call('envoyer');

    $conv = Conversation::firstOrFail();
    $assistant = $conv->messages()->where('role', 'assistant')->firstOrFail();
    $bloc = collect($assistant->contenu)->firstWhere('type', 'sources');
    expect($bloc)->not->toBeNull()
        ->and($bloc['documents'][0]['id'])->toBe($doc->id)
        ->and($bloc['documents'][0]['titre'])->toBe('Code électoral — Parrainage');
});

it('n’injecte pas de contexte ni de sources hors sujet', function () {
    app()->instance(AssistantIA::class, new FauxCapteur);
    seedDocParrainage();

    Livewire::actingAs(agentAncrage())->test(Index::class)
        ->set('saisie', 'Bonjour')
        ->call('envoyer');

    $assistant = Conversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail();
    expect(collect($assistant->contenu)->firstWhere('type', 'sources'))->toBeNull();
    // le prompt d'ancrage de base reste, mais sans extraits
    expect(FauxCapteur::$systemRecu)->not->toContain('Extraits de la bibliothèque');
});
