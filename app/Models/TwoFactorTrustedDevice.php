<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactorTrustedDevice extends Model
{
    protected $fillable = ['token_hash', 'expires_at', 'last_used_at', 'user_agent'];

    protected $casts = ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
