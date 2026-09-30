<div class="card" style="max-width:1040px;margin:0 auto;">
    <h1>Documents — gestion</h1>

    <form wire:submit="enregistrer" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:16px;background:var(--surface-2);border-radius:14px;">
        <label style="grid-column:1/-1">Titre <input class="field" wire:model="titre"></label>
        <label>Rubrique
            <select class="field" wire:model="rubrique_id">
                <option value="">—</option>
                @foreach ($rubriques as $r)<option value="{{ $r->id }}">{{ $r->nom }}</option>@endforeach
            </select>
        </label>
        <label>Type
            <select class="field" wire:model="type">
                @foreach (['loi','decret','reglement','rapport','circulaire','guide','archive','ordonnance','autre'] as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label>Référence <input class="field" wire:model="reference"></label>
        <label>Date <input class="field" type="date" wire:model="date_document"></label>
        <label style="grid-column:1/-1">Résumé <input class="field" wire:model="resume"></label>
        <label style="grid-column:1/-1">Mots-clés <input class="field" wire:model="mots_cles" placeholder="séparés par des virgules"></label>

        <label style="grid-column:1/-1">Source
            <select class="field" wire:model.live="source">
                <option value="texte">Texte (markdown)</option>
                <option value="fichier">Fichier (PDF/DOCX)</option>
                <option value="lien">Lien externe</option>
            </select>
        </label>

        @if ($source === 'texte')
            <label style="grid-column:1/-1">Contenu (markdown)
                <textarea class="field" wire:model="contenu" rows="8"></textarea>
            </label>
        @elseif ($source === 'fichier')
            <label style="grid-column:1/-1">Fichier <input class="field" type="file" wire:model="fichier" accept=".pdf,.docx"></label>
            @error('fichier') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @elseif ($source === 'lien')
            <label style="grid-column:1/-1">URL <input class="field" wire:model="url" placeholder="https://…"></label>
            @error('url') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
        @endif

        <div style="grid-column:1/-1;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">{{ $editId ? 'Mettre à jour' : 'Créer' }}</button>
            @if ($editId)<button class="btn btn-ghost" type="button" wire:click="annuler">Annuler</button>@endif
        </div>
        @error('titre') <span style="grid-column:1/-1;color:#b91c1c">{{ $message }}</span> @enderror
    </form>

    <input class="field" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un document…" style="margin-bottom:12px">

    <table style="width:100%;border-collapse:collapse">
        <thead><tr><th>Titre</th><th>Rubrique</th><th>Type</th><th>Source</th><th>Actif</th><th></th></tr></thead>
        <tbody>
        @foreach ($documents as $d)
            <tr style="border-top:1px solid var(--line)">
                <td>{{ $d->titre }}</td>
                <td>{{ $d->rubrique?->nom }}</td>
                <td>{{ $d->type }}</td>
                <td>{{ $d->source }}</td>
                <td><button class="btn btn-ghost" wire:click="basculer({{ $d->id }})">{{ $d->actif ? 'Oui' : 'Non' }}</button></td>
                <td style="display:flex;gap:6px">
                    <button class="btn btn-ghost" wire:click="editer({{ $d->id }})">Éditer</button>
                    <button class="btn btn-ghost" wire:click="supprimer({{ $d->id }})" wire:confirm="Supprimer ce document ?">Supprimer</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:12px">{{ $documents->links() }}</div>
</div>
