<div class="card">
    <h1>Assistant IA — gestion</h1>

    <form wire:submit="enregistrerPool" style="margin:1rem 0;">
        <label>Pool mensuel (crédits premium)</label>
        <input type="number" wire:model="pool" min="0">
        <button class="btn" type="submit">Enregistrer le pool</button>
        <p>Alloué : {{ $totalAlloue }} / {{ $pool }}</p>
        @error('pool') <span style="color:#b91c1c;">{{ $message }}</span> @enderror
    </form>

    <table>
        <thead><tr><th>Agent</th><th>Premium</th><th>Allocation</th><th>Consommé (mois)</th></tr></thead>
        <tbody>
        @foreach ($agents as $agent)
            <tr>
                <td>{{ $agent->name }}</td>
                <td>
                    <button class="btn" wire:click="basculerDrapeau({{ $agent->id }})">
                        {{ $agent->assistant_ia_actif ? 'Oui' : 'Non' }}
                    </button>
                </td>
                <td>
                    @if ($agent->assistant_ia_actif)
                        <input type="number" min="0" wire:model="allocations.{{ $agent->id }}">
                        <button class="btn" wire:click="enregistrerAllocation({{ $agent->id }})">OK</button>
                        @error("allocations.{$agent->id}") <span style="color:#b91c1c;">{{ $message }}</span> @enderror
                    @else — @endif
                </td>
                <td>{{ $agent->assistantCreditsConsommesMois() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
