<?php

namespace App\Support;

class NombreEnLettres
{
    private const UNITES = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function convertir(int $n): string
    {
        if ($n < 0) {
            return 'moins '.self::convertir(-$n);
        }
        if ($n < 100) {
            return self::deuxChiffres($n);
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $reste = $n % 100;
            if ($reste === 0) {
                return $c === 1 ? 'cent' : self::UNITES[$c].' cents';
            }
            $cent = $c === 1 ? 'cent' : self::UNITES[$c].' cent';

            return $cent.' '.self::deuxChiffres($reste);
        }

        $milliers = intdiv($n, 1000);
        $reste = $n % 1000;
        $prefixe = $milliers === 1 ? 'mille' : self::convertir($milliers).' mille';

        return $reste === 0 ? $prefixe : $prefixe.' '.self::convertir($reste);
    }

    private static function deuxChiffres(int $n): string
    {
        if ($n < 20) {
            return self::UNITES[$n];
        }

        $d = intdiv($n, 10);
        $u = $n % 10;

        return match ($d) {
            2, 3, 4, 5, 6 => self::assembler(self::DIZAINES[$d], $u),
            7 => $u === 1 ? 'soixante et onze' : 'soixante-'.self::UNITES[10 + $u],
            8 => $u === 0 ? 'quatre-vingts' : 'quatre-vingt-'.self::UNITES[$u],
            9 => 'quatre-vingt-'.self::UNITES[10 + $u],
            default => self::UNITES[$n],
        };
    }

    private static function assembler(string $base, int $u): string
    {
        if ($u === 0) {
            return $base;
        }
        if ($u === 1) {
            return $base.' et un';
        }

        return $base.'-'.self::UNITES[$u];
    }
}
