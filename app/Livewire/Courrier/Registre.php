<?php

namespace App\Livewire\Courrier;

use App\Livewire\Concerns\SortableList;
use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Registre extends Component
{
    use SortableList;
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
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
            ;

        $courriers = $this->appliquerTri($courriers, ['numero', 'objet', 'expediteur', 'date_arrivee'])
            ->paginate(15);

        return view('livewire.courrier.registre', ['courriers' => $courriers]);
    }
}
