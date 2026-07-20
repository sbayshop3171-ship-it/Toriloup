"use strict";

function stripe_payment() {
    let errorElement = document.getElementById('card-errors');

    if (errorElement) {
        errorElement.textContent = 'Stripe card form is not ready. Please check the payment gateway settings.';
    }
}

window.stripe_payment = stripe_payment;

if (stripeKey && typeof Stripe !== 'undefined') {
    try {
        let stripe = Stripe(stripeKey);
        let elements = stripe.elements();
        let style = {
            base: {
                fontSize: '16px',
                color: '#1f1f39',
                fontFamily: 'inherit',
                '::placeholder': {
                    color: '#a0a3bd',
                },
            },
            invalid: {
                color: '#e93c3c',
            },
        };

        let cardNumber = elements.create('cardNumber', {
            style: style,
            placeholder: '1234 1234 1234 1234',
        });
        let cardExpiry = elements.create('cardExpiry', {
            style: style,
            placeholder: 'MM / YY',
        });
        let cardCvc = elements.create('cardCvc', {
            style: style,
            placeholder: 'CVC',
        });

        cardNumber.mount('#card-number-element');
        cardExpiry.mount('#card-expiry-element');
        cardCvc.mount('#card-cvc-element');

        window.stripe_payment = function () {
            $('#payment_method').parent().removeClass('has-error');
            let cardholderName = document.getElementById('card-holder-name');

            stripe.createToken(cardNumber, {
                name: cardholderName ? cardholderName.value : '',
            }).then(function (result) {
                if (result.error) {
                    let errorElement = document.getElementById('card-errors');
                    errorElement.textContent = result.error.message;
                } else {
                    stripeTokenHandler(result.token);
                }
            });
        }

        function stripeTokenHandler(token) {
            let form = document.getElementById('paymentForm');
            let hiddenInput = document.createElement('input');
            hiddenInput.setAttribute('type', 'hidden');
            hiddenInput.setAttribute('name', 'stripeToken');
            hiddenInput.setAttribute('value', token.id);
            form.appendChild(hiddenInput);
            form.submit();
        }
    } catch (error) {
        let errorElement = document.getElementById('card-errors');

        if (errorElement) {
            errorElement.textContent = error.message;
        }
    }
}
