<div class="card" style="padding:30px">
    @php($lbl='display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px')
    @php($er='display:block;color:#b4341f;font-size:12px;margin-top:4px')
    <h1 style="font-size:22px;font-weight:600;margin:0 0 4px">Création de compte</h1>
    <p style="color:var(--muted);font-size:13px;margin:0 0 22px">Agent DGE — saisissez vos informations d'identification</p>

    <form wire:submit="register" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <label style="{{ $lbl }}">Matricule / NIN</label>
            <input type="text" wire:model="matricule" class="field">
            @error('matricule') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
                <label style="{{ $lbl }}">Nom</label>
                <input type="text" wire:model="noms" class="field">
                @error('noms') <span style="{{ $er }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label style="{{ $lbl }}">Prénom(s)</label>
                <input type="text" wire:model="prenoms" class="field">
                @error('prenoms') <span style="{{ $er }}">{{ $message }}</span> @enderror
            </div>
        </div>
        <div>
            <label style="{{ $lbl }}">Email professionnel</label>
            <input type="email" wire:model="email" class="field" placeholder="prenom.nom@dge.sn">
            @error('email') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="{{ $lbl }}">Mot de passe</label>
            <input type="password" wire:model="password" class="field">
            @error('password') <span style="{{ $er }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="{{ $lbl }}">Confirmer le mot de passe</label>
            <input type="password" wire:model="password_confirmation" class="field">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">Créer mon compte</button>
    </form>

    <p style="margin-top:18px;font-size:13px;color:var(--muted);text-align:center">
        Déjà un compte ?
        <a href="{{ route('login') }}" style="color:var(--green);font-weight:600;text-decoration:none">Se connecter</a>
    </p>
</div>
