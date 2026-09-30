<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\NombreEnLettres;
use App\Support\SignataireDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class DocumentDemandeController extends Controller
{
    /**
     * Document officiel d'une demande validée (attestation de congé ou décision
     * de permission). Accessible au propriétaire de la demande ou à la DRHF.
     */
    public function __invoke(Demande $demande)
    {
        $demande->loadMissing('agent.user', 'agent.direction.chef');

        $user = Auth::user();
        $estProprietaire = $demande->agent && $demande->agent->user_id === $user->id;
        abort_unless($user->isAdminRh() || $estProprietaire, 403);

        abort_unless($demande->statut === Demande::STATUT_VALIDEE_RH, 404);

        [$signataireNom, $signataireFonction] = SignataireDocument::pour($demande);

        $commun = [
            'd' => $demande,
            'agent' => $demande->agent,
            'nbJoursLettres' => NombreEnLettres::convertir((int) $demande->nb_jours),
            'signataireNom' => $signataireNom,
            'signataireFonction' => $signataireFonction,
        ];

        if ($demande->type === 'conge_annuel') {
            $pdf = Pdf::loadView('pdf.attestation-conge', $commun + [
                'dateReprise' => $demande->date_fin->copy()->addDay(),
            ]);

            return $pdf->stream("attestation-conge-{$demande->id}.pdf");
        }

        if ($demande->type === 'permission') {
            $pdf = Pdf::loadView('pdf.decision-permission', $commun);

            return $pdf->stream("decision-permission-{$demande->id}.pdf");
        }

        abort(404);
    }
}
