<div>
    @php
        $lbl = 'display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px;letter-spacing:.02em';
        $sec = 'font-family:\'Open Sans\',serif;font-size:14px;font-weight:600;color:var(--green-deep);margin:0 0 12px;display:flex;align-items:center;gap:8px';
        $err = 'display:block;color:#b4341f;font-size:12px;margin-top:4px';
    @endphp

    <form wire:submit="save" style="display:flex;flex-direction:column;gap:20px">

        {{-- Photo (édition uniquement) --}}
        @if ($agent)
            <div style="display:flex;align-items:center;gap:16px">
                @if ($agent->photo_path)
                    <img src="{{ $agent->photoUrl() }}" style="width:104px;height:104px;border-radius:16px;object-fit:cover">
                @else
                    <div style="width:104px;height:104px;border-radius:16px;background:var(--green-soft);color:var(--green-deep);display:grid;place-items:center;font-family:'Open Sans',sans-serif;font-weight:600;font-size:34px">
                        {{ strtoupper(mb_substr($prenoms ?: 'A',0,1).mb_substr($noms ?: 'A',0,1)) }}
                    </div>
                @endif
                <div>
                    <label style="{{ $lbl }}">Photo d'identité</label>
                    <input type="file" wire:model="photo" accept="image/*" style="font-size:13px">
                    <div wire:loading wire:target="photo" style="font-size:12px;color:var(--muted)">Téléversement…</div>
                    @error('photo') <span style="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        {{-- Identité --}}
        <div class="card" style="padding:18px">
            <h3 style="{{ $sec }}"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Identité</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label style="{{ $lbl }}">Prénom(s)</label>
                    <input type="text" wire:model="prenoms" class="field">
                    @error('prenoms') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
                <div><label style="{{ $lbl }}">Nom</label>
                    <input type="text" wire:model="noms" class="field">
                    @error('noms') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
                <div><label style="{{ $lbl }}">Matricule / NIN</label>
                    <input type="text" wire:model="matricule" class="field">
                    @error('matricule') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
                <div><label style="{{ $lbl }}">Date de naissance</label>
                    <input type="date" wire:model="date_naissance" class="field"></div>
            </div>
        </div>

        {{-- Affectation --}}
        <div class="card" style="padding:18px">
            <h3 style="{{ $sec }}"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Affectation</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label style="{{ $lbl }}">Direction</label>
                    <select wire:model="direction_id" class="field">
                        <option value="">— choisir —</option>
                        @foreach ($directions as $d)
                            <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->nom }}</option>
                        @endforeach
                    </select>
                    @error('direction_id') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
                <div><label style="{{ $lbl }}">Statut</label>
                    <select wire:model="statut" class="field">
                        <option value="fonctionnaire">Fonctionnaire</option>
                        <option value="police">Police</option>
                        <option value="contractuel_pav">Contractuel / PAV</option>
                        <option value="autre">Autre</option>
                    </select></div>
                <div><label style="{{ $lbl }}">Profession</label>
                    <input type="text" wire:model="profession" class="field"></div>
                <div><label style="{{ $lbl }}">Fonction</label>
                    <input type="text" wire:model="fonction" class="field"></div>
                <div><label style="{{ $lbl }}">Solde de congé (jours)</label>
                    <input type="number" step="0.5" wire:model="solde_conge_jours" class="field">
                    @error('solde_conge_jours') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
                <div><label style="{{ $lbl }}">Prise de service</label>
                    <input type="date" wire:model="date_prise_service" class="field"></div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="card" style="padding:18px">
            <h3 style="{{ $sec }}"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Contact</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label style="{{ $lbl }}">Téléphone</label>
                    <input type="text" wire:model="telephone" class="field"></div>
                <div><label style="{{ $lbl }}">Email</label>
                    <input type="email" wire:model="email" class="field">
                    @error('email') <span style="{{ $err }}">{{ $message }}</span> @enderror</div>
            </div>
        </div>

        {{-- Scolarité --}}
        <div class="card" style="padding:18px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <h3 style="{{ $sec }};margin:0"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Scolarité</h3>
                <button type="button" wire:click="ajouterFormation" class="btn btn-ghost" style="padding:6px 11px;font-size:13px">+ Ajouter une formation</button>
            </div>
            @forelse ($formations as $i => $f)
                <div wire:key="fo-{{ $i }}" style="border:1px solid var(--line);border-radius:12px;padding:13px;margin-bottom:10px;background:var(--surface-2)">
                    <div style="display:grid;grid-template-columns:70px 56px 70px 56px 1fr;gap:8px;align-items:end">
                        <div><label style="{{ $lbl }}">De (AAAA)</label><input type="number" wire:model="formations.{{ $i }}.annee_debut" class="field" placeholder="2004"></div>
                        <div><label style="{{ $lbl }}">MM</label><input type="number" wire:model="formations.{{ $i }}.mois_debut" class="field" placeholder="01"></div>
                        <div><label style="{{ $lbl }}">À (AAAA)</label><input type="number" wire:model="formations.{{ $i }}.annee_fin" class="field" placeholder="2015"></div>
                        <div><label style="{{ $lbl }}">MM</label><input type="number" wire:model="formations.{{ $i }}.mois_fin" class="field" placeholder="12"></div>
                        <div style="text-align:right"><button type="button" wire:click="retirerFormation({{ $i }})" class="btn btn-ghost" style="padding:6px 10px;font-size:12px;color:#b4341f;border-color:#e7c6bf">Retirer</button></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label style="{{ $lbl }}">Domaine d'études</label><input type="text" wire:model="formations.{{ $i }}.domaine" class="field" placeholder="Droit"></div>
                        <div><label style="{{ $lbl }}">École / Établissement</label><input type="text" wire:model="formations.{{ $i }}.etablissement" class="field" placeholder="UCAD"></div>
                        <div><label style="{{ $lbl }}">Ville</label><input type="text" wire:model="formations.{{ $i }}.ville" class="field" placeholder="Dakar"></div>
                    </div>
                    @error('formations.'.$i.'.annee_debut') <span style="{{ $err }}">{{ $message }}</span> @enderror
                    @error('formations.'.$i.'.domaine') <span style="{{ $err }}">{{ $message }}</span> @enderror
                    @error('formations.'.$i.'.etablissement') <span style="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            @empty
                <p style="color:var(--muted);font-size:13px;margin:0">Aucune formation saisie.</p>
            @endforelse
        </div>

        {{-- Expérience professionnelle --}}
        <div class="card" style="padding:18px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <h3 style="{{ $sec }};margin:0"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Expérience professionnelle</h3>
                <button type="button" wire:click="ajouterExperience" class="btn btn-ghost" style="padding:6px 11px;font-size:13px">+ Ajouter un emploi</button>
            </div>
            @forelse ($experiences as $i => $e)
                <div wire:key="ex-{{ $i }}" style="border:1px solid var(--line);border-radius:12px;padding:13px;margin-bottom:10px;background:var(--surface-2)">
                    <div style="display:grid;grid-template-columns:70px 56px 70px 56px 1fr;gap:8px;align-items:end">
                        <div><label style="{{ $lbl }}">De (AAAA)</label><input type="number" wire:model="experiences.{{ $i }}.annee_debut" class="field" placeholder="2011"></div>
                        <div><label style="{{ $lbl }}">MM</label><input type="number" wire:model="experiences.{{ $i }}.mois_debut" class="field" placeholder="02"></div>
                        <div><label style="{{ $lbl }}">À (AAAA)</label><input type="number" wire:model="experiences.{{ $i }}.annee_fin" class="field" placeholder="2015"></div>
                        <div><label style="{{ $lbl }}">MM</label><input type="number" wire:model="experiences.{{ $i }}.mois_fin" class="field" placeholder="03"></div>
                        <div style="text-align:right"><button type="button" wire:click="retirerExperience({{ $i }})" class="btn btn-ghost" style="padding:6px 10px;font-size:12px;color:#b4341f;border-color:#e7c6bf">Retirer</button></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label style="{{ $lbl }}">Activité / Profession</label><input type="text" wire:model="experiences.{{ $i }}.activite" class="field" placeholder="Enquêteur"></div>
                        <div><label style="{{ $lbl }}">Entreprise / Employeur</label><input type="text" wire:model="experiences.{{ $i }}.employeur" class="field" placeholder="Ministère de l'Intérieur"></div>
                        <div><label style="{{ $lbl }}">Ville</label><input type="text" wire:model="experiences.{{ $i }}.ville" class="field" placeholder="Dakar"></div>
                    </div>
                    @error('experiences.'.$i.'.annee_debut') <span style="{{ $err }}">{{ $message }}</span> @enderror
                    @error('experiences.'.$i.'.activite') <span style="{{ $err }}">{{ $message }}</span> @enderror
                    @error('experiences.'.$i.'.employeur') <span style="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            @empty
                <p style="color:var(--muted);font-size:13px;margin:0">Aucune expérience saisie.</p>
            @endforelse
        </div>

        {{-- Compte / rôle --}}
        @if ($agent && $agent->user)
            <div class="card" style="padding:18px">
                <h3 style="{{ $sec }}"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Compte utilisateur</h3>
                <label style="{{ $lbl }}">Rôle attribué</label>
                <select wire:model="role" class="field" style="max-width:280px">
                    <option value="agent">Agent</option>
                    <option value="chef_direction">Chef de direction</option>
                    <option value="admin_rh">Admin RH</option>
                    <option value="dg">Directeur Général</option>
                    <option value="secretaire">Secrétaire</option>
                    <option value="courrier">Agent courrier</option>
                    <option value="archiviste">Archiviste</option>
                </select>
                @error('role') <span style="{{ $err }}">{{ $message }}</span> @enderror
            </div>
        @elseif ($agent)
            <div class="card" style="padding:18px">
                <h3 style="{{ $sec }}"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>Compte utilisateur</h3>
                <p style="color:var(--muted);font-size:13px;margin:0">Aucun compte. Créez-le depuis la <strong>liste des agents</strong> (bouton « Créer le compte » en saisissant l'email) : l'agent recevra un mot de passe provisoire à changer à la première connexion.</p>
            </div>
        @endif

        {{-- Barre d'action --}}
        <div style="display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:linear-gradient(transparent,var(--paper) 30%);padding:14px 0 4px">
            <a href="{{ route('rh.agents.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary">
                <svg wire:loading.remove wire:target="save" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px"><path d="M20 6L9 17l-5-5"/></svg>
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
