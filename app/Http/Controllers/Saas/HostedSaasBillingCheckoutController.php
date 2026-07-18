<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Contracts\View\View;

class HostedSaasBillingCheckoutController extends Controller
{
    public function __construct(private readonly SubscriptionManagerService $subscriptionManagerService)
    {
    }

    public function show(string $providerCode, string $sessionToken): View
    {
        $session = $this->subscriptionManagerService->findCheckoutSessionByToken($sessionToken);

        abort_if($session === null || $session->provider_code !== $providerCode, 404, 'Checkout session not found.');

        return view('saas.manual-billing-checkout', [
            'providerCode' => $providerCode,
            'session' => $this->subscriptionManagerService->serializeCheckoutSession($session),
        ]);
    }
}
