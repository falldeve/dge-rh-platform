<div style="max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:20px">
    <a href="{{ route('courriers.registre') }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Registre</a>

    <div class="card" style="padding:22px">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="font-size:24px;font-weight:600;margin:0">Courrier N° {{ $courrier->numero }}</h1>
                <div style="color:var(--muted);font-size:14px;margin-top:4px">{{ $courrier->objet }}</div>
                <div style="font-size:13px;margin-top:8px">Expéditeur : <strong>{{ $courrier->expediteur }}</strong> · Arrivée : {{ $courrier->date_arrivee->format('d/m/Y') }}</div>
            </div>
            @if ($courrier->scanUrl())
                <a href="{{ $courrier->scanUrl() }}" target="_blank" class="btn btn-ghost" style="text-decoration:none">Voir le scan</a>
            @endif
        </div>
    </div>

    <div class="card" style="padding:22px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Fiche de ventilation</h2>
        <form wire:submit="ventiler" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Destinataires</span>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                    @foreach ($entites as $e)
                        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                            <input type="checkbox" wire:model="destinataires" value="{{ $e->id }}"> {{ $e->code }} — {{ $e->nom }}
                        </label>
                    @endforeach
                </div>
                @error('destinataires') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">Sélectionnez au moins un destinataire.</span> @enderror
            </div>

            <div>
                <span style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Soit transmis (mentions)</span>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
                    @foreach ($mentionsListe as $code => $libelle)
                        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
                            <input type="checkbox" wire:model="mentions" value="{{ $code }}"> {{ $libelle }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
                <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Observations</label>
                    <textarea wire:model="observations" rows="2" class="field"></textarea></div>
                <div><label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Signataire</label>
                    <input type="text" wire:model="signataire_nom" class="field"></div>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="ventiler" class="btn btn-primary">
                    <span wire:loading.remove wire:target="ventiler">Enregistrer la ventilation</span>
                    <span wire:loading wire:target="ventiler">Enregistrement…</span>
                </button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:22px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Historique d'imputation</h2>
        @forelse ($imputations as $imp)
            <div style="padding:12px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                <div style="font-weight:600;font-size:14px">
                    {{ $imp->niveau === 'dg' ? 'Ventilation DG' : 'Cascade '.($imp->entiteSource?->code) }}
                    <span style="color:var(--muted);font-weight:400">· {{ $imp->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div style="font-size:13px;margin-top:4px">→ {{ $imp->destinataires->pluck('code')->join(', ') }}</div>
                @if ($imp->mentions)
                    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px">
                        @foreach ($imp->mentions as $m)
                            <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ \App\Models\Imputation::MENTIONS[$m] ?? $m }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($imp->observations)
                    <div style="font-size:13px;color:#41504a;margin-top:6px">« {{ $imp->observations }} »</div>
                @endif
                <div style="font-size:12px;color:var(--muted);margin-top:4px">Signataire : {{ $imp->signataire_nom ?? '—' }}</div>
            </div>
        @empty
            <p style="color:var(--muted);font-size:13px;margin:0">Aucune imputation. Ventilez le courrier ci-dessus.</p>
        @endforelse
    </div>
</div>
