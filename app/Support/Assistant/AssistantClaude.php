<?php

namespace App\Support\Assistant;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;

final class AssistantClaude implements AssistantIA
{
    public function __construct(private Client $client) {}

    public function repondre(array $messages, string $modele, string $system, callable $onChunk): ClaudeReponse
    {
        $stream = $this->client->messages->createStream(
            maxTokens: (int) config('assistant.max_tokens_reponse'),
            messages: $messages,
            model: $modele,
            system: $system,
        );

        $accumulator = MessageAccumulator::forMessages();
        foreach ($stream as $event) {
            $accumulator->accumulate($event);
            // Émettre le delta de texte au fur et à mesure.
            if (($event->type ?? null) === 'content_block_delta'
                && ($event->delta->type ?? null) === 'text_delta') {
                $onChunk($event->delta->text);
            }
        }

        $message = $accumulator->message();
        $texte = '';
        foreach ($message->content as $bloc) {
            if (($bloc->type ?? null) === 'text') {
                $texte .= $bloc->text;
            }
        }

        return new ClaudeReponse(
            texte: $texte,
            jetonsInput: (int) $message->usage->inputTokens,
            jetonsOutput: (int) $message->usage->outputTokens,
        );
    }
}
