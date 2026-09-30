<?php

namespace App\Livewire\Admin;

use App\Actions\ProvisionAccount;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class ComptesSysteme extends Component
{
    public string $name = '';

    public ?string $matricule = null;

    public string $email = '';

    public string $role = 'admin_rh';

    /** Rôles système provisionnables par le super-admin (pas 'agent' — géré par la RH). */
    public array $rolesDispo = ['admin_rh', 'courrier', 'archiviste', 'dg'];

    public function creer(ProvisionAccount $action)
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin_rh,courrier,archiviste,dg'],
        ]);

        $action->handle([
            'name' => $this->name,
            'matricule' => $this->matricule,
            'email' => $this->email,
            'role' => $this->role,
        ]);

        $this->reset(['name', 'matricule', 'email']);
        $this->role = 'admin_rh';
        session()->flash('ok', 'Compte créé — mot de passe provisoire envoyé par email.');
    }

    public function basculer(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_if($user->isAdmin() || $user->id === auth()->id(), 403);
        $user->update(['compte_actif' => ! $user->compte_actif]);
        session()->flash('ok', $user->compte_actif ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    public function render()
    {
        return view('livewire.admin.comptes-systeme', [
            'comptes' => User::whereIn('role', ['admin', 'admin_rh', 'courrier', 'archiviste', 'dg'])->orderBy('role')->get(),
        ]);
    }
}
