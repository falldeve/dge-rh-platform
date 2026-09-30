<?php

return [
    'cle_api' => env('ANTHROPIC_API_KEY'),
    'modele_premium' => env('ANTHROPIC_MODEL_PREMIUM', 'claude-sonnet-5'),
    'modele_gratuit' => env('ANTHROPIC_MODEL_GRATUIT', 'claude-haiku-4-5'),
    'jetons_par_credit' => (int) env('ASSISTANT_JETONS_PAR_CREDIT', 1000),
    'forfait_gratuit_credits' => (int) env('ASSISTANT_FORFAIT_GRATUIT', 50),
    'max_upload_mo' => (int) env('ASSISTANT_MAX_UPLOAD_MO', 20),
    'max_tokens_reponse' => (int) env('ASSISTANT_MAX_TOKENS', 4096),
];
