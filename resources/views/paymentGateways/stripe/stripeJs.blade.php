@php $stripeKey = ""; @endphp
@if(!blank($paymentGateways))
    @foreach($paymentGateways as $paymentGateway)
        @if($paymentGateway->slug === 'stripe')
            @php
                $paymentGatewayOption = $paymentGateway->gatewayOptions->pluck('value', 'option');
                $stripeKey = \App\Support\PaymentGatewayCredentials::stripePublishableKey($paymentGatewayOption);
            @endphp
        @endif
    @endforeach
@endif

<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripeKey = @json($stripeKey);
</script>
<script src="{{ asset('paymentGateways/stripe/stripe.js') }}"></script>
