<?php

use App\Livewire\Assistant\Index;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\ClaudeReponse;
use App\Support\Assistant\FauxAssistant;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\FauxExtracteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentFichier(array $attrs = []): User
{
    return User::factory()->create(array_merge(['email_verified_at' => now(), 'must_change_password' => false], $attrs));
}

it('joint un PDF, l’extrait et l’enregistre comme pièce jointe', function () {
    Storage::fake('local');
    config()->set('assistant.jetons_par_credit', 1000);
    app()->instance(AssistantIA::class, new FauxAssistant('Voici l’analyse.', 1500, 500));
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: 'Texte du PDF extrait.'));
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Résume ce document')
        ->set('fichiers', [UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf')])
        ->call('envoyer');

    $conv = Conversation::where('user_id', $user->id)->firstOrFail();
    $userMsg = $conv->messages()->where('role', 'user')->firstOrFail();
    expect($userMsg->piecesJointes)->toHaveCount(1)
        ->and($userMsg->piecesJointes->first()->texte_extrait)->toBe('Texte du PDF extrait.')
        ->and($conv->messages()->where('role', 'assistant')->exists())->toBeTrue();
    Storage::disk('local')->assertExists($userMsg->piecesJointes->first()->chemin);
});

it('joint une image sans extraction', function () {
    Storage::fake('local');
    app()->instance(AssistantIA::class, new FauxAssistant('Image vue.', 900, 200));
    $user = agentFichier(['assistant_ia_actif' => false]); // gratuit peut aussi joindre

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Décris cette image')
        ->set('fichiers', [UploadedFile::fake()->image('photo.png')])
        ->call('envoyer');

    $pj = Conversation::where('user_id', $user->id)->firstOrFail()
        ->messages()->where('role', 'user')->firstOrFail()->piecesJointes->first();
    expect($pj->estImage())->toBeTrue()->and($pj->texte_extrait)->toBeNull();
});

it('refuse un type de fichier non autorisé', function () {
    Storage::fake('local');
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'test')
        ->set('fichiers', [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])
        ->call('envoyer')
        ->assertHasErrors('fichiers.0');
});

it('refuse une image de plus de 5 Mo avec un message clair', function () {
    Storage::fake('local');
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Décris cette image')
        ->set('fichiers', [UploadedFile::fake()->image('big.jpg')->size(6144)])
        ->call('envoyer')
        ->assertHasErrors('fichiers.0');

    expect(Conversation::where('user_id', $user->id)->exists())->toBeFalse();
});

it('accepte toujours une petite image (< 5 Mo)', function () {
    Storage::fake('local');
    app()->instance(AssistantIA::class, new FauxAssistant('Image vue.', 900, 200));
    $user = agentFichier(['assistant_ia_actif' => false]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Décris cette image')
        ->set('fichiers', [UploadedFile::fake()->image('photo.png')])
        ->call('envoyer')
        ->assertHasNoErrors('fichiers.0');

    expect(Conversation::where('user_id', $user->id)->exists())->toBeTrue();
});

it('nettoie les fichiers physiques orphelins quand l’appel à l’IA échoue', function () {
    Storage::fake('local');
    $assistantEnPanne = new class implements AssistantIA
    {
        public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
        {
            throw new RuntimeException('Erreur réseau simulée');
        }
    };
    app()->instance(AssistantIA::class, $assistantEnPanne);
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: 'Texte du PDF extrait.'));
    $user = agentFichier(['assistant_ia_actif' => true, 'assistant_quota_credits' => 100]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('saisie', 'Résume ce document')
        ->set('fichiers', [UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf')])
        ->call('envoyer');

    expect(Conversation::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Message::query()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles('assistant'))->toBeEmpty();
});
