<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    protected $fillable = ['message_id', 'nom_original', 'chemin', 'type_mime', 'taille', 'texte_extrait'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->type_mime, 'image/');
    }
}
