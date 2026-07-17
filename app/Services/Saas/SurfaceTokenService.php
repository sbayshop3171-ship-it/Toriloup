<?php

namespace App\Services\Saas;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class SurfaceTokenService
{
    public function abilityFor(string $surface): string
    {
        return 'surface:'.strtolower(trim($surface));
    }

    public function createToken(User $user, string $surface, ?string $tokenName = null): NewAccessToken
    {
        $normalizedSurface = strtolower(trim($surface));

        return $user->createToken(
            $tokenName ?: $normalizedSurface.'_auth_token',
            [$this->abilityFor($surface)]
        );
    }

    public function issueToken(User $user, string $surface): string
    {
        return $this->createToken($user, $surface)->plainTextToken;
    }

    public function replaceLatestLegacyToken(User $user, string $surface, string $legacyTokenName = 'auth_token'): string
    {
        $latestToken = $user->tokens()->latest('id')->first();

        if ($latestToken !== null && $latestToken->name === $legacyTokenName) {
            $latestToken->delete();
        }

        return $this->issueToken($user, $surface);
    }
}
