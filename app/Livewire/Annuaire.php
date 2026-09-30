<?php

namespace App\Livewire;

use App\Models\Agent;
use App\Models\Direction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Annuaire extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $directionId = null;
    public ?string $categorie = null; // fonctionnaire | non_fonctionnaire

    public function updating($name): void
    {
        if (in_array($name, ['search', 'directionId', 'categorie'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $u = auth()->user();
        $estChef = $u->isChefDirection(); // le chef est limité à sa direction (pas de filtre direction)

        $agents = Agent::query()
            ->with('direction')
            ->when($estChef && $u->agent, fn ($q) => $q->where('direction_id', $u->agent->direction_id))
            ->when(! $estChef && $this->directionId, fn ($q) => $q->where('direction_id', $this->directionId))
            ->when($this->categorie === 'fonctionnaire', fn ($q) => $q->fonctionnaires())
            ->when($this->categorie === 'non_fonctionnaire', fn ($q) => $q->nonFonctionnaires())
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('prenoms', 'like', $t)->orWhere('noms', 'like', $t)->orWhere('matricule', 'like', $t));
            })
            ->parHierarchie()
            ->paginate(15);

        return view('livewire.annuaire', [
            'agents' => $agents,
            'estChef' => $estChef,
            'directions' => $estChef ? collect() : Direction::orderBy('code')->get(),
        ]);
    }
}
