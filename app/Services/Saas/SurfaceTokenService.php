<?php

namespace App\Services\Saas;

use App\Models\User;

class SurfaceTokenService
{
    public function abilityFor(string $surface): string
    {
        return 'surface:'.strtolower(trim($surface));
    }

    public function issueToken(User $user, string $surface): string
    {
        return $user->createToken(
            strtolower(trim($surface)).'_auth_token',
            [$this->abilityFor($surface)]
        )->plainTextToken;
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
