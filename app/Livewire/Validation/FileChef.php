<?php

namespace App\Livewire\Validation;

use App\Actions\DeciderDemande;
use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FileChef extends Component
{
    public function decider(int $demandeId, string $decision, DeciderDemande $action): void
    {
        $demande = Demande::findOrFail($demandeId);
        $action->handle($demande, auth()->user(), 'chef', $decision);
        session()->flash('ok', 'Décision enregistrée.');
    }

    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        $demandes = Demande::query()
            ->with('agent')
            ->where('statut', Demande::STATUT_SOUMISE)
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when(! $directionId, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return view('livewire.validation.file-chef', ['demandes' => $demandes]);
    }
}
