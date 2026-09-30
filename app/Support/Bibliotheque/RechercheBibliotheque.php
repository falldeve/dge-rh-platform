<?php

namespace App\Support\Bibliotheque;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

final class RechercheBibliotheque
{
    /** @return array<int,array{contenu:string,document_id:int,titre:string,reference:?string}> */
    public function rechercher(string $requete, int $limit = 6): array
    {
        $requete = trim($requete);
        if (mb_strlen($requete) < 3) {
            return [];
        }

        $base = DocumentChunk::query()
            ->join('documents', 'documents.id', '=', 'document_chunks.document_id')
            ->where('documents.actif', true)
            ->limit($limit)
            ->select([
                'document_chunks.contenu as contenu',
                'documents.id as document_id',
                'documents.titre as titre',
                'documents.reference as reference',
            ]);

        if (DB::connection()->getDriverName() === 'mysql') {
            $base->whereRaw('MATCH(document_chunks.contenu) AGAINST (? IN NATURAL LANGUAGE MODE)', [$requete])
                ->orderByRaw('MATCH(document_chunks.contenu) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$requete]);
        } else {
            // Fallback SQLite/autres : LIKE par mots significatifs.
            $mots = array_filter(preg_split('/\s+/', $requete) ?: [], fn ($m) => mb_strlen($m) >= 3);
            if ($mots === []) {
                return [];
            }
            $base->where(function ($q) use ($mots) {
                foreach ($mots as $mot) {
                    $q->orWhere('document_chunks.contenu', 'like', '%'.$mot.'%');
                }
            });
        }

        return $base->get()->map(fn ($r) => [
            'contenu' => (string) $r->contenu,
            'document_id' => (int) $r->document_id,
            'titre' => (string) $r->titre,
            'reference' => $r->reference !== null ? (string) $r->reference : null,
        ])->all();
    }
}
