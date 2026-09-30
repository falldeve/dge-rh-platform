<?php

namespace App\Support\Assistant;

final class FauxAssistant implements AssistantIA
{
    public function __construct(
        private string $reponse = 'Réponse simulée.',
        private int $jetonsInput = 10,
        private int $jetonsOutput = 5,
    ) {}

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        foreach (str_split($this->reponse, 8) as $fragment) {
            $onChunk($fragment);
        }

        return new ClaudeReponse($this->reponse, $this->jetonsInput, $this->jetonsOutput);
    }
}
