<template>
    <PlatformWorkspaceShell
        title="Platform Dashboard"
        subtitle="Owner-only platform health, merchant activation, and domain status.">
        <LoadingComponent :props="loading" />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in cards"
                :key="card.key"
                class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-semibold text-[#111827]">{{ card.value }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Launch Readiness</h2>
                        <p class="text-sm text-[#6B7280]">Critical owner checks before public launch.</p>
                    </div>
                </div>
                <ul class="mt-5 space-y-3">
                    <li
                        v-for="item in readinessItems"
                        :key="item.label"
                        class="flex items-start justify-between gap-4 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-3">
                        <div>
                            <p class="font-medium">{{ item.label }}</p>
                            <p class="text-sm text-[#6B7280]">{{ item.description }}</p>
                        </div>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            :class="item.emphasis === 'alert' ? 'bg-[#FEF2F2] text-[#B91C1C]' : 'bg-[#EFF6FF] text-[#1D4ED8]'">
                            {{ item.value }}
                        </span>
                    </li>
                </ul>
            </article>

            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Workspace Boundaries</h2>
                <p class="text-sm text-[#6B7280]">Owner host is the temporary home for the full admin project while merchant modules are opened step-by-step.</p>
                <ul class="mt-5 space-y-3 text-sm text-[#374151]">
                    <li class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-3">
                        Full legacy admin tools now run on the owner host under <span class="font-semibold">/admin</span>.
                    </li>
                    <li class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-3">
                        Legacy `api/admin/*` calls are fenced to owner/admin access only.
                    </li>
                    <li class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-3">
                        Merchant host <span class="font-semibold">{{ merchantHost }}</span> is paused for store ops until modules are assigned.
                    </li>
                </ul>
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
            summary: {
                tenants_total: 0,
                tenants_active: 0,
                tenants_suspended: 0,
                tenants_live: 0,
                tenants_onboarding: 0,
                new_signups_today: 0,
                custom_domains_pending: 0,
                custom_domains_verified: 0,
                domain_issues: 0,
                provider_issues: 0,
                merchant_memberships_active: 0,
                subscriptions_active: 0,
                orders_today: 0,
                gmv_today: 0,
                support_alerts: 0,
            },
        };
    },
    computed: {
        merchantHost: function () {
            return ENV.MERCHANT_HOST || "merchant.toriloup.com";
        },
        cards: function () {
            return [
                { key: "tenants_total", label: "Total Tenants", value: this.summary.tenants_total },
                { key: "tenants_active", label: "Active Tenants", value: this.summary.tenants_active },
                { key: "new_signups_today", label: "New Signups Today", value: this.summary.new_signups_today },
                { key: "tenants_onboarding", label: "Merchants Onboarding", value: this.summary.tenants_onboarding },
                { key: "custom_domains_verified", label: "Verified Domains", value: this.summary.custom_domains_verified },
                { key: "provider_issues", label: "Provider Issues", value: this.summary.provider_issues },
                { key: "orders_today", label: "Orders Today", value: this.summary.orders_today },
                { key: "gmv_today", label: "GMV Today", value: Number(this.summary.gmv_today || 0).toFixed(2) },
            ];
        },
        readinessItems: function () {
            return [
                {
                    label: "Pending custom domains",
                    value: this.summary.custom_domains_pending,
                    description: "Domains waiting for owner verification should be cleared before wide launch.",
                    emphasis: this.summary.custom_domains_pending > 0 ? "alert" : "info",
                },
                {
                    label: "Domain issues",
                    value: this.summary.domain_issues,
                    description: "Failed DNS or SSL states should be handled before merchants promote stores.",
                    emphasis: this.summary.domain_issues > 0 ? "alert" : "info",
                },
                {
                    label: "Provider issues",
                    value: this.summary.provider_issues,
                    description: "Disabled payment, SMS, email, or push providers need owner review.",
                    emphasis: this.summary.provider_issues > 0 ? "alert" : "info",
                },
                {
                    label: "Suspended tenants",
                    value: this.summary.tenants_suspended,
                    description: "Review suspended merchants before rollout so support and billing are aligned.",
                    emphasis: this.summary.tenants_suspended > 0 ? "alert" : "info",
                },
                {
                    label: "Live-ready tenants",
                    value: this.summary.tenants_live,
                    description: "Track which merchants are already beyond onboarding and ready for stricter launch testing.",
                    emphasis: "info",
                },
            ];
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
                    this.summary = {
                        ...this.summary,
                        ...(res?.data?.summary || {}),
                    };
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
    },
};
</script>
