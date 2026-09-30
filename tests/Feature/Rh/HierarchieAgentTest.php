<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('trie par hiérarchie sans faux positifs', function () {
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $mk = fn ($noms, $fonction) => Agent::create(['prenoms' => 'X', 'noms' => $noms, 'direction_id' => $dir->id, 'statut' => 'autre', 'solde_conge_jours' => 0, 'fonction' => $fonction]);

    $mk('ZEBRE', 'Chauffeur');                                  // rang 4
    $mk('AGBUREAU', 'Agent bureau courrier');                   // rang 4 (pas chef)
    $mk('SECDIR', 'Secrétaire du Directeur Général');           // rang 4 (pas un directeur)
    $mk('BUREAU', 'Chef de bureau Statistique');                // rang 3
    $mk('DIRECTEUR', 'Directeur des Opérations électorales');   // rang 1
    $mk('DIVISION', 'Chef Division Carte électorale');          // rang 2

    $ordre = Agent::parHierarchie()->pluck('noms')->all();

    // rang1 DIRECTEUR, rang2 DIVISION, rang3 BUREAU, rang4 alpha: AGBUREAU, SECDIR, ZEBRE
    expect($ordre)->toBe(['DIRECTEUR', 'DIVISION', 'BUREAU', 'AGBUREAU', 'SECDIR', 'ZEBRE']);
});
