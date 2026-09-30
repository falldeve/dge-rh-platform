<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'prenoms', 'noms', 'matricule', 'profession', 'fonction',
        'direction_id', 'statut', 'solde_conge_jours', 'telephone',
        'email', 'photo_path', 'date_naissance', 'date_prise_service', 'user_id',
    ];

    protected $casts = [
        'solde_conge_jours' => 'decimal:1',
        'date_naissance' => 'date',
        'date_prise_service' => 'date',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenoms} {$this->noms}");
    }

    /** URL relative de la photo (résolue sur l'origine courante, indépendante d'APP_URL). */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? '/storage/'.ltrim($this->photo_path, '/') : null;
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class)->orderByDesc('annee_debut')->orderByDesc('mois_debut');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class)->orderByDesc('annee_debut')->orderByDesc('mois_debut');
    }

    /**
     * Fonctionnaire = possède un matricule de solde (contient « / ») et n'est ni
     * agent d'appui, ni PAV, ni contractuel. Sinon : non-fonctionnaire.
     */
    public function estFonctionnaire(): bool
    {
        $prof = mb_strtolower($this->profession ?? '');

        if (str_contains($prof, 'appui') || str_contains($prof, 'pav') || str_contains($prof, 'contractuel')) {
            return false;
        }

        return $this->matricule !== null && str_contains($this->matricule, '/');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $query */
    public function scopeFonctionnaires($query)
    {
        return $query->where('matricule', 'like', '%/%')
            ->where(function ($w) {
                $w->whereNull('profession')
                    ->orWhere(function ($p) {
                        $p->where('profession', 'not like', '%appui%')
                            ->where('profession', 'not like', '%pav%')
                            ->where('profession', 'not like', '%contractuel%');
                    });
            });
    }

    /**
     * Tri hiérarchique par fonction : Directeurs, puis Chefs de division,
     * puis Chefs de bureau, puis le reste ; alphabétique à l'intérieur.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeParHierarchie($query)
    {
        return $query->orderByRaw(
            "CASE
                WHEN LOWER(TRIM(fonction)) LIKE 'directeur%' OR LOWER(TRIM(fonction)) LIKE 'directrice%' THEN 1
                WHEN LOWER(fonction) LIKE '%chef%division%' THEN 2
                WHEN LOWER(fonction) LIKE '%chef%bureau%' THEN 3
                ELSE 4
            END"
        )->orderBy('noms')->orderBy('prenoms');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $query */
    public function scopeNonFonctionnaires($query)
    {
        return $query->where(function ($w) {
            $w->where('matricule', 'not like', '%/%')
                ->orWhereNull('matricule')
                ->orWhere('profession', 'like', '%appui%')
                ->orWhere('profession', 'like', '%pav%')
                ->orWhere('profession', 'like', '%contractuel%');
        });
    }
}
