<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #14201b; margin: 0; }
        .head { text-align: center; border-bottom: 2px solid #0d3f28; padding-bottom: 6px; margin-bottom: 10px; }
        .head .rep { font-size: 9px; letter-spacing: .5px; }
        .head .org { font-size: 14px; font-weight: bold; color: #0d3f28; margin-top: 2px; }
        .title { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 1px; margin: 8px 0 12px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 6px; border: 1px solid #999; }
        .info .lbl { background: #eef3ef; font-weight: bold; width: 22%; }
        .sect { font-weight: bold; color: #0d3f28; margin: 12px 0 5px; font-size: 11px; text-transform: uppercase; }
        .grid td { padding: 3px 5px; width: 33%; vertical-align: top; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #333; text-align: center; line-height: 10px; font-size: 9px; margin-right: 5px; }
        .obs { border: 1px solid #999; padding: 8px; min-height: 40px; }
        .sign { margin-top: 22px; text-align: right; }
        .sign .who { border-top: 1px solid #333; display: inline-block; padding-top: 4px; min-width: 200px; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <div class="rep">RÉPUBLIQUE DU SÉNÉGAL — Un Peuple · Un But · Une Foi</div>
        <div class="org">Direction Générale des Élections</div>
    </div>

    <div class="title">Fiche de Ventilation</div>

    <table class="info">
        <tr><td class="lbl">N° courrier</td><td>{{ $courrier->numero }}</td><td class="lbl">Date d'arrivée</td><td>{{ $courrier->date_arrivee->format('d/m/Y') }}</td></tr>
        <tr><td class="lbl">Expéditeur</td><td>{{ $courrier->expediteur }}</td><td class="lbl">Date de départ</td><td>{{ $courrier->date_depart?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="lbl">Objet</td><td colspan="3">{{ $courrier->objet }}</td></tr>
    </table>

    <div class="sect">Destinataires{{ $imp->niveau === 'direction' && $imp->entiteSource ? ' — Divisions de '.$imp->entiteSource->code : '' }}</div>
    <table class="grid">
        @foreach ($candidats->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $e)
                    <td><span class="box">{{ in_array($e->id, $coches) ? 'X' : '' }}</span>{{ $e->code }} — {{ $e->nom }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="sect">Soit transmis</div>
    <table class="grid">
        @foreach (collect($mentions)->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $code => $libelle)
                    <td><span class="box">{{ in_array($code, $imp->mentions ?? []) ? 'X' : '' }}</span>{{ $libelle }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="sect">Observations</div>
    <div class="obs">{{ $imp->observations }}</div>

    <div class="sign">
        <div class="who">
            {{ $imp->signataire_nom ?: config('dge.dg_nom') }}<br>
            <span style="font-size:9px">{{ $imp->niveau === 'dg' ? config('dge.dg_fonction') : 'Le Directeur' }}</span>
        </div>
    </div>

    @if (! empty($scanImage))
        <div style="page-break-before:always">
            <div class="sect" style="margin-top:0">Courrier scanné</div>
            <img src="{{ $scanImage }}" style="max-width:100%; max-height:250mm;">
        </div>
    @endif
</body>
</html>
