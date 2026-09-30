<?php

namespace App\Support\Bibliotheque;

use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory;

final class ExtracteurTextePoppler implements ExtracteurTexte
{
    public function extraire(string $cheminAbsolu): string
    {
        if (! is_file($cheminAbsolu)) {
            return '';
        }
        $ext = strtolower(pathinfo($cheminAbsolu, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $res = Process::run(['pdftotext', '-enc', 'UTF-8', $cheminAbsolu, '-']);

            return $res->successful() ? trim($res->output()) : '';
        }

        if ($ext === 'doc') {
            // Ancien format Word binaire : textutil (macOS) puis antiword (Linux) en secours.
            if (PHP_OS_FAMILY === 'Darwin') {
                $res = Process::run(['textutil', '-convert', 'txt', '-stdout', $cheminAbsolu]);
                if ($res->successful()) {
                    return trim($res->output());
                }
            }
            $res = Process::run(['antiword', $cheminAbsolu]);

            return $res->successful() ? trim($res->output()) : '';
        }

        if ($ext === 'docx') {
            try {
                $doc = IOFactory::load($cheminAbsolu);
                $texte = '';
                foreach ($doc->getSections() as $section) {
                    foreach ($section->getElements() as $el) {
                        if (method_exists($el, 'getText')) {
                            $texte .= $el->getText()."\n";
                        }
                    }
                }

                return trim($texte);
            } catch (\Throwable) {
                return '';
            }
        }

        if ($ext === 'xlsx') {
            try {
                $classeur = SpreadsheetIOFactory::load($cheminAbsolu);
                $texte = '';
                foreach ($classeur->getAllSheets() as $feuille) {
                    foreach ($feuille->toArray() as $ligne) {
                        $cellules = array_filter($ligne, fn ($c) => $c !== null && $c !== '');
                        if ($cellules !== []) {
                            $texte .= implode(' | ', $cellules)."\n";
                        }
                    }
                }

                return trim($texte);
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
