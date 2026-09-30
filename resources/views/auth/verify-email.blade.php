<x-layouts.app>
    <div class="card" style="max-width:460px;margin:60px auto;padding:28px">
        <h1 style="font-size:22px;font-weight:700;margin:0 0 8px">Vérifiez votre email</h1>
        <p style="color:var(--muted);font-size:14px;margin:0 0 16px">
            Un lien de vérification a été envoyé à <strong>{{ auth()->user()->email }}</strong>.
            Cliquez-le pour activer votre compte. Pensez à vérifier vos spams.
        </p>
        @if (session('ok'))
            <div style="background:var(--green-soft);color:var(--green-deep);padding:10px 14px;border-radius:10px;font-size:14px;margin-bottom:14px">{{ session('ok') }}</div>
        @endif
        <div style="display:flex;gap:10px;align-items:center">
            <form method="POST" action="{{ route('verification.send') }}">@csrf
                <button type="submit" class="btn btn-primary">Renvoyer le lien</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button type="submit" class="btn btn-ghost">Déconnexion</button>
            </form>
        </div>
    </div>
</x-layouts.app>
