<div>
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($err='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    @php($roleLabels = ['admin' => 'Super-admin', 'admin_rh' => 'RH', 'courrier' => 'Courrier', 'archiviste' => 'Archiviste', 'dg' => 'Direction générale'])
    <h1 style="font-size:28px;font-weight:600;margin:0 0 4px">Comptes système</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Provisionnez les comptes RH, courrier, archives et direction générale. Un mot de passe provisoire est envoyé par email.</p>

    <form wire:submit="creer" class="card" style="padding:16px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
        <div><label style="{{ $lbl }}">Nom</label>
            <input type="text" wire:model="name" class="field">
            @error('name') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div><label style="{{ $lbl }}">Matricule</label>
            <input type="text" wire:model="matricule" class="field">
            @error('matricule') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div><label style="{{ $lbl }}">Email</label>
            <input type="email" wire:model="email" class="field">
            @error('email') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div><label style="{{ $lbl }}">Rôle</label>
            <select wire:model="role" class="field">
                @foreach ($rolesDispo as $r)
                    <option value="{{ $r }}">{{ $roleLabels[$r] ?? $r }}</option>
                @endforeach
            </select>
            @error('role') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
        <div style="grid-column:1 / -1;display:flex;align-items:flex-end;gap:10px">
            <button type="submit" wire:loading.attr="disabled" wire:target="creer" class="btn btn-primary">
                <span wire:loading.remove wire:target="creer">Créer le compte</span>
                <span wire:loading wire:target="creer">Création…</span>
            </button>
        </div>
    </form>

    <div class="card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:var(--surface-2);color:var(--muted);text-align:left;font-size:12px;letter-spacing:.04em;text-transform:uppercase">
                    <th style="padding:12px 18px;font-weight:600">Nom</th>
                    <th style="padding:12px 12px;font-weight:600">Matricule</th>
                    <th style="padding:12px 12px;font-weight:600">Email</th>
                    <th style="padding:12px 12px;font-weight:600">Rôle</th>
                    <th style="padding:12px 12px;font-weight:600">Statut</th>
                    <th style="padding:12px 12px;font-weight:600"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comptes as $c)
                    <tr wire:key="cpt-{{ $c->id }}" style="border-top:1px solid var(--line)">
                        <td style="padding:11px 18px;font-weight:600">{{ $c->name }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->matricule ?? '—' }}</td>
                        <td style="padding:11px 12px;color:#41504a">{{ $c->email }}</td>
                        <td style="padding:11px 12px">
                            <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ $roleLabels[$c->role] ?? $c->role }}</span>
                        </td>
                        <td style="padding:11px 12px">
                            @if ($c->compte_actif)
                                <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">Actif</span>
                            @else
                                <span class="badge" style="background:#fdecec;color:#b3261e">Désactivé</span>
                            @endif
                        </td>
                        <td style="padding:11px 12px;text-align:right">
                            @if (! $c->isAdmin() && $c->id !== auth()->id())
                                <button type="button" class="btn btn-ghost" style="padding:5px 10px;font-size:12px"
                                        wire:click="basculer({{ $c->id }})"
                                        wire:confirm="{{ $c->compte_actif ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' }}">
                                    {{ $c->compte_actif ? 'Désactiver' : 'Réactiver' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Aucun compte système pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
