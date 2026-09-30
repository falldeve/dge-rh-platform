<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccuseReception extends Model
{
    protected $table = 'accuses_reception';

    protected $fillable = ['courrier_id', 'entite_id', 'user_id'];

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
