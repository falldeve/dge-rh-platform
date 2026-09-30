<?php

use App\Livewire\Bibliotheque\Gestion\Rubriques;
use App\Models\Rubrique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function archiviste(): User
{
    return User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('interdit la gestion à un agent (403)', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque/gerer/rubriques')->assertForbidden();
});

it('l’archiviste crée une rubrique avec slug auto', function () {
    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Jurisprudence')
        ->set('description', 'Décisions')
        ->call('enregistrer');

    $r = Rubrique::where('nom', 'Jurisprudence')->firstOrFail();
    expect($r->slug)->toBe('jurisprudence')->and($r->actif)->toBeTrue();
});

it('téléverse une photo de couverture', function () {
    Storage::fake('public');
    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Archives')
        ->set('photo', UploadedFile::fake()->image('cover.jpg'))
        ->call('enregistrer');

    $r = Rubrique::where('nom', 'Archives')->firstOrFail();
    expect($r->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($r->image_path);
});

it('rejette un SVG comme photo de couverture (anti-XSS stocké)', function () {
    // Note : la règle Laravel `image` de ce projet (Laravel 12) exclut déjà le SVG par défaut
    // (il faut le paramètre `allow_svg` pour l'autoriser), donc cette assertion était déjà vraie
    // avant le durcissement. On la garde en documentation du comportement attendu, et on vérifie
    // en complément qu'un format raster valide (PNG) est toujours accepté après le durcissement
    // explicite vers `mimes:jpg,jpeg,png,webp`.
    Storage::fake('public');
    $svg = "<?xml version=\"1.0\"?><svg xmlns=\"http://www.w3.org/2000/svg\" onload=\"alert(1)\"></svg>";

    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Archives SVG')
        ->set('photo', UploadedFile::fake()->createWithContent('x.svg', $svg))
        ->call('enregistrer')
        ->assertHasErrors('photo');

    expect(Rubrique::where('nom', 'Archives SVG')->exists())->toBeFalse();
});

it('accepte toujours un PNG valide comme photo de couverture après le durcissement des mimes', function () {
    Storage::fake('public');
    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->set('nom', 'Archives PNG')
        ->set('photo', UploadedFile::fake()->image('cover.png'))
        ->call('enregistrer')
        ->assertHasNoErrors('photo');

    $r = Rubrique::where('nom', 'Archives PNG')->firstOrFail();
    expect($r->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($r->image_path);
});

it('active/désactive et supprime une rubrique', function () {
    $r = Rubrique::factory()->create(['actif' => true]);

    Livewire::actingAs(archiviste())->test(Rubriques::class)
        ->call('basculer', $r->id)
        ->call('supprimer', $r->id);

    expect(Rubrique::find($r->id))->toBeNull();
});
