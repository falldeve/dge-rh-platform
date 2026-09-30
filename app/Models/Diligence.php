<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diligence extends Model
{
    protected $fillable = ['courrier_id', 'entite_id', 'user_id', 'contenu'];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function entite(): BelongsTo
    {
        return $this->belongsTo(Entite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
