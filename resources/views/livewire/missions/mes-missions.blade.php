<div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px">
        <div>
            <h1 style="font-size:28px;font-weight:600;margin:0">Ordres de mission</h1>
            <p style="color:var(--muted);font-size:14px;margin:4px 0 0">Émis pour les agents de votre direction</p>
        </div>
        <a href="{{ route('missions.nouvelle') }}" class="btn btn-primary" style="text-decoration:none">+ Nouvel ordre</a>
    </div>

    <div class="card" style="overflow:hidden;margin-top:20px">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Agent</th><th style="padding:12px 12px;font-weight:600">Destination</th>
                    <th style="padding:12px 12px;font-weight:600">Période</th><th style="padding:12px 12px;font-weight:600">Motif</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($missions as $m)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $m->agent->prenoms }} {{ $m->agent->noms }}</td>
                        <td style="padding:11px 12px">{{ $m->meta['destination'] ?? '—' }}</td>
                        <td style="padding:11px 12px">{{ $m->date_debut->format('d/m/Y') }} → {{ $m->date_fin->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $m->motif ?? '—' }}</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('missions.imprimer', $m) }}" target="_blank" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Imprimer (PDF)</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">Aucun ordre de mission.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
