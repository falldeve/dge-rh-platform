<?php

namespace App\Livewire\Courrier;

use App\Models\AccuseReception;
use App\Models\Courrier;
use App\Models\Diligence;
use App\Models\Entite;
use App\Models\Imputation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class CourrierEntite extends Component
{
    public Courrier $courrier;

    public array $divisions = [];
    public array $mentions_cascade = [];
    public ?string $observations_cascade = null;
    public string $reponse = '';

    public function mount(Courrier $courrier): void
    {
        Gate::authorize('voir', $courrier);
        $this->courrier = $courrier;
    }

    /** La direction dont l'utilisateur est chef OU secrétaire ET qui est destinataire du courrier. */
    private function directionCascade(): ?Entite
    {
        $agentId = auth()->user()->agent?->id;
        if (! $agentId) {
            return null;
        }

        $dir = Entite::where('type', 'direction')
            ->where(fn ($q) => $q->where('chef_agent_id', $agentId)->orWhere('secretaire_agent_id', $agentId))
            ->first();
        if (! $dir) {
            return null;
        }

        $estDestinataire = $this->courrier->imputations()
            ->whereHas('destinataires', fn ($q) => $q->where('entites.id', $dir->id))
            ->exists();

        return $estDestinataire ? $dir : null;
    }

    public function cascader()
    {
        $dir = $this->directionCascade();
        abort_unless($dir !== null, 403);

        $divisionsValides = Entite::where('type', 'division')->where('parent_id', $dir->id)->where('actif', true)->pluck('id')->all();

        $this->validate([
            'divisions' => ['array', 'min:1'],
            'divisions.*' => [Rule::in($divisionsValides)],
            'mentions_cascade' => ['array'],
            'mentions_cascade.*' => [Rule::in(array_keys(Imputation::MENTIONS))],
            'observations_cascade' => ['nullable', 'string', 'max:2000'],
        ], [], ['divisions' => 'divisions']);

        $imp = Imputation::create([
            'courrier_id' => $this->courrier->id,
            'niveau' => 'direction',
            'entite_source_id' => $dir->id,
            'mentions' => array_values($this->mentions_cascade),
            'observations' => $this->observations_cascade,
            'signataire_nom' => $dir->chef?->nomComplet(),
            'saisi_par' => auth()->id(),
        ]);
        $imp->destinataires()->sync($this->divisions);

        $this->reset(['divisions', 'mentions_cascade', 'observations_cascade']);
        session()->flash('ok', 'Cascade enregistrée.');
    }

    /** Entités gérées par l'utilisateur qui sont destinataires de ce courrier (peuvent accuser réception). */
    private function mesEntitesDestinataires()
    {
        $managed = auth()->user()->agentEntitesGereesIds();
        if (empty($managed)) {
            return collect();
        }

        $destIds = $this->courrier->imputations()
            ->with('destinataires:id')
            ->get()
            ->flatMap->destinataires->pluck('id')->unique();

        return Entite::whereIn('id', array_intersect($managed, $destIds->all()))->orderBy('code')->get();
    }

    public function accuser(int $entiteId): void
    {
        abort_unless(in_array($entiteId, auth()->user()->agentEntitesGereesIds(), true), 403);

        $estDestinataire = $this->courrier->imputations()
            ->whereHas('destinataires', fn ($q) => $q->where('entites.id', $entiteId))
            ->exists();
        abort_unless($estDestinataire, 403);

        AccuseReception::firstOrCreate(
            ['courrier_id' => $this->courrier->id, 'entite_id' => $entiteId],
            ['user_id' => auth()->id()]
        );

        session()->flash('ok', 'Accusé de réception enregistré.');
    }

    /** Réponse / compte-rendu de diligence du destinataire vers sa hiérarchie. */
    public function repondre(): void
    {
        $dest = $this->mesEntitesDestinataires();
        abort_if($dest->isEmpty(), 403);

        $this->validate(['reponse' => ['required', 'string', 'max:5000']]);

        Diligence::create([
            'courrier_id' => $this->courrier->id,
            'entite_id' => $dest->first()->id,
            'user_id' => auth()->id(),
            'contenu' => $this->reponse,
        ]);

        $this->reset('reponse');
        session()->flash('ok', 'Réponse transmise à votre hiérarchie.');
    }

    public function render()
    {
        $dir = $this->directionCascade();

        return view('livewire.courrier.courrier-entite', [
            'imputations' => $this->courrier->imputations()->with('destinataires', 'entiteSource')->get(),
            'directionCascade' => $dir,
            'divisionsDispo' => $dir ? Entite::where('type', 'division')->where('parent_id', $dir->id)->where('actif', true)->orderBy('code')->get() : collect(),
            'mentionsListe' => Imputation::MENTIONS,
            'retour' => auth()->user()->isArchiviste() ? 'archives' : 'mes-courriers',
            'mesEntitesDest' => $this->mesEntitesDestinataires(),
            'accuses' => $this->courrier->accuses()->with('user')->get()->keyBy('entite_id'),
            'diligences' => $this->courrier->diligences()->with('user', 'entite')->latest()->get(),
        ]);
    }
}
