<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validation extends Model
{
    protected $fillable = [
        'demande_id', 'validateur_id', 'niveau', 'decision', 'commentaire',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'validateur_id');
    }
}
