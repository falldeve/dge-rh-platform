<?php

namespace App\Livewire\Rh;

use App\Models\Direction;
use App\Support\EtatCongesQuery;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class EtatConges extends Component
{
    public ?int $directionId = null;
    public ?string $du = null;
    public ?string $au = null;

    public function render()
    {
        return view('livewire.rh.etat-conges', [
            'conges' => EtatCongesQuery::pour($this->directionId, $this->du, $this->au),
            'directions' => Direction::orderBy('code')->get(),
        ]);
    }
}
