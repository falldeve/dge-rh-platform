<div class="fiche">
    <style>
        .fiche{ max-width:1000px; margin:0 auto; animation:ficheUp .6s cubic-bezier(.32,.72,0,1) both; }
        @keyframes ficheUp{ from{ opacity:0; transform:translateY(16px); } to{ opacity:1; transform:translateY(0); } }
        .fiche-back{ display:inline-flex; align-items:center; gap:8px; padding:7px 14px; border-radius:999px;
            background:var(--surface); border:1px solid var(--line); color:var(--muted); text-decoration:none;
            font-size:13px; font-weight:600; transition:transform .4s cubic-bezier(.32,.72,0,1), border-color .3s, color .3s; }
        .fiche-back:hover{ transform:translateX(-3px); border-color:var(--green); color:var(--green-deep); }

        .fiche-head{ position:relative; overflow:hidden; border-radius:24px; padding:34px 34px 30px; margin:16px 0 18px;
            background:radial-gradient(110% 130% at 0% 0%, rgba(48,153,102,.30), transparent 55%), linear-gradient(155deg,var(--navy),#0d1f3c);
            color:#fff; box-shadow:0 26px 52px -32px rgba(15,37,69,.55); }
        .fiche-tags{ display:flex; flex-wrap:wrap; gap:7px; margin-bottom:14px; }
        .fiche-tags .t{ padding:4px 11px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize;
            background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.16); color:#eaf4ee; }
        .fiche-head h1{ margin:0; color:#fff; font-size:clamp(21px,2.6vw,30px); font-weight:800; line-height:1.22; letter-spacing:-.01em; }
        .fiche-meta{ display:flex; flex-wrap:wrap; gap:16px; margin-top:14px; color:#c6d5e8; font-size:13px; font-weight:600; }
        .fiche-meta .ref{ color:var(--gold); }
        .fiche-resume{ margin:14px 0 0; color:#d5e0ef; font-size:14.5px; line-height:1.6; max-width:70ch; }

        .fiche-body{ background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:8px;
            box-shadow:0 16px 40px -30px rgba(25,55,97,.5); }
        .prose{ padding:26px clamp(20px,4vw,44px); max-width:74ch; margin:0 auto; color:var(--ink); font-size:15.5px; line-height:1.72; }
        .prose h1,.prose h2,.prose h3,.prose h4{ font-weight:800; letter-spacing:-.01em; color:var(--navy); line-height:1.3; margin:1.7em 0 .5em; }
        .prose h1{ font-size:1.5em; } .prose h2{ font-size:1.28em; } .prose h3{ font-size:1.12em; }
        .prose h2{ padding-bottom:.3em; border-bottom:1px solid var(--line); }
        .prose p{ margin:0 0 1.05em; } .prose ul,.prose ol{ margin:0 0 1.1em; padding-left:1.4em; } .prose li{ margin:.3em 0; }
        .prose strong{ color:var(--navy); } .prose em{ color:var(--green-deep); }
        .prose a{ color:var(--green-deep); text-decoration:underline; text-underline-offset:2px; }
        .prose blockquote{ margin:1.2em 0; padding:.4em 1.1em; border-left:3px solid var(--green); background:var(--green-soft); border-radius:0 10px 10px 0; color:var(--green-deep); }
        .prose hr{ border:0; border-top:1px solid var(--line); margin:2em 0; }
        .prose:first-child > :first-child{ margin-top:0; }

        .fiche-pdf{ padding:8px; }
        .fiche-pdf iframe{ width:100%; height:78vh; border:0; border-radius:16px; background:var(--surface-2); box-shadow:inset 0 1px 2px rgba(25,55,97,.12); }
        .fiche-file-actions{ display:flex; gap:12px; padding:16px 8px 8px; flex-wrap:wrap; }
        .btn-island{ display:inline-flex; align-items:center; gap:10px; padding:11px 14px 11px 20px; border-radius:999px;
            background:var(--green); color:#fff; text-decoration:none; font-weight:700; font-size:14px;
            box-shadow:0 12px 24px -12px rgba(48,153,102,.7); transition:transform .4s cubic-bezier(.32,.72,0,1), background .3s; }
        .btn-island:hover{ background:var(--green-deep); } .btn-island:active{ transform:scale(.98); }
        .btn-island .ic{ width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,.18); display:inline-flex; align-items:center; justify-content:center; transition:transform .4s cubic-bezier(.32,.72,0,1); }
        .btn-island:hover .ic{ transform:translate(2px,-1px); }
        .fiche-none{ padding:48px; text-align:center; color:var(--muted); }
    </style>

    <a class="fiche-back" href="{{ route('bibliotheque') }}" wire:navigate>← Bibliothèque</a>

    <header class="fiche-head">
        <div class="fiche-tags">
            @if ($document->rubrique)<span class="t">{{ $document->rubrique->nom }}</span>@endif
            <span class="t">{{ $document->type }}</span>
        </div>
        <h1>{{ $document->titre }}</h1>
        <div class="fiche-meta">
            @if ($document->reference)<span class="ref">{{ $document->reference }}</span>@endif
            @if ($document->date_document)<span>{{ $document->date_document->format('d/m/Y') }}</span>@endif
        </div>
        @if ($document->resume)<p class="fiche-resume">{{ $document->resume }}</p>@endif
    </header>

    <section class="fiche-body">
        @if ($html)
            <article class="prose">{!! $html !!}</article>
        @elseif ($document->source === 'fichier' && $document->fichierUrl())
            <div class="fiche-pdf">
                @if ($document->estPdf())
                    <iframe src="{{ $document->fichierUrl() }}"></iframe>
                @endif
                <div class="fiche-file-actions">
                    <a class="btn-island" href="{{ $document->fichierUrl() }}" download>
                        Télécharger <span class="ic">↓</span>
                    </a>
                </div>
            </div>
        @elseif ($document->source === 'lien' && $document->url)
            <div class="fiche-file-actions">
                <a class="btn-island" href="{{ $document->url }}" target="_blank" rel="noopener">
                    Ouvrir le lien <span class="ic">↗</span>
                </a>
            </div>
        @else
            <p class="fiche-none">Contenu non disponible.</p>
        @endif
    </section>
</div>
