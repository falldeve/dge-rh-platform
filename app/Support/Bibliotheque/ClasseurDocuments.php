<?php

namespace App\Support\Bibliotheque;

use Illuminate\Support\Str;

class ClasseurDocuments
{
    /** Rubriques canoniques, dans l'ordre d'affichage. */
    public const RUBRIQUES = [
        'Constitution',
        'Code électoral',
        'Lois',
        'Ordonnances',
        'Décrets',
        'Arrêtés',
        'Circulaires & instructions',
        'Décisions du Conseil constitutionnel',
        'Guides pratiques & bréviaires',
        'Rapports CENA',
        'Audit du fichier électoral',
        'Comité de veille',
        "Missions d'observation",
        'Rapports divers',
        'Comptes rendus & réunions',
        'Communiqués & discours',
        'Investitures',
        'Données & cartes électorales',
        'Textes historiques (JO 1960-1982)',
    ];

    private const FINANCE = [
        'budget', 'paiement', 'etat financier', 'financ', 'salaire', 'indemnit',
        'facture', 'bon de commande', 'decharge', 'certificat de cessation', 'cessation',
        'comptable', 'depense', 'orsec', 'acte de donation', 'repertoire telephonique',
        'curriculum', 'cv ',
    ];

    private const SIGNAUX = [
        'election', 'electora', 'scrutin', 'vote', 'parrainage', 'candidat',
        'referendum', 'cena', 'recensement', 'parti', 'carte electeur',
        'liste electorale', 'code electoral', 'electeur', 'campagne',
        'bureau de vote', 'depute', 'circonscription',
        'certificat administratif', 'compte rendu',
    ];

    public function annee(?string $titre, ?string $reference = null, ?\DateTimeInterface $dateDocument = null): ?int
    {
        if ($dateDocument !== null) {
            return (int) $dateDocument->format('Y');
        }

        $max = (int) date('Y') + 1;
        $meilleure = null;
        foreach ([$reference, $titre] as $source) {
            if ($source === null) {
                continue;
            }
            if (preg_match_all('/\b(1[89]\d{2}|20\d{2})\b/', $source, $m)) {
                foreach ($m[1] as $a) {
                    $a = (int) $a;
                    if ($a >= 1840 && $a <= $max && ($meilleure === null || $a > $meilleure)) {
                        $meilleure = $a;
                    }
                }
            }
        }

        return $meilleure;
    }

    public function estHorsSujet(string $titre): bool
    {
        $t = $this->n($titre);
        $finance = false;
        foreach (self::FINANCE as $mot) {
            if (str_contains($t, $mot)) {
                $finance = true;
                break;
            }
        }
        if (! $finance) {
            return false;
        }
        foreach (self::SIGNAUX as $mot) {
            if (str_contains($t, $mot)) {
                return false;
            }
        }

        return true;
    }

    public function rubrique(string $titre): string
    {
        $t = $this->n($titre);

        return match (true) {
            str_contains($t, 'constitution') => 'Constitution',
            str_contains($t, 'code electoral') => 'Code électoral',
            str_contains($t, 'ordonnance') => 'Ordonnances',
            str_contains($t, 'decret') => 'Décrets',
            str_contains($t, 'arrete') => 'Arrêtés',
            str_contains($t, 'circulaire') || str_contains($t, 'instruction') || str_contains($t, 'note de service') => 'Circulaires & instructions',
            str_contains($t, 'conseil constitutionnel') || str_contains($t, 'decision n') || str_contains($t, 'avis n') => 'Décisions du Conseil constitutionnel',
            str_contains($t, 'loi ') || str_contains($t, 'loi n') || str_contains($t, 'loi organique') => 'Lois',
            str_contains($t, 'guide') || str_contains($t, 'breviaire') || str_contains($t, 'manuel') || str_contains($t, 'formation') => 'Guides pratiques & bréviaires',
            str_contains($t, 'communique') || str_contains($t, 'discours') || str_contains($t, 'allocution') || str_contains($t, 'point de presse') => 'Communiqués & discours',
            str_contains($t, 'cena') => 'Rapports CENA',
            str_contains($t, 'audit') || str_contains($t, 'mafe') => 'Audit du fichier électoral',
            str_contains($t, 'comite de veille') => 'Comité de veille',
            str_contains($t, 'observation') => "Missions d'observation",
            str_contains($t, 'compte rendu') || str_contains($t, 'reunion') || str_contains($t, 'proces verbal') || str_contains($t, 'pv ') || str_contains($t, 'coordination') || str_contains($t, 'cpdn') || str_contains($t, 'ctrce') => 'Comptes rendus & réunions',
            str_contains($t, 'investiture') || str_contains($t, 'parrainage') || str_contains($t, 'cautionnement') => 'Investitures',
            str_contains($t, 'carte electorale') || str_contains($t, 'liste des partis') || str_contains($t, 'repartition') || str_contains($t, 'bureaux de vote') || str_contains($t, 'electeurs') => 'Données & cartes électorales',
            str_contains($t, 'rapport') || str_contains($t, 'mission') || str_contains($t, 'bilan') || str_contains($t, 'evaluation') => 'Rapports divers',
            default => 'Données & cartes électorales',
        };
    }

