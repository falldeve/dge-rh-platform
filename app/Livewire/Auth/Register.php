<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterAgentAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Register extends Component
{
    public string $matricule = '';
    public string $noms = '';
    public string $prenoms = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(RegisterAgentAccount $action)
    {
        $this->validate([
            'matricule' => ['required', 'string'],
            'noms' => ['required', 'string'],
            'prenoms' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $action->handle($this->matricule, $this->noms, $this->prenoms, $this->email, $this->password);
        $user->sendEmailVerificationNotification();
        Auth::login($user);
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
