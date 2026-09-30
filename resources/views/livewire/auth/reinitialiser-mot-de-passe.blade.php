<div class="card" style="padding:30px">
    <h1 style="font-size:22px;font-weight:600;margin:0 0 4px">Réinitialiser le mot de passe</h1>
    <p style="color:var(--muted);font-size:13px;margin:0 0 22px">Choisissez un nouveau mot de passe pour votre compte.</p>

    <form wire:submit="reinitialiser" style="display:flex;flex-direction:column;gap:16px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Email</label>
            <input type="email" wire:model="email" class="field" autofocus>
            @error('email') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Nouveau mot de passe</label>
            <input type="password" wire:model="password" class="field">
            @error('password') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Confirmer le mot de passe</label>
            <input type="password" wire:model="password_confirmation" class="field">
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="reinitialiser" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
            <span wire:loading.remove wire:target="reinitialiser">Réinitialiser</span>
            <span wire:loading wire:target="reinitialiser">Réinitialisation…</span>
        </button>
    </form>

    <p style="margin-top:18px;font-size:13px;color:var(--muted);text-align:center">
        <a href="{{ route('login') }}" style="color:var(--green);font-weight:600;text-decoration:none">Retour à la connexion</a>
    </p>
</div>
