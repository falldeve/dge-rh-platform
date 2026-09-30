<div>
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Mes courriers</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Courriers imputés à votre entité</p>

    <div class="card" style="padding:12px 14px;margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div style="position:relative;flex:1;min-width:220px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher n° / objet / expéditeur…" class="field" style="padding-left:36px">
        </div>
        <div style="display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden">
            @foreach (['' => 'Tous', 'non_lus' => 'Non lus', 'lus' => 'Lus'] as $val => $libelle)
                <button wire:click="$set('statut', '{{ $val }}')" type="button"
                    style="padding:8px 14px;font-size:13px;font-weight:600;border:0;cursor:pointer;{{ $statut === $val ? 'background:var(--green);color:#fff' : 'background:#fff;color:var(--muted)' }}">{{ $libelle }}</button>
            @endforeach
        </div>
        @if ($search !== '' || $statut !== '')
            <button wire:click="reinitialiser" type="button" class="btn btn-ghost" style="padding:8px 12px;font-size:13px">Réinitialiser</button>
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
                    <th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courriers as $c)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">
                            {{ $c->numero }}
                            @unless ($c->lu)<span class="badge" style="background:#fbece9;color:#8a2b17;margin-left:6px">Non lu</span>@endunless
                        </td>
                        <td style="padding:11px 12px;{{ $c->lu ? '' : 'font-weight:600' }}">{{ $c->objet }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->expediteur }}</td>
                        <td style="padding:11px 12px">{{ $c->date_arrivee->format('d/m/Y') }}</td>
                        <td style="padding:11px 18px;text-align:right"><a href="{{ route('mes-courriers.fiche', $c) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:48px 40px;text-align:center">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" style="width:34px;height:34px;margin:0 auto 10px;opacity:.7"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 8h18M8 13h8M8 16h5"/></svg>
                        <div style="font-weight:600;font-size:15px;margin-bottom:3px">
                            @if ($statut === 'non_lus') Aucun courrier non lu
                            @elseif ($statut === 'lus') Aucun courrier accusé
                            @elseif ($search !== '') Aucun résultat pour « {{ $search }} »
                            @else Aucun courrier pour le moment
                            @endif
                        </div>
                        <div style="font-size:13px;color:var(--muted)">
                            @if ($statut === 'non_lus') Tous vos courriers ont été accusés.
                            @elseif ($search !== '' || $statut !== '') Ajustez la recherche ou le filtre.
                            @else Les courriers qui vous sont imputés apparaîtront ici.
                            @endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $courriers->links() }}</div>
</div>
