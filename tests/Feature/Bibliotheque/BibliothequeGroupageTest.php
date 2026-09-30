<?php

use App\Livewire\Bibliotheque\Index;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('groupe les documents d une rubrique par année décroissante', function () {
    $u = User::factory()->create();
    $r = Rubrique::create(['nom' => 'Décrets', 'slug' => 'decrets', 'ordre' => 5, 'actif' => true]);
    Document::create(['titre' => 'Décret A', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => 2012, 'actif' => true]);
    Document::create(['titre' => 'Décret B', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => 2019, 'actif' => true]);
    Document::create(['titre' => 'Décret C', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => null, 'actif' => true]);

    Livewire::actingAs($u)->test(Index::class)
        ->set('rubriqueId', $r->id)
        ->assertViewHas('groupesAnnee', function ($g) {
            $cles = $g->keys()->all();

            return $cles[0] === 2019 && $cles[1] === 2012 && end($cles) === 'Non daté';
        });
});

it('expose la rubrique courante en mode page', function () {
    $u = User::factory()->create();
    $r = Rubrique::create(['nom' => 'Décrets', 'slug' => 'decrets', 'ordre' => 5, 'actif' => true]);
    Document::create(['titre' => 'Décret X', 'rubrique_id' => $r->id, 'type' => 'decret', 'source' => 'fichier', 'annee' => 2020, 'actif' => true]);

    Livewire::actingAs($u)->test(Index::class)
        ->set('rubriqueId', $r->id)
        ->assertViewHas('rubriqueCourante', fn ($rc) => $rc !== null && $rc->id === $r->id)
        ->assertViewHas('groupesAnnee', fn ($g) => $g->keys()->contains(2020));
});
