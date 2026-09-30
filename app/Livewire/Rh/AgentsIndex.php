<?php

namespace App\Livewire\Rh;

use App\Actions\ProvisionAccount;
use App\Models\Agent;
use App\Models\Direction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class AgentsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $directionId = null;

    public ?string $categorie = null; // null=tous, 'fonctionnaire', 'non_fonctionnaire'

    /** null = fermé, 0 = nouvel agent, >0 = édition de cet agent. */
    public ?int $editId = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'directionId', 'categorie'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset(['search', 'directionId', 'categorie']);
        $this->resetPage();
    }

    public function ouvrirCreation(): void
    {
        $this->editId = 0;
    }

    public function ouvrirEdition(int $id): void
    {
        $this->editId = $id;
    }

    public function fermer(): void
    {
        $this->editId = null;
    }

    public function creerCompte(int $agentId, string $email): void
    {
        $agent = Agent::findOrFail($agentId);
        abort_if($agent->user_id !== null, 422);

        app(ProvisionAccount::class)->handle([
            'name' => $agent->nomComplet(),
            'matricule' => $agent->matricule,
            'email' => $email,
            'role' => 'agent',
            'agent_id' => $agent->id,
        ]);

        session()->flash('ok', 'Compte créé — identifiants envoyés à '.$email);
    }

    public function basculerCompte(int $userId): void
    {
        $user = \App\Models\User::findOrFail($userId);
        abort_if($user->isAdmin() || $user->id === auth()->id(), 403);
        abort_if($user->role !== 'agent', 403);
        $user->update(['compte_actif' => ! $user->compte_actif]);
        session()->flash('ok', $user->compte_actif ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    public function render()
    {
        $agents = Agent::query()
            ->with('direction', 'user')
            ->when($this->search !== '', function ($q) {
                $terme = '%'.$this->search.'%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('prenoms', 'like', $terme)
                        ->orWhere('noms', 'like', $terme)
                        ->orWhere('matricule', 'like', $terme);
                });
            })
            ->when($this->directionId, fn ($q) => $q->where('direction_id', $this->directionId))
            ->when($this->categorie === 'fonctionnaire', fn ($q) => $q->fonctionnaires())
            ->when($this->categorie === 'non_fonctionnaire', fn ($q) => $q->nonFonctionnaires())
            ->parHierarchie()
            ->paginate(15);

        return view('livewire.rh.agents-index', [
            'agents' => $agents,
            'directions' => Direction::orderBy('code')->get(),
            'agentEnEdition' => $this->editId ? Agent::find($this->editId) : null,
        ]);
    }
}
