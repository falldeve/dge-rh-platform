<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titre' => $this->faker->sentence(4),
            'type' => 'autre',
            'source' => 'texte',
            'contenu' => '# '.$this->faker->sentence(),
            'actif' => true,
        ];
    }
}
