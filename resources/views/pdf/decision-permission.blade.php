<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #000; line-height: 1.6; }
        .entete { text-align: center; margin-bottom: 6px; }
        .titre { text-align: center; font-weight: bold; font-size: 15px; margin: 24px 0; }
        .etoiles { text-align: center; margin-bottom: 24px; }
        .corps { text-align: justify; margin: 0 10px; }
        .signature { margin-top: 60px; text-align: right; margin-right: 30px; }
    </style>
</head>
<body>
    <div class="entete">
        RÉPUBLIQUE DU SÉNÉGAL<br>
        Un Peuple – Un But – Une Foi<br>
        MINISTÈRE DE L'INTÉRIEUR ET DE LA SÉCURITÉ PUBLIQUE<br>
        DIRECTION GÉNÉRALE DES ÉLECTIONS
    </div>

    <div class="titre">DÉCISION — PERMISSION D'ABSENCE</div>
    <div class="etoiles">*******************</div>

    <div class="corps">
        Je soussigné, {{ $signataireNom }}, {{ $signataireFonction }},
        accorde à {{ $agent->profession ? $agent->profession.' ' : '' }}{{ $agent->prenoms }} {{ $agent->noms }},
        matricule de solde n° {{ $agent->matricule ?? '—' }},
        une permission d'absence de {{ $nbJoursLettres }} ({{ $d->nb_jours }}) jour(s),
        du {{ $d->date_debut->locale('fr')->translatedFormat('l j F Y') }}
        au {{ $d->date_fin->locale('fr')->translatedFormat('l j F Y') }}.
        @if ($d->motif)
            <br><br>Motif : {{ $d->motif }}.
        @endif
        <br><br>
        En foi de quoi, la présente décision lui est délivrée pour servir et valoir ce que de droit.
    </div>

    <div class="signature">
        Dakar, le {{ now()->locale('fr')->translatedFormat('j F Y') }}<br><br>
        {{ $signataireFonction }}<br><br><br>
        <strong>{{ $signataireNom }}</strong>
    </div>
</body>
</html>
