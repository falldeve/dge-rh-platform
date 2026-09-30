<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Entite;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class EntitesManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $nom = '';
    public string $type = 'service';
    public ?int $parent_id = null;
    public ?int $chef_agent_id = null;
    public ?int $secretaire_agent_id = null;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('entites', 'code')->ignore($this->editingId)],
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['direction', 'service', 'division'])],
            'parent_id' => ['nullable', 'exists:entites,id'],
            'chef_agent_id' => ['nullable', 'exists:agents,id'],
            'secretaire_agent_id' => ['nullable', 'exists:agents,id'],
        ];
    }

    public function edit(int $id): void
    {
        $e = Entite::findOrFail($id);
        $this->editingId = $e->id;
        $this->code = $e->code;
        $this->nom = $e->nom;
        $this->type = $e->type;
        $this->parent_id = $e->parent_id;
        $this->chef_agent_id = $e->chef_agent_id;
        $this->secretaire_agent_id = $e->secretaire_agent_id;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Entite::findOrFail($this->editingId)->update($data);
        } else {
            Entite::create($data);
        }

        $this->reset(['editingId', 'code', 'nom', 'parent_id', 'chef_agent_id', 'secretaire_agent_id']);
        $this->type = 'service';
        session()->flash('ok', 'Entité enregistrée.');
    }

    public function annuler(): void
    {
        $this->reset(['editingId', 'code', 'nom', 'parent_id', 'chef_agent_id', 'secretaire_agent_id']);
        $this->type = 'service';
    }

    public function supprimer(int $id): void
    {
        $e = Entite::findOrFail($id);

        if ($e->enfants()->exists()) {
            session()->flash('error', "Impossible de supprimer « {$e->code} » : elle contient des sous-entités.");

            return;
        }

        $e->delete();

        if ($this->editingId === $id) {
            $this->reset(['editingId', 'code', 'nom', 'parent_id', 'chef_agent_id', 'secretaire_agent_id']);
            $this->type = 'service';
        }

        session()->flash('ok', 'Entité supprimée.');
    }

    public function render()
    {
        return view('livewire.rh.entites-manager', [
            'entites' => Entite::with('parent')->orderBy('type')->orderBy('code')->get(),
            'directions' => Entite::where('type', 'direction')->orderBy('code')->get(),
            'agents' => Agent::orderBy('noms')->get(),
        ]);
    }
}
