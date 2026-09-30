<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CourrierDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('password');

        $courrierU = User::updateOrCreate(['matricule' => 'COURRIER'], ['name' => 'Awa DIOP (Courrier)', 'password' => $pw, 'role' => 'courrier']);
        User::updateOrCreate(['matricule' => 'ARCHIVES'], ['name' => 'Modou FALL (Archives)', 'password' => $pw, 'role' => 'archiviste']);

        $chefU = User::updateOrCreate(['matricule' => 'CHEF-DOE'], ['name' => 'Ibrahima BA (Dir. DOE)', 'password' => $pw, 'role' => 'chef_direction']);
        $chefA = Agent::updateOrCreate(['matricule' => 'CHEF-DOE'], ['prenoms' => 'Ibrahima', 'noms' => 'BA', 'direction_id' => 2, 'statut' => 'autre', 'solde_conge_jours' => 30, 'user_id' => $chefU->id]);

        $secU = User::updateOrCreate(['matricule' => 'SEC-DOE'], ['name' => 'Khady SOW (Sec. DOE)', 'password' => $pw, 'role' => 'secretaire']);
        $secA = Agent::updateOrCreate(['matricule' => 'SEC-DOE'], ['prenoms' => 'Khady', 'noms' => 'SOW', 'direction_id' => 2, 'statut' => 'autre', 'solde_conge_jours' => 30, 'user_id' => $secU->id]);

        Entite::where('code', 'DOE')->update(['chef_agent_id' => $chefA->id, 'secretaire_agent_id' => $secA->id]);
        $doe = Entite::where('code', 'DOE')->first();

        $c = Courrier::updateOrCreate(['numero' => 'DEMO-001'], [
            'objet' => 'Organisation du scrutin — instructions',
            'expediteur' => "Ministère de l'Intérieur",
            'date_arrivee' => '2026-07-15',
            'date_depart' => '2026-07-16',
            'enregistre_par' => $courrierU->id,
        ]);
        $imp = Imputation::updateOrCreate(
            ['courrier_id' => $c->id, 'niveau' => 'dg'],
            ['mentions' => ['execution', 'urgent'], 'observations' => 'À traiter en priorité.', 'signataire_nom' => 'Biram SENE', 'saisi_par' => $courrierU->id]
        );
        $imp->destinataires()->sync([$doe->id]);
    }
}
