<?php

namespace Database\Seeders;

use App\Models\Direction;
use Illuminate\Database\Seeder;

class DirectionSeeder extends Seeder
{
    public function run(): void
    {
        $directions = [
            ['code' => 'DG',   'nom' => 'Direction Générale'],
            ['code' => 'DOE',  'nom' => 'Direction des Opérations Électorales'],
            ['code' => 'DRHF', 'nom' => 'Direction des Ressources Humaines et des Finances'],
            ['code' => 'DFC',  'nom' => 'Direction de la Formation et de la Communication'],
            ['code' => 'SI',   'nom' => 'Service Informatique'],
        ];

        foreach ($directions as $d) {
            Direction::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}
