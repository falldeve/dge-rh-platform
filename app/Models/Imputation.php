<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Imputation extends Model
{
    /** Mentions « Soit transmis » de la fiche de ventilation. */
    public const MENTIONS = [
        'urgent' => 'Urgent',
        'men_parler' => "M'en parler",
        'etude_reponse' => 'Pour étude et réponse',
        'accord' => 'Accord',
        'information' => 'Pour information',
        'attribution' => 'Pour attribution',
        'exploitation' => 'Pour exploitation',
        'execution' => 'Pour exécution',
        'suite_a_donner' => 'Pour suite à donner',
        'a_suivre' => 'À suivre',
        'diffusion' => 'Pour diffusion',
        'a_classer' => 'À classer',
    ];

    protected $fillable = [
        'courrier_id', 'niveau', 'entite_source_id', 'mentions', 'observations', 'signataire_nom', 'scan_path', 'saisi_par',
    ];

    protected $casts = [
        'mentions' => 'array',
    ];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function entiteSource(): BelongsTo
    {
        return $this->belongsTo(Entite::class, 'entite_source_id');
    }

    public function destinataires(): BelongsToMany
    {
        return $this->belongsToMany(Entite::class, 'entite_imputation');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
