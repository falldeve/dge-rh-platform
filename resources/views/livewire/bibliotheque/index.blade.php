<div class="biblio">
    <style>
        .biblio{ --r:20px; --r-sm:14px; }

        /* ---------- Hero (mode A) ---------- */
        .biblio-hero{
            position:relative; overflow:hidden; border-radius:26px; padding:56px 40px 48px;
            background:
                radial-gradient(120% 140% at 12% 0%, rgba(48,153,102,.42), transparent 55%),
                radial-gradient(90% 120% at 100% 100%, rgba(224,169,46,.20), transparent 50%),
                linear-gradient(155deg, var(--navy), #0d1f3c 85%);
            color:#fff; box-shadow:0 30px 60px -32px rgba(15,37,69,.55);
            animation:biblioUp .7s cubic-bezier(.32,.72,0,1) both;
        }
        .biblio-hero::after{ content:""; position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.5'/%3E%3C/svg%3E");
            opacity:.04; pointer-events:none; }
        .biblio-eyebrow{ display:inline-flex; align-items:center; gap:8px; padding:5px 13px; border-radius:999px;
            background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
            font-size:11px; letter-spacing:.22em; text-transform:uppercase; font-weight:600; color:#eaf4ee; }
        .biblio-hero h1{ margin:18px 0 8px; font-size:clamp(30px,4vw,46px); font-weight:800; letter-spacing:-.02em; color:#fff; line-height:1.05; }
        .biblio-hero p.lead{ margin:0 0 26px; color:#c6d5e8; font-size:15px; max-width:520px; }
        .biblio-search{ position:relative; max-width:660px; padding:6px; border-radius:16px;
            background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.16);
            box-shadow:inset 0 1px 1px rgba(255,255,255,.14); }
        .biblio-search svg{ position:absolute; left:22px; top:50%; transform:translateY(-50%); width:19px; height:19px; color:var(--muted); pointer-events:none; }
        .biblio-search input{ width:100%; border:0; border-radius:11px; padding:14px 18px 14px 46px; font-size:15px;
            background:#fff; color:var(--ink); transition:box-shadow .2s ease; }
        .biblio-search input:focus{ outline:none; box-shadow:0 0 0 3px rgba(48,153,102,.35); }
        .biblio-search input:focus-visible{ outline:2px solid var(--green); outline-offset:2px; }

        .biblio-sectitle{ margin:34px 4px 14px; font-size:12px; letter-spacing:.16em; text-transform:uppercase; color:var(--muted); font-weight:700; }

        /* ---------- Rubrique tiles (mode A) ---------- */
        .biblio-tiles{ display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
        .tile{ text-align:left; cursor:pointer; background:var(--surface); border:1px solid var(--line); border-radius:var(--r);
            padding:0; overflow:hidden; display:flex; flex-direction:column; width:100%; font:inherit;
            transition:transform .25s cubic-bezier(.32,.72,0,1), box-shadow .25s ease, border-color .2s ease;
            box-shadow:0 10px 26px -22px rgba(25,55,97,.5); animation:biblioUp .6s cubic-bezier(.32,.72,0,1) both; }
        .tile:hover{ transform:translateY(-3px); box-shadow:0 26px 46px -26px rgba(25,55,97,.55); border-color:#c3d3e0; }
        .tile:focus-visible{ outline:2px solid var(--green); outline-offset:2px; }
        .tile.on{ border-color:var(--green); box-shadow:0 20px 40px -24px rgba(48,153,102,.55); }
        .tile-cover{ position:relative; height:98px; background-size:cover; background-position:center; overflow:hidden; transition:transform .35s cubic-bezier(.32,.72,0,1); }
        .tile-cover::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg,rgba(13,31,60,.12),rgba(13,31,60,.64)); transition:opacity .25s ease; }
        .tile:hover .tile-cover{ transform:scale(1.03); }
        .tile.on .tile-cover::after{ background:linear-gradient(180deg,rgba(37,120,79,.2),rgba(37,120,79,.72)); }
        .tile-ico{ position:absolute; left:13px; bottom:11px; z-index:1; width:40px; height:40px; border-radius:12px;
            display:flex; align-items:center; justify-content:center; color:#fff;
            background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.30); backdrop-filter:blur(6px); }
        .tile-ico svg{ width:20px; height:20px; }
        .tile-body{ padding:14px 16px 16px; display:flex; flex-direction:column; gap:4px; }
        .tile-name{ font-weight:700; font-size:15px; color:var(--ink); line-height:1.25; }
        .tile-count{ font-size:12px; color:var(--muted); font-weight:600; font-variant-numeric:tabular-nums; }
        .cov-code{ background:linear-gradient(135deg,#309966,#155e3c); }
        .cov-lois{ background:linear-gradient(135deg,#254c86,#0d2140); }
        .cov-decrets{ background:linear-gradient(135deg,#2b6cb0,#173a5e); }
        .cov-ordonnances{ background:linear-gradient(135deg,#7c5cbf,#3b2a63); }
        .cov-cena{ background:linear-gradient(135deg,#e0a92e,#9a6a10); }
        .cov-textes{ background:linear-gradient(135deg,#3a7d5c,#1d4733); }
        .cov-archives{ background:linear-gradient(135deg,#5b6b7d,#2b3746); }
        .cov-all{ background:linear-gradient(135deg,#309966,#193761); }
        .cov-default{ background:linear-gradient(135deg,#4f5e6e,#293643); }

        /* ---------- Document cards (shared: search results + rubrique page) ---------- */
        .biblio-results{ display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }
        .biblio-doc-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
        .doc{ display:flex; flex-direction:column; gap:10px; background:var(--surface); border:1px solid var(--line); border-radius:var(--r);
            padding:20px; text-decoration:none; color:inherit; position:relative; overflow:hidden; cursor:pointer;
            transition:transform .25s cubic-bezier(.32,.72,0,1), box-shadow .25s ease, border-color .2s ease;
            box-shadow:0 12px 30px -24px rgba(25,55,97,.5); animation:biblioUp .5s cubic-bezier(.32,.72,0,1) both; }
        .doc::before{ content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--green); transform:scaleY(0); transform-origin:top; transition:transform .3s cubic-bezier(.32,.72,0,1); }
        .doc:hover{ transform:translateY(-3px); box-shadow:0 26px 46px -28px rgba(25,55,97,.55); border-color:#c3d3e0; }
        .doc:hover::before{ transform:scaleY(1); }
        .doc:focus-visible{ outline:2px solid var(--green); outline-offset:2px; }
        .doc-icon{ width:34px; height:34px; border-radius:10px; background:var(--green-soft); color:var(--green-deep);
            display:flex; align-items:center; justify-content:center; flex:none; }
        .doc-icon svg{ width:17px; height:17px; }
        .doc-tags{ display:flex; flex-wrap:wrap; gap:6px; }
        .tag{ display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.02em; text-transform:capitalize; }
        .tag-rub{ background:rgba(25,55,97,.08); color:var(--navy); }
        .tag-type{ background:var(--green-soft); color:var(--green-deep); }
        .tag-annee{ background:var(--surface-2); color:var(--muted); font-variant-numeric:tabular-nums; }
        .doc h3{ margin:0; font-size:16px; font-weight:700; line-height:1.3; color:var(--ink);
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .doc-ref{ font-size:12.5px; color:var(--muted); font-weight:600; }
        .doc-resume{ font-size:13.5px; color:var(--muted); line-height:1.55; overflow:hidden;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .doc-go{ margin-top:auto; display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--green-deep); }
        .doc-go .arr{ width:22px; height:22px; border-radius:50%; background:var(--green-soft); display:inline-flex; align-items:center; justify-content:center; transition:transform .3s cubic-bezier(.32,.72,0,1); }
        .doc:hover .doc-go .arr{ transform:translateX(3px); }

        .biblio-empty{ grid-column:1/-1; text-align:center; padding:56px 20px; color:var(--muted); animation:biblioUp .5s cubic-bezier(.32,.72,0,1) both; }
        .biblio-empty .ico{ width:60px;height:60px;border-radius:18px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;background:var(--green-soft);color:var(--green-deep); }
        .biblio-empty .ico svg{ width:26px; height:26px; }
        .biblio-empty code{ background:var(--surface-2); padding:3px 8px; border-radius:7px; font-size:13px; color:var(--navy); }

        /* ---------- Rubrique page (mode B) ---------- */
        .biblio-back{ margin-bottom:22px; border-radius:999px; padding:10px 18px 10px 14px; gap:8px;
            box-shadow:0 8px 20px -14px rgba(25,55,97,.35); transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease, color .2s ease;
            animation:biblioUp .5s cubic-bezier(.32,.72,0,1) both; }
        .biblio-back svg{ transition:transform .2s ease; }
        .biblio-back:hover{ transform:translateY(-2px); box-shadow:0 14px 26px -16px rgba(25,55,97,.4); }
        .biblio-back:hover svg{ transform:translateX(-2px); }

        .biblio-page-header{ margin:0 0 34px; animation:biblioUp .6s cubic-bezier(.32,.72,0,1) both; }
        .biblio-page-header h1{ margin:0 0 8px; font-size:clamp(26px,3vw,34px); font-weight:800; letter-spacing:-.02em; color:var(--navy); line-height:1.15; }
        .biblio-page-sub{ margin:0; font-size:14px; color:var(--muted); font-weight:600; font-variant-numeric:tabular-nums; }

        .biblio-year-section{ margin-bottom:34px; animation:biblioUp .6s cubic-bezier(.32,.72,0,1) both; }
        .biblio-year-chip{ display:inline-flex; align-items:center; gap:6px; margin:0 0 16px; padding:7px 16px;
            border-radius:999px; background:var(--green-soft); color:var(--green-deep);
            font-size:13px; font-weight:800; letter-spacing:.02em; font-variant-numeric:tabular-nums; }
        .biblio-year-chip .n{ font-weight:600; opacity:.75; }

        @keyframes biblioUp{ from{ opacity:0; transform:translateY(16px); } to{ opacity:1; transform:translateY(0); } }
        @media (max-width:768px){ .biblio-hero{ padding:38px 22px; } }
        @media (prefers-reduced-motion: reduce){
            .biblio *, .biblio *::before, .biblio *::after{ animation:none!important; transition:none!important; }
        }
    </style>

    @php
        $bookIcon = '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>';
        $folderIcon = '<path d="M3 7.5A2 2 0 0 1 5 5.5h4.2l2 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-11z"/>';
        $gridIcon = '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>';
    @endphp

    @if (! $rubriqueId)
        {{-- MODE A — landing : hero + rubriques + (optionnel) résultats de recherche --}}
        <header class="biblio-hero">
            <span class="biblio-eyebrow">Encyclopédie électorale</span>
            <h1>Bibliothèque électorale</h1>
            <p class="lead">Lois, décrets, ordonnances, rapports et archives des élections sénégalaises — recherchez par thème ou par mot-clé.</p>
            <div class="biblio-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" wire:model.live.debounce.300ms="recherche"
                       aria-label="Rechercher dans la bibliothèque"
                       placeholder="Rechercher une loi, un décret, un thème…">
            </div>
        </header>

        @php
            $covMap = [
                'code-electoral' => 'cov-code', 'lois' => 'cov-lois',
                'decrets-reglements' => 'cov-decrets', 'ordonnances' => 'cov-ordonnances',
                'rapports-cena' => 'cov-cena', 'textes-historiques-jo-1960-1982' => 'cov-textes',
                'archives' => 'cov-archives',
            ];
        @endphp
        <div class="biblio-sectitle">Rubriques</div>
        <section class="biblio-tiles">
            <button type="button" class="tile on" wire:click="choisirRubrique(null)" style="animation-delay:0ms">
                <span class="tile-cover cov-all"><span class="tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $gridIcon !!}</svg></span></span>
                <span class="tile-body">
                    <span class="tile-name">Toutes les rubriques</span>
                    <span class="tile-count">{{ $rubriques->sum('documents_count') }} documents</span>
                </span>
            </button>
            @foreach ($rubriques as $r)
                @php $img = $r->coverUrl(); @endphp
                <button type="button" class="tile" wire:click="choisirRubrique({{ $r->id }})" style="animation-delay:{{ min($loop->iteration, 9) * 40 }}ms">
                    <span class="tile-cover {{ $img ? '' : ($covMap[$r->slug] ?? 'cov-default') }}" @if($img) style="background-image:url('{{ $img }}')" @endif>
                        <span class="tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $folderIcon !!}</svg></span>
                    </span>
                    <span class="tile-body">
                        <span class="tile-name">{{ $r->nom }}</span>
                        <span class="tile-count">{{ $r->documents_count }} document{{ $r->documents_count > 1 ? 's' : '' }}</span>
                    </span>
                </button>
            @endforeach
        </section>

        @if ($recherche !== '')
            <div class="biblio-sectitle">Résultats</div>
            <section class="biblio-results">
                @forelse ($documents as $doc)
                    <a class="doc" href="{{ route('bibliotheque.document', $doc) }}" wire:navigate style="animation-delay:{{ min($loop->iteration, 9) * 40 }}ms">
                        <span class="doc-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $bookIcon !!}</svg></span>
                        <span class="doc-tags">
                            @if ($doc->rubrique)<span class="tag tag-rub">{{ $doc->rubrique->nom }}</span>@endif
                            <span class="tag tag-type">{{ $doc->type }}</span>
                        </span>
                        <h3>{{ $doc->titre }}</h3>
                        @if ($doc->reference)<span class="doc-ref">{{ $doc->reference }}</span>@endif
                        @if ($doc->resume)<p class="doc-resume">{{ Str::limit($doc->resume, 150) }}</p>@endif
                        <span class="doc-go">Consulter <span class="arr" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span></span>
                    </a>
                @empty
                    <div class="biblio-empty">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $bookIcon !!}</svg>
                        </div>
                        <p>Aucun document pour l'instant.<br>Lancez <code>php artisan bibliotheque:importer</code> pour charger le corpus.</p>
                    </div>
                @endforelse
            </section>

            <div style="margin-top:22px;">{{ $documents->links() }}</div>
        @endif
    @else
        {{-- MODE B — page dédiée à la rubrique sélectionnée --}}
        @php $totalDocuments = ($groupesAnnee ?? collect())->sum(fn ($g) => $g->count()); @endphp

        <button type="button" class="btn btn-ghost biblio-back" wire:click="choisirRubrique(null)" aria-label="Retour à la liste des rubriques">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            Toutes les rubriques
        </button>

        <header class="biblio-page-header">
            <h1>{{ $rubriqueCourante->nom ?? 'Rubrique' }}</h1>
            <p class="biblio-page-sub">{{ $totalDocuments }} document{{ $totalDocuments > 1 ? 's' : '' }}</p>
        </header>

        <div class="biblio-annees">
            @forelse (($groupesAnnee ?? collect()) as $annee => $docs)
                <section class="biblio-year-section">
                    <div class="biblio-year-chip">{{ $annee }} <span class="n">· {{ $docs->count() }}</span></div>
                    <div class="biblio-doc-grid">
                        @foreach ($docs as $doc)
                            <a class="doc" href="{{ route('bibliotheque.document', $doc) }}" wire:navigate style="animation-delay:{{ min($loop->iteration, 9) * 40 }}ms">
                                <span class="doc-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $bookIcon !!}</svg></span>
                                <span class="doc-tags">
                                    <span class="tag tag-type">{{ $doc->type }}</span>
                                    @if ($doc->annee)<span class="tag tag-annee">{{ $doc->annee }}</span>@endif
                                </span>
                                <h3>{{ $doc->titre }}</h3>
                                @if ($doc->reference)<span class="doc-ref">{{ $doc->reference }}</span>@endif
                                @if ($doc->resume)<p class="doc-resume">{{ Str::limit($doc->resume, 150) }}</p>@endif
                                <span class="doc-go">Consulter <span class="arr" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span></span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="biblio-empty">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $bookIcon !!}</svg>
                    </div>
                    <p>Aucun document dans cette rubrique pour l'instant.</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
