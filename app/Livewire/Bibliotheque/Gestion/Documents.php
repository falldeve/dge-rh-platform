<?php

namespace App\Livewire\Bibliotheque\Gestion;

use App\Models\Document;
use App\Models\Rubrique;
use App\Support\Bibliotheque\Chunker;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.rh')]
class Documents extends Component
{
    use WithFileUploads, WithPagination;

    public ?int $editId = null;

    public string $titre = '';

    public ?int $rubrique_id = null;

    public string $type = 'autre';

    public string $reference = '';

    public ?string $date_document = null;

    public string $resume = '';

    public string $mots_cles = '';

    public string $source = 'texte';

    public string $contenu = '';

    public string $url = '';

    public $fichier = null;

    public string $recherche = '';

    protected function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'rubrique_id' => 'nullable|exists:rubriques,id',
            'type' => ['required', Rule::in(['loi', 'decret', 'reglement', 'rapport', 'circulaire', 'guide', 'archive', 'ordonnance', 'autre'])],
            'reference' => 'nullable|string|max:255',
            'date_document' => 'nullable|date',
            'resume' => 'nullable|string|max:1000',
            'mots_cles' => 'nullable|string|max:255',
            'source' => ['required', Rule::in(['fichier', 'lien', 'texte'])],
            'contenu' => 'nullable|string',
            'url' => 'nullable|url',
            'fichier' => 'nullable|file|mimes:pdf,docx|max:20480',
        ];
    }

    public function editer(int $id): void
    {
        $d = Document::findOrFail($id);
        $this->editId = $d->id;
        $this->titre = $d->titre;
        $this->rubrique_id = $d->rubrique_id;
        $this->type = $d->type;
        $this->reference = (string) $d->reference;
        $this->date_document = $d->date_document?->format('Y-m-d');
        $this->resume = (string) $d->resume;
        $this->mots_cles = (string) $d->mots_cles;
        $this->source = $d->source;
        $this->contenu = (string) $d->contenu;
        $this->url = (string) $d->url;
        $this->fichier = null;
    }

    public function annuler(): void
    {
        $this->reset(['editId', 'titre', 'rubrique_id', 'type', 'reference', 'date_document', 'resume', 'mots_cles', 'source', 'contenu', 'url', 'fichier']);
        $this->type = 'autre';
        $this->source = 'texte';
    }

    public function enregistrer(ExtracteurTexte $extracteur, Chunker $chunker): void
    {
        $this->validate();

        if ($this->source === 'lien' && trim($this->url) === '') {
            $this->addError('url', 'Une URL est requise pour la source lien.');

            return;
        }
        $doc = Document::findOrNew($this->editId);
        $ancienFichier = $doc->fichier_path;

        if ($this->source === 'fichier' && ! $this->fichier && blank($doc->fichier_path)) {
            $this->addError('fichier', 'Un fichier est requis.');

            return;
        }

        $doc->fill([
            'titre' => $this->titre,
            'rubrique_id' => $this->rubrique_id,
            'type' => $this->type,
            'reference' => $this->reference ?: null,
            'date_document' => $this->date_document ?: null,
            'resume' => $this->resume ?: null,
            'mots_cles' => $this->mots_cles ?: null,
            'source' => $this->source,
            'publie_par' => auth()->id(),
            'actif' => $doc->actif ?? true,
        ]);

        $texteAChunker = null;
        $fichierRemplace = false;

        if ($this->source === 'texte') {
            $doc->contenu = $this->contenu;
            $doc->url = null;
            $doc->fichier_path = null;
            $texteAChunker = $this->contenu;
        } elseif ($this->source === 'lien') {
            $doc->url = $this->url;
            $doc->contenu = null;
            $doc->fichier_path = null;
        } elseif ($this->source === 'fichier') {
            $doc->contenu = null;
            $doc->url = null;
            if ($this->fichier) {
                $doc->fichier_path = $this->fichier->store('bibliotheque', 'public');
                $fichierRemplace = true;
            } else {
                $doc->fichier_path = $ancienFichier;
            }
        }

        $doc->save();

        // Nettoyage de l'ancien fichier : remplacé par un nouveau, ou source changée hors "fichier".
        if ($ancienFichier && $ancienFichier !== $doc->fichier_path) {
            Storage::disk('public')->delete($ancienFichier);
        }

        // Re-chunking
        if ($this->source === 'fichier' && $fichierRemplace) {
            $texte = $extracteur->extraire(Storage::disk('public')->path($doc->fichier_path));
            $texteAChunker = mb_strlen($texte) > 800 ? $texte : null;
        }

        if ($this->source === 'fichier' && ! $fichierRemplace) {
            // Édition sans re-upload : chunks et fichier existants restent intacts.
        } elseif ($this->source !== 'lien') {
            $doc->chunks()->delete();
            if ($texteAChunker) {
                foreach ($chunker->decouper($texteAChunker) as $i => $morceau) {
                    $doc->chunks()->create(['ordre' => $i, 'contenu' => $morceau]);
                }
            }
        } else {
            $doc->chunks()->delete();
        }

        $this->annuler();
    }

    public function basculer(int $id): void
    {
        $d = Document::findOrFail($id);
        $d->update(['actif' => ! $d->actif]);
    }

    public function supprimer(int $id): void
    {
        $d = Document::findOrFail($id);
        if ($d->fichier_path) {
            Storage::disk('public')->delete($d->fichier_path);
        }
        $d->delete();
    }

    public function render()
    {
        $q = Document::query()->with('rubrique')->latest('id');
        if ($this->recherche !== '') {
            $q->where('titre', 'like', '%'.$this->recherche.'%');
        }

        return view('livewire.bibliotheque.gestion.documents', [
            'documents' => $q->paginate(15),
            'rubriques' => Rubrique::orderBy('ordre')->get(),
        ]);
    }
}
