<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformBillingProviderCallbackController extends Controller
{
    public function __construct(private readonly SubscriptionManagerService $subscriptionManagerService)
    {
    }

    public function webhook(Request $request, string $providerCode): JsonResponse
    {
        $session = $this->resolveSession($providerCode, (string) $request->input('session_token', ''));
        $outcome = $this->normalizeOutcome($request->input('status', $request->input('outcome', 'paid')));

        if ($outcome === 'paid') {
            $session = $this->subscriptionManagerService->completeCheckoutSession($session, 'paid');
        } else {
            $session = $this->subscriptionManagerService->cancelCheckoutSession($session, $outcome);
        }

        return response()->json([
            'status' => true,
            'data' => $this->subscriptionManagerService->serializeCheckoutSession($session),
        ]);
    }

    public function handleReturn(Request $request, string $providerCode): JsonResponse|RedirectResponse
    {
        $session = $this->resolveSession($providerCode, (string) $request->input('session_token', $request->query('session_token', '')));
        $outcome = $this->normalizeOutcome($request->input('status', $request->input('outcome', $request->input('action', 'paid'))));

        if ($outcome === 'paid') {
            $session = $this->subscriptionManagerService->completeCheckoutSession($session, 'paid');
            $target = $session->return_url ?: $session->cancel_url;
        } else {
            $session = $this->subscriptionManagerService->cancelCheckoutSession($session, $outcome);
            $target = $session->cancel_url ?: $session->return_url;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'data' => $this->subscriptionManagerService->serializeCheckoutSession($session),
            ]);
        }

        return redirect()->away($target ?: sprintf('https://%s/admin/settings/billing', trim((string) config('saas.merchant_host'), '.')));
    }

    private function resolveSession(string $providerCode, string $token)
    {
        $session = $this->subscriptionManagerService->findCheckoutSessionByToken($token);

        abort_if($session === null || $session->provider_code !== $providerCode, 404, 'Checkout session not found.');

        return $session;
    }

    private function normalizeOutcome(mixed $status): string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'paid', 'success', 'completed' => 'paid',
            'failed', 'error' => 'failed',
            default => 'cancelled',
        };
    }
}
