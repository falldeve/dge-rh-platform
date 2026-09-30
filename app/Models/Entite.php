<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entite extends Model
{
    protected $fillable = [
        'code', 'nom', 'type', 'parent_id', 'chef_agent_id', 'secretaire_agent_id', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entite::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Entite::class, 'parent_id');
    }

    public function chef(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'chef_agent_id');
    }

    public function secretaire(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'secretaire_agent_id');
    }
}
