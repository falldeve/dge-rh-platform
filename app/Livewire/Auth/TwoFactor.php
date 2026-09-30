<?php

namespace App\Livewire\Auth;

use App\Mail\TwoFactorCode;
use App\Models\User;
use App\Services\TrustedDeviceManager;
use App\Services\TwoFactorChallenge;
use App\Support\Accueil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $code = '';
    public bool $remember_device = false;

    private function pendingUser(): ?User
    {
        $id = session('pending_2fa.id');

        return $id ? User::find($id) : null;
    }

    public function mount()
    {
        if (! session()->has('pending_2fa')) {
            return redirect()->route('login');
        }
    }

    public function verifier()
    {
        $user = $this->pendingUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $this->validate(['code' => ['required', 'string']]);

        // Anti-force brute sur la saisie du code (en plus du cap 5/code du service).
        $key = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }

        if (! app(TwoFactorChallenge::class)->verify($user, trim($this->code))) {
            RateLimiter::hit($key, 300);
            $this->addError('code', 'Code invalide ou expiré.');

            return;
        }

        RateLimiter::clear($key);

        if (! $user->estActif()) {
            session()->forget('pending_2fa');
            session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag([
                'matricule' => 'Votre compte a été désactivé. Contactez la RH.',
            ])));

            return redirect()->route('login');
        }

        if ($this->remember_device) {
            $token = app(TrustedDeviceManager::class)->remember($user, request()->userAgent());
            Cookie::queue('dge_td', $token, 60 * 24 * TrustedDeviceManager::TTL_DAYS);
        }

        Auth::login($user);
        session()->forget('pending_2fa');
        session()->regenerate();

        return redirect()->to(Accueil::pour($user));
    }

    public function renvoyer()
    {
        $user = $this->pendingUser();
        if (! $user) {
            return;
        }

        // Anti inbox-bombing : 3 renvois / 10 min.
        $key = '2fa-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            session()->flash('ok', 'Trop de renvois. Patientez quelques minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        $code = app(TwoFactorChallenge::class)->issueFor($user);
        Mail::to($user->email)->send(new TwoFactorCode($user, $code));
        session()->flash('ok', 'Un nouveau code vous a été envoyé.');
    }

    public function render()
    {
        return view('livewire.auth.two-factor');
    }
}
