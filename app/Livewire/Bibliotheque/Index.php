<?php

namespace App\Livewire\Bibliotheque;

use App\Models\Document;
use App\Models\Rubrique;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public ?int $rubriqueId = null;

    #[Url]
    public string $type = '';

    public function updating($name): void
    {
        if (in_array($name, ['recherche', 'rubriqueId', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function choisirRubrique(?int $id): void
    {
        $this->rubriqueId = $id;
        $this->resetPage();
    }

    public function render()
    {
        $q = Document::query()->where('actif', true)->with('rubrique');

        if ($this->recherche !== '') {
            $t = '%'.$this->recherche.'%';
            $q->where(fn ($w) => $w->where('titre', 'like', $t)
                ->orWhere('resume', 'like', $t)
                ->orWhere('mots_cles', 'like', $t)
                ->orWhere('reference', 'like', $t));
        }
        if ($this->rubriqueId) {
            $q->where('rubrique_id', $this->rubriqueId);
        }
        if ($this->type !== '') {
            $q->where('type', $this->type);
        }

        $groupesAnnee = null;
        $documents = null;

        if ($this->rubriqueId && $this->recherche === '') {
            // Mode rubrique : tout charger, grouper par année décroissante.
            $tous = (clone $q)->orderByDesc('annee')->orderBy('titre')->get();
            $groupesAnnee = $tous->groupBy(fn ($d) => $d->annee ?? 'Non daté')
                ->sortKeysDesc()
                ->sortBy(fn ($grp, $cle) => $cle === 'Non daté' ? 1 : 0);
        } else {
            $documents = $q->latest('date_document')->latest('id')->paginate(12);
        }

        return view('livewire.bibliotheque.index', [
            'documents' => $documents,
            'groupesAnnee' => $groupesAnnee,
            'rubriques' => Rubrique::where('actif', true)->withCount('documents')->orderBy('ordre')->get(),
            'rubriqueCourante' => $this->rubriqueId ? Rubrique::find($this->rubriqueId) : null,
        ]);
    }
}
