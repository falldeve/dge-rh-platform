<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RubriqueFactory extends Factory
{
    public function definition(): array
    {
        $nom = $this->faker->unique()->words(2, true);

        return ['nom' => ucfirst($nom), 'slug' => Str::slug($nom), 'ordre' => 0, 'actif' => true];
    }
}
