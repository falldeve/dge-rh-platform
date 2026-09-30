@php $pct = $allocation > 0 ? min(100, (int) round($creditsRestants / max(1,$allocation) * 100)) : 0; @endphp
<div class="ia">
    <style>
        .ia{ display:grid; grid-template-columns:290px 1fr; gap:18px; align-items:start; }
        .ia-rail{ position:sticky; top:16px; display:flex; flex-direction:column; gap:16px;
            background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:18px;
            box-shadow:0 16px 40px -30px rgba(25,55,97,.5); animation:iaUp .6s cubic-bezier(.32,.72,0,1) both; }
        .ia-new{ display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%;
            padding:12px 14px 12px 18px; border:0; cursor:pointer; border-radius:14px;
            background:var(--green); color:#fff; font-weight:700; font-size:14px;
            box-shadow:0 12px 24px -12px rgba(48,153,102,.7); transition:transform .4s cubic-bezier(.32,.72,0,1), background .3s; }
        .ia-new:hover{ background:var(--green-deep); } .ia-new:active{ transform:scale(.98); }
        .ia-new .ic{ width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px; }

        .ia-plan{ border:1px solid var(--line); border-radius:16px; padding:14px; background:var(--surface-2); }
        .ia-tier{ display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .ia-tier.prem{ background:var(--green-soft); color:var(--green-deep); }
        .ia-tier.grat{ background:rgba(25,55,97,.09); color:var(--navy); }
        .ia-credits{ margin-top:11px; font-size:12.5px; color:var(--muted); font-weight:600; display:flex; justify-content:space-between; }
        .ia-credits b{ color:var(--ink); }
        .ia-gauge{ margin-top:8px; height:7px; border-radius:999px; background:#dbe3ec; overflow:hidden; }
        .ia-gauge i{ display:block; height:100%; width:{{ $pct }}%; border-radius:999px;
            background:linear-gradient(90deg,var(--green),var(--green-deep)); transition:width .6s cubic-bezier(.32,.72,0,1); }

        .ia-convs{ display:flex; flex-direction:column; gap:4px; max-height:52vh; overflow:auto; }
        .ia-convs .lbl{ font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); font-weight:700; margin:4px 4px 6px; }
        .ia-conv{ text-align:left; width:100%; border:0; cursor:pointer; background:transparent; color:var(--ink);
            padding:10px 12px; border-radius:11px; font-size:13.5px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
            transition:background .25s; }
        .ia-conv:hover{ background:var(--surface-2); }
        .ia-conv.on{ background:var(--green-soft); color:var(--green-deep); }
        .ia-empty-convs{ font-size:12.5px; color:var(--muted); padding:8px 4px; }

        .ia-chat{ display:flex; flex-direction:column; min-height:calc(100dvh - 150px);
            background:var(--surface); border:1px solid var(--line); border-radius:22px; overflow:hidden;
            box-shadow:0 16px 40px -30px rgba(25,55,97,.5); animation:iaUp .6s cubic-bezier(.32,.72,0,1) both; }
        .ia-chead{ display:flex; align-items:center; gap:12px; padding:16px 22px; border-bottom:1px solid var(--line); }
        .ia-chead .dot{ width:34px;height:34px;border-radius:11px;background:linear-gradient(150deg,var(--green),var(--green-deep));display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px; }
        .ia-chead h2{ margin:0; font-size:15px; font-weight:700; }
        .ia-chead small{ color:var(--muted); font-size:12px; }

        .ia-alert{ margin:16px 22px 0; display:flex; align-items:center; gap:9px; padding:11px 15px; border-radius:12px;
            background:#fdecec; border:1px solid #f3c6c6; color:#b3261e; font-weight:600; font-size:13.5px; }

        .ia-fil{ flex:1; overflow:auto; padding:22px; display:flex; flex-direction:column; gap:16px; }
        .msg{ max-width:78%; display:flex; flex-direction:column; gap:5px; animation:iaUp .4s cubic-bezier(.32,.72,0,1) both; }
        .msg .who{ font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); }
        .msg .bubble{ padding:13px 16px; border-radius:16px; font-size:14.5px; line-height:1.6; white-space:pre-wrap; }
        .msg-user{ align-self:flex-end; align-items:flex-end; }
        .msg-user .bubble{ background:linear-gradient(150deg,var(--green),var(--green-deep)); color:#fff; border-bottom-right-radius:5px; box-shadow:0 12px 24px -16px rgba(48,153,102,.7); }
        .msg-assistant{ align-self:flex-start; }
        .msg-assistant .bubble{ background:var(--surface-2); border:1px solid var(--line); color:var(--ink); border-bottom-left-radius:5px; }
        [wire\:stream="reponse-en-cours"]:not(:empty){ align-self:flex-start; max-width:78%; padding:13px 16px; border-radius:16px; border-bottom-left-radius:5px;
            background:var(--surface-2); border:1px solid var(--line); color:var(--ink); font-size:14.5px; line-height:1.6; white-space:pre-wrap; }

        .ia-welcome{ margin:auto; text-align:center; max-width:440px; padding:24px; }
        .ia-welcome .orb{ width:64px;height:64px;border-radius:20px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;
            background:radial-gradient(120% 120% at 30% 20%, var(--green), var(--green-deep)); color:#fff; font-size:28px; box-shadow:0 18px 34px -18px rgba(48,153,102,.7); }
        .ia-welcome h3{ margin:0 0 6px; font-size:19px; font-weight:800; }
        .ia-welcome p{ margin:0 0 18px; color:var(--muted); font-size:14px; }
        .ia-chips{ display:flex; flex-wrap:wrap; gap:8px; justify-content:center; }
        .ia-chip{ padding:8px 14px; border-radius:999px; background:var(--surface); border:1px solid var(--line); color:var(--ink);
            font-size:12.5px; font-weight:600; cursor:pointer; transition:transform .4s cubic-bezier(.32,.72,0,1), border-color .3s, color .3s; }
        .ia-chip:hover{ transform:translateY(-2px); border-color:var(--green); color:var(--green-deep); }

        .ia-composer{ border-top:1px solid var(--line); padding:14px 16px; }
        .ia-inputwrap{ display:flex; align-items:flex-end; gap:10px; padding:8px; border-radius:18px;
            background:var(--surface-2); border:1px solid var(--line); box-shadow:inset 0 1px 1px rgba(255,255,255,.6);
            transition:border-color .3s, box-shadow .3s; }
        .ia-inputwrap:focus-within{ border-color:var(--green); box-shadow:0 0 0 3px rgba(48,153,102,.16); }
        .ia-inputwrap textarea{ flex:1; border:0; background:transparent; resize:none; font-family:inherit; font-size:14.5px; line-height:1.5;
            padding:8px 10px; color:var(--ink); max-height:160px; }
        .ia-inputwrap textarea:focus{ outline:none; }
        .ia-send{ flex:none; width:42px; height:42px; border-radius:13px; border:0; cursor:pointer;
            background:var(--green); color:#fff; font-size:18px; display:flex; align-items:center; justify-content:center;
            box-shadow:0 10px 20px -10px rgba(48,153,102,.7); transition:transform .4s cubic-bezier(.32,.72,0,1), background .3s; }
        .ia-send:hover{ background:var(--green-deep); } .ia-send:active{ transform:scale(.94); }
        .ia-send[disabled]{ opacity:.55; cursor:progress; }

        .ia-joindre{ flex:none; width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;
            cursor:pointer;background:var(--surface);border:1px solid var(--line);font-size:18px; transition:border-color .3s; }
        .ia-joindre:hover{ border-color:var(--green); }
        .ia-pending{ display:flex;flex-wrap:wrap;gap:6px;padding:0 8px 8px; }
        .ia-pj{ display:flex;flex-wrap:wrap;gap:6px;margin-top:6px; }
        .chip{ display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:var(--surface-2);border:1px solid var(--line);font-size:12px;color:var(--muted); }

        .ia-sources{ display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:8px; }
        .ia-sources .lbl{ font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted); }
        .ia-sources .chip{ display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:var(--green-soft);border:1px solid #bcd6c4;color:var(--green-deep);font-size:12px;text-decoration:none;transition:transform .3s; }
        .ia-sources .chip:hover{ transform:translateY(-1px); }

        @keyframes iaUp{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
        @media (max-width:900px){ .ia{ grid-template-columns:1fr; } .ia-rail{ position:static; } }
    </style>

    <aside class="ia-rail">
        <button type="button" class="ia-new" wire:click="nouvelleConversation">
            Nouvelle conversation <span class="ic">+</span>
        </button>

        <div class="ia-plan">
            <span class="ia-tier {{ $estPremium ? 'prem' : 'grat' }}">{{ $estPremium ? '★ Premium' : 'Gratuit' }}</span>
            <div class="ia-credits"><span>Crédits ce mois</span><span><b>{{ $creditsRestants }}</b> / {{ $allocation }}</span></div>
            <div class="ia-gauge"><i></i></div>
        </div>

        <div class="ia-convs">
            <div class="lbl">Conversations</div>
            @forelse ($conversations as $c)
                <button type="button" class="ia-conv @if($conversationId === $c->id) on @endif" wire:click="choisir({{ $c->id }})">{{ $c->titre ?? 'Sans titre' }}</button>
            @empty
                <div class="ia-empty-convs">Aucune conversation.</div>
            @endforelse
        </div>
    </aside>

    <section class="ia-chat">
        <div class="ia-chead">
            <span class="dot">✦</span>
            <div>
                <h2>Assistant IA — DGE</h2>
                <small>{{ $estPremium ? 'Modèle avancé · fichiers, web, textes officiels' : 'Assistant de base' }}</small>
            </div>
        </div>

        @if ($erreur)
            <div class="ia-alert">⚠ {{ $erreur }}</div>
        @endif

        <div class="ia-fil">
            @if ($conversation)
                @foreach ($conversation->messages as $m)
                    <div class="msg msg-{{ $m->role }}">
                        <span class="who">{{ $m->role === 'user' ? 'Vous' : 'Assistant' }}</span>
                        <div class="bubble">{{ collect($m->contenu)->pluck('text')->implode('') }}</div>
                        @if ($m->piecesJointes->isNotEmpty())
                            <div class="ia-pj">
                                @foreach ($m->piecesJointes as $pj)
                                    <span class="chip">📄 {{ $pj->nom_original }}</span>
                                @endforeach
                            </div>
                        @endif
                        @php($src = collect($m->contenu)->firstWhere('type', 'sources'))
                        @if ($m->role === 'assistant' && $src)
                            <div class="ia-sources">
                                <span class="lbl">Sources</span>
                                @foreach ($src['documents'] as $d)
                                    <a href="{{ route('bibliotheque.document', $d['id']) }}" wire:navigate class="chip">📄 {{ $d['titre'] }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                <div wire:stream="reponse-en-cours"></div>
            @else
                <div class="ia-welcome">
                    <div class="orb">✦</div>
                    <h3>Comment puis-je aider ?</h3>
                    <p>Posez une question pour vos tâches : rédaction, synthèse, traduction, analyse de textes électoraux.</p>
                    <div class="ia-chips">
                        <button type="button" class="ia-chip" wire:click="$set('saisie', 'Résume les grandes étapes du processus électoral au Sénégal.')">Résumer un processus</button>
                        <button type="button" class="ia-chip" wire:click="$set('saisie', 'Rédige une note de service pour la DGE.')">Rédiger une note</button>
                        <button type="button" class="ia-chip" wire:click="$set('saisie', 'Explique la procédure de parrainage des candidats.')">Expliquer une procédure</button>
                    </div>
                </div>
                <div wire:stream="reponse-en-cours"></div>
            @endif
        </div>

        <form class="ia-composer" wire:submit="envoyer">
            @if ($fichiers)
                <div class="ia-pending">
                    @foreach ($fichiers as $i => $f)
                        <span class="chip">{{ $f->getClientOriginalName() }}</span>
                    @endforeach
                </div>
            @endif
            @error('fichiers.*') <div class="ia-alert">{{ $message }}</div> @enderror
            <div class="ia-inputwrap">
                <label class="ia-joindre" title="Joindre un document">
                    <input type="file" wire:model="fichiers" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.docx,.xlsx" style="display:none">
                    <span>📎</span>
                </label>
                <textarea wire:model="saisie" rows="1" placeholder="Écrivez votre demande…"
                          x-data x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"></textarea>
                <button class="ia-send" type="submit" wire:loading.attr="disabled" title="Envoyer">↑</button>
            </div>
        </form>
    </section>
</div>
