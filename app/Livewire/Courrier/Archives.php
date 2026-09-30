<?php

namespace App\Livewire\Courrier;

use App\Livewire\Concerns\SortableList;
use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Archives extends Component
{
    use SortableList;
    use WithPagination;

    public string $search = '';
    public string $du = '';
    public string $au = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDu(): void
    {
        $this->resetPage();
    }

    public function updatingAu(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset(['search', 'du', 'au']);
        $this->resetPage();
    }

    public function render()
    {
        $courriers = Courrier::query()
            ->withCount('imputations')
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('numero', 'like', $t)->orWhere('objet', 'like', $t)->orWhere('expediteur', 'like', $t));
            })
            ->when($this->du !== '', fn ($q) => $q->whereDate('date_arrivee', '>=', $this->du))
            ->when($this->au !== '', fn ($q) => $q->whereDate('date_arrivee', '<=', $this->au));

        $courriers = $this->appliquerTri($courriers, ['numero', 'objet', 'expediteur', 'date_arrivee'])
            ->paginate(20);

        return view('livewire.courrier.archives', ['courriers' => $courriers]);
    }
}
