<?php

use App\Livewire\Bibliotheque\Index;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentBiblio(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
}

it('liste les documents actifs et filtre par recherche', function () {
    $r = Rubrique::factory()->create(['nom' => 'Lois']);
    Document::factory()->for($r)->create(['titre' => 'Loi sur le parrainage', 'mots_cles' => 'parrainage', 'actif' => true]);
    Document::factory()->for($r)->create(['titre' => 'Décret budget', 'actif' => true]);
    Document::factory()->create(['titre' => 'Document inactif', 'actif' => false]);

    Livewire::actingAs(agentBiblio())->test(Index::class)
        ->set('recherche', 'parrainage')
        ->assertSee('Loi sur le parrainage')
        ->assertDontSee('Décret budget')
        ->assertDontSee('Document inactif');
});

it('filtre par rubrique', function () {
    $lois = Rubrique::factory()->create(['nom' => 'Lois']);
    $decrets = Rubrique::factory()->create(['nom' => 'Décrets']);
    Document::factory()->for($lois)->create(['titre' => 'Une loi']);
    Document::factory()->for($decrets)->create(['titre' => 'Un décret']);

    Livewire::actingAs(agentBiblio())->test(Index::class)
        ->set('rubriqueId', $lois->id)
        ->assertSee('Une loi')
        ->assertDontSee('Un décret');
});
