<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class DirectionsManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $nom = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('directions', 'code')->ignore($this->editingId)],
            'nom' => ['required', 'string', 'max:255'],
        ];
    }

    public function edit(int $id): void
    {
        $dir = Direction::findOrFail($id);
        $this->editingId = $dir->id;
        $this->code = $dir->code;
        $this->nom = $dir->nom;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Direction::findOrFail($this->editingId)->update($data);
        } else {
            Direction::create($data);
        }

        $this->reset(['editingId', 'code', 'nom']);
    }

    public function annuler(): void
    {
        $this->reset(['editingId', 'code', 'nom']);
    }

    public function designerChef(int $directionId, int $agentId): void
    {
        $direction = Direction::findOrFail($directionId);
        $agent = Agent::findOrFail($agentId);

        $direction->update(['chef_id' => $agent->id]);

        if ($agent->user) {
            $agent->user->update(['role' => 'chef_direction']);
        }
    }

    public function render()
    {
        return view('livewire.rh.directions-manager', [
            'directions' => Direction::withCount('agents')->orderBy('code')->get(),
        ]);
    }
}
