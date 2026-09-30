<?php

use App\Livewire\Courrier\Registre;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('trie le registre par numéro asc/desc via trier()', function () {
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'role' => 'courrier']);
    Courrier::create(['numero' => 'A-1', 'objet' => 'x', 'expediteur' => 'y', 'date_arrivee' => '2026-07-01', 'enregistre_par' => $bc->id]);
    Courrier::create(['numero' => 'Z-9', 'objet' => 'x', 'expediteur' => 'y', 'date_arrivee' => '2026-07-02', 'enregistre_par' => $bc->id]);

    Livewire::actingAs($bc)->test(Registre::class)
        ->call('trier', 'numero')
        ->assertSet('sortField', 'numero')
        ->assertSet('sortDir', 'asc')
        ->assertSeeInOrder(['A-1', 'Z-9'])
        ->call('trier', 'numero') // re-clic => desc
        ->assertSet('sortDir', 'desc')
        ->assertSeeInOrder(['Z-9', 'A-1']);
});
