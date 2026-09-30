<?php

namespace App\Livewire\Bibliotheque\Gestion;

use App\Models\Rubrique;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class Rubriques extends Component
{
    use WithFileUploads;

    public ?int $editId = null;

    public string $nom = '';

    public string $description = '';

    public string $icone = '';

    public int $ordre = 0;

    public bool $actif = true;

    public $photo = null;

    protected function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icone' => 'nullable|string|max:8',
            'ordre' => 'integer|min:0',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ];
    }

    public function editer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        $this->editId = $r->id;
        $this->nom = $r->nom;
        $this->description = (string) $r->description;
        $this->icone = (string) $r->icone;
        $this->ordre = (int) $r->ordre;
        $this->actif = (bool) $r->actif;
        $this->photo = null;
    }

    public function annuler(): void
    {
        $this->reset(['editId', 'nom', 'description', 'icone', 'ordre', 'actif', 'photo']);
    }

    public function enregistrer(): void
    {
        $data = $this->validate();

        $rubrique = Rubrique::updateOrCreate(
            ['id' => $this->editId],
            [
                'nom' => $this->nom,
                'slug' => Str::slug($this->nom),
                'description' => $this->description ?: null,
                'icone' => $this->icone ?: null,
                'ordre' => $this->ordre,
                'actif' => $this->actif,
            ],
        );

        if ($this->photo) {
            $chemin = $this->photo->store('bibliotheque/rubriques', 'public');
            $rubrique->update(['image_path' => $chemin]);
        }

        $this->annuler();
    }

    public function basculer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        $r->update(['actif' => ! $r->actif]);
    }

    public function supprimer(int $id): void
    {
        $r = Rubrique::findOrFail($id);
        if ($r->image_path) {
            Storage::disk('public')->delete($r->image_path);
        }
        $r->delete(); // documents.rubrique_id → nullOnDelete
    }

    public function render()
    {
        return view('livewire.bibliotheque.gestion.rubriques', [
            'rubriques' => Rubrique::withCount('documents')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
