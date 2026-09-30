<?php

namespace App\Models;

use App\Models\TwoFactorCode;
use App\Models\TwoFactorTrustedDevice;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values for new, unsaved model instances.
     * Mirrors the DB column default so `compte_actif` isn't null (falsy under
     * the boolean cast) before the first fetch from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'compte_actif' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'matricule',
        'role',
        'must_change_password',
        'assistant_ia_actif',
        'assistant_quota_credits',
        'compte_actif',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'assistant_ia_actif' => 'boolean',
            'compte_actif' => 'boolean',
        ];
    }

    public function conversations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Conversation::class);
    }

    public function agent()
    {
        return $this->hasOne(\App\Models\Agent::class);
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isChefDirection(): bool
    {
        return $this->role === 'chef_direction';
    }

    public function isAdminRh(): bool
    {
        return $this->role === 'admin_rh';
    }

    public function isDg(): bool
    {
        return $this->role === 'dg';
    }

    public function isSecretaire(): bool
    {
        return $this->role === 'secretaire';
    }

    public function isCourrier(): bool
    {
        return $this->role === 'courrier';
    }

    public function isArchiviste(): bool
    {
        return $this->role === 'archiviste';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function estActif(): bool
    {
        return (bool) $this->compte_actif;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** IDs des entités dont l'agent lié est chef ou secrétaire. */
    public function agentEntitesGereesIds(): array
    {
        if (! $this->agent) {
            return [];
        }

        return \App\Models\Entite::where('chef_agent_id', $this->agent->id)
            ->orWhere('secretaire_agent_id', $this->agent->id)
            ->pluck('id')->all();
    }

    /** L'utilisateur est chef ou secrétaire d'au moins une entité (direction/service/division). */
    public function gereEntite(): bool
    {
        if (! $this->agent) {
            return false;
        }

        return \App\Models\Entite::where('chef_agent_id', $this->agent->id)
            ->orWhere('secretaire_agent_id', $this->agent->id)
            ->exists();
    }

    public function twoFactorCodes(): MorphMany
    {
        return $this->morphMany(TwoFactorCode::class, 'authenticatable');
    }

    public function trustedDevices(): MorphMany
    {
        return $this->morphMany(TwoFactorTrustedDevice::class, 'authenticatable');
    }

    public function assistantEstPremium(): bool
    {
        return $this->assistant_ia_actif || $this->isAdmin();
    }

    public function assistantModele(): string
    {
        return $this->assistantEstPremium()
            ? config('assistant.modele_premium')
            : config('assistant.modele_gratuit');
    }

    public function assistantAllocationCredits(): int
    {
        return $this->assistantEstPremium()
            ? (int) $this->assistant_quota_credits
            : (int) config('assistant.forfait_gratuit_credits');
    }

    public function assistantCreditsConsommesMois(): int
    {
        return (int) \App\Models\AssistantUsage::query()
            ->where('user_id', $this->id)
            ->where('annee_mois', now()->format('Y-m'))
            ->value('credits_consommes') ?? 0;
    }

    public function assistantCreditsRestants(): int
    {
        return max(0, $this->assistantAllocationCredits() - $this->assistantCreditsConsommesMois());
    }

    public function enregistrerConsommation(int $jetonsIn, int $jetonsOut, int $credits): void
    {
        $ligne = \App\Models\AssistantUsage::query()->firstOrCreate(
            ['user_id' => $this->id, 'annee_mois' => now()->format('Y-m')],
            ['credits_consommes' => 0, 'jetons_input' => 0, 'jetons_output' => 0],
        );
        $ligne->increment('credits_consommes', $credits);
        $ligne->increment('jetons_input', $jetonsIn);
        $ligne->increment('jetons_output', $jetonsOut);
    }
}
