<?php

use App\Support\Bibliotheque\Chunker;

it("découpe un long texte en morceaux d'environ N mots", function () {
    $texte = trim(str_repeat('mot ', 2000)); // 2000 mots
    $morceaux = (new Chunker)->decouper($texte, mots: 800);

    expect(count($morceaux))->toBeGreaterThanOrEqual(2)
        ->and(str_word_count($morceaux[0]))->toBeLessThanOrEqual(900);
});

it('renvoie une liste vide pour un texte vide', function () {
    expect((new Chunker)->decouper('   '))->toBe([]);
});
