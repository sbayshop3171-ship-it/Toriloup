<template>
    <PlatformWorkspaceShell
        title="Platform Dashboard"
        subtitle="Platform-wide metrics, merchant health, support alerts, and the quickest path to what needs attention.">
        <LoadingComponent :props="loading" />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in topCards"
                :key="card.key"
                class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-semibold text-[#111827]">{{ card.value }}</p>
                <p class="mt-2 text-sm text-[#6B7280]">{{ card.caption }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in secondaryCards"
                :key="card.key"
                class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#6B7280]">{{ card.label }}</p>
                <p class="mt-3 text-2xl font-semibold text-[#111827]">{{ card.value }}</p>
                <p class="mt-2 text-sm text-[#6B7280]">{{ card.caption }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Merchant Growth Trend</h2>
                        <p class="text-sm text-[#6B7280]">New merchant registrations over the last two weeks.</p>
                    </div>
                </div>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="point in merchantGrowth"
                        :key="point.date"
                        class="grid grid-cols-[90px_1fr_48px] items-center gap-3">
                        <p class="text-xs text-[#6B7280]">{{ shortDate(point.date) }}</p>
                        <div class="h-3 rounded-full bg-[#F3F4F6]">
                            <div class="h-3 rounded-full bg-[#FB7185]" :style="{ width: barWidth(point.count, growthMax) }"></div>
                        </div>
                        <p class="text-right text-sm font-semibold text-[#111827]">{{ point.count }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Platform Sales Trend</h2>
                <p class="text-sm text-[#6B7280]">Paid GMV and order activity across every live merchant.</p>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="point in salesTrend"
                        :key="point.date"
                        class="grid grid-cols-[90px_1fr_70px] items-center gap-3">
                        <p class="text-xs text-[#6B7280]">{{ shortDate(point.date) }}</p>
                        <div class="h-3 rounded-full bg-[#F3F4F6]">
                            <div class="h-3 rounded-full bg-[#4F46E5]" :style="{ width: barWidth(point.gmv_total, salesMax) }"></div>
                        </div>
                        <p class="text-right text-sm font-semibold text-[#111827]">{{ money(point.gmv_total) }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr_0.9fr]">
            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Top Merchants by GMV</h2>
                        <p class="text-sm text-[#6B7280]">Revenue leaders across the platform.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="merchant in topMerchants"
                        :key="merchant.id"
                        class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-[#111827]">{{ merchant.name }}</p>
                                <p class="text-xs text-[#6B7280]">{{ merchant.slug }} • {{ merchant.primary_domain || merchantHost }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-[#111827]">{{ money(merchant.gmv_total) }}</p>
                                <p class="text-xs text-[#6B7280]">{{ merchant.completed_orders_count }} delivered</p>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 text-xs text-[#6B7280] sm:grid-cols-2">
                            <p>{{ merchant.products_count }} products</p>
                            <p>{{ merchant.customers_count }} customers</p>
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Merchants Needing Action</h2>
                <p class="text-sm text-[#6B7280]">Draft, suspended, or blocked merchants needing owner attention.</p>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="merchant in merchantsNeedingAction"
                        :key="merchant.id"
                        class="rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-4">
                        <p class="font-semibold text-[#111827]">{{ merchant.name }}</p>
                        <p class="mt-1 text-xs text-[#92400E]">{{ merchant.primary_domain || merchant.slug }}</p>
                        <ul class="mt-3 space-y-2 text-sm text-[#92400E]">
                            <li v-for="reason in merchant.reasons" :key="`${merchant.id}-${reason}`">{{ reason }}</li>
                        </ul>
                    </div>
                    <div v-if="merchantsNeedingAction.length === 0" class="rounded-2xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-6 text-center text-sm text-[#6B7280]">
                        No merchants are currently flagged.
                    </div>
                </div>
            </article>

            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Latest Audit Events</h2>
                <p class="text-sm text-[#6B7280]">Recent owner-wide platform actions and support-sensitive changes.</p>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="event in latestAuditEvents"
                        :key="event.id"
                        class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-[#111827]">{{ event.action_code }}</p>
                                <p class="text-xs text-[#6B7280]">{{ event.tenant?.name || "platform" }} • {{ event.actor?.name || "system" }}</p>
                            </div>
                            <p class="text-xs text-[#6B7280]">{{ formatDateTime(event.created_at) }}</p>
                        </div>
                    </div>
                    <div v-if="latestAuditEvents.length === 0" class="rounded-2xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-6 text-center text-sm text-[#6B7280]">
                        No recent audit events.
                    </div>
                </div>
            </article>
        </section>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import ENV from "../../config/env";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";
import PlatformWorkspaceShell from "./PlatformWorkspaceShell.vue";

export default {
    name: "PlatformDashboardComponent",
    components: {
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            summary: {},
            merchantGrowth: [],
            salesTrend: [],
            topMerchants: [],
            merchantsNeedingAction: [],
            latestAuditEvents: [],
        };
    },
    computed: {
        merchantHost: function () {
            return ENV.MERCHANT_HOST || "merchant.toriloup.com";
        },
        topCards: function () {
            return [
                {
                    key: "merchants_total",
                    label: "Total Registered Merchants",
                    value: this.summary.merchants_total || 0,
                    caption: "Every merchant account created on the platform.",
                },
                {
                    key: "products_total",
                    label: "Total Platform Products",
                    value: this.summary.products_total || 0,
                    caption: "All products uploaded across every merchant catalog.",
                },
                {
                    key: "customers_total",
                    label: "Total Platform Customers",
                    value: this.summary.customers_total || 0,
                    caption: "Deduped customer footprint across merchants.",
                },
                {
                    key: "gmv_total",
                    label: "Total Platform Revenue",
                    value: this.money(this.summary.gmv_total || 0),
                    caption: "Paid GMV used for platform growth tracking.",
                },
            ];
        },
        secondaryCards: function () {
            return [
                {
                    key: "merchants_active",
                    label: "Active Merchants",
                    value: this.summary.merchants_active || 0,
                    caption: "Merchants currently allowed to operate live stores.",
                },
                {
                    key: "merchants_suspended",
                    label: "Suspended Merchants",
                    value: this.summary.merchants_suspended || 0,
                    caption: "Stores blocked by owner action or platform policy.",
                },
                {
                    key: "new_signups_today",
                    label: "New Merchants Today",
                    value: this.summary.new_signups_today || 0,
                    caption: "Fresh registrations created today.",
                },
                {
                    key: "new_signups_this_month",
                    label: "New Merchants This Month",
                    value: this.summary.new_signups_this_month || 0,
                    caption: "Month-to-date merchant acquisition.",
                },
                {
                    key: "live_domains",
                    label: "Live Domains",
                    value: this.summary.live_domains || 0,
                    caption: "Verified domains with active SSL.",
                },
                {
                    key: "pending_domains",
                    label: "Pending Domains",
                    value: this.summary.pending_domains || 0,
                    caption: "Custom domains still waiting on verification.",
                },
                {
                    key: "active_subscriptions",
                    label: "Active Subscriptions",
                    value: this.summary.active_subscriptions || 0,
                    caption: "Merchant plans currently active or trialing.",
                },
                {
                    key: "failed_renewals",
                    label: "Failed Renewals",
                    value: this.summary.failed_renewals || 0,
                    caption: "Past-due subscriptions needing billing follow-up.",
                },
                {
                    key: "support_sessions_active",
                    label: "Active Support Sessions",
                    value: this.summary.support_sessions_active || 0,
                    caption: "Audited owner-as-merchant sessions currently open.",
                },
                {
                    key: "provider_issues",
                    label: "Provider Issues",
                    value: this.summary.provider_issues || 0,
                    caption: "Disabled or broken master providers to review.",
                },
            ];
        },
        growthMax: function () {
            return Math.max(...this.merchantGrowth.map((item) => Number(item.count || 0)), 1);
        },
        salesMax: function () {
            return Math.max(...this.salesTrend.map((item) => Number(item.gmv_total || 0)), 1);
        },
    },
    mounted() {
        this.fetchSummary();
    },
    methods: {
        fetchSummary: function () {
            this.loading.isActive = true;

            axios.get("platform/overview")
                .then((res) => {
                    this.summary = res?.data?.summary || {};
                    this.merchantGrowth = Array.isArray(res?.data?.merchant_growth) ? res.data.merchant_growth : [];
                    this.salesTrend = Array.isArray(res?.data?.sales_trend) ? res.data.sales_trend : [];
                    this.topMerchants = Array.isArray(res?.data?.top_merchants) ? res.data.top_merchants : [];
                    this.merchantsNeedingAction = Array.isArray(res?.data?.merchants_needing_action) ? res.data.merchants_needing_action : [];
                    this.latestAuditEvents = Array.isArray(res?.data?.latest_audit_events) ? res.data.latest_audit_events : [];
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
        barWidth: function (value, max) {
            const safeMax = Number(max || 0);
            const numericValue = Number(value || 0);

            if (safeMax <= 0 || numericValue <= 0) {
                return "0%";
            }

            return `${Math.max(8, Math.round((numericValue / safeMax) * 100))}%`;
        },
        money: function (amount, currency = "USD") {
            return `${currency} ${Number(amount || 0).toFixed(2)}`;
        },
        shortDate: function (value) {
            if (!value) {
                return "n/a";
            }

            return new Date(value).toLocaleDateString();
        },
        formatDateTime: function (value) {
            if (!value) {
                return "Not available";
            }

            return new Date(value).toLocaleString();
        },
    },
};
</script>
