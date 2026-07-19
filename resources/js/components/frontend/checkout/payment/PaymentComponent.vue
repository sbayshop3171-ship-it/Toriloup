<template>
    <LoadingComponent :props="loading" />
    <div class="row">
        <div class="col-12 lg:col-8">
            <div class="mb-6 rounded-2xl shadow-card">
                <h4 class="font-bold capitalize p-4 border-b border-gray-100">
                    {{ $t('label.select_payment_method') }}
                </h4>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 p-4">
                    <div v-if="Object.keys(cashOnDelivery).length > 0"
                        :key="cashOnDelivery.id"
                        @click.prevent="selectPaymentMethod(cashOnDelivery)"
                        :class="paymentMethodCardClass(cashOnDelivery)"
                        class="flex flex-col items-center justify-center gap-2.5 py-4 rounded-lg shadow-xs cursor-pointer border">
                        <img class="h-6" :src="cashOnDelivery.image" alt="payment" loading="eager" decoding="async" fetchpriority="high" />
                        <span class="text-xs font-medium">{{ cashOnDelivery.name }}</span>
                    </div>

                    <div v-if="Object.keys(credit).length > 0 && profile.balance >= total"
                        :key="credit.id"
                        @click.prevent="selectPaymentMethod(credit)"
                        :class="paymentMethodCardClass(credit)"
                        class="flex flex-col items-center justify-center gap-2.5 py-4 rounded-lg shadow-xs cursor-pointer border">
                        <img class="h-6" :src="credit.image" alt="payment" loading="eager" decoding="async" fetchpriority="high" />
                        <span class="text-xs font-medium">{{ credit.name }} ({{ profile.balance }})</span>
                    </div>

                    <div v-for="(paymentGateway, index) in paymentGateways"
                        :key="paymentGateway.id"
                        @click.prevent="selectPaymentMethod(paymentGateway)"
                        :class="paymentMethodCardClass(paymentGateway)"
                        class="flex flex-col items-center justify-center gap-2.5 py-4 rounded-lg shadow-xs cursor-pointer border">
                        <img
                            class="h-6"
                            :src="paymentGateway.image"
                            alt="payment"
                            decoding="async"
                            :loading="index < 4 ? 'eager' : 'lazy'"
                            :fetchpriority="index < 4 ? 'high' : 'auto'" />
                        <span class="text-xs font-medium">{{ paymentGateway.name }}</span>
                    </div>
                </div>
            </div>

            <div class="max-lg:hidden flex items-center justify-between gap-5 mt-10">
                <router-link :to="{ name: 'frontend.checkout.checkout' }"
                    class="field-button w-fit font-semibold tracking-wide normal-case text-secondary bg-[#F7F7FC]">
                    {{ $t('button.back_to_checkout') }}
                </router-link>

                <button @click.prevent="confirmOrder($event)"
                    :disabled="isSubmitting"
                    :class="{ 'opacity-60 cursor-not-allowed': isSubmitting }"
                    class="field-button w-fit font-semibold tracking-wide normal-case">
                    {{ $t('button.confirm_order') }}
                </button>
            </div>
        </div>

        <div class="col-12 lg:col-4">
            <CouponComponent />
            <SummeryComponent />

            <div class="max-lg:flex hidden flex-col-reverse sm:flex-row items-center justify-between gap-5 mt-10">
                <router-link :to="{ name: 'frontend.checkout.checkout' }"
                    class="field-button font-semibold tracking-wide normal-case text-secondary bg-[#F7F7FC]">
                    {{ $t('button.back_to_checkout') }}
                </router-link>

                <button @click.prevent="confirmOrder($event)"
                    :disabled="isSubmitting"
                    :class="{ 'opacity-60 cursor-not-allowed': isSubmitting }"
                    class="field-button font-semibold tracking-wide normal-case">
                    {{ $t('button.confirm_order') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import statusEnum from "../../../../enums/modules/statusEnum";
import SummeryComponent from "../SummeryComponent.vue";
import CouponComponent from "../CouponComponent.vue";
import LoadingComponent from "../../components/LoadingComponent.vue";
import _ from "lodash";
import alertService from "../../../../services/alertService";
import sourceEnum from "../../../../enums/modules/sourceEnum";
import orderTypeEnum from "../../../../enums/modules/orderTypeEnum";

export default {
    name: "PaymentComponent",
    components: { CouponComponent, SummeryComponent, LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false
            },
            paymentGateways: [],
            credit: {},
            cashOnDelivery: {},
            statusEnum: statusEnum,
            sourceEnum: sourceEnum,
            form: {},
            isSubmitting: false
        }
    },
    computed: {
        profile: function () {
            return this.$store.getters.authInfo;
        },
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        paymentMethod: function () {
            return this.$store.getters['frontendCart/paymentMethod'];
        },
        subtotal: function () {
            return this.$store.getters['frontendCart/subtotal'];
        },
        discount: function () {
            return this.$store.getters['frontendCart/discount'];
        },
        total: function () {
            return this.$store.getters['frontendCart/total'];
        },
        orderType: function () {
            return this.$store.getters['frontendCart/orderType'];
        },
        getShippingAddress: function () {
            return this.$store.getters['frontendCart/shippingAddress'];
        },
        getBillingAddress: function () {
            return this.$store.getters['frontendCart/billingAddress'];
        },
        getOutletAddress: function () {
            return this.$store.getters['frontendCart/outletAddress'];
        },
        cartCoupon: function () {
            return this.$store.getters['frontendCart/coupon'];
        },
        products: function () {
            return this.$store.getters['frontendCart/lists'];
        },
        shippingCharge: function () {
            return this.$store.getters['frontendCart/shippingCharge']
        },
        totalTax: function () {
            return this.$store.getters['frontendCart/totalTax'];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch('frontendPaymentGateway/lists', { status: this.statusEnum.ACTIVE }).then(res => {
            if (res.data.data.length > 0) {
                _.forEach(res.data.data, (gateway) => {
                    if (gateway.slug === "credit") {
                        this.credit = gateway;
                    } else if (gateway.slug === "cashondelivery") {
                        this.cashOnDelivery = gateway;
                    } else {
                        this.paymentGateways.push(gateway);
                    }
                });
                this.syncSelectedPaymentMethod();
            }
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });
    },
    methods: {
        selectPaymentMethod: function (paymentMethod) {
            if (this.isSubmitting) {
                return;
            }

            this.$store.dispatch("frontendCart/paymentMethod", paymentMethod);
        },
        paymentMethodCardClass: function (paymentMethod) {
            return [
                this.isSelectedPaymentMethod(paymentMethod) ? 'border-primary/50 bg-[#FFF4F1]' : 'border-white bg-white',
                this.isSubmitting ? 'pointer-events-none opacity-60' : ''
            ];
        },
        isSelectedPaymentMethod: function (paymentMethod) {
            return Object.keys(this.paymentMethod).length > 0 && paymentMethod?.id === this.paymentMethod.id;
        },
        availablePaymentMethods: function () {
            const methods = [];

            if (Object.keys(this.cashOnDelivery).length > 0) {
                methods.push(this.cashOnDelivery);
            }

            if (Object.keys(this.credit).length > 0 && this.profile.balance >= this.total) {
                methods.push(this.credit);
            }

            return methods.concat(this.paymentGateways);
        },
        syncSelectedPaymentMethod: function () {
            const methods = this.availablePaymentMethods();
            const selected = Object.keys(this.paymentMethod).length > 0
                ? methods.find((method) => method.id === this.paymentMethod.id)
                : null;

            if (selected) {
                this.selectPaymentMethod(selected);
                return;
            }

            this.selectPaymentMethod(methods.length > 0 ? methods[0] : {});
        },
        resetConfirmButton: function (button) {
            if (button) {
                button.disabled = false;
            }

            this.isSubmitting = false;
            this.loading.isActive = false;
        },
        buildOrderForm: function (paymentMethod) {
            return {
                subtotal: this.subtotal,
                discount: this.discount,
                tax: this.totalTax,
                shipping_charge: this.shippingCharge,
                total: this.total,
                order_type: this.orderType,
                shipping_id: Object.keys(this.getShippingAddress).length > 0 ? this.getShippingAddress.id : 0,
                billing_id: Object.keys(this.getBillingAddress).length > 0 ? this.getBillingAddress.id : 0,
                outlet_id: Object.keys(this.getOutletAddress).length > 0 ? this.getOutletAddress.id : 0,
                coupon_id: Object.keys(this.cartCoupon).length > 0 ? this.cartCoupon.id : 0,
                source: sourceEnum.WEB,
                payment_method: paymentMethod?.id || 0,
                products: JSON.stringify(this.products)
            };
        },
        reportConfirmError: function (err) {
            const errors = err?.response?.data?.errors;

            if (Array.isArray(errors)) {
                _.forEach(errors, (error) => alertService.error(error));
                return;
            }

            if (errors && typeof errors === 'object') {
                _.forEach(errors, (error) => {
                    if (Array.isArray(error)) {
                        _.forEach(error, (message) => alertService.error(message));
                    } else if (typeof error === 'string') {
                        alertService.error(error);
                    }
                });
                return;
            }

            alertService.error(err?.response?.data?.message || err?.message || this.$t('message.something_wrong'));
        },
        confirmOrder: async function (e) {
            if (this.isSubmitting) {
                return;
            }

            const button = e?.currentTarget || e?.target;
            if (button) {
                button.disabled = true;
            }

            if (Object.keys(this.paymentMethod).length === 0 || !this.paymentMethod.id) {
                alertService.error(this.$t('message.payment_method_required'));
                this.resetConfirmButton(button);
                return;
            }

            if (!this.hasRequiredCheckoutAddress()) {
                alertService.error(this.$t('message.shipping_and_billing_address'));
                this.resetConfirmButton(button);
                this.$router.push({ name: 'frontend.checkout.checkout' });
                return;
            }

            this.isSubmitting = true;
            this.loading.isActive = true;
            let isRedirecting = false;

            try {
                await this.$store.dispatch("frontendCart/reprice", { setting: this.setting });

                const selectedPaymentMethod = this.paymentMethod;
                this.form = this.buildOrderForm(selectedPaymentMethod);

                const orderResponse = await this.$store.dispatch('frontendOrder/save', this.form);
                const orderId = orderResponse?.data?.data?.id;
                const paymentSlug = selectedPaymentMethod?.slug;

                if (!paymentSlug || !orderId) {
                    throw new Error(this.$t('message.something_wrong'));
                }

                isRedirecting = true;
                window.location.assign(new URL(
                    "/payment/" + paymentSlug + "/pay/" + orderId,
                    window.location.origin
                ).toString());
            } catch (err) {
                this.reportConfirmError(err);
            } finally {
                if (!isRedirecting) {
                    this.resetConfirmButton(button);
                }
            }
        },
        hasRequiredCheckoutAddress: function () {
            const orderType = Number(this.orderType);

            if (orderType === orderTypeEnum.DELIVERY) {
                return this.hasObject(this.getShippingAddress)
                    && this.hasObject(this.getBillingAddress);
            }

            if (orderType === orderTypeEnum.PICK_UP) {
                return this.hasObject(this.getOutletAddress);
            }

            return false;
        },
        hasObject: function (value) {
            return value && typeof value === 'object' && Object.keys(value).length > 0;
        }
    }
}
</script>
