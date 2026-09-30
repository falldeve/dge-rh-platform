<?php

namespace App\Livewire\Assistant;

use App\Support\Assistant\AssistantIA;
use App\Support\Assistant\ConstructeurContenu;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.rh')]
class Index extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $conversationId = null;

    public string $saisie = '';

    public string $erreur = '';

    /** @var array<int,TemporaryUploadedFile> */
    public array $fichiers = [];

    public function nouvelleConversation(): void
    {
        $this->conversationId = null;
        $this->saisie = '';
        $this->erreur = '';
    }

    public function choisir(int $id): void
    {
        $conv = auth()->user()->conversations()->findOrFail($id);
        $this->conversationId = $conv->id;
        $this->erreur = '';
    }

    public function envoyer(AssistantIA $assistant, ExtracteurTexte $extracteur): void
    {
        $this->erreur = '';
        $texte = trim($this->saisie);
        if ($texte === '' && $this->fichiers === []) {
            return;
        }

        $this->validate([
            'fichiers' => 'array|max:5',
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,gif,docx,xlsx', 'max:10240', function ($attr, $value, $fail) {
                if ($value && str_starts_with((string) $value->getMimeType(), 'image/') && $value->getSize() > 5 * 1024 * 1024) {
                    $fail('Chaque image doit faire moins de 5 Mo.');
                }
            }],
        ]);

        $tailleImagesBase64 = 0;
        foreach ($this->fichiers as $f) {
            if (str_starts_with((string) $f->getMimeType(), 'image/')) {
                $tailleImagesBase64 += $f->getSize() * 1.34;
            }
        }
        if ($tailleImagesBase64 > 30 * 1024 * 1024) {
            $this->addError('fichiers', 'Les images jointes sont trop volumineuses au total (max ~30 Mo).');

            return;
        }

        $user = auth()->user();

        if ($user->assistantCreditsRestants() <= 0) {
            $this->erreur = 'Vos crédits sont épuisés pour ce mois. Demandez un rechargement à l’administrateur.';

            return;
        }

        $conversationNouvelle = $this->conversationId === null;

        $conv = $this->conversationId
            ? $user->conversations()->findOrFail($this->conversationId)
            : $user->conversations()->create([
                'titre' => Str::limit($texte !== '' ? $texte : 'Document', 60),
                'modele' => $user->assistantModele(),
                'niveau' => $user->assistantEstPremium() ? 'premium' : 'gratuit',
            ]);
        $this->conversationId = $conv->id;

        $messageUtilisateur = $conv->messages()->create([
            'role' => 'user',
            'contenu' => [['type' => 'text', 'text' => $texte]],
        ]);

        $cheminsStockes = [];
        foreach ($this->fichiers as $f) {
            $chemin = $f->store('assistant', 'local');
            $cheminsStockes[] = $chemin;
            $mime = $f->getMimeType();
            $extrait = str_starts_with((string) $mime, 'image/')
                ? null
                : ($extracteur->extraire(Storage::disk('local')->path($chemin)) ?: null);
            $messageUtilisateur->piecesJointes()->create([
                'nom_original' => $f->getClientOriginalName(),
                'chemin' => $chemin,
                'type_mime' => $mime,
                'taille' => $f->getSize(),
                'texte_extrait' => $extrait,
            ]);
        }

        $this->saisie = '';
        $this->fichiers = [];

        // Historique pour l'API
        $messages = $conv->messages()->with('piecesJointes')->get()->map(fn ($m) => [
            'role' => $m->role,
            'content' => ConstructeurContenu::pour($m),
        ])->all();

        // Ancrage : récupérer les extraits pertinents de la bibliothèque.
        $extraits = $texte !== '' ? app(\App\Support\Bibliotheque\RechercheBibliotheque::class)->rechercher($texte) : [];
        $system = $this->promptSysteme();
        $sources = [];
        if ($extraits !== []) {
            $bloc = "Extraits de la bibliothèque électorale de la DGE (Sénégal) — fonde ta réponse sur ces extraits et cite les documents (titre + référence) :\n\n";
            foreach ($extraits as $e) {
                $ref = $e['reference'] ? ' · '.$e['reference'] : '';
                $bloc .= "— [{$e['titre']}{$ref}]\n{$e['contenu']}\n\n";
            }
            $system .= "\n\n".$bloc;

            $sources = collect($extraits)
                ->unique('document_id')
                ->map(fn ($e) => ['id' => $e['document_id'], 'titre' => $e['titre'], 'reference' => $e['reference']])
                ->values()->all();
        }

        try {
            $rep = $assistant->repondre(
                messages: $messages,
                modele: $conv->modele,
                system: $system,
                onChunk: fn (string $d) => $this->stream(to: 'reponse-en-cours', content: $d),
            );
        } catch (\Throwable $e) {
            if ($cheminsStockes !== []) {
                Storage::disk('local')->delete($cheminsStockes);
            }

            if ($conversationNouvelle) {
                $conv->delete();
                $this->conversationId = null;
            } else {
                $messageUtilisateur->delete();
            }

            $this->erreur = 'L’assistant est momentanément indisponible. Merci de réessayer dans un instant.';

            return;
        }

        $credits = (int) ceil(($rep->jetonsInput + $rep->jetonsOutput) / (int) config('assistant.jetons_par_credit'));

        $contenuAssistant = [['type' => 'text', 'text' => $rep->texte]];
        if ($sources !== []) {
            $contenuAssistant[] = ['type' => 'sources', 'documents' => $sources];
        }

        $conv->messages()->create([
            'role' => 'assistant',
            'contenu' => $contenuAssistant,
            'jetons_input' => $rep->jetonsInput,
            'jetons_output' => $rep->jetonsOutput,
            'credits' => $credits,
        ]);

        $user->enregistrerConsommation($rep->jetonsInput, $rep->jetonsOutput, $credits);
    }

    private function promptSysteme(): string
    {
        return "Tu es l'assistant IA de la Direction Générale des Élections (DGE) du Sénégal. "
            ."Le cadre est exclusivement sénégalais : élections, droit et procédures électorales du Sénégal. "
            ."Réponds en français, de façon professionnelle et concise. "
            ."Quand des extraits de la bibliothèque te sont fournis, FONDE ta réponse dessus et CITE les documents (titre et référence). "
            ."N'invente jamais une loi, un décret ou une référence ; si l'information n'est pas dans les extraits fournis et que tu n'es pas certain pour le cas sénégalais, dis-le clairement et invite à consulter la bibliothèque.";
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.assistant.index', [
            'conversations' => $user->conversations()->latest()->get(),
            'conversation' => $this->conversationId
                ? $user->conversations()->with('messages.piecesJointes')->find($this->conversationId)
                : null,
            'creditsRestants' => $user->assistantCreditsRestants(),
            'allocation' => $user->assistantAllocationCredits(),
            'estPremium' => $user->assistantEstPremium(),
        ]);
    }
}
