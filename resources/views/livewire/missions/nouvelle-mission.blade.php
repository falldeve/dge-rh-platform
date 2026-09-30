<div style="max-width:680px;margin:0 auto">
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($er='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:26px;font-weight:600;margin:0 0 18px">Nouvel ordre de mission</h1>

    <form wire:submit="creer" class="card" style="padding:24px;display:flex;flex-direction:column;gap:16px">
        <div>
            <label style="{{ $lbl }}">Agent (missionnaire)</label>
            <select wire:model="agent_id" class="field">
                <option value="">— choisir —</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}">{{ $a->prenoms }} {{ $a->noms }} — {{ $a->matricule ?? 'sans matricule' }}</option>
                @endforeach
            </select>
            @error('agent_id') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label style="{{ $lbl }}">Date de départ</label>
                <input type="date" wire:model="date_debut" class="field">
                @error('date_debut') <span style="{{ $er }}">{{ $message }}</span> @enderror</div>
            <div><label style="{{ $lbl }}">Date de retour</label>
                <input type="date" wire:model="date_fin" class="field">
                @error('date_fin') <span style="{{ $er }}">{{ $message }}</span> @enderror</div>
        </div>

        <div><label style="{{ $lbl }}">Se rendre à (destination)</label>
            <input type="text" wire:model="destination" class="field" placeholder="Dakar – Mbour – Dakar"></div>

        <div><label style="{{ $lbl }}">Motif</label>
            <textarea wire:model="motif" rows="2" class="field"></textarea></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label style="{{ $lbl }}">Moyen de transport</label>
                <input type="text" wire:model="moyen_transport" class="field"></div>
            <div><label style="{{ $lbl }}">Imputation budgétaire</label>
                <input type="text" wire:model="imputation" class="field"></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
            <div><label style="{{ $lbl }}">Indice</label><input type="text" wire:model="indice" class="field"></div>
            <div><label style="{{ $lbl }}">Groupe</label><input type="text" wire:model="groupe" class="field"></div>
            <div><label style="{{ $lbl }}">Chapitre</label><input type="text" wire:model="chapitre" class="field"></div>
            <div><label style="{{ $lbl }}">Article</label><input type="text" wire:model="article" class="field"></div>
        </div>

        <div style="border-top:1px solid var(--line);padding-top:14px">
            <span style="{{ $lbl }}">Signataire(s)</span>
            <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:14px">
                <label style="display:inline-flex;align-items:center;gap:8px"><input type="checkbox" wire:model="signataires" value="dg"> Directeur Général</label>
                <label style="display:inline-flex;align-items:center;gap:8px"><input type="checkbox" wire:model="signataires" value="directeur"> Directeur de la direction</label>
            </div>
            @error('signataires') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px">
            <a href="{{ route('missions.mes') }}" class="btn btn-ghost" style="text-decoration:none">Annuler</a>
            <button type="submit" class="btn btn-primary">Créer</button>
        </div>
    </form>
</div>
