<div>
    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px">
        <h1 style="font-size:28px;font-weight:600;margin:0">État des congés</h1>
        <div style="display:flex;gap:10px">
            <a href="{{ route('rh.etat-conges.csv', ['direction' => $directionId, 'du' => $du, 'au' => $au]) }}" class="btn btn-ghost" style="text-decoration:none">Export CSV</a>
            <a href="{{ route('rh.etat-conges.pdf', ['direction' => $directionId, 'du' => $du, 'au' => $au]) }}" target="_blank" class="btn btn-primary" style="text-decoration:none">Export PDF</a>
        </div>
    </div>

    <div class="card" style="padding:12px 14px;margin:18px 0;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <select wire:model.live="directionId" class="field" style="width:auto;min-width:190px">
            <option value="">Toutes les directions</option>
            @foreach ($directions as $d)
                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="du" class="field" style="width:auto">
        <input type="date" wire:model.live="au" class="field" style="width:auto">
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Agent</th><th style="padding:12px 12px;font-weight:600">Direction</th>
                    <th style="padding:12px 12px;font-weight:600">Période</th><th style="padding:12px 12px;font-weight:600">Jours</th>
                    <th style="padding:12px 12px;font-weight:600">Solde restant</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($conges as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->agent->prenoms }} {{ $c->agent->noms }}</td>
                        <td style="padding:11px 12px"><span class="badge" style="background:#eef2f7;color:#334">{{ $c->agent->direction?->code }}</span></td>
                        <td style="padding:11px 12px">{{ $c->date_debut->format('d/m/Y') }} → {{ $c->date_fin->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">{{ $c->nb_jours }}</td>
                        <td style="padding:11px 12px">{{ rtrim(rtrim((string) $c->agent->solde_conge_jours, '0'), '.') }} j</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('conges.attestation', $c) }}" target="_blank" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Attestation</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucun congé validé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
