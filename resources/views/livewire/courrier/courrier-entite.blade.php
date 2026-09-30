<div x-data="{ showScan: true, zoom: false }">
    <style>
        /* Cette page exploite toute la largeur disponible (le scan remplit jusqu'au bord droit). */
        .rh-main{ max-width:none; }
        .ce-grid{ display:grid; gap:20px; align-items:start; grid-template-columns:minmax(0,360px) minmax(0,1fr); }
        .ce-grid.solo{ grid-template-columns:1fr; max-width:820px; }
        .ce-col{ display:flex; flex-direction:column; gap:20px; }
        .ce-scan{ position:sticky; top:20px; }
        @media (max-width:1100px){ .ce-grid, .ce-grid.solo{ grid-template-columns:1fr; max-width:820px; } .ce-scan{ position:static; } }
    </style>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px">
        <a href="{{ route($retour) }}" style="color:var(--muted);font-size:13px;text-decoration:none">← {{ $retour === 'archives' ? 'Archives' : 'Mes courriers' }}</a>
        @if ($courrier->scanUrl())
            <button type="button" @click="showScan = !showScan" class="btn btn-ghost" style="padding:6px 12px;font-size:13px">
                <span x-show="showScan">Cacher le courrier</span>
                <span x-show="!showScan" x-cloak>Afficher le courrier</span>
            </button>
        @endif
    </div>

    <div class="ce-grid" :class="{ 'solo': !showScan || {{ $courrier->scanUrl() ? 'false' : 'true' }} }">
        {{-- Colonne gauche : infos --}}
        <div class="ce-col">
            <header style="padding:0 2px 4px;border-bottom:1px solid var(--line)">
                <h1 style="font-size:24px;font-weight:600;margin:0">Courrier N° {{ $courrier->numero }}</h1>
                <div style="color:var(--muted);font-size:14px;margin-top:4px">{{ $courrier->objet }}</div>
                <div style="font-size:13px;margin:8px 0 12px">Expéditeur : <strong>{{ $courrier->expediteur }}</strong> · Arrivée : {{ $courrier->date_arrivee->format('d/m/Y') }}</div>
            </header>

            @php($instructions = $imputations->first(fn ($i) => filled($i->mentions) || filled($i->observations)))
            @if ($instructions)
                <div class="card" style="padding:22px;border-left:4px solid var(--gold)">
                    <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 10px">Instructions du Directeur</h2>
                    @if (filled($instructions->mentions))
                        <div style="display:flex;flex-wrap:wrap;gap:6px">
                            @foreach ($instructions->mentions as $m)
                                <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">✓ {{ \App\Models\Imputation::MENTIONS[$m] ?? $m }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($instructions->observations)
                        <div style="font-size:14px;color:#41504a;margin-top:10px">« {{ $instructions->observations }} »</div>
                    @endif
                </div>
            @endif

            @if ($mesEntitesDest->isNotEmpty())
                <div class="card" style="padding:22px">
                    <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Accusé de réception</h2>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        @foreach ($mesEntitesDest as $e)
                            @php($ack = $accuses[$e->id] ?? null)
                            @if ($ack)
                                <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:var(--green-deep)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong title="{{ $e->nom }}" style="cursor:help">{{ $e->code }}</strong> — Reçu le {{ $ack->created_at->format('d/m/Y à H:i') }}@if ($ack->user) par {{ $ack->user->name }}@endif</span>
                                </div>
                            @else
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                    <span style="font-size:14px;color:var(--muted)">Confirmer la réception pour <strong>{{ $e->code }} — {{ $e->nom }}</strong></span>
                                    <button wire:click="accuser({{ $e->id }})" wire:loading.attr="disabled" wire:target="accuser({{ $e->id }})" class="btn btn-primary">
                                        <span wire:loading.remove wire:target="accuser({{ $e->id }})">Accuser réception</span>
                                        <span wire:loading wire:target="accuser({{ $e->id }})">Enregistrement…</span>
                                    </button>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($diligences->isNotEmpty() || $mesEntitesDest->isNotEmpty())
                <div class="card" style="padding:22px">
                    <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Réponses &amp; diligences</h2>

                    @forelse ($diligences as $d)
                        <div style="padding:12px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                            <div style="font-size:13px;font-weight:600">
                                <span @if ($d->entite) title="{{ $d->entite->nom }}" style="cursor:help" @endif>{{ $d->entite?->code ?? '—' }}</span>
                                <span style="color:var(--muted);font-weight:400">· {{ $d->user?->name }} · {{ $d->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div style="font-size:14px;color:#41504a;margin-top:5px;white-space:pre-line">{{ $d->contenu }}</div>
                        </div>
                    @empty
                        <p style="color:var(--muted);font-size:13px;margin:0 0 4px">Aucune réponse pour le moment. Rendez compte des diligences ci-dessous.</p>
                    @endforelse

                    @if ($mesEntitesDest->isNotEmpty())
                        <form wire:submit="repondre" style="display:flex;flex-direction:column;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
                            <textarea wire:model="reponse" rows="3" class="field" placeholder="Rendre compte des diligences à ma hiérarchie…"></textarea>
                            @error('reponse') <span style="color:#b4341f;font-size:12px">{{ $message }}</span> @enderror
                            <div style="display:flex;justify-content:flex-end">
                                <button type="submit" wire:loading.attr="disabled" wire:target="repondre" class="btn btn-primary">
                                    <span wire:loading.remove wire:target="repondre">Transmettre</span>
                                    <span wire:loading wire:target="repondre">Envoi…</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            @if ($directionCascade && $divisionsDispo->isNotEmpty())
                <div class="card" style="padding:22px">
                    <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 4px">Imputer aux divisions ({{ $directionCascade->code }})</h2>
                    <p style="color:var(--muted);font-size:13px;margin:0 0 14px">Imputez ce courrier aux divisions concernées (mentions « Soit transmis »).</p>
                    <form wire:submit="cascader" style="display:flex;flex-direction:column;gap:16px">
                        <div>
                            <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Divisions destinataires</span>
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                                @foreach ($divisionsDispo as $d)
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                                        <input type="checkbox" wire:model="divisions" value="{{ $d->id }}"> {{ $d->code }} — {{ $d->nom }}
                                    </label>
                                @endforeach
                            </div>
                            @error('divisions') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">Sélectionnez au moins une division.</span> @enderror
                        </div>
                        <div>
                            <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Soit transmis (mentions)</span>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
                                @foreach ($mentionsListe as $code => $libelle)
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                                        <input type="checkbox" wire:model="mentions_cascade" value="{{ $code }}"> {{ $libelle }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Observations</label>
                            <textarea wire:model="observations_cascade" rows="2" class="field"></textarea></div>
                        <div style="display:flex;justify-content:flex-end">
                            <button type="submit" wire:loading.attr="disabled" wire:target="cascader" class="btn btn-primary">
                                <span wire:loading.remove wire:target="cascader">Enregistrer la cascade</span>
                                <span wire:loading wire:target="cascader">Enregistrement…</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="card" style="padding:22px">
                <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Historique d'imputation</h2>
                @foreach ($imputations as $imp)
                    <div style="padding:12px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                        <div style="font-weight:600;font-size:14px">
                            {{ $imp->niveau === 'dg' ? 'Ventilation DG' : 'Cascade '.($imp->entiteSource?->code) }}
                            <span style="color:var(--muted);font-weight:400">· {{ $imp->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div style="font-size:13px;margin-top:4px">→
                            @foreach ($imp->destinataires as $dest)<span title="{{ $dest->nom }}" style="text-decoration:underline dotted;text-underline-offset:2px;cursor:help">{{ $dest->code }}</span>@if (! $loop->last), @endif @endforeach
                        </div>
                        @if ($imp->mentions)
                            <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px">
                                @foreach ($imp->mentions as $m)
                                    <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ \App\Models\Imputation::MENTIONS[$m] ?? $m }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($imp->observations)<div style="font-size:13px;color:#41504a;margin-top:6px">« {{ $imp->observations }} »</div>@endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Colonne droite : courrier scanné (masquable) --}}
        @if ($courrier->scanUrl())
            <div class="ce-col" x-show="showScan">
                <div class="card ce-scan" style="padding:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin:4px 4px 12px">
                        <span style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">Courrier scanné</span>
                        <a href="{{ $courrier->scanUrl() }}" target="_blank" style="font-size:12px;color:var(--green);text-decoration:none">Ouvrir en entier ↗</a>
                    </div>

                    @if ($courrier->scanEstImage())
                        <img src="{{ $courrier->scanUrl() }}" alt="Courrier scanné" @click="zoom = true"
                             style="width:100%;border-radius:10px;display:block;cursor:zoom-in">
                    @elseif ($courrier->scanEstPdf())
                        <iframe src="{{ $courrier->scanUrl() }}" title="Courrier scanné" style="width:100%;height:900px;border:0;border-radius:10px"></iframe>
                    @else
                        <a href="{{ $courrier->scanUrl() }}" target="_blank" class="btn btn-ghost" style="text-decoration:none">Ouvrir le fichier</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Agrandissement : l'image remplit la LARGEUR (défilement vertical), pas d'extension horizontale --}}
    @if ($courrier->scanEstImage())
        <div x-show="zoom" x-cloak @click="zoom = false" @keydown.escape.window="zoom = false"
             style="position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:60;overflow:auto;overflow-x:hidden;padding:24px 0;cursor:zoom-out">
            <img src="{{ $courrier->scanUrl() }}" alt="Courrier scanné" @click.stop
                 style="display:block;width:100%;max-width:1100px;height:auto;margin:0 auto;border-radius:6px;cursor:default">
        </div>
    @endif
</div>
