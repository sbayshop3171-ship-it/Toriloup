<template>
    <LoadingComponent :props="loading" />

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="db-card xl:col-span-2">
            <div class="db-card-header">
                <div>
                    <h3 class="db-card-title">Billing Summary</h3>
                    <p class="text-sm text-gray-500">Check plan status, period dates, and your operational limits before launch.</p>
                </div>
            </div>
            <div class="db-card-body" v-if="summary">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</p>
                        <h4 class="mt-1 text-lg font-semibold text-gray-900">{{ summary.subscription?.plan?.name || summary.tenant?.plan_code || "-" }}</h4>
                        <p class="mt-1 text-sm text-gray-500 capitalize">Status: {{ summary.subscription?.status || summary.tenant?.status }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Billing Cycle</p>
                        <h4 class="mt-1 text-lg font-semibold text-gray-900 capitalize">{{ summary.subscription?.billing_interval || "-" }}</h4>
                        <p class="mt-1 text-sm text-gray-500">{{ summary.subscription?.current_period_starts_at || "-" }} to {{ summary.subscription?.current_period_ends_at || "-" }}</p>
                    </div>
                </div>

                <div class="grid gap-4 mt-4 md:grid-cols-3">
                    <div v-for="(usage, key) in summary.usage" :key="key" class="p-4 rounded-lg border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ key }}</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ usage.used ?? 0 }} / {{ usage.limit === null ? 'Unlimited' : usage.limit }}</p>
                        <p class="mt-1 text-sm text-gray-500">Remaining: {{ usage.remaining === null ? 'Unlimited' : usage.remaining }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header">
                <h3 class="db-card-title">Latest Invoices</h3>
            </div>
            <div class="db-card-body">
                <div v-if="invoices.length === 0" class="text-sm text-gray-500">No invoices found for this store.</div>
                <div v-for="invoice in invoices" :key="invoice.id" class="p-3 rounded-lg border border-gray-200 not-last:mb-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ invoice.invoice_no }}</p>
                            <p class="text-xs text-gray-500 capitalize">{{ invoice.status }}</p>
                        </div>
                        <p class="text-sm font-semibold text-gray-900">{{ invoice.currency_code }} {{ invoice.total_amount }}</p>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Issued: {{ invoice.issued_at || "-" }}</p>
                    <p class="text-xs text-gray-500">Due: {{ invoice.due_at || "-" }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";

export default {
    name: "BillingSummaryComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
        };
    },
    computed: {
        summary: function () {
            return this.$store.getters["merchantBilling/summary"];
        },
        invoices: function () {
            return this.$store.getters["merchantBilling/invoices"];
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        fetchData: function () {
            this.loading.isActive = true;
            Promise.all([
                this.$store.dispatch("merchantBilling/summary"),
                this.$store.dispatch("merchantBilling/invoices"),
            ]).finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
