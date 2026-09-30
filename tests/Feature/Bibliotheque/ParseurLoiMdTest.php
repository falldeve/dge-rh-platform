<?php

use App\Support\Bibliotheque\ParseurLoiMd;

it('découpe le markdown par titre de niveau 2', function () {
    $md = "# Recueil\n\n## 1. Loi A du 1960\n\nCorps A.\n\n### Titre premier\nSous-corps.\n\n## 2. Décret B\n\nCorps B.\n";
    $sections = (new ParseurLoiMd)->sections($md);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['titre'])->toBe('1. Loi A du 1960')
        ->and($sections[0]['contenu'])->toContain('Corps A.')
        ->and($sections[0]['contenu'])->toContain('Titre premier')
        ->and($sections[1]['titre'])->toBe('2. Décret B');
});
