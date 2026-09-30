<?php

use App\Livewire\Bibliotheque\Gestion\Documents;
use App\Models\Document;
use App\Models\Rubrique;
use App\Models\User;
use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\FauxExtracteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function archivisteDoc(): User
{
    return User::factory()->create(['role' => 'archiviste', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('interdit la gestion documents à un agent (403)', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/bibliotheque/gerer/documents')->assertForbidden();
});

it('crée un document texte et le chunk', function () {
    $r = Rubrique::factory()->create();
    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Note interne')
        ->set('rubrique_id', $r->id)
        ->set('type', 'guide')
        ->set('source', 'texte')
        ->set('contenu', str_repeat('article ', 300))
        ->call('enregistrer');

    $d = Document::where('titre', 'Note interne')->firstOrFail();
    expect($d->source)->toBe('texte')
        ->and($d->chunks()->count())->toBeGreaterThanOrEqual(1);
});

it('crée un document fichier PDF, le stocke et le chunk via extracteur', function () {
    Storage::fake('public');
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: str_repeat('texte extrait ', 200)));
    $r = Rubrique::factory()->create();

    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Décret X')
        ->set('rubrique_id', $r->id)
        ->set('type', 'decret')
        ->set('source', 'fichier')
        ->set('fichier', UploadedFile::fake()->create('decret.pdf', 100, 'application/pdf'))
        ->call('enregistrer');

    $d = Document::where('titre', 'Décret X')->firstOrFail();
    expect($d->source)->toBe('fichier')
        ->and($d->fichier_path)->not->toBeNull()
        ->and($d->chunks()->count())->toBeGreaterThanOrEqual(1);
    Storage::disk('public')->assertExists($d->fichier_path);
});

it('exige une url pour la source lien', function () {
    $r = Rubrique::factory()->create();
    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->set('titre', 'Lien externe')
        ->set('rubrique_id', $r->id)
        ->set('source', 'lien')
        ->set('url', '')
        ->call('enregistrer')
        ->assertHasErrors('url');
});

it('supprime un document et son fichier', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bibliotheque/x.pdf', 'data');
    $d = Document::factory()->create(['source' => 'fichier', 'fichier_path' => 'bibliotheque/x.pdf']);

    Livewire::actingAs(archivisteDoc())->test(Documents::class)
        ->call('supprimer', $d->id);

    expect(Document::find($d->id))->toBeNull();
    Storage::disk('public')->assertMissing('bibliotheque/x.pdf');
});

it('préserve les chunks et le fichier quand on édite un document fichier sans re-uploader', function () {
    Storage::fake('public');
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: str_repeat('texte extrait ', 200)));
    $r = Rubrique::factory()->create();

    $component = Livewire::actingAs($user = archivisteDoc())->test(Documents::class)
        ->set('titre', 'Décret Y')
        ->set('rubrique_id', $r->id)
        ->set('type', 'decret')
        ->set('source', 'fichier')
        ->set('fichier', UploadedFile::fake()->create('decret-y.pdf', 100, 'application/pdf'))
        ->call('enregistrer');

    $d = Document::where('titre', 'Décret Y')->firstOrFail();
    $cheminOriginal = $d->fichier_path;
    $nombreChunksOriginal = $d->chunks()->count();
    expect($nombreChunksOriginal)->toBeGreaterThan(0);

    Livewire::actingAs($user)->test(Documents::class)
        ->call('editer', $d->id)
        ->set('titre', 'Décret Y (révisé)')
        ->call('enregistrer');

    $d->refresh();
    expect($d->titre)->toBe('Décret Y (révisé)')
        ->and($d->fichier_path)->toBe($cheminOriginal)
        ->and($d->chunks()->count())->toBe($nombreChunksOriginal)
        ->and($d->chunks()->count())->toBeGreaterThan(0);
    Storage::disk('public')->assertExists($cheminOriginal);
});

it('supprime l\'ancien fichier quand on remplace la source fichier par du texte', function () {
    Storage::fake('public');
    app()->instance(ExtracteurTexte::class, new FauxExtracteur(defaut: str_repeat('texte extrait ', 200)));
    $r = Rubrique::factory()->create();

    Livewire::actingAs($user = archivisteDoc())->test(Documents::class)
        ->set('titre', 'Décret Z')
        ->set('rubrique_id', $r->id)
        ->set('type', 'decret')
        ->set('source', 'fichier')
        ->set('fichier', UploadedFile::fake()->create('decret-z.pdf', 100, 'application/pdf'))
        ->call('enregistrer');

    $d = Document::where('titre', 'Décret Z')->firstOrFail();
    $ancienChemin = $d->fichier_path;
    Storage::disk('public')->assertExists($ancienChemin);

    Livewire::actingAs($user)->test(Documents::class)
        ->call('editer', $d->id)
        ->set('source', 'texte')
        ->set('contenu', str_repeat('nouveau contenu ', 300))
        ->call('enregistrer');

    $d->refresh();
    expect($d->source)->toBe('texte')
        ->and($d->fichier_path)->toBeNull();
    Storage::disk('public')->assertMissing($ancienChemin);
});

it('refuse de basculer un document texte vers fichier sans upload et préserve les chunks existants', function () {
    Storage::fake('public');
    $r = Rubrique::factory()->create();

    Livewire::actingAs($user = archivisteDoc())->test(Documents::class)
        ->set('titre', 'Note à protéger')
        ->set('rubrique_id', $r->id)
        ->set('type', 'guide')
        ->set('source', 'texte')
        ->set('contenu', str_repeat('article ', 300))
        ->call('enregistrer');

    $d = Document::where('titre', 'Note à protéger')->firstOrFail();
    $contenuOriginal = $d->contenu;
    $nombreChunksOriginal = $d->chunks()->count();
    expect($nombreChunksOriginal)->toBeGreaterThan(0);

    Livewire::actingAs($user)->test(Documents::class)
        ->call('editer', $d->id)
        ->set('source', 'fichier')
        ->call('enregistrer')
        ->assertHasErrors('fichier');

    $d->refresh();
    expect($d->source)->toBe('texte')
        ->and($d->contenu)->toBe($contenuOriginal)
        ->and($d->fichier_path)->toBeNull()
        ->and($d->chunks()->count())->toBe($nombreChunksOriginal);
});
