<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courrier extends Model
{
    protected $fillable = [
        'numero', 'objet', 'expediteur', 'date_arrivee', 'date_depart', 'scan_path', 'enregistre_par',
    ];

    protected $casts = [
        'date_arrivee' => 'date',
        'date_depart' => 'date',
    ];

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class)->latest();
    }

    public function accuses(): HasMany
    {
        return $this->hasMany(AccuseReception::class);
    }

    public function diligences(): HasMany
    {
        return $this->hasMany(Diligence::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function scanUrl(): ?string
    {
        return $this->scan_path ? '/storage/'.ltrim($this->scan_path, '/') : null;
    }

    private function scanExt(): ?string
    {
        return $this->scan_path ? strtolower(pathinfo($this->scan_path, PATHINFO_EXTENSION)) : null;
    }

    public function scanEstImage(): bool
    {
        return in_array($this->scanExt(), ['jpg', 'jpeg', 'png'], true);
    }

    public function scanEstPdf(): bool
    {
        return $this->scanExt() === 'pdf';
    }

    /**
     * Courriers ayant au moins une imputation dont un destinataire est dans $entiteIds.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int>  $entiteIds
     */
    public function scopePourEntites($query, array $entiteIds)
    {
        return $query->whereHas('imputations.destinataires', function ($q) use ($entiteIds) {
            $q->whereIn('entites.id', $entiteIds);
        });
    }
}
