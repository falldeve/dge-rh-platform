<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Définir votre mot de passe</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Choisissez un nouveau mot de passe pour sécuriser votre compte.</p>
    <form wire:submit="changer" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Nouveau mot de passe</label>
            <input type="password" wire:model="password" class="field">
            @error('password') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Confirmer</label>
            <input type="password" wire:model="password_confirmation" class="field">
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</div>
