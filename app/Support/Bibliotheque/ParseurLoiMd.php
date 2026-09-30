<?php

namespace App\Support\Bibliotheque;

final class ParseurLoiMd
{
    /** @return array<int,array{titre:string,contenu:string}> */
    public function sections(string $markdown): array
    {
        $lignes = preg_split('/\r?\n/', $markdown) ?: [];
        $sections = [];
        $courant = null;

        foreach ($lignes as $ligne) {
            if (preg_match('/^##\s+(?!#)(.+)$/', $ligne, $m)) {
                if ($courant) {
                    $sections[] = $courant;
                }
                $courant = ['titre' => trim($m[1]), 'contenu' => ''];
            } elseif ($courant !== null) {
                $courant['contenu'] .= $ligne."\n";
            }
        }
        if ($courant) {
            $sections[] = $courant;
        }

        foreach ($sections as &$s) {
            $s['contenu'] = trim($s['contenu']);
        }

        return $sections;
    }
}
