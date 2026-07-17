<template>
    <LoadingComponent :props="loading" />
    <div id="payment" class="db-tab-div active">
        <div class="db-card">
            <div class="db-card-header">
                <div>
                    <h3 class="db-card-title">Payment Methods</h3>
                    <p class="text-sm text-gray-500">Enable only the owner-approved payment methods your storefront should expose.</p>
                </div>
            </div>
            <div class="db-card-body">
                <form @submit.prevent="save" class="form-row">
                    <div class="form-col-12" v-if="formMethods.length === 0">
                        <p class="text-sm text-gray-500">No payment methods are available for this store yet.</p>
                    </div>

                    <div v-for="(method, index) in formMethods" :key="method.id" class="form-col-12">
                        <div class="p-4 rounded-lg border border-gray-200 bg-white">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h4 class="text-base font-semibold capitalize text-gray-900">{{ method.display_name || method.provider_code }}</h4>
                                    <p class="text-sm text-gray-500">Provider: {{ method.provider_code }}</p>
                                </div>

                                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input v-model="method.status" type="checkbox" class="w-4 h-4" />
                                    {{ $t("label.status") }}
                                </label>
                            </div>

                            <div class="grid gap-4 mt-4 md:grid-cols-3">
                                <div>
                                    <label class="db-field-title">Display Name</label>
                                    <input v-model="method.display_name" type="text" class="db-field-control" />
                                </div>
                                <div>
                                    <label class="db-field-title">Checkout Label</label>
                                    <input v-model="method.checkout_label" type="text" class="db-field-control" />
                                </div>
                                <div>
                                    <label class="db-field-title">Sort Order</label>
                                    <input v-model.number="method.sort_order" type="number" min="0" max="999" class="db-field-control" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-col-12">
                        <button type="submit" class="db-btn text-white bg-primary">
                            <i class="lab lab-fill-save"></i>
                            <span>{{ $t("button.save") }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import alertService from "../../../../services/alertService";

export default {
    name: "PaymentGatewayComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            formMethods: [],
            errors: {},
        };
    },
    mounted() {
        try {
            this.loading.isActive = true;
            this.$store.dispatch("merchantPaymentMethod/lists").then((res) => {
                this.formMethods = res.data.data.map((method) => ({
                    ...method,
                    status: Boolean(method.status),
                }));
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        } catch (err) {
            this.loading.isActive = false;
            alertService.error(err);
        }
    },
    methods: {
        save: function () {
            try {
                this.loading.isActive = true;
                this.$store.dispatch("merchantPaymentMethod/save", {
                    methods: this.formMethods.map((method) => ({
                        id: method.id,
                        status: Boolean(method.status),
                        display_name: method.display_name,
                        checkout_label: method.checkout_label,
                        sort_order: method.sort_order ?? 0,
                    })),
                }).then((res) => {
                    this.loading.isActive = false;
                    this.formMethods = res.data.data.map((method) => ({
                        ...method,
                        status: Boolean(method.status),
                    }));
                    alertService.successFlip(res.config.method === "put" ?? 0, "Payment Methods");
                    this.errors = {};
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.errors;
                });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
    },
};
</script>
