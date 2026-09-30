<?php

namespace App\Livewire\Demandes;

use App\Actions\SoumettreDemande;
use App\Models\Demande;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class NouvelleDemande extends Component
{
    public string $type = 'conge_annuel';
    public ?string $date_debut = null;
    public ?string $date_fin = null;
    public ?string $motif = null;

    public function soumettre(SoumettreDemande $action)
    {
        $this->validate([
            'type' => ['required', Rule::in(Demande::TYPES_SELF_SERVICE)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent = auth()->user()->agent;

        if (! $agent) {
            $this->addError('type', "Aucune fiche agent n'est liée à votre compte. Contactez la DRHF.");

            return;
        }

        try {
            $action->handle($agent, $this->type, $this->date_debut, $this->date_fin, $this->motif);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ, $messages[0]);
            }

            return;
        }

        session()->flash('ok', 'Demande soumise.');

        return redirect()->route('demandes.mes');
    }

    public function render()
    {
        return view('livewire.demandes.nouvelle-demande');
    }
}
