<div>
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($err='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Directions, bureaux & divisions</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Structure organisationnelle et rattachements. Le bureau courrier applique cette structure.</p>

    @if (session('error'))
        <div style="margin-bottom:18px;display:flex;align-items:center;gap:10px;background:#fbece9;border:1px solid #e6b9ae;color:#8a2b17;padding:11px 15px;border-radius:12px;font-weight:600;font-size:14px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="save" class="card" style="padding:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
        <div><label style="{{ $lbl }}">Code</label>
            <input type="text" wire:model="code" class="field">
            @error('code') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="grid-column:span 2"><label style="{{ $lbl }}">Nom</label>
            <input type="text" wire:model="nom" class="field">
            @error('nom') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div><label style="{{ $lbl }}">Type</label>
            <select wire:model.live="type" class="field">
                <option value="direction">Direction</option>
                <option value="service">Service / Bureau</option>
                <option value="division">Division</option>
            </select></div>
        <div><label style="{{ $lbl }}">Direction parente (division / bureau)</label>
            <select wire:model="parent_id" class="field" @disabled($type === 'direction')>
                <option value="">—</option>
                @foreach ($directions as $d)
                    <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
                @endforeach
            </select></div>
        <div><label style="{{ $lbl }}">Chef</label>
            <select wire:model="chef_agent_id" class="field">
                <option value="">—</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }}</option>
                @endforeach
            </select></div>
        <div><label style="{{ $lbl }}">Secrétaire</label>
            <select wire:model="secretaire_agent_id" class="field">
                <option value="">—</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }}</option>
                @endforeach
            </select></div>
        <div style="display:flex;align-items:flex-end;gap:10px">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">
                <span wire:loading.remove wire:target="save">{{ $editingId ? 'Mettre à jour' : 'Ajouter' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
            @if ($editingId)
                <button type="button" wire:click="annuler" class="btn btn-ghost">Annuler</button>
            @endif
        </div>
    </form>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Code</th><th style="padding:12px 12px;font-weight:600">Nom</th>
                    <th style="padding:12px 12px;font-weight:600">Type</th><th style="padding:12px 12px;font-weight:600">Parent</th>
                    <th style="padding:12px 12px;font-weight:600">Chef</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entites as $e)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px"><span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ $e->code }}</span></td>
                        <td style="padding:11px 12px;font-weight:600">{{ $e->nom }}</td>
                        <td style="padding:11px 12px">{{ ucfirst($e->type) }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $e->parent?->code ?? '—' }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ optional($e->chef)->noms ?? '—' }}</td>
                        <td style="padding:11px 18px;text-align:right;white-space:nowrap">
                            <button wire:click="edit({{ $e->id }})" class="btn btn-ghost" style="padding:6px 12px;font-size:13px">Éditer</button>
                            <button wire:click="supprimer({{ $e->id }})" wire:confirm="Supprimer l'entité « {{ $e->code }} » ?" wire:loading.attr="disabled" wire:target="supprimer({{ $e->id }})" class="btn btn-ghost" style="padding:6px 12px;font-size:13px;color:#b4341f;border-color:#e6b9ae">Supprimer</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
