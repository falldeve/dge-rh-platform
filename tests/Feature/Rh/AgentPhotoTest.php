<?php

use App\Livewire\Rh\AgentForm;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminRhPhoto(): User
{
    return User::create(['name' => 'RH', 'matricule' => 'RH-1', 'password' => bcrypt('secret'), 'role' => 'admin_rh']);
}

it('téléverse et stocke la photo d’un agent', function () {
    Storage::fake('public');
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhPhoto())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('save')
        ->assertRedirect(route('rh.agents.index'));

    $agent->refresh();
    expect($agent->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($agent->photo_path);
});

it('refuse un fichier non image', function () {
    Storage::fake('public');
    $dir = Direction::create(['code' => 'DG', 'nom' => 'Direction Générale']);
    $agent = Agent::create(['prenoms' => 'Biram', 'noms' => 'SENE', 'matricule' => '636324/D', 'direction_id' => $dir->id, 'statut' => 'fonctionnaire', 'solde_conge_jours' => 30]);

    Livewire::actingAs(adminRhPhoto())
        ->test(AgentForm::class, ['agent' => $agent])
        ->set('photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photo');

    expect($agent->fresh()->photo_path)->toBeNull();
});
