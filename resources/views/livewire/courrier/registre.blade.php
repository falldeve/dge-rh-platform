<div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px">
        <div>
            <h1 style="font-size:28px;font-weight:600;margin:0">Registre du courrier</h1>
            <p style="color:var(--muted);font-size:14px;margin:4px 0 0">{{ $courriers->total() }} courrier(s)</p>
        </div>
        <a href="{{ route('courriers.nouveau') }}" class="btn btn-primary" style="text-decoration:none">+ Nouveau courrier</a>
    </div>

    <div class="card" style="padding:12px 14px;margin:18px 0">
        <div style="position:relative">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher n° / objet / expéditeur…" class="field" style="padding-left:36px">
        </div>
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    @include('livewire.courrier.partials.th-sort', ['field' => 'numero', 'label' => 'N°', 'pad' => '18px'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'objet', 'label' => 'Objet'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'expediteur', 'label' => 'Expéditeur'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'date_arrivee', 'label' => 'Arrivée'])
                    <th style="padding:12px 12px;font-weight:600">Ventilé</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->numero }}</td>
                        <td style="padding:11px 12px">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">
                            @if ($c->imputations_count > 0)
                                <span class="badge" style="background:#e7efe8;color:#1c6b45">Oui</span>
                            @else
                                <span class="badge" style="background:#f6ecdd;color:#8a5a1e">À ventiler</span>
                            @endif
                        </td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('courriers.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucun courrier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
