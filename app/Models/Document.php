<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre', 'rubrique_id', 'type', 'reference', 'date_document', 'annee',
        'resume', 'mots_cles', 'source', 'fichier_path', 'url', 'contenu',
        'publie_par', 'actif',
    ];

    protected function casts(): array
    {
        return ['date_document' => 'date', 'annee' => 'integer', 'actif' => 'boolean'];
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('ordre');
    }

    public function fichierUrl(): ?string
    {
        return $this->fichier_path ? '/storage/'.ltrim($this->fichier_path, '/') : null;
    }

    public function estPdf(): bool
    {
        return $this->source === 'fichier' && str_ends_with(strtolower((string) $this->fichier_path), '.pdf');
    }

    public function estTexte(): bool
    {
        return $this->source === 'texte';
    }
}
