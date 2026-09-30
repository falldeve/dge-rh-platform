<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class TwoFactorChallenge
{
    public const CODE_LENGTH = 6;
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /** Crée un code, le stocke haché, retourne le code en clair (pour l'e-mail). */
    public function issueFor(Model $user): string
    {
        $user->twoFactorCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $user->twoFactorCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'attempts' => 0,
        ]);

        return $code;
    }

    /** Vérifie le code actif : correct, non expiré, non consommé, < MAX_ATTEMPTS. */
    public function verify(Model $user, string $code): bool
    {
        $row = $user->twoFactorCodes()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $row || $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return false;
        }

        $row->update(['consumed_at' => now()]);

        return true;
    }
}
