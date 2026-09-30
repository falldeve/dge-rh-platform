<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use App\Models\Entite;
use App\Models\Imputation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FicheCourrier extends Component
{
    public Courrier $courrier;

    public array $destinataires = [];
    public array $mentions = [];
    public ?string $observations = null;
    public ?string $signataire_nom = null;

    public function mount(Courrier $courrier): void
    {
        $this->courrier = $courrier;
        $this->signataire_nom = config('dge.dg_nom');
    }

    public function ventiler()
    {
        $this->validate([
            'destinataires' => ['array', 'min:1'],
            'destinataires.*' => ['exists:entites,id'],
            'mentions' => ['array'],
            'mentions.*' => [Rule::in(array_keys(Imputation::MENTIONS))],
            'observations' => ['nullable', 'string', 'max:2000'],
            'signataire_nom' => ['nullable', 'string', 'max:255'],
        ], [], ['destinataires' => 'destinataires']);

        $imp = Imputation::create([
            'courrier_id' => $this->courrier->id,
            'niveau' => 'dg',
            'entite_source_id' => null,
            'mentions' => array_values($this->mentions),
            'observations' => $this->observations,
            'signataire_nom' => $this->signataire_nom,
            'saisi_par' => auth()->id(),
        ]);
        $imp->destinataires()->sync($this->destinataires);

        if (! $this->courrier->date_depart) {
            $this->courrier->update(['date_depart' => now()->toDateString()]);
        }

        $this->reset(['destinataires', 'mentions', 'observations']);
        session()->flash('ok', 'Ventilation enregistrée.');
    }

    public function render()
    {
        return view('livewire.courrier.fiche-courrier', [
            'entites' => Entite::whereIn('type', ['direction', 'service'])->where('actif', true)->orderBy('type')->orderBy('code')->get(),
            'imputations' => $this->courrier->imputations()->with('destinataires', 'entiteSource')->get(),
            'mentionsListe' => Imputation::MENTIONS,
        ]);
    }
}
