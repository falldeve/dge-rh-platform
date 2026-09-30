<?php

namespace App\Livewire\Dg;

use App\Support\TableauBord as Agregats;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class TableauBord extends Component
{
    public function render()
    {
        return view('livewire.dg.tableau-bord', [
            'parStatut' => Agregats::parStatut(),
            'parType' => Agregats::parType(),
            'parDirection' => Agregats::parDirection(),
        ]);
    }
}
