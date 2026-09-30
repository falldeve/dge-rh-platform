<div>
    @php($typeLabel = ['conge_annuel' => 'Congé annuel', 'permission' => 'Permission', 'ordre_mission' => 'Ordre de mission'])
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Demandes à valider</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 20px">DRHF — file de niveau 2 (validation finale)</p>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Agent</th><th style="padding:12px 12px;font-weight:600">Type</th>
                    <th style="padding:12px 12px;font-weight:600">Période</th><th style="padding:12px 12px;font-weight:600">Jours</th>
                    <th style="padding:12px 12px;font-weight:600">Motif</th><th style="padding:12px 18px;font-weight:600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demandes as $d)
                    <tr wire:key="fr-{{ $d->id }}" style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $d->agent->prenoms }} {{ $d->agent->noms }}</td>
                        <td style="padding:11px 12px">{{ $typeLabel[$d->type] ?? $d->type }}</td>
                        <td style="padding:11px 12px">{{ $d->date_debut->format('d/m/Y') }} → {{ $d->date_fin->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">{{ $d->nb_jours }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $d->motif ?? '—' }}</td>
                        <td style="padding:11px 18px;white-space:nowrap">
                            <button wire:click="decider({{ $d->id }}, 'ok')" class="btn btn-primary" style="padding:6px 13px;font-size:13px">Valider</button>
                            <button wire:click="decider({{ $d->id }}, 'refus')" class="btn" style="padding:6px 13px;font-size:13px;background:#b4341f;color:#fff;margin-left:6px">Refuser</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucune demande en attente. ✓</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($traitees->isNotEmpty())
        <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:26px 0 12px">Récemment validées</h2>
        <div class="card" style="overflow:hidden">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead>
                    <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                        <th style="padding:12px 18px;font-weight:600">Agent</th><th style="padding:12px 12px;font-weight:600">Type</th>
                        <th style="padding:12px 12px;font-weight:600">Période</th><th style="padding:12px 18px;font-weight:600">Document</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($traitees as $t)
                        <tr style="border-top:1px solid var(--line)">
                            <td style="padding:11px 18px;font-weight:600">{{ $t->agent->prenoms }} {{ $t->agent->noms }}</td>
                            <td style="padding:11px 12px">{{ $typeLabel[$t->type] ?? $t->type }}</td>
                            <td style="padding:11px 12px">{{ $t->date_debut->format('d/m/Y') }} → {{ $t->date_fin->format('d/m/Y') }}</td>
                            <td style="padding:11px 18px"><a href="{{ route('demandes.document', $t) }}" target="_blank" class="btn btn-primary" style="padding:6px 13px;font-size:13px;text-decoration:none">Télécharger le PDF</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
