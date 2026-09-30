<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantUsage extends Model
{
    protected $table = 'assistant_usage';

    protected $fillable = ['user_id', 'annee_mois', 'credits_consommes', 'jetons_input', 'jetons_output'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
