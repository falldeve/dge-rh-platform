<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Espace RH — DGE' }}</title>
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
        body{ font-family:'Open Sans',ui-sans-serif,system-ui,sans-serif; background:var(--paper); color:var(--ink); }
        h1,h2,h3,.font-display{ font-family:'Open Sans',ui-sans-serif,system-ui,sans-serif; font-weight:700; letter-spacing:-.01em; }
        .rh-shell{ display:grid; grid-template-columns:250px 1fr; min-height:100vh; }
        @media (max-width:900px){ .rh-shell{ grid-template-columns:1fr; } .rh-aside{ display:none; } }
        .rh-aside{
            background:linear-gradient(180deg,var(--navy),#0f2545);
            color:#eaf0f7; padding:22px 16px; position:sticky; top:0; height:100vh;
        }
        .rh-brand{ display:flex; align-items:center; gap:10px; padding:6px 8px 20px; }
        .rh-brand .mark{ width:46px;height:46px;border-radius:11px;overflow:hidden;background:#fff;padding:3px;
            box-shadow:0 6px 18px -8px rgba(25,55,97,.5); }
        .rh-brand .mark img{ width:100%;height:100%;object-fit:contain;display:block; }
        .rh-brand small{ display:block;color:#9fb2cc;font-size:11px;letter-spacing:.14em;text-transform:uppercase; }
        .rh-nav a{ display:flex;align-items:center;gap:11px;padding:10px 12px;margin:2px 0;border-radius:10px;
            color:#c3d0e0;font-weight:500;font-size:14px;transition:.15s; }
        .rh-nav a:hover{ background:rgba(255,255,255,.08);color:#fff; }
        .rh-nav a.on{ background:rgba(48,153,102,.22);color:#fff;box-shadow:inset 3px 0 0 var(--green); }
        .rh-nav a svg{ width:18px;height:18px;opacity:.85;flex:none; }
        .rh-nav .sep{ margin:16px 8px 8px;color:#7e93b0;font-size:11px;letter-spacing:.14em;text-transform:uppercase; }
        .rh-main{ padding:30px 40px 60px; max-width:1600px; }
        @media (max-width:900px){ .rh-main{ padding:20px; } }
        .rh-topbar{ display:flex;align-items:center;justify-content:space-between;margin-bottom:26px; }
        .rh-user{ display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted); }
        .rh-user .av{ width:34px;height:34px;border-radius:50%;background:var(--green);color:#fff;
            display:grid;place-items:center;font-weight:600;font-size:13px; }
        .card{ background:var(--surface);border:1px solid var(--line);border-radius:16px;
            box-shadow:0 1px 0 rgba(25,55,97,.03),0 12px 30px -22px rgba(25,55,97,.25); }
        .badge{ display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;
            font-size:11px;font-weight:600;letter-spacing:.02em; }
        .field{ width:100%;border:1px solid var(--line);background:#fff;border-radius:10px;padding:9px 12px;font-size:14px;
            transition:.15s;color:var(--ink); }
        .field:focus{ outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(48,153,102,.16); }
        .field::placeholder{ color:#5f6a58; }
        .btn{ display:inline-flex;align-items:center;gap:7px;border-radius:10px;padding:9px 16px;font-size:14px;font-weight:600;
            cursor:pointer;transition:.15s;border:1px solid transparent; }
        .btn-primary{ background:var(--green);color:#fff;box-shadow:0 8px 18px -10px rgba(48,153,102,.6); }
        .btn-primary:hover{ background:var(--green-deep); }
        .btn-ghost{ background:#fff;border-color:var(--line);color:var(--ink); }
        .btn-ghost:hover{ border-color:var(--green);color:var(--green); }
        .btn-secondary{ background:var(--navy);color:#fff; }
        .btn-secondary:hover{ background:#122a4b; }
        @keyframes toastIn{ from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }
        @keyframes drawerIn{ from{transform:translateX(100%)} to{transform:none} }
        @keyframes fadeIn{ from{opacity:0} to{opacity:1} }
        .toast{ animation:toastIn .3s ease both; }
        [x-cloak]{ display:none!important; }
        /* Focus visible au clavier (jamais retirer sans remplacement) */
        .btn:focus-visible, .rh-nav a:focus-visible{ outline:2px solid var(--green); outline-offset:2px; }
        /* Feedback pendant une action Livewire : bouton occupé, non recliquable */
        [wire\:loading][wire\:target], .btn[wire\:loading]{ opacity:.65; cursor:progress; }
        button[disabled]{ opacity:.55; cursor:not-allowed; }
        @media (prefers-reduced-motion: reduce){
            *, *::before, *::after{ animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important; scroll-behavior:auto!important; }
        }
    </style>
</head>
<body class="antialiased">
    @php($__u = auth()->user())
    <div class="rh-shell">
        <aside class="rh-aside">
            <div class="rh-brand">
                <div class="mark">
                    <img src="/images/logo-dge.png" alt="Direction générale des Élections">
                </div>
                <div>
                    <div style="font-family:'Open Sans',sans-serif;font-weight:600;font-size:17px;line-height:1">DGE</div>
                    <small>Ressources Humaines</small>
                </div>
            </div>
            @php($ico = [
                'grid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
                'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M17 8.5a3 3 0 0 1 0 5"/><path d="M19 20a5 5 0 0 0-3-4.6"/></svg>',
                'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
                'car' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13v5h-2v-2H7v2H5z"/><circle cx="7.5" cy="16" r="1"/><circle cx="16.5" cy="16" r="1"/></svg>',
                'bank' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/></svg>',
                'cal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>',
                'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>',
                'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
                'livre' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
            ])
            <nav class="rh-nav">
                @if ($__u->isAdmin())
                    <a href="{{ route('admin.comptes') }}" class="{{ request()->routeIs('admin.comptes') ? 'on' : '' }}">{!! $ico['bank'] !!} Comptes système</a>
                @endif

                @if ($__u->isAdminRh() || $__u->isAdmin())
                    <a href="{{ route('rh.tableau-bord') }}" class="{{ request()->routeIs('rh.tableau-bord') ? 'on' : '' }}">{!! $ico['grid'] !!} Tableau de bord</a>
                    <a href="{{ route('rh.agents.index') }}" class="{{ request()->routeIs('rh.agents.*') ? 'on' : '' }}">{!! $ico['users'] !!} Agents</a>
                    <a href="{{ route('validation.rh') }}" class="{{ request()->routeIs('validation.rh') ? 'on' : '' }}">{!! $ico['check'] !!} Validation DRHF</a>
                    <a href="{{ route('rh.directions.index') }}" class="{{ request()->routeIs('rh.directions.*') ? 'on' : '' }}">{!! $ico['bank'] !!} Directions</a>
                    <a href="{{ route('rh.entites') }}" class="{{ request()->routeIs('rh.entites') ? 'on' : '' }}">{!! $ico['bank'] !!} Divisions & bureaux</a>
                    <a href="{{ route('rh.etat-conges') }}" class="{{ request()->routeIs('rh.etat-conges') ? 'on' : '' }}">{!! $ico['cal'] !!} État des congés</a>
                @endif

                @if ($__u->isDg())
                    <a href="{{ route('dg.tableau-bord') }}" class="{{ request()->routeIs('dg.tableau-bord') ? 'on' : '' }}">{!! $ico['grid'] !!} Synthèse</a>
                    <a href="{{ route('annuaire') }}" class="{{ request()->routeIs('annuaire','agents.profil') ? 'on' : '' }}">{!! $ico['users'] !!} Agents</a>
                @endif

                @if ($__u->isChefDirection())
                    <a href="{{ route('validation.chef') }}" class="{{ request()->routeIs('validation.chef') ? 'on' : '' }}">{!! $ico['check'] !!} À valider</a>
                    <a href="{{ route('annuaire') }}" class="{{ request()->routeIs('annuaire','agents.profil') ? 'on' : '' }}">{!! $ico['users'] !!} Ma direction</a>
                @endif

                @if ($__u->isSecretaire())
                    <a href="{{ route('missions.mes') }}" class="{{ request()->routeIs('missions.*') ? 'on' : '' }}">{!! $ico['car'] !!} Ordres de mission</a>
                @endif

                @if ($__u->isChefDirection() || $__u->isSecretaire() || $__u->gereEntite())
                    <a href="{{ route('mes-courriers') }}" class="{{ request()->routeIs('mes-courriers*') ? 'on' : '' }}">{!! $ico['cal'] !!} Mes courriers</a>
                @endif

                @if ($__u->isCourrier() || $__u->isAdmin())
                    <a href="{{ route('courriers.registre') }}" class="{{ request()->routeIs('courriers.registre','courriers.nouveau','courriers.fiche') ? 'on' : '' }}">{!! $ico['cal'] !!} Registre courrier</a>
                @endif

                @if ($__u->isArchiviste() || $__u->isAdmin())
                    <a href="{{ route('archives') }}" class="{{ request()->routeIs('archives*') ? 'on' : '' }}">{!! $ico['bank'] !!} Archives courrier</a>
                @endif

                <div class="sep">Personnel</div>
                <a href="{{ route('dashboard') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg> Mon espace</a>
                <a href="{{ route('assistant') }}" class="{{ request()->routeIs('assistant') ? 'on' : '' }}">{!! $ico['chat'] !!} Assistant IA</a>
                <a href="{{ route('bibliotheque') }}" class="{{ request()->routeIs('bibliotheque*') ? 'on' : '' }}">{!! $ico['livre'] !!} Bibliothèque</a>
                @if ($__u->isArchiviste() || $__u->isAdmin())
                    <a href="{{ route('bibliotheque.gerer.rubriques') }}" class="{{ request()->routeIs('bibliotheque.gerer.rubriques') ? 'on' : '' }}">{!! $ico['livre'] !!} <span>Gérer la bibliothèque</span></a>
                    <a href="{{ route('bibliotheque.gerer.documents') }}" class="{{ request()->routeIs('bibliotheque.gerer.documents') ? 'on' : '' }}">{!! $ico['livre'] !!} <span>Gérer les documents</span></a>
                @endif
                @if ($__u->isAdmin())
                    <a href="{{ route('admin.assistant') }}" class="{{ request()->routeIs('admin.assistant') ? 'on' : '' }}">{!! $ico['chat'] !!} Gérer l’assistant</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit" class="rh-nav" style="width:100%;background:none;border:none;text-align:left;cursor:pointer">
                        <span style="display:flex;align-items:center;gap:11px;padding:10px 12px;color:#c8d8cd;font-weight:500;font-size:14px">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M15 4h4v16h-4"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h11"/></svg> Déconnexion
                        </span>
                    </button>
                </form>
            </nav>
        </aside>

        <main class="rh-main">
            <div class="rh-topbar">
                <div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted)">Direction Générale des Élections</div>
                <div class="rh-user">
                    <span>{{ $__u->name }} · <strong style="color:var(--green)">{{ str_replace('_',' ',$__u->role) }}</strong></span>
                    <div class="av">{{ strtoupper(mb_substr($__u->name,0,1)) }}</div>
                </div>
            </div>

            @if (session('ok'))
                <div class="toast" x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,3500)"
                     style="margin-bottom:18px;display:flex;align-items:center;gap:10px;background:var(--green-soft);
                     border:1px solid #bcd6c4;color:var(--green-deep);padding:11px 15px;border-radius:12px;font-weight:600;font-size:14px">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="width:18px;height:18px"><path d="M20 6L9 17l-5-5"/></svg>
                    {{ session('ok') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
