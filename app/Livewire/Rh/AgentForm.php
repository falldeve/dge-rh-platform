<?php

namespace App\Livewire\Rh;

use App\Models\Agent;
use App\Models\Direction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class AgentForm extends Component
{
    use WithFileUploads;

    public ?Agent $agent = null;

    public string $prenoms = '';
    public string $noms = '';
    public ?string $matricule = null;
    public ?string $profession = null;
    public ?string $fonction = null;
    public ?int $direction_id = null;
    public string $statut = 'autre';
    public float $solde_conge_jours = 0;
    public ?string $telephone = null;
    public ?string $email = null;
    public ?string $date_naissance = null;
    public ?string $date_prise_service = null;
    public ?string $role = null;
    public $photo = null;

    /** @var array<int,array<string,mixed>> Scolarité (formations). */
    public array $formations = [];

    /** @var array<int,array<string,mixed>> Expérience professionnelle (emplois). */
    public array $experiences = [];

    public function mount(?Agent $agent = null): void
    {
        if ($agent && $agent->exists) {
            $this->agent = $agent;
            $this->fill($agent->only([
                'prenoms', 'noms', 'matricule', 'profession', 'fonction',
                'direction_id', 'statut', 'solde_conge_jours', 'telephone',
                'email', 'date_naissance', 'date_prise_service',
            ]));
            $this->role = $agent->user?->role;

            $this->formations = $agent->formations->map(fn ($f) => $f->only([
                'annee_debut', 'mois_debut', 'annee_fin', 'mois_fin', 'domaine', 'etablissement', 'ville',
            ]))->toArray();

            $this->experiences = $agent->experiences->map(fn ($e) => $e->only([
                'annee_debut', 'mois_debut', 'annee_fin', 'mois_fin', 'activite', 'employeur', 'ville',
            ]))->toArray();
        }
    }

    public function ajouterFormation(): void
    {
        $this->formations[] = ['annee_debut' => null, 'mois_debut' => null, 'annee_fin' => null, 'mois_fin' => null, 'domaine' => '', 'etablissement' => '', 'ville' => null];
    }

    public function retirerFormation(int $i): void
    {
        unset($this->formations[$i]);
        $this->formations = array_values($this->formations);
    }

    public function ajouterExperience(): void
    {
        $this->experiences[] = ['annee_debut' => null, 'mois_debut' => null, 'annee_fin' => null, 'mois_fin' => null, 'activite' => '', 'employeur' => '', 'ville' => null];
    }

    public function retirerExperience(int $i): void
    {
        unset($this->experiences[$i]);
        $this->experiences = array_values($this->experiences);
    }

    protected function rules(): array
    {
        $ignore = $this->agent?->id;

        return [
            'prenoms' => ['required', 'string', 'max:255'],
            'noms' => ['required', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:255', Rule::unique('agents', 'matricule')->ignore($ignore)],
            'profession' => ['nullable', 'string', 'max:255'],
            'fonction' => ['nullable', 'string', 'max:255'],
            'direction_id' => ['required', 'exists:directions,id'],
            'statut' => ['required', Rule::in(['fonctionnaire', 'police', 'contractuel_pav', 'autre'])],
            'solde_conge_jours' => ['required', 'numeric', 'min:0'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'date_prise_service' => ['nullable', 'date'],
            'role' => ['nullable', Rule::in(['agent', 'chef_direction', 'admin_rh', 'dg', 'secretaire', 'courrier', 'archiviste'])],
            'photo' => ['nullable', 'image', 'max:4096'],

            'formations' => ['array'],
            'formations.*.annee_debut' => ['required', 'integer', 'min:1950', 'max:2100'],
            'formations.*.mois_debut' => ['nullable', 'integer', 'min:1', 'max:12'],
            'formations.*.annee_fin' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'formations.*.mois_fin' => ['nullable', 'integer', 'min:1', 'max:12'],
            'formations.*.domaine' => ['required', 'string', 'max:255'],
            'formations.*.etablissement' => ['required', 'string', 'max:255'],
            'formations.*.ville' => ['nullable', 'string', 'max:255'],

            'experiences' => ['array'],
            'experiences.*.annee_debut' => ['required', 'integer', 'min:1950', 'max:2100'],
            'experiences.*.mois_debut' => ['nullable', 'integer', 'min:1', 'max:12'],
            'experiences.*.annee_fin' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'experiences.*.mois_fin' => ['nullable', 'integer', 'min:1', 'max:12'],
            'experiences.*.activite' => ['required', 'string', 'max:255'],
            'experiences.*.employeur' => ['required', 'string', 'max:255'],
            'experiences.*.ville' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formations.*.annee_debut' => 'année de début',
            'formations.*.domaine' => "domaine d'études",
            'formations.*.etablissement' => 'établissement',
            'experiences.*.annee_debut' => 'année de début',
            'experiences.*.activite' => 'activité',
            'experiences.*.employeur' => 'employeur',
        ];
    }

    public function save()
    {
        $data = $this->validate();
        foreach (['role', 'photo', 'formations', 'experiences'] as $k) {
            unset($data[$k]);
        }

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('agents-photos', 'public');
        }

        if ($this->agent) {
            $this->agent->update($data);
        } else {
            $this->agent = Agent::create($data);
        }

        if ($this->agent && $this->agent->user && $this->role !== null) {
            $this->agent->user->update(['role' => $this->role]);
        }

        // Synchronisation scolarité + expérience (remplacement complet)
        $this->agent->formations()->delete();
        foreach ($this->formations as $f) {
            $this->agent->formations()->create($f);
        }
        $this->agent->experiences()->delete();
        foreach ($this->experiences as $e) {
            $this->agent->experiences()->create($e);
        }

        session()->flash('ok', 'Agent enregistré.');

        return redirect()->route('rh.agents.index');
    }

    public function render()
    {
        return view('livewire.rh.agent-form', [
            'directions' => Direction::orderBy('code')->get(),
        ]);
    }
}
