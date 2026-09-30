<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
        h1 { text-align: center; font-size: 15px; }
        .periode { text-align: center; margin-bottom: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 5px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>État des congés — DGE</h1>
    <div class="periode">
        @if ($du || $au) Période : {{ $du ?? '…' }} au {{ $au ?? '…' }} @endif
    </div>

    <table>
        <thead>
            <tr><th>Agent</th><th>Direction</th><th>Début</th><th>Fin</th><th>Jours</th><th>Solde restant</th></tr>
        </thead>
        <tbody>
            @foreach ($conges as $c)
                <tr>
                    <td>{{ $c->agent->prenoms }} {{ $c->agent->noms }}</td>
                    <td>{{ $c->agent->direction?->code }}</td>
                    <td>{{ $c->date_debut->format('d/m/Y') }}</td>
                    <td>{{ $c->date_fin->format('d/m/Y') }}</td>
                    <td>{{ $c->nb_jours }}</td>
                    <td>{{ $c->agent->solde_conge_jours }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
