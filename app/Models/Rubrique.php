<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubrique extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'slug', 'description', 'icone', 'ordre', 'actif', 'image_path'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function coverUrl(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
