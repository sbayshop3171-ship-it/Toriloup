<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Checkout</title>
    <style>
        body {
            margin: 0;
            font-family: "Public Sans", Arial, sans-serif;
            background: #f6f8fb;
            color: #111827;
        }
        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 48px 20px;
        }
        .hero {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.08);
        }
        .eyebrow {
            color: #f23e14;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }
        .grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin-top: 24px;
        }
        .card {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 18px;
            padding: 20px;
        }
        .title {
            margin: 8px 0 0;
            font-size: 34px;
            line-height: 1.15;
        }
        .muted {
            color: #6b7280;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }
        button {
            border: 0;
            border-radius: 14px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            padding: 14px 22px;
        }
        .primary {
            background: #f23e14;
            color: #fff;
        }
        .secondary {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 12px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
@php
    $subscription = $session['subscription'] ?? null;
    $invoice = $session['invoice'] ?? null;
    $plan = $subscription['plan'] ?? [];
@endphp
<div class="wrap">
    <div class="hero">
        <span class="eyebrow">Manual SaaS Billing</span>
        <h1 class="title">Complete your subscription payment</h1>
        <p class="muted">
            This hosted billing step is controlled by the platform owner. Your new plan will activate only after payment is confirmed.
        </p>

        <div class="grid">
            <div class="card">
                <p class="muted">Plan</p>
                <h2 style="margin: 8px 0 0; font-size: 24px;">{{ $plan['name'] ?? 'Plan' }}</h2>
                <p class="muted" style="margin-top: 8px;">{{ $plan['short_description'] ?? ($plan['description'] ?? 'Subscription plan') }}</p>
            </div>
            <div class="card">
                <p class="muted">Billing Cycle</p>
                <h2 style="margin: 8px 0 0; font-size: 24px; text-transform: capitalize;">{{ $subscription['billing_interval'] ?? 'monthly' }}</h2>
                <p class="muted" style="margin-top: 8px;">Amount due: {{ $invoice['currency_code'] ?? 'USD' }} {{ $invoice['total_amount'] ?? '0.00' }}</p>
            </div>
            <div class="card">
                <p class="muted">Invoice</p>
                <h2 style="margin: 8px 0 0; font-size: 24px;">{{ $invoice['invoice_no'] ?? 'Pending invoice' }}</h2>
                <div class="status" style="margin-top: 12px;">{{ $session['status'] ?? 'pending' }}</div>
            </div>
        </div>

        <div class="actions">
            <form method="post" action="{{ url('/api/platform/billing/providers/' . $providerCode . '/return') }}">
                <input type="hidden" name="session_token" value="{{ $session['session_token'] ?? '' }}">
                <input type="hidden" name="status" value="paid">
                <button type="submit" class="primary">Confirm Payment</button>
            </form>

            <form method="post" action="{{ url('/api/platform/billing/providers/' . $providerCode . '/return') }}">
                <input type="hidden" name="session_token" value="{{ $session['session_token'] ?? '' }}">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="secondary">Cancel</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
