<?php

namespace App\Livewire\Rh;

use App\Support\TableauBord as Agregats;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class TableauBord extends Component
{
    public function render()
    {
        return view('livewire.rh.tableau-bord', [
            'parStatut' => Agregats::parStatut(),
            'parType' => Agregats::parType(),
            'parDirection' => Agregats::parDirection(),
            'enAttenteRh' => Agregats::enAttenteRh(),
        ]);
    }
}
