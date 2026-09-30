<?php

namespace App\Support\Bibliotheque;

interface ExtracteurTexte
{
    /** Texte brut d'un .pdf, .docx ou .xlsx ; '' si non extractible. */
    public function extraire(string $cheminAbsolu): string;
}
