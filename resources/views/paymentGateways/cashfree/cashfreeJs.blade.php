<!DOCTYPE html>
<html lang="en">

@php
    $paymentViewVars = get_defined_vars();
    $paymentLogoUrl = array_key_exists('logoUrl', $paymentViewVars) ? $logoUrl : ($paymentViewVars['logo']->logo ?? null);
    $paymentFaviconUrl = array_key_exists('faviconUrl', $paymentViewVars) ? $faviconUrl : ($paymentViewVars['faviconLogo']->faviconLogo ?? asset('images/required/theme-favicon-logo.png'));
@endphp

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company['company_name'] }}</title>
    <link rel="icon" href="{{ $paymentFaviconUrl }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/custom.css') }}">
</head>

<body>

    <div class="py-14 px-4 w-full max-w-3xl mx-auto">
        @if ($paymentLogoUrl)
            <a href="{{ route('home') }}" class="block mx-auto w-36 mb-8">
                <img class="w-full max-h-14 object-contain" src="{{ $paymentLogoUrl }}" alt="logo">
            </a>
        @endif
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
