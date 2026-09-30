<?php

namespace App\Livewire\Missions;

use App\Actions\InitierMission;
use App\Models\Agent;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class NouvelleMission extends Component
{
    public ?int $agent_id = null;
    public ?string $date_debut = null;
    public ?string $date_fin = null;
    public ?string $motif = null;
    public ?string $destination = null;
    public ?string $moyen_transport = null;
    public ?string $indice = null;
    public ?string $groupe = null;
    public ?string $imputation = null;
    public ?string $chapitre = null;
    public ?string $article = null;
    public array $signataires = [];

    public function creer(InitierMission $action)
    {
        $this->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:2000'],
            'destination' => ['nullable', 'string', 'max:255'],
            'moyen_transport' => ['nullable', 'string', 'max:255'],
            'indice' => ['nullable', 'string', 'max:50'],
            'groupe' => ['nullable', 'string', 'max:50'],
            'imputation' => ['nullable', 'string', 'max:255'],
            'chapitre' => ['nullable', 'string', 'max:50'],
            'article' => ['nullable', 'string', 'max:50'],
            'signataires' => ['array'],
        ]);

        $agent = Agent::findOrFail($this->agent_id);

        $meta = array_filter([
            'destination' => $this->destination,
            'moyen_transport' => $this->moyen_transport,
            'indice' => $this->indice,
            'groupe' => $this->groupe,
            'imputation' => $this->imputation,
            'chapitre' => $this->chapitre,
            'article' => $this->article,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $action->handle(auth()->user(), $agent, $this->date_debut, $this->date_fin, $this->motif, $meta, $this->signataires);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ, $messages[0]);
            }

            return;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->addError('agent_id', $e->getMessage());

            return;
        }

        session()->flash('ok', 'Ordre de mission créé.');

        return redirect()->route('missions.mes');
    }

    public function render()
    {
        $directionId = auth()->user()->agent?->direction_id;

        return view('livewire.missions.nouvelle-mission', [
            'agents' => Agent::where('direction_id', $directionId)->orderBy('noms')->get(),
        ]);
    }
}
