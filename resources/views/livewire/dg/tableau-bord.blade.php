<div style="display:flex;flex-direction:column;gap:22px">
    <div>
        <h1 style="font-size:28px;font-weight:600;margin:0">Synthèse — Direction Générale</h1>
        <p style="color:var(--muted);font-size:14px;margin:4px 0 0">Vue de consultation (lecture seule).</p>
    </div>

    @php($stat = function ($t, $v, $c = 'var(--ink)') {
        return '<div class="card" style="padding:18px"><div style="font-size:13px;color:var(--muted)">'.$t.'</div><div style="font-family:\'Open Sans\',serif;font-size:30px;font-weight:600;color:'.$c.'">'.$v.'</div></div>';
    })

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px">
        {!! $stat('Congés validés', $parStatut['validee_rh'], 'var(--green-deep)') !!}
        {!! $stat('En attente DRHF', $parStatut['validee_chef'], 'var(--gold)') !!}
        {!! $stat('En attente chef', $parStatut['soumise']) !!}
        {!! $stat('Ordres de mission', $parType['ordre_mission']) !!}
    </div>

    <div class="card" style="padding:20px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Absences par direction (aujourd'hui)</h2>
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead><tr style="color:var(--muted);text-align:left;font-size:12px"><th style="padding:6px 0">Direction</th><th>Agents</th><th>En congé</th><th>Taux</th></tr></thead>
            <tbody>
                @foreach ($parDirection as $d)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:8px 0;font-weight:600">{{ $d['code'] }} — {{ $d['nom'] }}</td>
                        <td>{{ $d['agents'] }}</td>
                        <td>{{ $d['en_conge'] }}</td>
                        <td><span class="badge" style="background:{{ $d['taux'] > 0 ? '#f6ecdd' : 'var(--surface-2)' }};color:{{ $d['taux'] > 0 ? '#8a5a1e' : 'var(--muted)' }}">{{ $d['taux'] }} %</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
