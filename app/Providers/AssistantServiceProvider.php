<?php

namespace App\Providers;

use Anthropic\Client;
use App\Support\Assistant\AssistantClaude;
use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\FauxAssistant;
use Illuminate\Support\ServiceProvider;

class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: (string) config('assistant.cle_api'),
        ));

        // Sans clé API (dév/démo), on retombe sur un assistant simulé pour que
        // l'interface soit testable de bout en bout sans dépense ni appel réseau.
        $this->app->bind(AssistantIA::class, function () {
            if (blank(config('assistant.cle_api'))) {
                return new FauxAssistant(
                    "⚙️ Mode démonstration — aucune clé API Anthropic configurée.\n\n"
                    ."L'interface (conversations, crédits, niveaux, écran admin) fonctionne, "
                    ."mais je ne suis pas relié au vrai Claude. Renseignez ANTHROPIC_API_KEY "
                    ."dans le .env pour des réponses réelles.",
                    jetonsInput: 12,
                    jetonsOutput: 40,
                );
            }

            return $this->app->make(AssistantClaude::class);
        });
    }
}
