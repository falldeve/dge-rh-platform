<?php

namespace App\Support\Bibliotheque;

final class FauxExtracteur implements ExtracteurTexte
{
    /** @param array<string,string> $parChemin  chemin => texte renvoyé */
    public function __construct(private array $parChemin = [], private string $defaut = '') {}

    public function extraire(string $cheminAbsolu): string
    {
        return $this->parChemin[$cheminAbsolu] ?? $this->parChemin[basename($cheminAbsolu)] ?? $this->defaut;
    }
}
