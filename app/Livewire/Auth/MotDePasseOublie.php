<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MotDePasseOublie extends Component
{
    public string $email = '';

    public function envoyer()
    {
        $this->validate(['email' => ['required', 'email']]);

        // Anti-spam d'emails par IP (5 demandes / 10 min).
        $key = 'pwd-reset:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            session()->flash('ok', 'Trop de demandes. Réessayez dans quelques minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        Password::sendResetLink(['email' => strtolower(trim($this->email))]);

        // Message neutre (pas d'énumération d'emails).
        session()->flash('ok', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
    }

    public function render()
    {
        return view('livewire.auth.mot-de-passe-oublie');
    }
}
