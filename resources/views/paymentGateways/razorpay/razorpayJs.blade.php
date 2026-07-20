@php $razorpayKey = ""; @endphp
@if(!blank($paymentGateways))
    @foreach($paymentGateways as $paymentGateway)
        @if($paymentGateway->slug === 'razorpay')
            @php $paymentGatewayOption = $paymentGateway->gatewayOptions->pluck('value', 'option'); $razorpayKey = $paymentGatewayOption['razorpay_key']; @endphp
        @endif
    @endforeach
@endif

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    const razorpayKey          = @json($razorpayKey);
    const razorpayTotalAmount  = @json($order->total);
    const razorpayCurrencyCode = @json($currency->code);
    const razorpayCompany      = @json($company['company_name']);
    const razorpayLogo         = @json($logoUrl ?? ($logo?->logo ?? null));
    const razorpayUserName     = @json($order->user?->name);
    const razorpayUserEmail    = @json($order->user?->email);
    const razorpayPayLink      = @json(route('payment.store', ['order' => $order]));
    const razorpaySuccessLink  = @json(route('payment.successful', ['order' => $order]));
    const razorpayCancelLink   = @json(route('payment.cancel', ['order' => $order, 'paymentGateway' => 'razorpay']));
</script>
<script src="{{ asset('paymentGateways/razorpay/razorpay.js') }}"></script>
