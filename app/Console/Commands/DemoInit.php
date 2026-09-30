<?php

namespace App\Console\Commands;

use App\Models\Direction;
use App\Models\Entite;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DemoInit extends Command
{
    protected $signature = 'demo:init {--password=Demo@2026}';

    protected $description = 'Initialise les comptes de démonstration (logins vérifiés, rôles, chef DOE, Gustave secrétaire DOE).';

    /** matricule => [email, role, libellé] */
    private const COMPTES = [
        '636324/D' => ['biram.sene@dge.local', 'dg', 'Biram SENE (DG)'],
        '606832/E' => ['aziz.sarr@dge.local', 'chef_direction', 'Abdoul Aziz SARR (chef DOE)'],
        'GUEYE-DRHF' => ['astou.gueye@dge.local', 'admin_rh', 'Ndeye Astou GUEYE (DRH)'],
        '643312/A' => ['gustave.manga@dge.local', 'agent', 'Sicounfy Gustave MANGA (chef div. Suivi/Missions)'],
    ];

    public function handle(): int
    {
        $mdp = (string) $this->option('password');
        $agents = [];

        foreach (self::COMPTES as $matricule => [$email, $role, $libelle]) {
            $user = User::where('matricule', $matricule)->first();
            if (! $user) {
                $this->warn("  ✗ compte introuvable : $matricule ($libelle)");

                continue;
            }

            if (blank($user->email)) {
                $user->email = $email;
            }
            $user->email_verified_at = now();
            $user->compte_actif = true;
            $user->role = $role;
            $user->password = Hash::make($mdp);
            $user->save();

            $agents[$matricule] = $user->agent;
            $this->line(sprintf('  ✓ %-46s | mat=%-11s | %s', $libelle, $matricule, $user->email));
        }

        // Sarr = chef de la DOE (direction + entité).
        $sarr = $agents['606832/E'] ?? null;
        if ($sarr) {
            if ($dir = Direction::where('code', 'DOE')->first()) {
                $dir->update(['chef_id' => $sarr->id]);
                $this->line('  ✓ Direction DOE : chef = Abdoul Aziz SARR');
            }
            if ($ent = $this->entiteDoe()) {
                $ent->update(['chef_agent_id' => $sarr->id]);
                $this->line('  ✓ Entité DOE : chef = Abdoul Aziz SARR');
            }
        }

        // Gustave = chef de la Division Mission du DOE (reçoit les courriers imputés à sa division).
        $gustave = $agents['643312/A'] ?? null;
        if ($gustave) {
            if ($div = $this->divisionMission()) {
                $div->update(['chef_agent_id' => $gustave->id]);
                $this->line('  ✓ Division « '.$div->nom.' » : chef = Gustave MANGA (reçoit les courriers du DOE)');
            } else {
                $this->warn('  ✗ Division Mission introuvable — Gustave non rattaché.');
            }

            if ($gustave->solde_conge_jours < 5) {
                $gustave->update(['solde_conge_jours' => 30]);
            }
            $this->line('  ✓ Gustave : solde congé = '.$gustave->fresh()->solde_conge_jours.' j');
        }

        $this->newLine();
        $this->info("Comptes démo initialisés. Mot de passe commun : $mdp");
        $this->warn('2FA à désactiver séparément (DGE_2FA=false + config:cache) si voulu.');

        return self::SUCCESS;
    }

    private function entiteDoe(): ?Entite
    {
        return Entite::where('code', 'DOE')->where('type', 'direction')->first()
            ?? Entite::where('code', 'DOE')->first();
    }

    /** La Division Mission (Suivi des Opérations et Missions) du DOE. */
    private function divisionMission(): ?Entite
    {
        return Entite::where('type', 'division')
            ->where(fn ($q) => $q->where('code', 'DOE-SUIVI')
                ->orWhere('nom', 'like', '%Mission%')
                ->orWhere('nom', 'like', '%Suivi%'))
            ->first();
    }
}
