<?php

namespace Database\Seeders;

use App\Models\Entite;
use Illuminate\Database\Seeder;

class EntiteSeeder extends Seeder
{
    public function run(): void
    {
        $directions = [
            'DG' => 'Direction Générale',
            'DOE' => 'Direction des Opérations Électorales',
            'DFC' => 'Direction de la Formation et de la Communication',
            'DRHF' => 'Direction des Ressources Humaines et des Finances',
            'SI' => 'Service Informatique',
        ];
        foreach ($directions as $code => $nom) {
            Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'direction', 'parent_id' => null]);
        }

        $services = [
            'SP' => 'Secrétariat Particulier',
            'BDA' => 'Bureau de la Documentation et des Archives',
            'BCI' => 'Bureau de la Coopération internationale',
            'BC' => 'Bureau du Courrier',
        ];
        foreach ($services as $code => $nom) {
            Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'service', 'parent_id' => null]);
        }

        $divisions = [
            'DOE' => [
                'DOE-CARTE' => 'Division Carte électorale et fichiers',
                'DOE-JUR' => 'Division des Études et des Affaires juridiques',
                'DOE-SUIVI' => 'Division Suivi Opérations et Missions',
                'DOE-LOG' => 'Division logistique',
            ],
            'DFC' => [
                'DFC-FORM' => 'Division de la Formation permanente',
                'DFC-RP' => 'Division des Relations publiques et de la Communication',
            ],
        ];
        foreach ($divisions as $parentCode => $items) {
            $parent = Entite::where('code', $parentCode)->first();
            foreach ($items as $code => $nom) {
                Entite::updateOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'division', 'parent_id' => $parent?->id]);
            }
        }
    }
}
