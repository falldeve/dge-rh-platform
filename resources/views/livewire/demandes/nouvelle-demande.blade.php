<div style="max-width:560px;margin:0 auto">
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($er='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:26px;font-weight:600;margin:0 0 18px">Nouvelle demande</h1>

    <form wire:submit="soumettre" class="card" style="padding:24px;display:flex;flex-direction:column;gap:16px">
        <div>
            <label style="{{ $lbl }}">Type de demande</label>
            <select wire:model="type" class="field">
                <option value="conge_annuel">Congé annuel</option>
                <option value="permission">Permission d'absence</option>
            </select>
            @error('type') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
                <label style="{{ $lbl }}">Du</label>
                <input type="date" wire:model="date_debut" class="field">
                @error('date_debut') <span style="{{ $er }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label style="{{ $lbl }}">Au</label>
                <input type="date" wire:model="date_fin" class="field">
                @error('date_fin') <span style="{{ $er }}">{{ $message }}</span> @enderror
                @error('nb_jours') <span style="{{ $er }}">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label style="{{ $lbl }}">Motif</label>
            <textarea wire:model="motif" rows="3" class="field"></textarea>
            @error('motif') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px">
            <a href="{{ route('demandes.mes') }}" class="btn btn-ghost" style="text-decoration:none">Annuler</a>
            <button type="submit" class="btn btn-primary">Soumettre</button>
        </div>
    </form>
</div>
