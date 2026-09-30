<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Support\Bibliotheque\Chunker;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class OcrBibliotheque extends Command
{
    protected $signature = 'bibliotheque:ocr {--force-ocr} {--limit=} {--langues=fra+eng} {--timeout=1800}';

    protected $description = 'OCR les PDF scannés (source fichier sans texte) puis les indexe (RAG).';

    public function handle(ExtracteurTexte $extracteur, Chunker $chunker): int
    {
        $query = Document::where('source', 'fichier')
            ->whereNotNull('fichier_path')
            ->whereDoesntHave('chunks');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $docs = $query->get();
        $total = $docs->count();
        $this->info("Scans à traiter : $total");

        $langues = (string) $this->option('langues');
        $forceOcr = (bool) $this->option('force-ocr');

        $ocrises = 0;
        $indexes = 0;
        $vides = 0;
        $erreurs = 0;
        $i = 0;

        foreach ($docs as $doc) {
            $i++;
            $chemin = Storage::disk('public')->path($doc->fichier_path);
            if (! is_file($chemin)) {
                $this->warn("  ✗ [$i/$total] fichier absent : {$doc->fichier_path}");
                $erreurs++;

                continue;
            }

            $tmp = $chemin.'.ocr.pdf';

            $args = ['ocrmypdf', '-l', $langues, '--optimize', '1', '--quiet'];
            $args[] = $forceOcr ? '--force-ocr' : '--skip-text';
            $args[] = $chemin;
            $args[] = $tmp;

            $process = new Process($args);
            $process->setTimeout((float) $this->option('timeout')); // secondes max / fichier

            try {
                $process->run();
            } catch (\Throwable $e) {
                @unlink($tmp);
                $this->warn("  ✗ [$i/$total] {$doc->titre} : ".$e->getMessage());
                $erreurs++;

                continue;
            }

            // Codes ocrmypdf : 0 = ok ; 6 = déjà du texte (--skip-text, rien à faire).
            $code = $process->getExitCode();
            if (! in_array($code, [0, 6], true) || ! is_file($tmp)) {
                @unlink($tmp);
                $this->warn("  ✗ [$i/$total] {$doc->titre} : ocrmypdf code $code");
                $erreurs++;

                continue;
            }

            // Remplace l'original par la version océrisée.
            rename($tmp, $chemin);
            $ocrises++;

            // Ré-extraction + ré-indexation.
            try {
                $texte = $extracteur->extraire($chemin);
                if (mb_strlen($texte) > 800) {
                    $doc->chunks()->delete();
                    foreach ($chunker->decouper($texte) as $ordre => $morceau) {
                        $doc->chunks()->create(['ordre' => $ordre, 'contenu' => $morceau]);
                    }
                    $indexes++;
                    $this->line("  ✓ [$i/$total] {$doc->titre} — indexé");
                } else {
                    $vides++;
                    $this->line("  · [$i/$total] {$doc->titre} — OCR sans texte exploitable");
                }
            } catch (\Throwable $e) {
                $erreurs++;
                $this->warn("  ✗ [$i/$total] {$doc->titre} : extraction ".$e->getMessage());
            }
        }

        $this->info("Terminé — océrisés : $ocrises, indexés : $indexes, sans texte : $vides, erreurs : $erreurs.");

        return self::SUCCESS;
    }
}
