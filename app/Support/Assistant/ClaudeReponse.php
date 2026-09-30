<?php

namespace App\Support\Assistant;

final readonly class ClaudeReponse
{
    public function __construct(
        public string $texte,
        public int $jetonsInput,
        public int $jetonsOutput,
    ) {}
}
