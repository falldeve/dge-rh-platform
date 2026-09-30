<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Demande;
use App\Models\Direction;
use Illuminate\Support\Collection;

class TableauBord
{
    /** @return array<string,int> */
    public static function parStatut(): array
    {
        $counts = Demande::selectRaw('statut, count(*) as c')->groupBy('statut')->pluck('c', 'statut')->toArray();

        $out = [];
        foreach (['soumise', 'validee_chef', 'validee_rh', 'refusee', 'emise'] as $s) {
            $out[$s] = (int) ($counts[$s] ?? 0);
        }

        return $out;
    }

    /** @return array<string,int> */
    public static function parType(): array
    {
        $counts = Demande::selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type')->toArray();

        $out = [];
        foreach (Demande::TYPES as $t) {
            $out[$t] = (int) ($counts[$t] ?? 0);
        }

        return $out;
    }

    public static function enAttenteRh(): int
    {
        return Demande::where('statut', Demande::STATUT_VALIDEE_CHEF)->count();
    }

    public static function enAttenteChef(int $directionId): int
    {
        return Demande::where('statut', Demande::STATUT_SOUMISE)
            ->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId))
            ->count();
    }

    /**
     * @return Collection<int, array{code:string, nom:string, agents:int, en_conge:int, taux:float}>
     */
    public static function parDirection(?string $refDate = null): Collection
    {
        $ref = $refDate ?? now()->toDateString();

        return Direction::orderBy('code')->get()->map(function (Direction $dir) use ($ref) {
            $agents = Agent::where('direction_id', $dir->id)->count();

            $enConge = Demande::where('type', 'conge_annuel')
                ->where('statut', Demande::STATUT_VALIDEE_RH)
                ->whereDate('date_debut', '<=', $ref)
                ->whereDate('date_fin', '>=', $ref)
                ->whereHas('agent', fn ($a) => $a->where('direction_id', $dir->id))
                ->distinct('agent_id')
                ->count('agent_id');

            $taux = $agents > 0 ? round($enConge / $agents * 100, 1) : 0.0;

            return [
                'code' => $dir->code,
                'nom' => $dir->nom,
                'agents' => $agents,
                'en_conge' => $enConge,
                'taux' => $taux,
            ];
        });
    }
}
