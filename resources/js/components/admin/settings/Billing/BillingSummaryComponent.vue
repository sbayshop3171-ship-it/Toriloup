<template>
    <OwnerBillingPlanManagerComponent v-if="isOwnerBillingSurface" />
    <template v-else>
        <LoadingComponent :props="loading" />

        <div class="space-y-6">
        <div v-if="flashMessage" class="db-card">
            <div class="db-card-body">
                <div class="rounded-lg border px-4 py-3 text-sm" :class="flashMessage.type === 'success' ? 'border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]' : 'border-[#FED7AA] bg-[#FFF7ED] text-[#C2410C]'">
                    {{ flashMessage.text }}
                </div>
            </div>
        </div>

        <div v-if="summary?.pending_upgrade" class="db-card">
            <div class="db-card-body">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">Pending upgrade</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">
                            {{ summary.pending_upgrade.subscription?.plan?.name || "Plan change pending" }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Complete payment to activate {{ summary.pending_upgrade.subscription?.billing_interval || "selected" }} billing.
                        </p>
                    </div>
                    <a :href="checkoutUrl(summary.pending_upgrade)" class="db-btn py-2 text-white bg-primary">
                        Continue payment
                    </a>
                </div>
            </div>
        </div>

        <div v-if="billingNotice" class="db-card">
            <div class="db-card-body">
                <div class="rounded-lg border px-4 py-3 text-sm" :class="billingNotice.className">
                    <p class="font-semibold">{{ billingNotice.title }}</p>
                    <p class="mt-1">{{ billingNotice.body }}</p>
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="db-card-title">Choose your plan</h3>
                    <p class="text-sm text-gray-500">Pick the plan that fits your store. Paid plans activate only after successful payment.</p>
                </div>
                <div class="inline-flex rounded-full border border-gray-200 bg-white p-1">
                    <button
                        v-for="interval in intervals"
                        :key="interval.value"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="selectedInterval === interval.value ? 'bg-primary text-white' : 'text-gray-500 hover:text-primary'"
                        @click="selectedInterval = interval.value">
                        {{ interval.label }}
                    </button>
                </div>
            </div>
            <div class="db-card-body">
                <div v-if="plans.length === 0" class="rounded-2xl border border-dashed border-gray-200 bg-[#FAFBFC] p-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#FFF4F1] text-primary">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <h4 class="mt-3 text-base font-semibold text-gray-900">{{ summary?.catalog?.has_active_public_plans ? "No public plans available yet" : "All plan features are available" }}</h4>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ summary?.catalog?.has_active_public_plans
                            ? "The platform owner can publish Free, Basic, Premium, or Advanced plans from Owner Plans & Billing. Your current plan stays active until an upgrade is available."
                            : "The platform owner has not published an active public plan, so your store can use plan-gated features without upgrade locks." }}
                    </p>
                </div>

                <div v-else class="grid gap-4 xl:grid-cols-4 lg:grid-cols-2">
                    <article
                        v-for="plan in plans"
                        :key="plan.code"
                        class="relative rounded-2xl border p-5 transition"
                        :class="isCurrentPlan(plan) ? 'border-primary bg-[#FFF6F3]' : 'border-gray-200 bg-white'">
                        <span
                            v-if="plan.badge_label || plan.recommended"
                            class="absolute right-4 top-4 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="plan.recommended ? 'bg-[#EEF2FF] text-[#4338CA]' : 'bg-[#F3F4F6] text-[#4B5563]'">
                            {{ plan.badge_label || "Recommended" }}
                        </span>

                        <p class="text-xl font-semibold text-gray-900">{{ plan.name }}</p>
                        <p class="mt-2 min-h-[44px] text-sm text-gray-500">{{ plan.short_description || plan.description || "Plan details not added yet." }}</p>

                        <div class="mt-5 flex items-end gap-1">
                            <span class="text-4xl font-bold text-gray-900">{{ money(plan, selectedInterval) }}</span>
                            <span class="pb-1 text-sm text-gray-500">/ {{ intervalLabel(selectedInterval) }}</span>
                        </div>

                        <button
                            type="button"
                            class="db-btn mt-5 w-full py-2"
                            :class="isCurrentPlan(plan) ? 'bg-gray-100 text-gray-500 cursor-default' : 'bg-primary text-white'"
                            :disabled="isCurrentPlan(plan) || checkoutLoading === plan.code"
                            @click="checkout(plan)">
                            {{ isCurrentPlan(plan) ? 'Current plan' : checkoutLoading === plan.code ? 'Processing...' : 'Upgrade' }}
                        </button>

                        <div class="mt-5 space-y-3 border-t border-gray-100 pt-5">
                            <div v-for="highlight in highlightItems(plan)" :key="plan.code + '-' + highlight.code" class="flex items-start gap-3 text-sm text-gray-600">
                                <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full" :class="highlight.enabled ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#F3F4F6] text-[#9CA3AF]'">
                                    {{ highlight.enabled ? "✓" : "•" }}
                                </span>
                                <span>{{ highlight.label }} <span class="font-semibold text-gray-900" v-if="highlight.type !== 'boolean'">{{ highlight.display_value }}</span></span>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="plans.length > 0 && compareGroups.length > 0" class="mt-8 overflow-x-auto rounded-2xl border border-gray-200 bg-white">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="border-b border-gray-200 bg-[#FAFBFC]">
                            <th class="px-4 py-4 font-semibold text-gray-900">Features</th>
                            <th v-for="plan in plans" :key="'head-' + plan.code" class="px-4 py-4 text-center font-semibold text-gray-900">
                                <div>{{ plan.name }}</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">{{ money(plan, selectedInterval) }}/{{ intervalLabel(selectedInterval) }}</div>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <template v-for="group in compareGroups" :key="group.group">
                            <tr class="border-b border-gray-200 bg-[#FAFBFC]">
                                <td :colspan="plans.length + 1" class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ group.group }}
                                </td>
                            </tr>
                            <tr v-for="row in group.rows" :key="group.group + '-' + row.code" class="border-b border-gray-100 last:border-b-0">
                                <td class="px-4 py-4 font-medium text-gray-700">{{ row.label }}</td>
                                <td v-for="plan in plans" :key="row.code + '-' + plan.code" class="px-4 py-4 text-center">
                                    <span v-if="featureValue(plan, row.code).type === 'boolean'" class="inline-flex h-7 w-7 items-center justify-center rounded-full" :class="featureValue(plan, row.code).enabled ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#F3F4F6] text-[#9CA3AF]'">
                                        {{ featureValue(plan, row.code).enabled ? "✓" : "—" }}
                                    </span>
                                    <span v-else class="font-semibold text-gray-900">
                                        {{ featureValue(plan, row.code).display_value }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="plans.length > 0" class="mt-8 rounded-2xl border border-dashed border-gray-200 bg-[#FAFBFC] p-6 text-center text-sm text-gray-500">
                    Plan feature comparison will appear here after the owner configures plan features.
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="db-card xl:col-span-2">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Subscription status</h3>
                        <p class="text-sm text-gray-500">Your active plan, renewal state, and usage stay visible here.</p>
                    </div>
                </div>
                <div class="db-card-body" v-if="summary">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current plan</p>
                            <h4 class="mt-1 text-lg font-semibold text-gray-900">{{ currentPlanLabel }}</h4>
                            <p class="mt-1 text-sm capitalize text-gray-500">Status: {{ currentPlanStatus }}</p>
                            <p v-if="summary.subscription?.grace_ends_at" class="mt-1 text-sm text-[#C2410C]">
                                Grace ends at {{ formatDate(summary.subscription.grace_ends_at) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current period</p>
                            <h4 class="mt-1 text-lg font-semibold capitalize text-gray-900">{{ summary.subscription?.billing_interval || (summary?.features?.enforced === false ? "No subscription required" : "-") }}</h4>
                            <p class="mt-1 text-sm text-gray-500">{{ summary.subscription ? `${formatDate(summary.subscription?.current_period_starts_at)} to ${formatDate(summary.subscription?.current_period_ends_at)}` : "Available while platform billing is relaxed." }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div v-for="(usage, key) in summary.usage" :key="key" class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ formatLabel(key) }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ usage.used ?? 0 }} / {{ usage.limit === null ? 'Unlimited' : usage.limit }}</p>
                            <p class="mt-1 text-sm text-gray-500">Remaining: {{ usage.remaining === null ? 'Unlimited' : usage.remaining }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Invoice history</h3>
                        <p class="text-sm text-gray-500">Recent activation and renewal invoices.</p>
                    </div>
                </div>
                <div class="db-card-body">
                    <div v-if="invoices.length === 0" class="text-sm text-gray-500">No invoices found for this store.</div>
                    <div v-for="invoice in invoices.slice(0, 8)" :key="invoice.id" class="rounded-lg border border-gray-200 p-3 not-last:mb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ invoice.invoice_no }}</p>
                                <p class="text-xs capitalize text-gray-500">{{ invoice.status }}</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">{{ invoice.currency_code }} {{ invoice.total_amount }}</p>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Issued: {{ formatDate(invoice.issued_at) }}</p>
                        <p class="text-xs text-gray-500">Due: {{ formatDate(invoice.due_at) }}</p>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </template>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import OwnerBillingPlanManagerComponent from "./OwnerBillingPlanManagerComponent.vue";
import { isMerchantHost } from "../../../../services/workspaceService";

export default {
    name: "BillingSummaryComponent",
    components: { LoadingComponent, OwnerBillingPlanManagerComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            selectedInterval: "monthly",
            checkoutLoading: null,
            intervals: [
                { value: "monthly", label: "Monthly" },
                { value: "semiannual", label: "6 Months" },
                { value: "yearly", label: "Yearly" },
            ],
        };
    },
    computed: {
        isOwnerBillingSurface() {
            return !isMerchantHost();
        },
        summary() {
            return this.$store.getters["merchantBilling/summary"];
        },
        invoices() {
            return this.$store.getters["merchantBilling/invoices"] || [];
        },
        plans() {
            return this.$store.getters["merchantBilling/plans"] || [];
        },
        flashMessage() {
            const status = this.$route.query?.billing;

            if (status === "success") {
                return { type: "success", text: "Payment confirmed. Your subscription is now active." };
            }

            if (status === "cancelled") {
                return { type: "warning", text: "Payment was not completed. Your current active plan stayed unchanged." };
            }

            return null;
        },
        currentPlanCode() {
            return this.summary?.subscription?.plan?.code || this.summary?.tenant?.plan_code || null;
        },
        currentPlanLabel() {
            if (this.summary?.subscription?.plan?.name) {
                return this.summary.subscription.plan.name;
            }

            if (this.summary?.tenant?.plan_code) {
                return this.summary.tenant.plan_code;
            }

            return this.summary?.features?.enforced === false ? "Full access" : "-";
        },
        currentPlanStatus() {
            if (this.summary?.subscription?.status) {
                return this.summary.subscription.status;
            }

            return this.summary?.features?.enforced === false ? "available" : (this.summary?.tenant?.status || "-");
        },
        billingNotice() {
            const mode = this.summary?.catalog?.mode || this.summary?.features?.mode;

            if (mode === "grandfathered") {
                return {
                    title: "Full access retained",
                    body: "This store was created before plan enforcement, so existing access remains available until a new plan is selected.",
                    className: "border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]",
                };
            }

            if (mode === "catalog_disabled") {
                return {
                    title: "Plan enforcement is relaxed",
                    body: "No active public plan is published right now, so plan-gated features are available without upgrade locks.",
                    className: "border-gray-200 bg-[#FAFBFC] text-gray-600",
                };
            }

            if (mode === "no_active_subscription") {
                return {
                    title: "Choose a plan to unlock store features",
                    body: "Plan enforcement is active, but this store does not have an active subscription yet.",
                    className: "border-[#FED7AA] bg-[#FFF7ED] text-[#C2410C]",
                };
            }

            return null;
        },
        compareGroups() {
            const groups = {};

            this.plans.forEach((plan) => {
                (plan.compare_groups || []).forEach((group) => {
                    if (!groups[group.group]) {
                        groups[group.group] = {};
                    }

                    (group.items || []).forEach((item) => {
                        if (!groups[group.group][item.code]) {
                            groups[group.group][item.code] = {
                                code: item.code,
                                label: item.label,
                                sort_order: item.sort_order || 0,
                            };
                        }
                    });
                });
            });

            return Object.keys(groups).map((groupName) => ({
                group: groupName,
                rows: Object.values(groups[groupName]).sort((a, b) => a.sort_order - b.sort_order),
            }));
        },
    },
    mounted() {
        if (this.isOwnerBillingSurface) {
            return;
        }

        this.fetchData();
    },
    methods: {
        fetchData() {
            this.loading.isActive = true;
            Promise.all([
                this.$store.dispatch("merchantBilling/summary"),
                this.$store.dispatch("merchantBilling/invoices"),
                this.$store.dispatch("merchantBilling/plans"),
                this.$store.dispatch("merchantDashboard/setup").catch(() => {}),
            ]).finally(() => {
                this.loading.isActive = false;
            });
        },
        isCurrentPlan(plan) {
            return plan.code === this.currentPlanCode && !this.summary?.pending_upgrade;
        },
        intervalLabel(interval) {
            return interval === "semiannual" ? "6 mo" : interval === "yearly" ? "yr" : "mo";
        },
        money(plan, interval) {
            const currency = plan.currency_code || "USD";
            const amount = Number(plan?.prices?.[interval] || 0).toFixed(2);
            return `${currency} ${amount}`;
        },
        formatDate(value) {
            if (!value) {
                return "-";
            }

            return new Date(value).toLocaleDateString();
        },
        formatLabel(value) {
            return String(value || "").replace(/_/g, " ");
        },
        highlightItems(plan) {
            return (plan.features || []).filter((feature) => ["custom_domain", "theme_builder", "report_exports", "returns", "external_gateways"].includes(feature.code)).slice(0, 5);
        },
        featureValue(plan, code) {
            const row = (plan.features || []).find((feature) => feature.code === code);

            if (row) {
                return row;
            }

            const compareRow = (plan.compare_groups || [])
                .flatMap((group) => group.items || [])
                .find((item) => item.code === code);

            return compareRow || { type: "text", display_value: "-", enabled: false };
        },
        checkoutUrl(session) {
            return session?.checkout_url || session?.return_url || "#";
        },
        checkout(plan) {
            this.checkoutLoading = plan.code;
            this.$store.dispatch("merchantBilling/checkout", {
                plan_code: plan.code,
                billing_interval: this.selectedInterval,
            }).then((res) => {
                const payload = res?.data?.data || {};

                if (payload.mode === "checkout" && payload.checkout_url) {
                    window.location.href = payload.checkout_url;
                    return;
                }

                this.fetchData();
            }).finally(() => {
                this.checkoutLoading = null;
            });
        },
    },
};
</script>
