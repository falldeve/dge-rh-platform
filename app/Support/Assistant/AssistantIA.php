<?php

namespace App\Support\Assistant;

interface AssistantIA
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  callable(string):void  $onChunk
     */
    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse;
}
