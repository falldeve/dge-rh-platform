<?php

use App\Models\Conversation;
use App\Support\Assistant\ConstructeurContenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function messageAvec(array $contenu, array $pieces = [])
{
    $conv = Conversation::factory()->create();
    $m = $conv->messages()->create(['role' => 'user', 'contenu' => $contenu]);
    foreach ($pieces as $p) {
        $m->piecesJointes()->create($p);
    }

    return $m->fresh('piecesJointes');
}

it('renvoie une string quand pas de pièce', function () {
    $m = messageAvec([['type' => 'text', 'text' => 'Bonjour']]);
    expect(ConstructeurContenu::pour($m))->toBe('Bonjour');
});

it("construit des blocs avec texte extrait d'un document", function () {
    $m = messageAvec(
        [['type' => 'text', 'text' => 'Analyse ceci']],
        [['nom_original' => 'loi.pdf', 'chemin' => 'assistant/loi.pdf', 'type_mime' => 'application/pdf', 'taille' => 10, 'texte_extrait' => 'Article premier.']],
    );

    $blocs = ConstructeurContenu::pour($m);
    expect($blocs)->toBeArray()
        ->and($blocs[0])->toMatchArray(['type' => 'text', 'text' => 'Analyse ceci'])
        ->and($blocs[1]['type'])->toBe('text')
        ->and($blocs[1]['text'])->toContain('loi.pdf')
        ->and($blocs[1]['text'])->toContain('Article premier.');
});

it('construit un bloc image base64 pour une image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('assistant/photo.png', 'FAKEIMG');
    $m = messageAvec(
        [['type' => 'text', 'text' => 'Décris']],
        [['nom_original' => 'photo.png', 'chemin' => 'assistant/photo.png', 'type_mime' => 'image/png', 'taille' => 7, 'texte_extrait' => null]],
    );

    $blocs = ConstructeurContenu::pour($m);
    expect($blocs[1]['type'])->toBe('image')
        ->and($blocs[1]['source']['type'])->toBe('base64')
        ->and($blocs[1]['source']['media_type'])->toBe('image/png')
        ->and($blocs[1]['source']['data'])->toBe(base64_encode('FAKEIMG'));
});
