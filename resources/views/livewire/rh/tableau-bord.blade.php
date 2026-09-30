<div style="display:flex;flex-direction:column;gap:22px">
    <h1 style="font-size:28px;font-weight:600;margin:0">Tableau de bord — DRHF</h1>

    @php($stat = function ($t, $v, $c = 'var(--ink)') {
        return '<div class="card" style="padding:18px"><div style="font-size:13px;color:var(--muted)">'.$t.'</div><div style="font-family:\'Open Sans\',serif;font-size:30px;font-weight:600;color:'.$c.'">'.$v.'</div></div>';
    })

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px">
        {!! $stat('En attente DRHF', $enAttenteRh, 'var(--gold)') !!}
        {!! $stat('Soumises (attente chef)', $parStatut['soumise']) !!}
        {!! $stat('Congés validés', $parStatut['validee_rh'], 'var(--green-deep)') !!}
        {!! $stat('Refusées', $parStatut['refusee'], '#b4341f') !!}
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
        <div class="card" style="padding:20px">
            <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Par type de demande</h2>
            <ul style="list-style:none;padding:0;margin:0;font-size:14px;display:flex;flex-direction:column;gap:8px">
                <li style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:6px"><span>Congé annuel</span><strong>{{ $parType['conge_annuel'] }}</strong></li>
                <li style="display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:6px"><span>Permission</span><strong>{{ $parType['permission'] }}</strong></li>
                <li style="display:flex;justify-content:space-between"><span>Ordre de mission</span><strong>{{ $parType['ordre_mission'] }}</strong></li>
            </ul>
        </div>

        <div class="card" style="padding:20px">
            <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Absences par direction (aujourd'hui)</h2>
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead><tr style="color:var(--muted);text-align:left;font-size:12px"><th style="padding:6px 0">Direction</th><th>Agents</th><th>En congé</th><th>Taux</th></tr></thead>
                <tbody>
                    @foreach ($parDirection as $d)
                        <tr style="border-top:1px solid var(--line)">
                            <td style="padding:8px 0;font-weight:600">{{ $d['code'] }}</td>
                            <td>{{ $d['agents'] }}</td>
                            <td>{{ $d['en_conge'] }}</td>
                            <td><span class="badge" style="background:{{ $d['taux'] > 0 ? '#f6ecdd' : 'var(--surface-2)' }};color:{{ $d['taux'] > 0 ? '#8a5a1e' : 'var(--muted)' }}">{{ $d['taux'] }} %</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
