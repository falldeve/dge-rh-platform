<?php

namespace App\Providers;

use App\Support\Bibliotheque\ExtracteurTexte;
use App\Support\Bibliotheque\ExtracteurTextePoppler;
use Illuminate\Support\ServiceProvider;

class BibliothequeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExtracteurTexte::class, ExtracteurTextePoppler::class);
    }
}
