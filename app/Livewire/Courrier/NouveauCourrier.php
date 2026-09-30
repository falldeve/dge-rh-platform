<?php

namespace App\Livewire\Courrier;

use App\Models\Courrier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class NouveauCourrier extends Component
{
    use WithFileUploads;

    public string $numero = '';
    public string $objet = '';
    public string $expediteur = '';
    public ?string $date_arrivee = null;
    public ?string $date_depart = null;
    public $scan = null;

    public function enregistrer()
    {
        $data = $this->validate([
            'numero' => ['required', 'string', 'max:50', 'unique:courriers,numero'],
            'objet' => ['required', 'string', 'max:255'],
            'expediteur' => ['required', 'string', 'max:255'],
            'date_arrivee' => ['required', 'date'],
            'date_depart' => ['nullable', 'date'],
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        unset($data['scan']);
        if ($this->scan) {
            $data['scan_path'] = $this->scan->store('courriers-scans', 'public');
        }
        $data['enregistre_par'] = auth()->id();

        Courrier::create($data);
        session()->flash('ok', 'Courrier enregistré.');

        return redirect()->route('courriers.registre');
    }

    public function render()
    {
        return view('livewire.courrier.nouveau-courrier');
    }
}
