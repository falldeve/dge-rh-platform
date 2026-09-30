<div>
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    <h1 style="font-size:28px;font-weight:600;margin:0 0 18px">Directions</h1>

    <form wire:submit="save" class="card" style="padding:16px;display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin-bottom:20px">
        <div style="width:120px">
            <label style="{{ $lbl }}">Code</label>
            <input type="text" wire:model="code" class="field">
            @error('code') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div style="flex:1;min-width:220px">
            <label style="{{ $lbl }}">Nom</label>
            <input type="text" wire:model="nom" class="field">
            @error('nom') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">
            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Mettre à jour' : 'Ajouter' }}</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </button>
        @if ($editingId)
            <button type="button" wire:click="annuler" class="btn btn-ghost">Annuler</button>
        @endif
    </form>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Code</th><th style="padding:12px 12px;font-weight:600">Nom</th>
                    <th style="padding:12px 12px;font-weight:600">Agents</th><th style="padding:12px 12px;font-weight:600">Chef</th><th style="padding:12px 18px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($directions as $d)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px"><span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ $d->code }}</span></td>
                        <td style="padding:11px 12px;font-weight:600">{{ $d->nom }}</td>
                        <td style="padding:11px 12px">{{ $d->agents_count }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ optional(\App\Models\Agent::find($d->chef_id))->noms ?? '—' }}</td>
                        <td style="padding:11px 18px;text-align:right"><button wire:click="edit({{ $d->id }})" class="btn btn-ghost" style="padding:6px 12px;font-size:13px">Éditer</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
