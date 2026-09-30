<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantParametre extends Model
{
    protected $table = 'assistant_parametres';

    protected $fillable = ['pool_credits'];

    /** La ligne unique de paramètres (créée à la migration). */
    public static function courant(): self
    {
        return static::query()->firstOrCreate([], ['pool_credits' => 0]);
    }
}
