<?php

namespace App\Services;

use App\Models\FirebaseServiceAccount;
use Illuminate\Support\Facades\Crypt;

class FirebaseServiceAccountService
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function store(array $credentials): void
    {
        FirebaseServiceAccount::query()->updateOrCreate(
            ['id' => 1],
            ['encrypted_json' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR))]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credentials(): ?array
    {
        $encrypted = FirebaseServiceAccount::query()->whereKey(1)->value('encrypted_json');
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        $credentials = json_decode(Crypt::decryptString($encrypted), true);

        return is_array($credentials) ? $credentials : null;
    }

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }
}
