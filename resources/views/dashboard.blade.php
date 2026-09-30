<x-layouts.app :title="'Tableau de bord'">
    @php($__u = auth()->user())
    @php($__agent = $__u->agent)

    <div style="display:flex;flex-direction:column;gap:22px">
        <div>
            <h1 style="font-size:30px;font-weight:600;margin:0">Bonjour {{ $__u->name }}</h1>
            <p style="color:var(--muted);font-size:14px;margin:4px 0 0">
                Rôle : <span class="badge" style="background:var(--green-soft);color:var(--green-deep)">{{ str_replace('_',' ',$__u->role) }}</span>
            </p>
        </div>

        {{-- Cartes statistiques --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
            @if ($__agent)
                <div class="card" style="padding:18px">
                    <div style="font-size:13px;color:var(--muted)">Solde de congé</div>
                    <div style="font-family:'Open Sans',sans-serif;font-size:28px;font-weight:600;color:var(--green-deep)">{{ rtrim(rtrim((string) $__agent->solde_conge_jours, '0'), '.') }} <span style="font-size:15px;color:var(--muted)">jours</span></div>
                </div>
                <div class="card" style="padding:18px">
                    <div style="font-size:13px;color:var(--muted)">Demandes en cours</div>
                    <div style="font-family:'Open Sans',sans-serif;font-size:28px;font-weight:600">{{ $__agent->demandes()->whereIn('statut', ['soumise', 'validee_chef'])->count() }}</div>
                </div>
            @endif
            @if ($__u->isChefDirection() && $__agent)
                <div class="card" style="padding:18px">
                    <div style="font-size:13px;color:var(--muted)">À valider (ma direction)</div>
                    <div style="font-family:'Open Sans',sans-serif;font-size:28px;font-weight:600;color:var(--gold)">{{ \App\Support\TableauBord::enAttenteChef($__agent->direction_id) }}</div>
                </div>
            @endif
        </div>

        {{-- Accès rapides --}}
        <div>
            <h2 style="font-size:15px;font-weight:600;color:var(--green-deep);margin:0 0 12px">Accès rapides</h2>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                @php($tuile = 'display:flex;align-items:center;gap:10px;padding:14px 18px;text-decoration:none;color:var(--ink);font-weight:600;font-size:14px')
                <a href="{{ route('demandes.mes') }}" class="card" style="{{ $tuile }}">📄 Mes demandes</a>
                @if ($__u->isDg() || $__u->isChefDirection() || $__u->isAdminRh())
                    <a href="{{ route('annuaire') }}" class="card" style="{{ $tuile }}">🔎 Annuaire des agents</a>
                @endif
                @if ($__u->isChefDirection())
                    <a href="{{ route('validation.chef') }}" class="card" style="{{ $tuile }}">✅ File de validation (chef)</a>
                @endif
                @if ($__u->isAdminRh())
                    <a href="{{ route('validation.rh') }}" class="card" style="{{ $tuile }}">✅ File de validation (DRHF)</a>
                    <a href="{{ route('rh.agents.index') }}" class="card" style="{{ $tuile }}">👥 Espace RH</a>
                @endif
                @if ($__u->isSecretaire())
                    <a href="{{ route('missions.mes') }}" class="card" style="{{ $tuile }}">🚗 Ordres de mission</a>
                @endif
                @if ($__u->isDg())
                    <a href="{{ route('dg.tableau-bord') }}" class="card" style="{{ $tuile }}">📊 Synthèse (DG)</a>
                @endif
            </div>
        </div>

        <livewire:notifications.cloche />
    </div>
</x-layouts.app>
