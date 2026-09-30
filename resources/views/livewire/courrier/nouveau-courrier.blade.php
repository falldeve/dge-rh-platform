<div style="max-width:640px;margin:0 auto">
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($err='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:26px;font-weight:600;margin:0 0 18px">Nouveau courrier</h1>

    <form wire:submit="enregistrer" class="card" style="padding:24px;display:flex;flex-direction:column;gap:16px">
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px">
            <div><label style="{{ $lbl }}">N° d'arrivée</label>
                <input type="text" wire:model="numero" class="field" placeholder="000145">
                @error('numero') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
            <div><label style="{{ $lbl }}">Expéditeur</label>
                <input type="text" wire:model="expediteur" class="field">
                @error('expediteur') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        </div>
        <div><label style="{{ $lbl }}">Objet</label>
            <input type="text" wire:model="objet" class="field">
            @error('objet') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label style="{{ $lbl }}">Date d'arrivée</label>
                <input type="date" wire:model="date_arrivee" class="field">
                @error('date_arrivee') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
            <div><label style="{{ $lbl }}">Date de départ (optionnel)</label>
                <input type="date" wire:model="date_depart" class="field"></div>
        </div>
        <div><label style="{{ $lbl }}">Scan du courrier (PDF ou image)</label>
            <input type="file" wire:model="scan" accept=".pdf,image/*" style="font-size:13px">
            <div wire:loading wire:target="scan" style="font-size:12px;color:var(--muted)">Téléversement…</div>
            @error('scan') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="display:flex;justify-content:flex-end;gap:10px">
            <a href="{{ route('courriers.registre') }}" class="btn btn-ghost" style="text-decoration:none">Annuler</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer,scan" class="btn btn-primary">
                <span wire:loading.remove wire:target="enregistrer,scan">Enregistrer</span>
                <span wire:loading wire:target="enregistrer,scan">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
