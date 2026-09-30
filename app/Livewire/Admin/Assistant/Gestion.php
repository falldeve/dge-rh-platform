<?php

namespace App\Livewire\Admin\Assistant;

use App\Models\AssistantParametre;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class Gestion extends Component
{
    public int $pool = 0;

    /** @var array<int,int> */
    public array $allocations = [];

    public function mount(): void
    {
        $this->pool = (int) AssistantParametre::courant()->pool_credits;
        $this->allocations = User::where('assistant_ia_actif', true)
            ->pluck('assistant_quota_credits', 'id')->map(fn ($v) => (int) $v)->all();
    }

    public function basculerDrapeau(int $userId): void
    {
        $u = User::findOrFail($userId);
        $u->update(['assistant_ia_actif' => ! $u->assistant_ia_actif]);
        if ($u->assistant_ia_actif) {
            $this->allocations[$u->id] = (int) $u->assistant_quota_credits;
        } else {
            unset($this->allocations[$u->id]);
        }
    }

    public function enregistrerPool(): void
    {
        $this->validate(['pool' => 'required|integer|min:0']);
        AssistantParametre::courant()->update(['pool_credits' => $this->pool]);
    }

    public function enregistrerAllocation(int $userId): void
    {
        $valeur = (int) ($this->allocations[$userId] ?? 0);

        $totalAutres = User::where('assistant_ia_actif', true)
            ->where('id', '!=', $userId)
            ->sum('assistant_quota_credits');

        $pool = (int) AssistantParametre::courant()->pool_credits;

        if ($totalAutres + $valeur > $pool) {
            $this->addError("allocations.$userId", "Dépasse le pool disponible ({$pool} crédits).");

            return;
        }

        User::whereKey($userId)->update(['assistant_quota_credits' => $valeur]);
    }

    public function render()
    {
        $agents = User::orderBy('name')->get();
        $totalAlloue = User::where('assistant_ia_actif', true)->sum('assistant_quota_credits');

        return view('livewire.admin.assistant.gestion', [
            'agents' => $agents,
            'totalAlloue' => (int) $totalAlloue,
        ]);
    }
}
