<div class="card" style="max-width:960px;margin:0 auto;">
    <h1>Rubriques — gestion</h1>

    <form wire:submit="enregistrer" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:16px;background:var(--surface-2);border-radius:14px;">
        <label>Nom <input class="field" wire:model="nom"></label>
        <label>Icône (emoji) <input class="field" wire:model="icone" maxlength="8"></label>
        <label style="grid-column:1/-1">Description <input class="field" wire:model="description"></label>
        <label>Ordre <input class="field" type="number" wire:model="ordre"></label>
        <label>Photo de couverture <input class="field" type="file" wire:model="photo" accept="image/*"></label>
        <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" wire:model="actif"> Active</label>
        <div style="grid-column:1/-1;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">{{ $editId ? 'Mettre à jour' : 'Créer' }}</button>
            @if ($editId)<button class="btn btn-ghost" type="button" wire:click="annuler">Annuler</button>@endif
        </div>
        @error('nom') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @error('photo') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
    </form>

    <table style="width:100%;border-collapse:collapse">
        <thead><tr><th>Rubrique</th><th>Docs</th><th>Ordre</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @foreach ($rubriques as $r)
            <tr style="border-top:1px solid var(--line)">
                <td>{{ $r->icone }} {{ $r->nom }}</td>
                <td>{{ $r->documents_count }}</td>
                <td>{{ $r->ordre }}</td>
                <td><button class="btn btn-ghost" wire:click="basculer({{ $r->id }})">{{ $r->actif ? 'Oui' : 'Non' }}</button></td>
                <td style="display:flex;gap:6px">
                    <button class="btn btn-ghost" wire:click="editer({{ $r->id }})">Éditer</button>
                    <button class="btn btn-ghost" wire:click="supprimer({{ $r->id }})" wire:confirm="Supprimer cette rubrique ? Les documents liés seront détachés.">Supprimer</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
