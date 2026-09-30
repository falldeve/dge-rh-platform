<?php

namespace App\Livewire\Auth;

use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use App\Support\Accueil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $matricule = '';
    public string $password = '';

    public function login()
    {
        $this->validate([
            'matricule' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Anti-force brute : 5 essais / IP+matricule, blocage 1 min.
        $key = 'login:'.request()->ip().'|'.Str::lower(trim($this->matricule));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'matricule' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $user = User::where('matricule', trim($this->matricule))->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou mot de passe invalide.',
            ]);
        }

        RateLimiter::clear($key);
        // Régénère l'ID de session dès l'identification (anti-fixation) avant de poser l'état pending.
        session()->regenerate();

        if (! $user->compte_actif) {
            throw ValidationException::withMessages([
                'matricule' => 'Votre compte a été désactivé. Contactez la RH.',
            ]);
        }

        // 1) Compte sans email → capture obligatoire.
        if (blank($user->email)) {
            session()->put('pending_email_user', $user->id);

            return redirect()->route('email.requis');
        }

        // 2) Email non vérifié → connecter puis renvoyer vers la notice (middleware verified bloque l'app).
        if (! $user->hasVerifiedEmail()) {
            Auth::login($user);
            session()->regenerate();

            return redirect()->route('verification.notice');
        }

        // 3) 2FA désactivée (tests locaux) ou appareil de confiance → connexion directe.
        if (! config('dge.deux_facteurs')
            || app(TrustedDeviceManager::class)->matches($user, request()->cookie('dge_td'))) {
            Auth::login($user);
            session()->regenerate();

            return redirect()->to(Accueil::pour($user));
        }

        $code = app(TwoFactorChallenge::class)->issueFor($user);
        Mail::to($user->email)->send(new TwoFactorCode($user, $code));

        session()->put('pending_2fa', ['id' => $user->id]);

        return redirect()->route('two-factor.show');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
