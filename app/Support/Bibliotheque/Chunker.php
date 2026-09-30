<?php

namespace App\Support\Bibliotheque;

final class Chunker
{
    /** @return array<int,string> */
    public function decouper(string $texte, int $mots = 800): array
    {
        $texte = trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
        if ($texte === '') {
            return [];
        }

        $tokens = explode(' ', $texte);
        $morceaux = [];
        $chevauchement = 40; // ~1-2 phrases de recouvrement
        $pas = max(1, $mots - $chevauchement);

        for ($i = 0; $i < count($tokens); $i += $pas) {
            $tranche = array_slice($tokens, $i, $mots);
            if ($tranche === []) {
                break;
            }
            $morceaux[] = implode(' ', $tranche);
            if ($i + $mots >= count($tokens)) {
                break;
            }
        }

        return $morceaux;
    }
}
