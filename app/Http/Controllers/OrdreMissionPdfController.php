<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class OrdreMissionPdfController extends Controller
{
    public function __invoke(Demande $demande)
    {
        abort_unless($demande->type === 'ordre_mission', 404);

        $demande->loadMissing('agent.direction.chef', 'agent');

        $directionId = Auth::user()->agent?->direction_id;
        abort_unless($directionId && $demande->agent->direction_id === $directionId, 403);

        $pdf = Pdf::loadView('pdf.ordre-mission', [
            'm' => $demande,
            'agent' => $demande->agent,
            'signataires' => $this->resoudreSignataires($demande),
        ]);

        return $pdf->stream("ordre-mission-{$demande->id}.pdf");
    }

    /**
     * @return array<int, array{nom: string, fonction: string}>
     */
    public function resoudreSignataires(Demande $demande): array
    {
        $codes = $demande->meta['signataires'] ?? [];
        $resolus = [];

        foreach ($codes as $code) {
            if ($code === 'dg') {
                $resolus[] = [
                    'nom' => config('dge.dg_nom'),
                    'fonction' => config('dge.dg_fonction'),
                ];
            } elseif ($code === 'directeur') {
                $chef = $demande->agent->direction?->chef;
                $resolus[] = [
                    'nom' => $chef ? $chef->nomComplet() : '',
                    'fonction' => $chef?->fonction ?: ('Directeur de '.($demande->agent->direction?->nom ?? '')),
                ];
            }
        }

        return $resolus;
    }
}
