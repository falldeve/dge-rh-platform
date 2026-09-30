<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => 'user',
            'contenu' => [['type' => 'text', 'text' => $this->faker->sentence()]],
        ];
    }
}
