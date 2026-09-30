<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactorCode extends Model
{
    protected $fillable = ['code_hash', 'expires_at', 'attempts', 'consumed_at'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
