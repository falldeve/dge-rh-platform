<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\Chunker;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\ParseurLoiMd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImporterBibliotheque extends Command
{
    protected $signature = 'bibliotheque:importer {--loi=} {--dossier=} {--exclure=} {--exclure-chemin=} {--pdf-seul}';

    protected $description = 'Importe le corpus (loi.md + dossier) dans la bibliothèque électorale.';

    /** Rubriques socles (loi.md) + taxonomie DGE (dossiers lettrés A–L). */
    private const RUBRIQUES = [
        'Code électoral',
        'Constitution',
        'Textes législatifs & réglementaires',
        'Textes juridiques',
        'Décrets & règlements',
        'Ordonnances',
        'Élections & résultats',
        'Rapports CENA',
        'Comité de veille',
        'Comptes rendus & réunions',
        'Contentieux électoral',
        'Décisions Conseil constitutionnel',
        'Audit du fichier électoral',
        'Investitures',
        'Guides pratiques & bréviaires',
        'Rapports divers',
        'Documents traités',
        'Textes historiques (JO 1960-1982)',
        'Divers',
        'Archives',
    ];

    /** @var array<string,int> hash md5 des fichiers déjà importés ce run (dédup) */
    private array $vus = [];

    public function handle(ExtracteurTexte $extracteur, Chunker $chunker, ParseurLoiMd $parseur): int
    {
        $rubriques = $this->seedRubriques();

        if ($loi = $this->option('loi')) {
            $this->importerLoiMd($loi, $rubriques['Textes historiques (JO 1960-1982)'], $parseur, $chunker);
        }

        if ($dossier = $this->option('dossier')) {
            $this->importerDossier($dossier, $rubriques, $extracteur, $chunker);
        }

        $this->info('Import terminé.');

        return self::SUCCESS;
    }

    /** @return array<string,int>  nom => id */
    private function seedRubriques(): array
    {
        $ids = [];
        foreach (self::RUBRIQUES as $i => $nom) {
            $r = Rubrique::updateOrCreate(
                ['nom' => $nom],
                ['slug' => Str::slug($nom), 'ordre' => $i, 'actif' => true],
            );
            $ids[$nom] = $r->id;
        }

        return $ids;
    }

    private function importerLoiMd(string $chemin, int $rubriqueId, ParseurLoiMd $parseur, Chunker $chunker): void
    {
        if (! is_file($chemin)) {
            $this->warn("loi.md introuvable: $chemin");

            return;
        }

        foreach ($parseur->sections((string) file_get_contents($chemin)) as $s) {
            $doc = Document::updateOrCreate(
                ['titre' => $s['titre']],
                [
                    'rubrique_id' => $rubriqueId,
                    'type' => $this->typeDepuisTitre($s['titre']),
                    'source' => 'texte',
                    'contenu' => $s['contenu'],
                    'reference' => $this->referenceDepuisTitre($s['titre']),
                    'actif' => true,
                ],
            );
            $this->rechunker($doc, $s['contenu'], $chunker);
        }
        $this->line('loi.md importé.');
    }

    private function importerDossier(string $dossier, array $rubriques, ExtracteurTexte $extracteur, Chunker $chunker): void
    {
        $dossier = rtrim($dossier, '/\\');
        if (! is_dir($dossier)) {
            $this->warn("dossier introuvable: $dossier");

            return;
        }

        $pdfSeul = (bool) $this->option('pdf-seul');
        $extensions = $pdfSeul ? ['pdf'] : ['pdf', 'docx', 'doc'];

        // Dossiers de premier niveau à ignorer (dumps perso / non électoraux).
        $exclure = array_filter(array_map(
            fn ($x) => $this->normaliser(trim($x)),
            explode(',', (string) $this->option('exclure')),
        ));

        // Segments à ignorer où qu'ils apparaissent dans le chemin (ex. « resultat »).
        $exclureChemin = array_filter(array_map(
            fn ($x) => $this->normaliser(trim($x)),
            explode(',', (string) $this->option('exclure-chemin')),
        ));

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS));

        $importes = 0;
        $ignores = 0;
        $doublons = 0;
        $erreurs = 0;

        foreach ($it as $fichier) {
            /** @var \SplFileInfo $fichier */
            $ext = strtolower($fichier->getExtension());
            $nom = $fichier->getFilename();
            if (! in_array($ext, $extensions, true) || str_starts_with($nom, '~$') || str_starts_with($nom, '.')) {
                continue;
            }

            $rel = ltrim(str_replace($dossier, '', $fichier->getPathname()), '/\\');
            $segments = preg_split('#[/\\\\]#', $rel);
            $premier = count($segments) > 1 ? $this->normaliser($segments[0]) : '';
            $relNorm = $this->normaliser($rel);

            // Exclusion des dossiers de premier niveau (sous-chaîne).
            if ($premier !== '' && $this->contientUn($premier, $exclure)) {
                $ignores++;

                continue;
            }

            // Exclusion par segment n'importe où dans le chemin (ex. résultats).
            if ($this->contientUn($relNorm, $exclureChemin)) {
                $ignores++;

                continue;
            }

            // Dédup : même contenu binaire déjà importé ce run.
            $hash = @md5_file($fichier->getPathname());
            if ($hash !== false && isset($this->vus[$hash])) {
                $doublons++;

                continue;
            }

            try {
                $titre = trim(pathinfo($nom, PATHINFO_FILENAME));
                $rubrique = $this->rubriquePourDossier($premier, $titre);
                $type = $this->typeDepuisDossierOuTitre($premier, $titre);

                $contenu = (string) file_get_contents($fichier->getPathname());
                $destination = 'bibliotheque/'.Str::slug($rel).'.'.$ext;
                Storage::disk('public')->put($destination, $contenu);

                $doc = Document::updateOrCreate(
                    ['fichier_path' => $destination],
                    [
                        'titre' => $titre,
                        'rubrique_id' => $rubriques[$rubrique],
                        'type' => $type,
                        'source' => 'fichier',
                        'reference' => $this->referenceDepuisTitre($titre),
                        'actif' => true,
                    ],
                );

                $texte = $extracteur->extraire($fichier->getPathname());
                if (mb_strlen($texte) > 800) {
                    $this->rechunker($doc, $texte, $chunker);
                } else {
                    $doc->chunks()->delete();
                }

                if ($hash !== false) {
                    $this->vus[$hash] = $doc->id;
                }
                $importes++;
                if ($importes % 20 === 0) {
                    $this->line("  … $importes importés");
                }
            } catch (\Throwable $e) {
                $erreurs++;
                $this->warn("  ✗ $rel : ".$e->getMessage());
            }
        }

        $this->line("dossier importé — $importes documents, $doublons doublons, $ignores ignorés, $erreurs erreurs.");
    }

    private function rechunker(Document $doc, string $texte, Chunker $chunker): void
    {
        $doc->chunks()->delete();
        foreach ($chunker->decouper($texte) as $i => $morceau) {
            $doc->chunks()->create(['ordre' => $i, 'contenu' => $morceau]);
        }
    }

    /** Normalise un nom de dossier : minuscule, sans accents, espaces compactés. */
    private function normaliser(string $s): string
    {
        $s = Str::lower(Str::ascii($s));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Vrai si $sujet contient au moins un des jetons (déjà normalisés). */
    private function contientUn(string $sujet, array $jetons): bool
    {
        foreach ($jetons as $j) {
            if ($j !== '' && str_contains($sujet, $j)) {
                return true;
            }
        }

        return false;
    }

    /** Mappe un dossier de premier niveau (taxonomie DGE) vers une rubrique. */
    private function rubriquePourDossier(string $premier, string $titre): string
    {
        if ($premier === '') {
            // Fichier à la racine : classer par mot-clé du titre.
            return $this->classerParTitre($titre);
        }

        $t = $this->normaliser($titre);

        return match (true) {
            str_contains($premier, 'conseil constitutionnel') => 'Décisions Conseil constitutionnel',
            str_contains($premier, 'constitution') => 'Constitution',
            str_contains($premier, 'comite de veille') => 'Comité de veille',
            str_contains($premier, 'cena') => 'Rapports CENA',
            str_contains($premier, 'contentieux') => 'Contentieux électoral',
            str_contains($premier, 'textes legislatifs') || str_contains($premier, 'textes de lois') || str_contains($premier, 'decret et loi') => 'Textes législatifs & réglementaires',
            str_contains($premier, 'textes juridiques') => 'Textes juridiques',
            str_contains($premier, 'audit') => 'Audit du fichier électoral',
            str_contains($premier, 'investiture') || str_contains($premier, 'geo invest') => 'Investitures',
            str_contains($premier, 'guides') || str_contains($premier, 'breviaire') || str_contains($premier, 'formation') => 'Guides pratiques & bréviaires',
            // Comptes rendus / réunions (dossier ou titre).
            str_contains($premier, 'compte rendu') || str_contains($premier, 'cr reunion') || str_contains($premier, 'coordination')
                || str_contains($premier, 'cpdn') || str_contains($premier, 'ctrce') || str_contains($premier, 'ctcre') || str_contains($premier, 'rele')
                || str_contains($t, 'compte rendu') || str_contains($t, 'reunion') || str_contains($t, 'proces verbal') || str_contains($t, 'pv ') => 'Comptes rendus & réunions',
            str_contains($premier, 'referendum') => 'Élections & résultats',
            str_contains($premier, 'observation') => 'Rapports divers',
            str_contains($premier, 'communique') => 'Documents traités',
            str_contains($premier, 'rapport') || str_contains($premier, 'travaux') => 'Rapports divers',
            str_contains($premier, 'election') || str_contains($premier, 'municipal') || str_contains($premier, 'locales') || str_contains($premier, 'presidentiel') || str_contains($premier, 'revision') => 'Élections & résultats',
            str_contains($premier, 'document') || str_contains($premier, 'inserer') => 'Documents traités',
            str_contains($premier, 'divers') => 'Divers',
            default => $this->classerParTitre($titre),
        };
    }

    private function classerParTitre(string $titre): string
    {
        $t = $this->normaliser($titre);

        return match (true) {
            str_contains($t, 'compte rendu') || str_contains($t, 'reunion') || str_contains($t, 'proces verbal') => 'Comptes rendus & réunions',
            str_contains($t, 'cena') => 'Rapports CENA',
            str_contains($t, 'code electoral') => 'Code électoral',
            str_contains($t, 'constitution') => 'Constitution',
            str_contains($t, 'decret') => 'Décrets & règlements',
            str_contains($t, 'audit') => 'Audit du fichier électoral',
            str_contains($t, 'rapport') || str_contains($t, 'mission') => 'Rapports divers',
            str_contains($t, 'presidentielle') || str_contains($t, 'legislative') || str_contains($t, 'referendum') || str_contains($t, 'resultat') || str_contains($t, 'election') => 'Élections & résultats',
            default => 'Divers',
        };
    }

    private function typeDepuisDossierOuTitre(string $premier, string $titre): string
    {
        return match (true) {
            str_contains($premier, 'cena') || str_contains($premier, 'rapport') || str_contains($premier, 'comite de veille') || str_contains($premier, 'audit') => 'rapport',
            str_contains($premier, 'guides') || str_contains($premier, 'breviaire') => 'guide',
            str_contains($premier, 'textes legislatifs') || str_contains($premier, 'textes juridiques') => 'reglement',
            str_contains($premier, 'constitution') => 'loi',
            str_contains($premier, 'election') => 'archive',
            default => $this->typeDepuisTitre($titre),
        };
    }

    private function typeDepuisTitre(string $titre): string
    {
        $t = Str::lower($titre);

        return match (true) {
            str_contains($t, 'ordonnance') => 'ordonnance',
            str_contains($t, 'décret'), str_contains($t, 'decret') => 'decret',
            str_contains($t, 'loi') => 'loi',
            str_contains($t, 'rapport'), str_contains($t, 'mission'), str_contains($t, 'compte rendu'), str_contains($t, 'réunion'), str_contains($t, 'reunion') => 'rapport',
            default => 'autre',
        };
    }

    private function referenceDepuisTitre(string $titre): ?string
    {
        return preg_match('/n[°o]\s?[\d\-\/]+/ui', $titre, $m) ? trim($m[0]) : null;
    }
}
