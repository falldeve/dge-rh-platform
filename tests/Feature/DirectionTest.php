<?php

use App\Models\Direction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seed les 5 directions dont SI', function () {
    $this->seed(\Database\Seeders\DirectionSeeder::class);

    expect(Direction::count())->toBe(5);
    expect(Direction::pluck('code')->sort()->values()->all())
        ->toBe(['DFC', 'DG', 'DOE', 'DRHF', 'SI']);
    expect(Direction::where('code', 'SI')->exists())->toBeTrue();
});
