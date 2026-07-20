<!DOCTYPE html>
<html lang="en">

@php
    $paymentLogoUrl = $logoUrl ?? ($logo?->logo ?? null);
    $paymentFaviconUrl = $faviconUrl ?? ($faviconLogo?->faviconLogo ?? asset('images/required/theme-favicon-logo.png'));
@endphp

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company['company_name'] }}</title>
    <link rel="icon" href="{{ $paymentFaviconUrl }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/custom.css') }}">
    <style>
        .payment-logo-placeholder {
            align-items: center;
            background: #fff;
            border: 1px dashed #d9dbe9;
            border-radius: 12px;
            color: #a0a3bd;
            display: flex;
            font-size: 12px;
            font-weight: 700;
            height: 56px;
            justify-content: center;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            width: 144px;
        }
    </style>
</head>

<body>

    <div class="py-14 px-4 w-full max-w-3xl mx-auto">
        <a href="{{ route('home') }}" class="block mx-auto w-36 mb-8">
            @if ($paymentLogoUrl)
                <img class="w-full max-h-14 object-contain" src="{{ $paymentLogoUrl }}" alt="logo">
            @else
                <span class="payment-logo-placeholder">Logo</span>
            @endif
        </a>
    </div>

    <script type="application/javascript">
    let data       = JSON.parse(localStorage.getItem('vuex') || '{}');
    const url      = '<?=URL::to('/') . "/table-order/"?>';
    const order_id = '<?=$order?->id?>';
    const homeRoute = document.getElementById('home-route');
    if (homeRoute && data.tableCart && data.tableCart.paymentMethod) {
        homeRoute.setAttribute('href', url + data.tableCart.table.slug + '/' + order_id);
    }
</script>

    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    <script>
        const paymentSessionId = '<?= $paymentSessionId ?>';
        const cashfreePayLink = '<?= $cashfreePayLink ?>';
        const cashfreeCancelLink = '<?= $cashfreeCancelLink ?>';
        const mode = '<?= $mode ?>';

        const cashfree = Cashfree({
            mode: mode
        });
        let checkoutOptions = {
            paymentSessionId: paymentSessionId,
            redirectTarget: "_modal"
        }
        cashfree.checkout(checkoutOptions).then((result) => {
            if (result.error) {
                window.location.href = cashfreeCancelLink;
            }
            if (result.paymentDetails) {
                window.location.href = cashfreePayLink;
            }
        });
    </script>

</body>

</html>
