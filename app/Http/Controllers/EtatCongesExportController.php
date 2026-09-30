<?php

namespace App\Http\Controllers;

use App\Support\EtatCongesQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EtatCongesExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $conges = EtatCongesQuery::pour(
            $request->integer('direction') ?: null,
            $request->query('du'),
            $request->query('au'),
        );

        return response()->streamDownload(function () use ($conges) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Agent', 'Direction', 'Debut', 'Fin', 'Jours', 'Solde restant']);
            foreach ($conges as $c) {
                fputcsv($out, [
                    $c->agent->prenoms.' '.$c->agent->noms,
                    $c->agent->direction?->code,
                    $c->date_debut->format('Y-m-d'),
                    $c->date_fin->format('Y-m-d'),
                    $c->nb_jours,
                    $c->agent->solde_conge_jours,
                ]);
            }
            fclose($out);
        }, 'etat-conges.csv', ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request)
    {
        $conges = EtatCongesQuery::pour(
            $request->integer('direction') ?: null,
            $request->query('du'),
            $request->query('au'),
        );

        $pdf = Pdf::loadView('pdf.etat-conges', [
            'conges' => $conges,
            'du' => $request->query('du'),
            'au' => $request->query('au'),
        ]);

        return $pdf->stream('etat-conges.pdf');
    }
}
