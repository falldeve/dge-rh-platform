<div>
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Archives courrier</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Consultation de l'ensemble du registre</p>

    <div class="card" style="padding:14px;margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:220px;position:relative">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher n° / objet / expéditeur…" class="field" style="padding-left:36px">
        </div>
        <div><label style="display:block;font-size:11px;color:var(--muted);margin-bottom:3px">Du</label><input type="date" wire:model.live="du" class="field"></div>
        <div><label style="display:block;font-size:11px;color:var(--muted);margin-bottom:3px">Au</label><input type="date" wire:model.live="au" class="field"></div>
        @if ($search !== '' || $du !== '' || $au !== '')
            <button wire:click="reinitialiser" type="button" class="btn btn-ghost" style="padding:9px 12px;font-size:13px">Réinitialiser</button>
        @endif
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    @include('livewire.courrier.partials.th-sort', ['field' => 'numero', 'label' => 'N°', 'pad' => '18px'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'objet', 'label' => 'Objet'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'expediteur', 'label' => 'Expéditeur'])
                    @include('livewire.courrier.partials.th-sort', ['field' => 'date_arrivee', 'label' => 'Arrivée'])
                    <th style="padding:12px 12px;font-weight:600">Imput.</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->numero }}</td>
                        <td style="padding:11px 12px">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 12px">{{ $c->imputations_count }}</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('archives.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:48px 40px;text-align:center">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" style="width:34px;height:34px;margin:0 auto 10px;opacity:.7"><path d="M3 7h18v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M3 7l2-3h14l2 3M9 12h6"/></svg>
                        <div style="font-weight:600;font-size:15px;margin-bottom:3px">
                            {{ ($search !== '' || $du !== '' || $au !== '') ? 'Aucun courrier ne correspond' : 'Aucun courrier archivé' }}
                        </div>
                        <div style="font-size:13px;color:var(--muted)">
                            {{ ($search !== '' || $du !== '' || $au !== '') ? 'Ajustez la recherche ou la période.' : 'Les courriers enregistrés apparaîtront ici.' }}
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
