<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureSurfaceTokenAbility
{
    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user === null || $token === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $ability = 'surface:'.strtolower($surface);

        if (
            $token instanceof TransientToken ||
            (
                $request->bearerToken() === null &&
                $token->can($ability)
            )
        ) {
            return $next($request);
        }

        $bearerToken = $request->bearerToken();
        $personalAccessToken = $bearerToken !== null ? PersonalAccessToken::findToken($bearerToken) : null;

        if (
            $personalAccessToken === null ||
            $personalAccessToken->name !== strtolower($surface).'_auth_token' ||
            !$personalAccessToken->can($ability)
        ) {
            return response()->json([
                'message' => sprintf('Forbidden. %s token required.', strtolower($surface)),
            ], 403);
        }

        $tokenable = $personalAccessToken->tokenable;

        if ($tokenable instanceof Authenticatable) {
            if (method_exists($tokenable, 'withAccessToken')) {
                $tokenable->withAccessToken($personalAccessToken);
            }

            $request->setUserResolver(static fn () => $tokenable);
            Auth::setUser($tokenable);
        }

        return $next($request);
    }
}
