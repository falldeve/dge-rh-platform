<?php

namespace App\Livewire\Courrier;

use App\Livewire\Concerns\SortableList;
use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class MesCourriers extends Component
{
    use SortableList;
    use WithPagination;

    public string $search = '';
    public string $statut = ''; // '', 'non_lus', 'lus' — « lu » = accusé de réception par une de mes entités

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatut(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset(['search', 'statut']);
        $this->resetPage();
    }

    public function render()
    {
        $ids = auth()->user()->agentEntitesGereesIds();

        $courriers = Courrier::query()
            ->when(empty($ids), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(! empty($ids), fn ($q) => $q->pourEntites($ids))
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('numero', 'like', $t)->orWhere('objet', 'like', $t)->orWhere('expediteur', 'like', $t));
            })
            ->when($this->statut === 'lus' && ! empty($ids), fn ($q) => $q->whereHas('accuses', fn ($a) => $a->whereIn('entite_id', $ids)))
            ->when($this->statut === 'non_lus' && ! empty($ids), fn ($q) => $q->whereDoesntHave('accuses', fn ($a) => $a->whereIn('entite_id', $ids)))
            ->withExists(['accuses as lu' => fn ($a) => $a->whereIn('entite_id', $ids ?: [0])]);

        $courriers = $this->appliquerTri($courriers, ['numero', 'objet', 'expediteur', 'date_arrivee'])
            ->paginate(15);

        return view('livewire.courrier.mes-courriers', ['courriers' => $courriers]);
    }
}
