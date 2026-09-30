<div class="card" style="padding:30px">
    <h1 style="font-size:22px;font-weight:600;margin:0 0 4px">Connexion</h1>
    <p style="color:var(--muted);font-size:13px;margin:0 0 22px">Accédez à votre espace RH DGE</p>

    <form wire:submit="login" style="display:flex;flex-direction:column;gap:16px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Matricule / NIN</label>
            <input type="text" wire:model="matricule" class="field" autofocus>
            @error('matricule') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Mot de passe</label>
            <input type="password" wire:model="password" class="field">
            @error('password') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">Se connecter</button>
    </form>

    <p style="margin-top:18px;font-size:13px;color:var(--muted);text-align:center">
        <a href="{{ route('password.request') }}" style="color:var(--green);font-weight:600;text-decoration:none">Mot de passe oublié ?</a>
    </p>
</div>
