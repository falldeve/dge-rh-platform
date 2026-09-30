<div class="ag">
    <style>
        .ag-head{ display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px;
            animation:agUp .5s cubic-bezier(.32,.72,0,1) both; }
        .ag-head h1{ font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;margin:0;color:var(--navy); }
        .ag-head .sub{ color:var(--muted);font-size:13.5px;margin:5px 0 0;font-weight:600; }
        .ag-add{ display:inline-flex;align-items:center;gap:9px;padding:11px 15px 11px 18px;border:0;cursor:pointer;
            border-radius:999px;background:var(--green);color:#fff;font-weight:700;font-size:14px;
            box-shadow:0 12px 24px -12px rgba(48,153,102,.7);transition:transform .4s cubic-bezier(.32,.72,0,1),background .3s; }
        .ag-add:hover{ background:var(--green-deep); } .ag-add:active{ transform:scale(.98); }
        .ag-add .ic{ width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.2);display:grid;place-items:center; }

        .ag-filters{ display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:14px;border-radius:18px;
            background:var(--surface);border:1px solid var(--line);box-shadow:0 12px 30px -26px rgba(25,55,97,.5);margin-bottom:18px; }
        .ag-search{ position:relative;flex:1;min-width:240px; }
        .ag-search svg{ width:17px;height:17px;position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted); }
        .ag-search input{ padding-left:38px; }

        .ag-table-wrap{ background:var(--surface);border:1px solid var(--line);border-radius:20px;overflow:hidden;
            box-shadow:0 16px 40px -30px rgba(25,55,97,.5); }
        table.ag-table{ width:100%;border-collapse:collapse;font-size:14px; }
        .ag-table thead tr{ background:var(--surface-2);color:var(--muted);text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase; }
        .ag-table th{ padding:13px 16px;font-weight:700; }
        .ag-table tbody tr{ border-top:1px solid var(--line);cursor:pointer;position:relative;transition:background .25s; }
        .ag-table tbody tr:hover{ background:var(--surface-2); }
        .ag-table tbody tr::before{ content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--green);transform:scaleY(0);transform-origin:center;transition:transform .3s cubic-bezier(.32,.72,0,1); }
        .ag-table tbody tr:hover::before{ transform:scaleY(1); }
        .ag-table td{ padding:12px 16px;color:var(--ink); }
        .ag-agent{ display:flex;align-items:center;gap:12px; }
        .ag-av{ width:40px;height:40px;border-radius:50%;object-fit:cover;flex:none;box-shadow:0 0 0 2px #fff,0 0 0 3px var(--line); }
        .ag-av-ph{ width:40px;height:40px;border-radius:50%;flex:none;display:grid;place-items:center;font-weight:700;font-size:13px;
            background:linear-gradient(150deg,var(--green-soft),#fff);color:var(--green-deep);box-shadow:0 0 0 2px #fff,0 0 0 3px var(--line); }
        .ag-name{ font-weight:700;line-height:1.2; }
        .ag-mat{ color:var(--muted);font-size:12px;margin-top:1px; }
        .ag-fonction{ color:var(--muted);max-width:320px; }
        .pill{ display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700; }
        .pill-dir{ background:rgba(25,55,97,.08);color:var(--navy); }
        .pill-fonc{ background:var(--green-soft);color:var(--green-deep); }
        .pill-nonfonc{ background:#f6ecdd;color:#8a5a1e; }
        .pill-actif{ background:var(--green-soft);color:var(--green-deep); }
        .pill-inactif{ background:#fdecec;color:#b3261e; }
        .ag-solde{ text-align:center;font-weight:700; }
        .ag-solde span{ color:var(--muted);font-weight:400; }
        .ag-modif{ display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;
            border:1px solid var(--line);background:#fff;color:var(--ink);font-size:13px;font-weight:600;transition:border-color .3s,color .3s; }
        .ag-table tbody tr:hover .ag-modif{ border-color:var(--green);color:var(--green-deep); }
        .ag-empty{ padding:52px;text-align:center;color:var(--muted); }
        .ag-empty .t{ font-size:15px;font-weight:600;color:var(--ink); }
        @keyframes agUp{ from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        @media(max-width:820px){ .ag-fonction{ display:none } }
    </style>

    <div class="ag-head">
        <div>
            <h1>Agents</h1>
            <p class="sub">{{ $agents->total() }} agents · {{ $directions->count() }} directions</p>
        </div>
        <button wire:click="ouvrirCreation" class="ag-add">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:15px;height:15px"><path d="M12 5v14M5 12h14"/></svg></span>
            Nouvel agent
        </button>
    </div>

    <div class="ag-filters">
        <div class="ag-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher un nom, un matricule…" class="field">
        </div>
        <select wire:model.live="directionId" class="field" style="width:auto;min-width:190px">
            <option value="">Toutes les directions</option>
            @foreach ($directions as $d)
                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
            @endforeach
        </select>
        <select wire:model.live="categorie" class="field" style="width:auto;min-width:185px">
            <option value="">Toutes catégories</option>
            <option value="fonctionnaire">Fonctionnaires</option>
            <option value="non_fonctionnaire">Non-fonctionnaires</option>
        </select>
        @if ($search || $directionId || $categorie)
            <button wire:click="reinitialiser" class="btn btn-ghost" style="padding:8px 14px">Réinitialiser</button>
        @endif
    </div>

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Fonction</th>
                    <th>Direction</th>
                    <th>Catégorie</th>
                    <th style="text-align:center">Solde</th>
                    <th>Compte</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr wire:key="ag-{{ $agent->id }}" wire:click="ouvrirEdition({{ $agent->id }})">
                        <td>
                            <div class="ag-agent">
                                @if ($agent->photo_path)
                                    <img src="{{ $agent->photoUrl() }}" class="ag-av" alt="">
                                @else
                                    <div class="ag-av-ph">{{ strtoupper(mb_substr($agent->prenoms,0,1).mb_substr($agent->noms,0,1)) }}</div>
                                @endif
                                <div>
                                    <div class="ag-name">{{ $agent->prenoms }} {{ $agent->noms }}</div>
                                    <div class="ag-mat">{{ $agent->matricule ?? 'Sans matricule' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="ag-fonction">{{ $agent->fonction ?? '—' }}</td>
                        <td><span class="pill pill-dir">{{ $agent->direction?->code }}</span></td>
                        <td>
                            @if ($agent->estFonctionnaire())
                                <span class="pill pill-fonc">Fonctionnaire</span>
                            @else
                                <span class="pill pill-nonfonc">Non-fonctionnaire</span>
                            @endif
                        </td>
                        <td class="ag-solde">{{ rtrim(rtrim((string) $agent->solde_conge_jours, '0'), '.') }}<span> j</span></td>
                        <td onclick="event.stopPropagation()">
                            @if ($agent->user_id)
                                @php($u = $agent->user)
                                <div style="display:flex;align-items:center;gap:8px">
                                    @if ($u && $u->compte_actif)
                                        <span class="pill pill-actif">Compte actif</span>
                                    @else
                                        <span class="pill pill-inactif">Désactivé</span>
                                    @endif
                                    @if ($u && ! $u->isAdmin() && $u->id !== auth()->id())
                                        <button type="button" class="btn btn-ghost" style="padding:5px 10px;font-size:12px"
                                                wire:click="basculerCompte({{ $u->id }})"
                                                wire:confirm="{{ $u->compte_actif ? 'Désactiver ce compte ? L’agent ne pourra plus se connecter.' : 'Réactiver ce compte ?' }}">
                                            {{ $u->compte_actif ? 'Désactiver' : 'Réactiver' }}
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div x-data="{ open: false, email: '' }">
                                    <button type="button" @click="open = !open" class="btn btn-ghost" style="padding:5px 10px;font-size:12px">Créer le compte</button>
                                    <div x-show="open" x-cloak style="display:flex;gap:6px;margin-top:6px;align-items:center">
                                        <input type="email" x-model="email" placeholder="email@dge.sn" class="field" style="padding:5px 8px;font-size:12px;width:170px">
                                        <button type="button"
                                            @click="$wire.creerCompte({{ $agent->id }}, email); open = false"
                                            wire:loading.attr="disabled" wire:target="creerCompte"
                                            class="btn btn-primary" style="padding:5px 10px;font-size:12px">OK</button>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td style="text-align:right"><span class="ag-modif">Modifier</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ag-empty">
                        <div class="t">Aucun agent trouvé</div>
                        <div style="font-size:13px;margin-top:4px">Ajustez la recherche ou le filtre de direction.</div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $agents->links() }}</div>

    {{-- Drawer d'édition / création --}}
    @if ($editId !== null)
        <div style="position:fixed;inset:0;z-index:50">
            <div wire:click="fermer" style="position:absolute;inset:0;background:rgba(25,55,97,.42);backdrop-filter:blur(2px);animation:fadeIn .2s ease"></div>
            <div style="position:absolute;right:0;top:0;height:100%;width:min(560px,100%);background:var(--paper);
                        box-shadow:-24px 0 60px -30px rgba(25,55,97,.6);animation:drawerIn .28s cubic-bezier(.16,1,.3,1);overflow-y:auto">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--paper);z-index:2">
                    <div style="font-family:'Open Sans',sans-serif;font-size:19px;font-weight:700">
                        {{ $editId ? 'Modifier la fiche' : 'Nouvel agent' }}
                    </div>
                    <button wire:click="fermer" class="btn btn-ghost" style="padding:7px 10px" aria-label="Fermer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div style="padding:22px">
                    @if ($editId && $agentEnEdition)
                        <livewire:rh.agent-form :agent="$agentEnEdition" :key="'form-'.$editId" />
                    @else
                        <livewire:rh.agent-form :key="'form-new'" />
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
