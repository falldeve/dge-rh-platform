<?php

namespace App\Http\Controllers;

use App\Models\Entite;
use App\Models\Imputation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class FicheVentilationPdfController extends Controller
{
    public function __invoke(Imputation $imputation)
    {
        Gate::authorize('voir', $imputation->courrier);

        $imputation->loadMissing('courrier', 'destinataires', 'entiteSource');

        // Candidats affichés sur la fiche (cases à cocher) selon le niveau.
        if ($imputation->niveau === 'direction' && $imputation->entite_source_id) {
            $candidats = Entite::where('type', 'division')->where('parent_id', $imputation->entite_source_id)->orderBy('code')->get();
        } else {
            $candidats = Entite::whereIn('type', ['direction', 'service'])->orderBy('type')->orderBy('code')->get();
        }

        $scanPath = $imputation->courrier->scan_path
            ? Storage::disk('public')->path($imputation->courrier->scan_path)
            : null;
        $scanExiste = $scanPath && is_file($scanPath);
        $ext = $scanExiste ? strtolower(pathinfo($scanPath, PATHINFO_EXTENSION)) : null;
        $scanEstImage = in_array($ext, ['jpg', 'jpeg', 'png'], true);

        // Scan image → intégré directement dans le PDF de la fiche (page suivante), rendu robuste par dompdf.
        $ficheBytes = Pdf::loadView('pdf.fiche-ventilation', [
            'imp' => $imputation,
            'courrier' => $imputation->courrier,
            'candidats' => $candidats,
            'coches' => $imputation->destinataires->pluck('id')->all(),
            'mentions' => Imputation::MENTIONS,
            'scanImage' => $scanEstImage ? $this->dataUri($scanPath, $ext) : null,
        ])->output();

        $filename = "fiche-ventilation-{$imputation->id}.pdf";

        // Scan PDF → fusion des pages (la fiche fait office de couverture, puis le courrier scanné).
        if ($scanExiste && $ext === 'pdf') {
            try {
                return $this->pdfResponse($this->fusionner($ficheBytes, $scanPath), $filename);
            } catch (\Throwable $e) {
                // Scan illisible par FPDI (PDF > 1.4, corrompu…) : on retombe sur la fiche seule.
                report($e);
            }
        }

        return $this->pdfResponse($ficheBytes, $filename);
    }

    /** data: URI base64 d'une image pour intégration dans le HTML dompdf. */
    private function dataUri(string $path, string $ext): string
    {
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    /** Fusionne la fiche (bytes PDF) puis les pages du scan PDF en un seul document. */
    private function fusionner(string $ficheBytes, string $scanPath): string
    {
        $fpdi = new Fpdi();

        $tmp = tempnam(sys_get_temp_dir(), 'fiche').'.pdf';
        file_put_contents($tmp, $ficheBytes);
        try {
            $this->importerPdf($fpdi, $tmp);
        } finally {
            @unlink($tmp);
        }

        $this->importerPdf($fpdi, $scanPath);

        return $fpdi->Output('S');
    }

    /** Importe toutes les pages d'un PDF source dans le document FPDI. */
    private function importerPdf(Fpdi $fpdi, string $path): void
    {
        $pages = $fpdi->setSourceFile($path);
        for ($i = 1; $i <= $pages; $i++) {
            $tpl = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($tpl);
        }
    }

    private function pdfResponse(string $bytes, string $filename)
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
