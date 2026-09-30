<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titre' => $this->faker->sentence(3),
            'modele' => 'claude-haiku-4-5',
            'niveau' => 'gratuit',
        ];
    }
}
