<?php

namespace App\Livewire\Auth;

use App\Support\Accueil;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangerMotDePasse extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function changer()
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth()->user();
        $user->update([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ]);

        session()->flash('ok', 'Mot de passe mis à jour.');

        return redirect()->to(Accueil::pour($user));
    }

    public function render()
    {
        return view('livewire.auth.changer-mot-de-passe');
    }
}
