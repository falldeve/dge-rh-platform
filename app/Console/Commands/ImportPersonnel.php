<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Console\Command;

class ImportPersonnel extends Command
{
    protected $signature = 'rh:import-personnel {--file=database/data/personnel_dge.json}';
    protected $description = 'Importe le personnel DGE depuis le fichier JSON source';

    public function handle(): int
    {
        $path = base_path($this->option('file'));
        if (! file_exists($path)) {
            $this->error("Fichier introuvable : {$path}");
            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($path), true);
        if (! is_array($records)) {
            $this->error('JSON invalide.');
            return self::FAILURE;
        }

        $directions = Direction::pluck('id', 'code');
        $siId = $directions['SI'] ?? null;
        $imported = 0;

        foreach ($records as $r) {
            $code = $r['direction'] ?? null;
            $directionId = $directions[$code] ?? null;
            if (! $directionId) {
                $this->warn("Direction inconnue « {$code} » — agent ignoré : {$r['prenoms']} {$r['noms']}");
                continue;
            }

            $haystack = strtolower(($r['fonction'] ?? '').' '.($r['profession'] ?? ''));
            if ($siId && str_contains($haystack, 'informatique')) {
                $directionId = $siId;
            }

            $key = ! empty($r['matricule'])
                ? ['matricule' => $r['matricule']]
                : ['prenoms' => $r['prenoms'], 'noms' => $r['noms']];

            Agent::updateOrCreate($key, [
                'prenoms' => $r['prenoms'],
                'noms' => $r['noms'],
                'matricule' => $r['matricule'] ?? null,
                'profession' => $r['profession'] ?? null,
                'fonction' => $r['fonction'] ?? null,
                'direction_id' => $directionId,
                'statut' => 'autre',
                'solde_conge_jours' => 30,
            ]);
            $imported++;
        }

        $this->info("Import terminé : {$imported} agents.");
        return self::SUCCESS;
    }
}
