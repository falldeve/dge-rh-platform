<?php

namespace App\Livewire\Bibliotheque;

use App\Models\Document;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.rh')]
class FicheDocument extends Component
{
    public Document $document;

    public function mount(Document $document): void
    {
        $this->document = $document->load('rubrique');

        abort_unless($this->document->actif, 404);
    }

    public function render()
    {
        $html = $this->document->estTexte() && $this->document->contenu
            ? Str::markdown($this->document->contenu)
            : null;

        return view('livewire.bibliotheque.fiche-document', ['html' => $html]);
    }
}