    public function typePourRubrique(string $rubrique): string
    {
        return match ($rubrique) {
            'Constitution', 'Code électoral', 'Lois' => 'loi',
            'Ordonnances' => 'ordonnance',
            'Décrets' => 'decret',
            'Arrêtés' => 'reglement',
            'Circulaires & instructions' => 'circulaire',
            'Guides pratiques & bréviaires' => 'guide',
            'Rapports CENA', 'Audit du fichier électoral', 'Comité de veille',
            "Missions d'observation", 'Rapports divers', 'Comptes rendus & réunions' => 'rapport',
            default => 'archive',
        };
    }

    public function estDonneeSupprimable(string $titre): bool
    {
        $t = $this->n($titre);

        // Ne jamais supprimer un document de référence (rapport, audit, texte, communiqué…).
        foreach (['rapport', 'audit', 'mission', 'comite', 'communique', 'discours',
                  'loi', 'decret', 'arrete', 'code', 'guide', 'textes', 'refonte', 'lettre'] as $ref) {
            if (str_contains($t, $ref)) {
                return false;
            }
        }

        foreach (['carte electorale', 'fichier electoral', 'fichier general des electeurs',
                  'repartition des electeurs', 'lieux et bureaux de vote'] as $mot) {
            if (str_contains($t, $mot)) {
                return true;
            }
        }

        return false;
    }

    /** Signaux qui protègent TOUJOURS un document de la purge junk. */
    private const JUNK_PROTEGE = [
        'election', 'electora', 'scrutin', 'referendum', 'loi ', 'decret', 'arrete',
        'ordonnance', 'code electoral', 'rapport', 'cena', 'audit', 'journal officiel',
        'arret', 'cour d', 'conseil', 'coalition', 'parti', 'decoupage', 'accredit',
        'observ', 'communiqu', 'discours', 'recensement', 'parrainage', 'candidat',
        'resultat', 'textes', 'circonscription', 'contentieux', 'senat', 'hcct',
        'constitution', 'refonte', 'carte electorale', 'commune', 'processus electoral',
    ];

    /** Junk haute-confiance (scans bruts, doublons, codes régionaux, RH), hors documents à signal électoral. */
    public function estJunk(string $titre): bool
    {
        $t = $this->n($titre);

        foreach (self::JUNK_PROTEGE as $s) {
            if (str_contains($t, $s)) {
                return false;
            }
        }

        return (bool) preg_match('/^(scan|img|image|numeris|new doc|nouveau doc|document \d|doc ?\d|copie|sans titre|photo)/', $t)
            || (bool) preg_match('/\[\d\]|_le_|_du_|_de_|ministr\d/', $t)
            || (bool) preg_match('/^\d{1,2}\s+[a-z]/', $t)
            || (bool) preg_match('/demande d.?emploi|planning|conge|curriculum|statut civil|inscription civil|amicale|^famille /', $t)
            || str_contains($t, 'texte a supprimer');
    }

    private function n(string $s): string
    {
        $s = Str::lower(Str::ascii($s));

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
