<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'role', 'contenu', 'jetons_input', 'jetons_output', 'credits'];

    protected function casts(): array
    {
        return ['contenu' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class, 'message_id');
    }
}
