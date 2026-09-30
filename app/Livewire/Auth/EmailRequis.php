<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;

class EmailRequis extends Component
{
    public string $email = '';

    private function pendingUser(): ?User
    {
        $id = session('pending_email_user');

        return $id ? User::find($id) : null;
    }

    public function mount()
    {
        if (! $this->pendingUser()) {
            return redirect()->route('login');
        }
    }

    public function enregistrer()
    {
        $user = $this->pendingUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $this->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        if (! $user->estActif()) {
            session()->forget('pending_email_user');
            session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag([
                'matricule' => 'Votre compte a été désactivé. Contactez la RH.',
            ])));

            return redirect()->route('login');
        }

        $user->update(['email' => strtolower(trim($this->email))]);
        $user->sendEmailVerificationNotification();

        Auth::login($user);
        session()->forget('pending_email_user');
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.email-requis');
    }
}
