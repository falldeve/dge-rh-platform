<?php

namespace App\Support\Assistant;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

final class ConstructeurContenu
{
    /** @return string|array<int,array<string,mixed>> */
    public static function pour(Message $m): string|array
    {
        $texte = collect($m->contenu)->pluck('text')->implode('');

        if ($m->piecesJointes->isEmpty()) {
            return $texte;
        }

        $blocs = [];
        if ($texte !== '') {
            $blocs[] = ['type' => 'text', 'text' => $texte];
        }

        foreach ($m->piecesJointes as $pj) {
            if ($pj->estImage() && Storage::disk('local')->exists($pj->chemin)) {
                $blocs[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $pj->type_mime,
                        'data' => base64_encode(Storage::disk('local')->get($pj->chemin)),
                    ],
                ];
            } elseif (filled($pj->texte_extrait)) {
                $blocs[] = ['type' => 'text', 'text' => "[Document joint : {$pj->nom_original}]\n".$pj->texte_extrait];
            } else {
                $blocs[] = ['type' => 'text', 'text' => "[Document joint : {$pj->nom_original} — contenu non extractible (scan ?)]"];
            }
        }

        return $blocs;
    }
}
