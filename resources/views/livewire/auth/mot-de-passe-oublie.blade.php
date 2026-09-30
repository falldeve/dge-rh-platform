<div class="card" style="padding:30px">
    <h1 style="font-size:22px;font-weight:600;margin:0 0 4px">Mot de passe oublié</h1>
    <p style="color:var(--muted);font-size:13px;margin:0 0 22px">Saisissez votre email, nous vous enverrons un lien de réinitialisation.</p>

    @if (session('ok'))
        <div style="background:var(--green-soft);color:var(--green-deep);padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:14px">{{ session('ok') }}</div>
    @endif

    <form wire:submit="envoyer" style="display:flex;flex-direction:column;gap:16px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Email</label>
            <input type="email" wire:model="email" class="field" autofocus>
            @error('email') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="envoyer" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
            <span wire:loading.remove wire:target="envoyer">Envoyer le lien</span>
            <span wire:loading wire:target="envoyer">Envoi…</span>
        </button>
    </form>

    <p style="margin-top:18px;font-size:13px;color:var(--muted);text-align:center">
        <a href="{{ route('login') }}" style="color:var(--green);font-weight:600;text-decoration:none">Retour à la connexion</a>
    </p>
</div>
