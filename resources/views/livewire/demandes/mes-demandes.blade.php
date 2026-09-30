<div>
    @php
        $stMap = [
            'soumise' => ['Soumise', '#8a5a1e', '#f6ecdd'],
            'validee_chef' => ['Validée chef', '#1e5a8a', '#e4eef6'],
            'validee_rh' => ['Validée', '#1c6b45', '#e7efe8'],
            'refusee' => ['Refusée', '#b4341f', '#f7e3df'],
            'emise' => ['Émise', '#5b6560', '#eceee9'],
            'brouillon' => ['Brouillon', '#5b6560', '#eceee9'],
        ];
        $typeLabel = ['conge_annuel' => 'Congé annuel', 'permission' => 'Permission', 'ordre_mission' => 'Ordre de mission'];
    @endphp

    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px">
        <div>
            <h1 style="font-size:28px;font-weight:600;margin:0">Mes demandes</h1>
            @if ($agent)
                <p style="color:var(--muted);font-size:14px;margin:4px 0 0">Solde de congé : <strong style="color:var(--green-deep)">{{ rtrim(rtrim((string) $agent->solde_conge_jours, '0'), '.') }} jours</strong></p>
            @endif
        </div>
        <a href="{{ route('demandes.nouvelle') }}" class="btn btn-primary" style="text-decoration:none">+ Nouvelle demande</a>
    </div>

    <div class="card" style="overflow:hidden;margin-top:20px">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Type</th><th style="padding:12px 12px;font-weight:600">Période</th>
                    <th style="padding:12px 12px;font-weight:600">Jours</th><th style="padding:12px 12px;font-weight:600">Motif</th>
                    <th style="padding:12px 12px;font-weight:600">Statut</th>
                    <th style="padding:12px 18px;font-weight:600">Document</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demandes as $d)
                    @php($st = $stMap[$d->statut] ?? $stMap['brouillon'])
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $typeLabel[$d->type] ?? $d->type }}</td>
                        <td style="padding:11px 12px">{{ $d->date_debut->format('d/m/Y') }} → {{ $d->date_fin->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">{{ $d->nb_jours }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $d->motif ?? '—' }}</td>
                        <td style="padding:11px 12px"><span class="badge" style="background:{{ $st[2] }};color:{{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td style="padding:11px 18px">
                            @if ($d->statut === 'validee_rh' && in_array($d->type, ['conge_annuel', 'permission']))
                                <a href="{{ route('demandes.document', $d) }}" target="_blank" class="btn btn-ghost" style="padding:5px 11px;font-size:13px;text-decoration:none">PDF</a>
                            @else
                                <span style="color:var(--muted)">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucune demande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
