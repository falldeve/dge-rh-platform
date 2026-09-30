<div style="display:flex;flex-direction:column;gap:20px">
    @php($mois = ['','janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'])
    @php($periode = function ($ad, $md, $af, $mf) use ($mois) {
        $d = ($md ? $mois[$md].' ' : '').$ad;
        $f = $af ? (($mf ? $mois[$mf].' ' : '').$af) : "aujourd'hui";
        return $d.' → '.$f;
    })

    <a href="{{ url()->previous() }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Retour</a>

    {{-- En-tête profil --}}
    <div class="card" style="padding:24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        @if ($agent->photo_path)
            <img src="{{ $agent->photoUrl() }}" style="width:150px;height:150px;border-radius:20px;object-fit:cover">
        @else
            <div style="width:150px;height:150px;border-radius:20px;background:var(--green-soft);color:var(--green-deep);display:grid;place-items:center;font-family:'Open Sans',sans-serif;font-weight:600;font-size:48px">
                {{ strtoupper(mb_substr($agent->prenoms,0,1).mb_substr($agent->noms,0,1)) }}
            </div>
        @endif
        <div style="flex:1;min-width:220px">
            <h1 style="font-size:26px;font-weight:600;margin:0">{{ $agent->prenoms }} {{ $agent->noms }}</h1>
            <div style="color:var(--muted);font-size:14px;margin-top:3px">{{ $agent->fonction ?? '—' }}{{ $agent->profession ? ' · '.$agent->profession : '' }}</div>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <span class="badge" style="background:#eef2f7;color:#334">{{ $agent->direction?->code }} — {{ $agent->direction?->nom }}</span>
                @if ($agent->estFonctionnaire())
                    <span class="badge" style="background:#e7efe8;color:#1c6b45">Fonctionnaire</span>
                @else
                    <span class="badge" style="background:#f6ecdd;color:#8a5a1e">Non-fonctionnaire</span>
                @endif
                <span class="badge" style="background:var(--surface-2);color:var(--muted)">{{ $agent->matricule ?? 'Sans matricule' }}</span>
            </div>
        </div>
    </div>

    {{-- Informations --}}
    <div class="card" style="padding:20px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Informations</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;font-size:14px">
            @php($info = fn($l,$v) => '<div><div style="font-size:12px;color:var(--muted)">'.$l.'</div><div style="font-weight:500">'.($v ?: '—').'</div></div>')
            {!! $info('Téléphone', e($agent->telephone)) !!}
            {!! $info('Email', e($agent->email)) !!}
            {!! $info('Statut', e(ucfirst(str_replace('_',' ',$agent->statut)))) !!}
            {!! $info('Solde de congé', e(rtrim(rtrim((string)$agent->solde_conge_jours,'0'),'.')).' jours') !!}
            {!! $info('Date de naissance', $agent->date_naissance?->format('d/m/Y')) !!}
            {!! $info('Prise de service', $agent->date_prise_service?->format('d/m/Y')) !!}
        </div>
    </div>

    {{-- Scolarité --}}
    <div class="card" style="padding:20px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Scolarité</h2>
        @forelse ($agent->formations as $f)
            <div style="display:flex;gap:14px;padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                <div style="width:150px;flex:none;color:var(--muted);font-size:13px;padding-top:2px">{{ $periode($f->annee_debut, $f->mois_debut, $f->annee_fin, $f->mois_fin) }}</div>
                <div>
                    <div style="font-weight:600">{{ $f->domaine }}</div>
                    <div style="color:#41504a;font-size:13px">{{ $f->etablissement }}{{ $f->ville ? ' — '.$f->ville : '' }}</div>
                </div>
            </div>
        @empty
            <p style="color:var(--muted);font-size:13px;margin:0">Aucune formation enregistrée.</p>
        @endforelse
    </div>

    {{-- Expérience --}}
    <div class="card" style="padding:20px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 14px">Expérience professionnelle</h2>
        @forelse ($agent->experiences as $e)
            <div style="display:flex;gap:14px;padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
                <div style="width:150px;flex:none;color:var(--muted);font-size:13px;padding-top:2px">{{ $periode($e->annee_debut, $e->mois_debut, $e->annee_fin, $e->mois_fin) }}</div>
                <div>
                    <div style="font-weight:600">{{ $e->activite }}</div>
                    <div style="color:#41504a;font-size:13px">{{ $e->employeur }}{{ $e->ville ? ' — '.$e->ville : '' }}</div>
                </div>
            </div>
        @empty
            <p style="color:var(--muted);font-size:13px;margin:0">Aucune expérience enregistrée.</p>
        @endforelse
    </div>
</div>
