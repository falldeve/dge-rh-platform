<?php

namespace App\Livewire\Validation;

use App\Actions\DeciderDemande;
use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FileRh extends Component
{
    public function decider(int $demandeId, string $decision, DeciderDemande $action): void
    {
        $demande = Demande::findOrFail($demandeId);
        $action->handle($demande, auth()->user(), 'rh', $decision);
        session()->flash('ok', 'Décision enregistrée.');
    }

    public function render()
    {
        $demandes = Demande::query()
            ->with('agent')
            ->where('statut', Demande::STATUT_VALIDEE_CHEF)
            ->latest()
            ->get();

        $traitees = Demande::query()
            ->with('agent')
            ->where('statut', Demande::STATUT_VALIDEE_RH)
            ->whereIn('type', ['conge_annuel', 'permission'])
            ->latest('updated_at')
            ->take(8)
            ->get();

        return view('livewire.validation.file-rh', ['demandes' => $demandes, 'traitees' => $traitees]);
    }
}
