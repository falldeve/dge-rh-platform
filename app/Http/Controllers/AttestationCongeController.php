<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\NombreEnLettres;
use App\Support\SignataireDocument;
use Barryvdh\DomPDF\Facade\Pdf;

class AttestationCongeController extends Controller
{
    public function __invoke(Demande $demande)
    {
        abort_unless(
            $demande->type === 'conge_annuel' && $demande->statut === Demande::STATUT_VALIDEE_RH,
            404
        );

        $demande->loadMissing('agent');

        // L'attestation de congé est validée et signée par la DRHF (pas le chef de direction).
        [$signataireNom, $signataireFonction] = SignataireDocument::pour($demande);

        $pdf = Pdf::loadView('pdf.attestation-conge', [
            'd' => $demande,
            'agent' => $demande->agent,
            'nbJoursLettres' => NombreEnLettres::convertir((int) $demande->nb_jours),
            'dateReprise' => $demande->date_fin->copy()->addDay(),
            'signataireNom' => $signataireNom,
            'signataireFonction' => $signataireFonction,
        ]);

        return $pdf->stream("attestation-conge-{$demande->id}.pdf");
    }
}
