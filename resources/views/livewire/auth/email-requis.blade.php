<div class="card" style="padding:26px">
    <h1 style="font-size:22px;font-weight:700;margin:0 0 6px">Ajoutez votre email</h1>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">Pour sécuriser votre compte, renseignez une adresse email. Un lien de vérification vous y sera envoyé.</p>
    <form wire:submit="enregistrer" style="display:flex;flex-direction:column;gap:14px">
        <div>
            <input type="email" wire:model="email" class="field" placeholder="prenom.nom@dge.sn">
            @error('email') <span style="display:block;color:#b4341f;font-size:12px;margin-top:4px">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer et vérifier</button>
    </form>
</div>
