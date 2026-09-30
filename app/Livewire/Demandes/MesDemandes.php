<?php

namespace App\Livewire\Demandes;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MesDemandes extends Component
{
    public function render()
    {
        $agent = auth()->user()->agent;

        $demandes = $agent
            ? $agent->demandes()->latest()->get()
            : collect();

        return view('livewire.demandes.mes-demandes', [
            'demandes' => $demandes,
            'agent' => $agent,
        ]);
    }
}
