<div>
    <div style="margin-bottom:6px">
        <h1 style="font-size:28px;font-weight:600;margin:0">Annuaire des agents</h1>
        <p style="color:var(--muted);font-size:14px;margin:4px 0 0">
            {{ $agents->total() }} agent{{ $agents->total() > 1 ? 's' : '' }} —
            @if (auth()->user()->isChefDirection() && ! auth()->user()->isAdminRh() && ! auth()->user()->isDg())
                votre direction
            @else
                toutes directions
            @endif
        </p>
    </div>

    <div class="card" style="padding:12px 14px;margin:18px 0;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <div style="position:relative;flex:1;min-width:220px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;position:absolute;left:12px;top:11px;color:var(--muted)"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher un nom, un matricule…" class="field" style="padding-left:36px">
        </div>
        @unless ($estChef)
            <select wire:model.live="directionId" class="field" style="width:auto;min-width:185px">
                <option value="">Toutes les directions</option>
                @foreach ($directions as $d)
                    <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
                @endforeach
            </select>
        @endunless
        <select wire:model.live="categorie" class="field" style="width:auto;min-width:185px">
            <option value="">Toutes catégories</option>
            <option value="fonctionnaire">Fonctionnaires</option>
            <option value="non_fonctionnaire">Non-fonctionnaires</option>
        </select>
        @if ($search || $directionId || $categorie)
            <button wire:click="$set('search','');$set('directionId',null);$set('categorie',null)" class="btn btn-ghost" style="padding:8px 12px">Réinitialiser</button>
        @endif
    </div>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Agent</th>
                    <th style="padding:12px 12px;font-weight:600">Fonction</th>
                    <th style="padding:12px 12px;font-weight:600">Direction</th>
                    <th style="padding:12px 12px;font-weight:600">Catégorie</th>
                    <th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr wire:key="an-{{ $agent->id }}" style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px">
                            <div style="display:flex;align-items:center;gap:11px">
                                @if ($agent->photo_path)
                                    <img src="{{ $agent->photoUrl() }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                                @else
                                    <div style="width:38px;height:38px;border-radius:50%;background:var(--green-soft);color:var(--green-deep);display:grid;place-items:center;font-weight:600;font-size:13px">
                                        {{ strtoupper(mb_substr($agent->prenoms,0,1).mb_substr($agent->noms,0,1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div style="font-weight:600">{{ $agent->prenoms }} {{ $agent->noms }}</div>
                                    <div style="color:var(--muted);font-size:12px">{{ $agent->matricule ?? 'Sans matricule' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:11px 12px;color:#41504a">{{ $agent->fonction ?? '—' }}</td>
                        <td style="padding:11px 12px"><span class="badge" style="background:#eef2f7;color:#334">{{ $agent->direction?->code }}</span></td>
                        <td style="padding:11px 12px">
                            @if ($agent->estFonctionnaire())
                                <span class="badge" style="background:#e7efe8;color:#1c6b45">Fonctionnaire</span>
                            @else
                                <span class="badge" style="background:#f6ecdd;color:#8a5a1e">Non-fonctionnaire</span>
                            @endif
                        </td>
                        <td style="padding:11px 18px;text-align:right">
                            <a href="{{ route('agents.profil', $agent) }}" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;text-decoration:none">Voir le profil</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">Aucun agent.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $agents->links() }}</div>
</div>
