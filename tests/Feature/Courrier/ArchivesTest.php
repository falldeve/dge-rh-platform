<?php

use App\Livewire\Courrier\Archives;
use App\Livewire\Courrier\CourrierEntite;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ctxArchives(): array
{
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);
    $arch = User::create(['name' => 'Arch', 'matricule' => 'AR1', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'archiviste']);
    $c1 = Courrier::create(['numero' => 'C-001', 'objet' => 'Budget 2026', 'expediteur' => 'Ministère', 'date_arrivee' => '2026-07-01', 'enregistre_par' => $bc->id]);
    $c2 = Courrier::create(['numero' => 'C-002', 'objet' => 'Recensement', 'expediteur' => 'Préfecture', 'date_arrivee' => '2026-07-05', 'enregistre_par' => $bc->id]);

    return compact('arch', 'c1', 'c2');
}

it('interdit les archives aux non-archivistes (403)', function () {
    $this->withoutVite();
    $bc = User::create(['name' => 'BC', 'matricule' => 'BC9', 'password' => bcrypt('s'), 'email_verified_at' => now(), 'role' => 'courrier']);

    $this->actingAs($bc)->get('/archives')->assertForbidden();
});

it('l’archiviste voit tous les courriers', function () {
    ['arch' => $arch] = ctxArchives();

    Livewire::actingAs($arch)->test(Archives::class)
        ->assertSee('C-001')->assertSee('C-002');
});

it('la recherche filtre par numéro / objet / expéditeur', function () {
    ['arch' => $arch] = ctxArchives();

    Livewire::actingAs($arch)->test(Archives::class)
        ->set('search', 'Recensement')
        ->assertSee('C-002')->assertDontSee('C-001');
});

it('l’archiviste peut ouvrir la fiche de n’importe quel courrier', function () {
    ['arch' => $arch, 'c1' => $c1] = ctxArchives();

    Livewire::actingAs($arch)->test(CourrierEntite::class, ['courrier' => $c1])->assertOk();
});
