<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'RH — DGE' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=open-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root{
            /* Charte DGB : vert #309966, bleu marine #193761, gris-bleu clair */
            --ink:#1c2b3a; --paper:#e8edf2; --surface:#ffffff; --surface-2:#eef2f6;
            --green:#309966; --green-deep:#25784f; --green-soft:#e2f1e9;
            --navy:#193761; --gold:#e0a92e; --line:#d3dbe4; --muted:#4f5e6e;
        }
        body{ font-family:'Open Sans',ui-sans-serif,system-ui,sans-serif; background:var(--paper); color:var(--ink); min-height:100vh; }
        h1,h2,h3,.font-display{ font-family:'Open Sans',ui-sans-serif,system-ui,sans-serif; font-weight:700; letter-spacing:-.01em; }
        .app-top{ background:linear-gradient(90deg,var(--navy),#0f2545); color:#eaf0f7; }
        .app-top-in{ max-width:940px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:13px 22px; }
        .app-brand{ display:flex;align-items:center;gap:10px;font-family:'Open Sans',sans-serif;font-weight:700; }
        .app-brand .mark{ width:42px;height:42px;border-radius:10px;overflow:hidden;background:#fff;padding:2px;border:1px solid rgba(0,0,0,.06);box-shadow:0 4px 12px -6px rgba(25,55,97,.4); }
        .app-brand .mark img{ width:100%;height:100%;object-fit:contain;display:block; }
        .app-brand small{ display:block;color:#9fb2cc;font-size:10px;letter-spacing:.14em;text-transform:uppercase;font-family:'Open Sans',sans-serif;font-weight:600 }
        .app-user{ display:flex;align-items:center;gap:12px;font-size:13px }
        .app-nav{ display:flex;gap:4px;flex-wrap:wrap }
        .app-nav a{ color:#c6d5e8;text-decoration:none;font-size:14px;font-weight:600;padding:8px 13px;border-radius:10px;transition:background .2s,color .2s }
        .app-nav a:hover{ background:rgba(255,255,255,.10);color:#fff }
        .app-nav a.on{ background:rgba(48,153,102,.28);color:#fff }
        @media(max-width:680px){ .app-top-in{ flex-wrap:wrap;gap:10px } .app-nav a{ padding:7px 10px;font-size:13px } }
        .app-logout{ background:rgba(255,255,255,.12);color:#eaf0f7;border:none;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;cursor:pointer }
        .app-logout:hover{ background:rgba(255,255,255,.2) }
        .card{ background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 0 rgba(25,55,97,.03),0 12px 30px -22px rgba(25,55,97,.28); }
        .badge{ display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600 }
        .field{ width:100%;border:1px solid var(--line);background:#fff;border-radius:10px;padding:9px 12px;font-size:14px;color:var(--ink) }
        .field:focus{ outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(48,153,102,.16) }
        .btn{ display:inline-flex;align-items:center;gap:7px;border-radius:10px;padding:9px 16px;font-size:14px;font-weight:600;cursor:pointer;transition:.15s;border:1px solid transparent }
        .btn-primary{ background:var(--green);color:#fff;box-shadow:0 8px 18px -10px rgba(48,153,102,.6) }
        .btn-primary:hover{ background:var(--green-deep) }
        .btn-ghost{ background:#fff;border-color:var(--line);color:var(--ink) }
        .btn-secondary{ background:var(--navy);color:#fff }
        .btn-secondary:hover{ background:#122a4b }
        @keyframes toastIn{ from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }
        .toast{ animation:toastIn .3s ease both }
    </style>
</head>
<body class="antialiased">
    @auth
        <header class="app-top">
            <div class="app-top-in">
                <a href="{{ route('dashboard') }}" class="app-brand" style="color:#eaf2ec">
                    <span class="mark"><img src="/images/logo-dge.png" alt="Direction générale des Élections"></span>
                    <span>DGE<small>Ressources Humaines</small></span>
                </a>
                <nav class="app-nav">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">Tableau de bord</a>
                    <a href="{{ route('bibliotheque') }}" class="{{ request()->routeIs('bibliotheque*') ? 'on' : '' }}">Bibliothèque</a>
                    <a href="{{ route('assistant') }}" class="{{ request()->routeIs('assistant') ? 'on' : '' }}">Assistant IA</a>
                </nav>
                <div class="app-user">
                    <span>{{ auth()->user()->name }} · <strong style="color:var(--gold)">{{ str_replace('_',' ',auth()->user()->role) }}</strong></span>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="app-logout">Déconnexion</button>
                    </form>
                </div>
            </div>
        </header>
        <main style="max-width:1200px;margin:0 auto;padding:30px 24px 60px">
            @if (session('ok'))
                <div class="toast" style="margin-bottom:18px;display:flex;align-items:center;gap:10px;background:var(--green-soft);border:1px solid #bcd6c4;color:var(--green-deep);padding:11px 15px;border-radius:12px;font-weight:600;font-size:14px">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px"><path d="M20 6L9 17l-5-5"/></svg>
                    {{ session('ok') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    @else
        <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px">
            <img src="/images/logo-dge.png" alt="Direction générale des Élections" style="height:108px;width:auto;margin-bottom:22px">
            <div style="width:100%;max-width:420px">{{ $slot }}</div>
        </div>
    @endauth
    @livewireScripts
</body>
</html>
