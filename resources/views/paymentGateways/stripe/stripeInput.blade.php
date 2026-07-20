<style>
    .stripe-payment-panel {
        border: 1px solid #d9dbe9;
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(31, 31, 57, 0.06);
        margin-bottom: 24px;
        overflow: hidden;
    }

    .stripe-payment-heading {
        color: #1f1f39;
        font-size: 18px;
        font-weight: 800;
        padding: 22px 28px 0;
    }

    .stripe-payment-card {
        padding: 18px 28px 28px;
    }

    .stripe-payment-title {
        color: #1f1f39;
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 18px;
    }

    .stripe-payment-label {
        color: #4b5563;
        display: block;
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stripe-card-box {
        border: 1px solid #d9dbe9;
        border-radius: 10px;
        overflow: hidden;
    }

    .stripe-card-row {
        align-items: center;
        background: #fff;
        display: flex;
        gap: 12px;
        min-height: 58px;
        padding: 0 16px;
    }

    .stripe-card-row + .stripe-card-row,
    .stripe-card-split {
        border-top: 1px solid #e5e7eb;
    }

    .stripe-card-field {
        flex: 1;
        min-width: 0;
    }

    .stripe-card-icons {
        display: flex;
        flex-shrink: 0;
        gap: 6px;
    }

    .stripe-card-icon {
        align-items: center;
        border: 1px solid #d9dbe9;
        border-radius: 4px;
        color: #1f1f39;
        display: flex;
        font-size: 10px;
        font-weight: 800;
        height: 24px;
        justify-content: center;
        line-height: 1;
        min-width: 34px;
        padding: 0 5px;
    }

    .stripe-card-split {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }

    .stripe-card-split .stripe-card-row + .stripe-card-row {
        border-left: 1px solid #e5e7eb;
        border-top: 0;
    }

    .stripe-cardholder-input {
        border: 1px solid #d9dbe9;
        border-radius: 10px;
        color: #1f1f39;
        font-size: 16px;
        height: 54px;
        outline: none;
        padding: 0 16px;
        width: 100%;
    }

    .stripe-cardholder-input::placeholder {
        color: #a0a3bd;
    }

    .stripe-cardholder-input:focus,
    .stripe-card-box:focus-within {
        border-color: rgb(var(--primary));
        box-shadow: 0 0 0 3px rgba(242, 62, 20, 0.12);
    }

    .stripe-card-error {
        color: #e93c3c;
        font-size: 13px;
        font-weight: 600;
        margin-top: 10px;
        min-height: 20px;
    }

    @media (max-width: 640px) {
        .stripe-payment-heading,
        .stripe-payment-card {
            padding-left: 18px;
            padding-right: 18px;
        }

        .stripe-card-icons {
            gap: 4px;
        }

        .stripe-card-icon {
            font-size: 9px;
            min-width: 30px;
        }
    }
</style>

<fieldset id="stripe_div" class="mb-6 hidden">
    <div class="stripe-payment-panel">
        <h1 class="stripe-payment-heading">Payment method</h1>

        <div class="stripe-payment-card">
            <h2 class="stripe-payment-title">Card</h2>

            <label class="stripe-payment-label">Card information</label>
            <div class="stripe-card-box">
                <div class="stripe-card-row">
                    <div id="card-number-element" class="stripe-card-field"></div>
                    <div class="stripe-card-icons" aria-hidden="true">
                        <span class="stripe-card-icon">VISA</span>
                        <span class="stripe-card-icon">MC</span>
                        <span class="stripe-card-icon">AMEX</span>
                        <span class="stripe-card-icon">UPI</span>
                    </div>
                </div>
                <div class="stripe-card-split">
                    <div class="stripe-card-row">
                        <div id="card-expiry-element" class="stripe-card-field"></div>
                    </div>
                    <div class="stripe-card-row">
                        <div id="card-cvc-element" class="stripe-card-field"></div>
                    </div>
                </div>
            </div>

            <label class="stripe-payment-label mt-5" for="card-holder-name">Cardholder name</label>
            <input id="card-holder-name" class="stripe-cardholder-input" type="text" autocomplete="cc-name" placeholder="Name on card">
            <div id="card-errors" class="stripe-card-error" role="alert"></div>
        </div>
    </div>
</fieldset>
