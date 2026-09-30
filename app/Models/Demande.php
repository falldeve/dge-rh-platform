<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demande extends Model
{
    public const TYPES = ['conge_annuel', 'permission', 'ordre_mission'];
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_SOUMISE = 'soumise';
    public const STATUT_VALIDEE_CHEF = 'validee_chef';
    public const STATUT_VALIDEE_RH = 'validee_rh';
    public const STATUT_REFUSEE = 'refusee';
    public const STATUT_EMISE = 'emise'; // ordres de mission (Plan 5)

    /** Types disponibles en libre-service agent (hors ordre de mission). */
    public const TYPES_SELF_SERVICE = ['conge_annuel', 'permission'];

    protected $fillable = [
        'agent_id', 'type', 'date_debut', 'date_fin', 'nb_jours',
        'motif', 'statut', 'piece_jointe_path', 'meta',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'nb_jours' => 'integer',
        'meta' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }

    public function estCongeAnnuel(): bool
    {
        return $this->type === 'conge_annuel';
    }
}
