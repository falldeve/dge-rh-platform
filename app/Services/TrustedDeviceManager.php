<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrustedDeviceManager
{
    public const TTL_DAYS = 30;

    public function remember(Model $user, ?string $userAgent = null): string
    {
        $token = Str::random(48);

        $user->trustedDevices()->create([
            'token_hash' => $this->hash($token),
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'user_agent' => $userAgent,
        ]);

        return $token;
    }

    public function matches(Model $user, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $device = $user->trustedDevices()
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
