<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Vérification en deux étapes</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Saisissez le code à 6 chiffres envoyé à votre adresse email.</p>

    @if (session('ok'))
        <div style="background:var(--green-soft);color:var(--green-deep);padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:14px">{{ session('ok') }}</div>
    @endif

    <form wire:submit="verifier" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="field"
                   style="letter-spacing:8px;text-align:center;font-size:22px" placeholder="______">
            @error('code') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px">
            <input type="checkbox" wire:model="remember_device"> Se souvenir de cet appareil (30 jours)
        </label>
        <button type="submit" wire:loading.attr="disabled" wire:target="verifier" class="btn btn-primary">
            <span wire:loading.remove wire:target="verifier">Vérifier</span>
            <span wire:loading wire:target="verifier">Vérification…</span>
        </button>
    </form>

    <div style="margin-top:14px;font-size:13px">
        <button type="button" wire:click="renvoyer" style="background:none;border:none;color:var(--green);cursor:pointer;padding:0">Renvoyer le code</button>
    </div>
</div>
