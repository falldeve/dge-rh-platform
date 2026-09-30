<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Formation extends Model
{
    protected $fillable = [
        'agent_id', 'annee_debut', 'mois_debut', 'annee_fin', 'mois_fin',
        'domaine', 'etablissement', 'ville',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
