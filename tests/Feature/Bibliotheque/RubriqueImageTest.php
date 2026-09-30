<?php

use App\Models\Rubrique;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expose coverUrl relatif quand image_path est renseigné', function () {
    $r = Rubrique::factory()->create(['image_path' => 'bibliotheque/rubriques/lois.jpg']);
    expect($r->coverUrl())->toBe('/storage/bibliotheque/rubriques/lois.jpg');
});

it('coverUrl null sans image', function () {
    $r = Rubrique::factory()->create(['image_path' => null]);
    expect($r->coverUrl())->toBeNull();
});
