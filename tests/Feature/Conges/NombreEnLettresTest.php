<?php

use App\Support\NombreEnLettres;

it('convertit les nombres en toutes lettres (français)', function (int $n, string $attendu) {
    expect(NombreEnLettres::convertir($n))->toBe($attendu);
})->with([
    [0, 'zéro'],
    [1, 'un'],
    [7, 'sept'],
    [15, 'quinze'],
    [20, 'vingt'],
    [21, 'vingt et un'],
    [30, 'trente'],
    [45, 'quarante-cinq'],
    [60, 'soixante'],
    [71, 'soixante et onze'],
    [75, 'soixante-quinze'],
    [80, 'quatre-vingts'],
    [81, 'quatre-vingt-un'],
    [90, 'quatre-vingt-dix'],
    [91, 'quatre-vingt-onze'],
    [100, 'cent'],
    [180, 'cent quatre-vingts'],
    [200, 'deux cents'],
    [215, 'deux cent quinze'],
    [365, 'trois cent soixante-cinq'],
]);
