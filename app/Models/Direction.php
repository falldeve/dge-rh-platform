<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    protected $fillable = ['code', 'nom', 'chef_id'];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function chef(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'chef_id');
    }
}
