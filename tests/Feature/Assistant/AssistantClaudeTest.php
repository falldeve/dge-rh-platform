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
