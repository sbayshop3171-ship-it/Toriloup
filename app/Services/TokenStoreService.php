<?php

namespace App\Services;

use App\Http\Requests\TokenStoreRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class TokenStoreService
{
    /** @throws Exception */
    public function webToken(TokenStoreRequest $request): bool
    {
        return $this->store($request, 'web');
    }

    /** @throws Exception */
    public function deviceToken(TokenStoreRequest $request): bool
    {
        return $this->store($request, 'android');
    }

    private function store(TokenStoreRequest $request, string $platform): bool
    {
        try {
            $user = User::findOrFail($request->user()->id);
            app(PushDeviceTokenService::class)->register(
                $user,
                $request->input('installation_id') ?: $this->legacyInstallationId($user, $platform),
                $platform,
                $request->string('token')->toString()
            );

            return true;
        } catch (Exception $exception) {
            Log::warning('Push device token registration failed.', ['reason' => $exception->getMessage()]);
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function legacyInstallationId(User $user, string $platform): string
    {
        $hash = substr(hash('sha256', 'toriloup:legacy:'.$user->id.':'.$platform), 0, 32);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3)
            .'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
