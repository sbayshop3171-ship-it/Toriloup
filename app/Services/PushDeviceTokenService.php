<?php

namespace App\Services;

use App\Models\PushDeviceToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PushDeviceTokenService
{
    public function register(User $user, string $installationId, string $platform, string $token): PushDeviceToken
    {
        $token = trim($token);
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($user, $installationId, $platform, $token, $tokenHash): PushDeviceToken {
            $movedRegistrations = PushDeviceToken::query()
                ->where('installation_id', $installationId)
                ->where('user_id', '!=', $user->id)
                ->get();

            foreach ($movedRegistrations as $moved) {
                $previousUser = User::query()->find($moved->user_id);
                $legacyColumn = $moved->platform === 'web' ? 'web_token' : 'device_token';
                if ($previousUser?->getAttribute($legacyColumn) === $moved->token) {
                    $previousUser->forceFill([$legacyColumn => null])->save();
                }
                $moved->delete();
            }

            $refreshedRegistrations = PushDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('installation_id', $installationId)
                ->where('token_hash', '!=', $tokenHash)
                ->get();

            foreach ($refreshedRegistrations as $refreshed) {
                $legacyColumn = $refreshed->platform === 'web' ? 'web_token' : 'device_token';
                if ($user->getAttribute($legacyColumn) === $refreshed->token) {
                    $user->forceFill([$legacyColumn => null])->save();
                }
                $refreshed->delete();
            }

            $sameTokenRegistration = PushDeviceToken::query()
                ->where('token_hash', $tokenHash)
                ->first();
            if ($sameTokenRegistration !== null && $sameTokenRegistration->user_id !== $user->id) {
                $previousUser = User::query()->find($sameTokenRegistration->user_id);
                $previousLegacyColumn = $sameTokenRegistration->platform === 'web' ? 'web_token' : 'device_token';
                if ($previousUser?->getAttribute($previousLegacyColumn) === $sameTokenRegistration->token) {
                    $previousUser->forceFill([$previousLegacyColumn => null])->save();
                }
                $sameTokenRegistration->delete();
            }

            $registration = PushDeviceToken::query()->updateOrCreate(
                ['token_hash' => $tokenHash],
                [
                    'user_id' => $user->id,
                    'installation_id' => $installationId,
                    'platform' => $platform,
                    'token' => $token,
                    'last_seen_at' => now(),
                ]
            );

            $legacyColumn = $platform === 'web' ? 'web_token' : 'device_token';
            if ($user->getAttribute($legacyColumn) !== $token) {
                $user->forceFill([$legacyColumn => $token])->save();
            }

            return $registration;
        });
    }

    public function unregister(User $user, string $installationId): bool
    {
        return DB::transaction(function () use ($user, $installationId): bool {
            $registrations = PushDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('installation_id', $installationId)
                ->get();

            foreach ($registrations as $registration) {
                $legacyColumn = $registration->platform === 'web' ? 'web_token' : 'device_token';
                if ($user->getAttribute($legacyColumn) === $registration->token) {
                    $replacement = PushDeviceToken::query()
                        ->where('user_id', $user->id)
                        ->where('platform', $registration->platform)
                        ->where('installation_id', '!=', $installationId)
                        ->value('token');
                    $user->forceFill([$legacyColumn => $replacement])->save();
                }
            }

            return PushDeviceToken::query()
                ->where('user_id', $user->id)
                ->where('installation_id', $installationId)
                ->delete() > 0;
        });
    }

    /** @return Collection<int, string> */
    public function tokensFor(User $user): Collection
    {
        return PushDeviceToken::query()
            ->where('user_id', $user->id)
            ->whereNotNull('token')
            ->pluck('token')
            ->merge([$user->web_token, $user->device_token])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->unique()
            ->values();
    }
}
