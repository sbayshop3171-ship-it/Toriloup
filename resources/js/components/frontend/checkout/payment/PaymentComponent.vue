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
                        :class="Object.keys(paymentMethod).length > 0 && cashOnDelivery.id === paymentMethod.id ? 'border-primary/50 bg-[#FFF4F1]' : 'border-white bg-white'"
                        class="flex flex-col items-center justify-center gap-2.5 py-4 rounded-lg shadow-xs cursor-pointer border">
                        <img class="h-6" :src="cashOnDelivery.image" alt="payment" loading="eager" decoding="async" fetchpriority="high" />
                        <span class="text-xs font-medium">{{ cashOnDelivery.name }}</span>
                    </div>

                    <div v-if="Object.keys(credit).length > 0 && profile.balance >= total"
                        :key="credit.id"
                        @click.prevent="selectPaymentMethod(credit)"
                        :class="Object.keys(paymentMethod).length > 0 && credit.id === paymentMethod.id ? 'border-primary/50 bg-[#FFF4F1]' : 'border-white bg-white'"
                        class="flex flex-col items-center justify-center gap-2.5 py-4 rounded-lg shadow-xs cursor-pointer border">
                        <img class="h-6" :src="credit.image" alt="payment" loading="eager" decoding="async" fetchpriority="high" />
                        <span class="text-xs font-medium">{{ credit.name }} ({{ profile.balance }})</span>
                    </div>

                    <div v-for="(paymentGateway, index) in paymentGateways"
                        :key="paymentGateway.id"
                        @click.prevent="selectPaymentMethod(paymentGateway)"
                        :class="Object.keys(paymentMethod).length > 0 && paymentGateway.id === paymentMethod.id ? 'border-primary/50 bg-[#FFF4F1]' : 'border-white bg-white'"
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

                <button @click.prevent="confirmOrder" class="field-button w-fit font-semibold tracking-wide normal-case">
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

                <button @click.prevent="confirmOrder($event)" class="field-button font-semibold tracking-wide normal-case">
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
            form: {}
        }
    },
    computed: {
        profile: function () {
            return this.$store.getters.authInfo;
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
            this.$store.dispatch("frontendCart/paymentMethod", paymentMethod);
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

            this.loading.isActive = false;
        },
        confirmOrder: function (e) {
            const button = e?.currentTarget || e?.target;
            if (button) {
                button.disabled = true;
            }

            if (Object.keys(this.paymentMethod).length === 0 || !this.paymentMethod.id) {
                alertService.error(this.$t('message.payment_method_required'));
                this.resetConfirmButton(button);
                return;
            }

            this.loading.isActive = true;
            this.form = {
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
                payment_method: Object.keys(this.paymentMethod).length > 0 ? this.paymentMethod.id : 0,
                products: JSON.stringify(this.products)
            }

            this.$store.dispatch('frontendOrder/save', this.form).then(orderResponse => {
                this.loading.isActive = false;
                let paymentSlug = Object.keys(this.paymentMethod).length > 0 ? this.paymentMethod.slug : '';
                if (paymentSlug) {
                    window.location.href = new URL(
                        "/payment/" + paymentSlug + "/pay/" + orderResponse.data.data.id,
                        window.location.origin
                    ).toString();
                } else {
                    alertService.error(this.$t('message.payment_method_required'));
                    this.resetConfirmButton(button);
                }
            }).catch((err) => {
                this.resetConfirmButton(button);
                if (typeof err.response.data.errors === 'object') {
                    _.forEach(err.response.data.errors, (error) => {
                        alertService.error(error[0]);
                    });
                }
            });
        }
    }
}
</script>
