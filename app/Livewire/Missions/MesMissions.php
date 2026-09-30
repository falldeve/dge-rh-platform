<?php

namespace App\Livewire\Missions;

use App\Models\Demande;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class MesMissions extends Component
{
    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        $missions = Demande::query()
            ->with('agent')
            ->where('type', 'ordre_mission')
            ->when($directionId, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $directionId)))
            ->when(! $directionId, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return view('livewire.missions.mes-missions', ['missions' => $missions]);
    }
}
