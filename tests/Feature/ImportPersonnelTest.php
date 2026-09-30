<?php

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\DirectionSeeder::class);
});

it('importe les 116 agents du fichier source', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::count())->toBe(116);
});

it('réaffecte les agents informatiques à la direction SI', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    $si = Direction::where('code', 'SI')->first();
    $diallo = Agent::where('noms', 'DIALLO')->where('prenoms', 'Cheikh Tidiane')->first();
    expect($diallo)->not->toBeNull();
    expect($diallo->direction_id)->toBe($si->id);
    expect($si->agents()->count())->toBeGreaterThanOrEqual(2);
});

it('est idempotente (pas de doublons)', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::count())->toBe(116);
});

it('gère les agents sans matricule', function () {
    $this->artisan('rh:import-personnel')->assertExitCode(0);

    expect(Agent::whereNull('matricule')->count())->toBe(16);
});
