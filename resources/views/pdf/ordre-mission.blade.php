<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #000; }
        .entete { text-align: center; margin-bottom: 8px; }
        .titre { text-align: center; font-weight: bold; font-size: 16px; letter-spacing: 1px; margin: 18px 0; }
        .ligne { margin: 10px 0; border-bottom: 1px dotted #333; padding-bottom: 2px; }
        .label { font-weight: bold; }
        .signatures { margin-top: 50px; width: 100%; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding-top: 10px; }
        .lieu { margin-top: 40px; text-align: right; }
    </style>
</head>
<body>
    <div class="entete">
        RÉPUBLIQUE DU SÉNÉGAL<br>
        Un Peuple – Un But – Une Foi<br>
        MINISTÈRE DE L'INTÉRIEUR ET DE LA SÉCURITÉ PUBLIQUE<br>
        DIRECTION GÉNÉRALE DES ÉLECTIONS
    </div>

    <div class="titre">ORDRE DE MISSION</div>

    <div class="ligne"><span class="label">Prénom et nom :</span> {{ $agent->prenoms }} {{ $agent->noms }}
        &nbsp;&nbsp;<span class="label">Matricule :</span> {{ $agent->matricule ?? '' }}</div>
    <div class="ligne"><span class="label">Fonction :</span> {{ $agent->fonction ?? '' }}</div>
    <div class="ligne"><span class="label">Indice :</span> {{ $m->meta['indice'] ?? '' }}
        &nbsp;&nbsp;<span class="label">Groupe :</span> {{ $m->meta['groupe'] ?? '' }}</div>
    <div class="ligne"><span class="label">Se rendre à :</span> {{ $m->meta['destination'] ?? '' }}</div>
    <div class="ligne"><span class="label">Motif :</span> {{ $m->motif ?? '' }}</div>
    <div class="ligne"><span class="label">Date de départ :</span> {{ $m->date_debut->format('d/m/Y') }}
        &nbsp;&nbsp;<span class="label">Date de retour :</span> {{ $m->date_fin->format('d/m/Y') }}</div>
    <div class="ligne"><span class="label">Moyen de transport :</span> {{ $m->meta['moyen_transport'] ?? '' }}</div>
    <div class="ligne"><span class="label">Imputation budgétaire des frais de transport / indemnités :</span> {{ $m->meta['imputation'] ?? '' }}</div>
    <div class="ligne"><span class="label">Chapitre :</span> {{ $m->meta['chapitre'] ?? '' }}
        &nbsp;&nbsp;<span class="label">Article :</span> {{ $m->meta['article'] ?? '' }}</div>

    <div class="lieu">Dakar, le {{ now()->format('d/m/Y') }}</div>

    <table class="signatures">
        <tr>
            @foreach ($signataires as $s)
                <td>
                    <div>{{ $s['fonction'] }}</div>
                    <div style="height:40px;"></div>
                    <div><strong>{{ $s['nom'] }}</strong></div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
