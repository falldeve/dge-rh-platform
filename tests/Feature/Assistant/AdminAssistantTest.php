<?php

use App\Livewire\Admin\Assistant\Gestion;
use App\Models\AssistantParametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false]);
}

it('un non-admin ne peut pas accéder à l’écran admin', function () {
    $agent = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false]);
    $this->actingAs($agent)->get('/admin/assistant')->assertForbidden();
});

it('l’admin bascule le drapeau premium d’un agent', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => false]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->call('basculerDrapeau', $agent->id);

    expect($agent->fresh()->assistant_ia_actif)->toBeTrue();
});

it('l’admin règle le pool et alloue des crédits', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);
    AssistantParametre::courant()->update(['pool_credits' => 1000]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->set('pool', 1000)
        ->call('enregistrerPool')
        ->set("allocations.{$agent->id}", 300)
        ->call('enregistrerAllocation', $agent->id);

    expect($agent->fresh()->assistant_quota_credits)->toBe(300);
});

it('refuse une allocation qui dépasse le pool', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);
    AssistantParametre::courant()->update(['pool_credits' => 100]);

    Livewire::actingAs(admin())->test(Gestion::class)
        ->set("allocations.{$agent->id}", 500)
        ->call('enregistrerAllocation', $agent->id)
        ->assertHasErrors("allocations.{$agent->id}");

    expect($agent->fresh()->assistant_quota_credits)->toBe(0);
});

it('l’allocation reste bornée par le pool persisté même si la propriété pool locale n’est pas sauvegardée', function () {
    $agent = User::factory()->create(['assistant_ia_actif' => true, 'assistant_quota_credits' => 0]);
    AssistantParametre::courant()->update(['pool_credits' => 100]);

    Livewire::actingAs(admin())->test(Gestion::class)
        // L'admin modifie le champ pool dans le formulaire mais ne clique jamais "enregistrer" :
        // la valeur en base reste 100.
        ->set('pool', 5000)
        ->set("allocations.{$agent->id}", 500)
        ->call('enregistrerAllocation', $agent->id)
        ->assertHasErrors("allocations.{$agent->id}");

    expect($agent->fresh()->assistant_quota_credits)->toBe(0)
        ->and(AssistantParametre::courant()->pool_credits)->toBe(100);
});
