<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\ClasseurDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReclasserBibliotheque extends Command
{
    protected $signature = 'bibliotheque:reclasser {--appliquer} {--supprimer}';

    protected $description = 'Réorganise la bibliothèque (rubriques par type + année) et purge le hors-sujet.';

    public function handle(ClasseurDocuments $classeur): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $supprimer = (bool) $this->option('supprimer');
        $rubriques = $this->seedRubriques($appliquer);

        $aSupprimer = [];
        $parRubrique = [];
        $sansAnnee = 0;
        $reclasses = 0;

        Document::where('source', 'fichier')->chunkById(200, function ($lot) use (
            $classeur, $appliquer, $supprimer, $rubriques, &$aSupprimer, &$parRubrique, &$sansAnnee, &$reclasses
        ) {
            foreach ($lot as $doc) {
                if ($classeur->estHorsSujet($doc->titre) || $classeur->estDonneeSupprimable($doc->titre) || $classeur->estJunk($doc->titre)) {
                    $aSupprimer[] = $doc->titre;
                    if ($appliquer && $supprimer) {
                        $chemin = $doc->fichier_path;
                        DB::transaction(function () use ($doc) {
                            $doc->chunks()->delete();
                            $doc->delete();
                        });
                        if (filled($chemin)) {
                            Storage::disk('public')->delete($chemin);
                        }
                    }

                    continue;
                }

                $nom = $classeur->rubrique($doc->titre);
                $type = $classeur->typePourRubrique($nom);
                $annee = $classeur->annee($doc->titre, $doc->reference, $doc->date_document);

                $parRubrique[$nom] = ($parRubrique[$nom] ?? 0) + 1;
                if ($annee === null) {
                    $sansAnnee++;
                }
                $reclasses++;

                if ($appliquer) {
                    $doc->update([
                        'rubrique_id' => $rubriques[$nom],
                        'type' => $type,
                        'annee' => $annee,
                    ]);
                }
            }
        });

        if ($appliquer) {
            $this->desactiverRubriquesVides();
        }

        $this->rapport($aSupprimer, $parRubrique, $sansAnnee, $reclasses, $appliquer, $supprimer);

        return self::SUCCESS;
    }

    /** @return array<string,int> nom => id */
    private function seedRubriques(bool $appliquer): array
    {
        $ids = [];
        foreach (ClasseurDocuments::RUBRIQUES as $i => $nom) {
            if ($appliquer) {
                $r = Rubrique::updateOrCreate(
                    ['nom' => $nom],
                    ['slug' => Str::slug($nom), 'ordre' => $i, 'actif' => true],
                );
            } else {
                $r = Rubrique::firstOrNew(['nom' => $nom]);
                $r->id ??= 0;
            }
            $ids[$nom] = $r->id;
        }

        return $ids;
    }

    private function desactiverRubriquesVides(): void
    {
        Rubrique::whereNotIn('nom', ClasseurDocuments::RUBRIQUES)
            ->whereDoesntHave('documents', fn ($q) => $q->where('actif', true))
            ->update(['actif' => false]);
    }

    private function rapport(array $aSupprimer, array $parRubrique, int $sansAnnee, int $reclasses, bool $appliquer, bool $supprimer): void
    {
        $mode = $appliquer ? ($supprimer ? 'APPLIQUER + SUPPRIMER' : 'APPLIQUER') : 'DRY-RUN (aucune écriture)';
        $this->info("Mode : $mode");
        $this->line('Reclassés : '.$reclasses.' | sans année : '.$sansAnnee);
        arsort($parRubrique);
        foreach ($parRubrique as $nom => $n) {
            $this->line(sprintf('  %-42s %5d', $nom, $n));
        }
        $this->newLine();
        $this->warn('Hors-sujet détectés : '.count($aSupprimer).($appliquer && $supprimer ? ' (SUPPRIMÉS)' : ' (non supprimés)'));
        foreach ($aSupprimer as $titre) {
            $this->line('  ✗ '.$titre);
        }
    }
}
