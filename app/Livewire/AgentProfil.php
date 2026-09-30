<?php

namespace App\Livewire;

use App\Models\Agent;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class AgentProfil extends Component
{
    public Agent $agent;

    public function mount(Agent $agent): void
    {
        Gate::authorize('voir', $agent);
        $this->agent = $agent->load(['direction', 'formations', 'experiences']);
    }

    public function render()
    {
        return view('livewire.agent-profil');
    }
}
