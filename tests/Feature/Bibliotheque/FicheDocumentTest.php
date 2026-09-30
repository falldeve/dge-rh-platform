<?php

use App\Livewire\Bibliotheque\FicheDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function agentFiche(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
}

it('rend le markdown d’un document texte', function () {
    $d = Document::factory()->create(['source' => 'texte', 'contenu' => "## Article premier\n\nLe corps électoral.", 'titre' => 'Loi X']);

    Livewire::actingAs(agentFiche())->test(FicheDocument::class, ['document' => $d])
        ->assertSee('Article premier')
        ->assertSee('Le corps électoral')
        ->assertSee('Loi X');
});

it('expose l’URL fichier et le drapeau PDF pour une source fichier', function () {
    $d = Document::factory()->create(['source' => 'fichier', 'fichier_path' => 'bibliotheque/code.pdf', 'contenu' => null, 'titre' => 'Code']);

    Livewire::actingAs(agentFiche())->test(FicheDocument::class, ['document' => $d])
        ->assertSee('/storage/bibliotheque/code.pdf');
});

it('interdit l’accès à un invité', function () {
    $d = Document::factory()->create();
    $this->get(route('bibliotheque.document', $d))->assertRedirect('/login');
});

it('renvoie 404 pour un document inactif', function () {
    $d = Document::factory()->create(['actif' => false]);

    $this->actingAs(agentFiche())
        ->get(route('bibliotheque.document', $d))
        ->assertNotFound();
});
