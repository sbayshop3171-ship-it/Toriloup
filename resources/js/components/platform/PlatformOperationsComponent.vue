<template>
    <PlatformWorkspaceShell :title="page.title" :subtitle="page.subtitle">
        <LoadingComponent :props="loading" />

        <section v-if="page.key === 'domains'" class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Domain Center</h2>
                    <p class="text-sm text-[#6B7280]">Verify custom domains, watch SSL status, and keep fallback domains visible.</p>
                </div>
                <input v-model.trim="filters.q" type="text" placeholder="Search domain" class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary" />
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                            <th class="px-4 py-3 font-semibold">Domain</th>
                            <th class="px-4 py-3 font-semibold">Tenant</th>
                            <th class="px-4 py-3 font-semibold">Verification</th>
                            <th class="px-4 py-3 font-semibold">SSL</th>
                            <th class="px-4 py-3 font-semibold">Fallback Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredDomains.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-[#6B7280]">No domains matched.</td>
                        </tr>
                        <tr v-for="domain in filteredDomains" :key="domain.id" class="border-b border-[#F3F4F6] last:border-b-0">
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-[#111827]">{{ domain.hostname }}</p>
                                <p class="text-xs text-[#6B7280]">{{ domain.domain_type }} <span v-if="domain.is_primary">primary</span><span v-if="domain.is_fallback">fallback</span></p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ domain.tenant?.name || "Unknown tenant" }}</p>
                                <p class="text-xs text-[#6B7280]">{{ domain.tenant?.slug || "" }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(domain.verification_status)">
                                    {{ domain.verification_status }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(domain.ssl_status)">
                                    {{ domain.ssl_status || "unknown" }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top text-[#374151]">{{ domain.dns_instructions?.cname_target || "Not required" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="page.key === 'billing'" class="grid gap-6 xl:grid-cols-[1fr_1.4fr]">
            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Plan Catalog</h2>
                <p class="text-sm text-[#6B7280]">Owner-managed packages and limits.</p>
                <div class="mt-5 space-y-3">
                    <div v-for="plan in plans" :key="plan.id" class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-[#111827]">{{ plan.name }}</p>
                                <p class="text-xs uppercase tracking-[0.14em] text-[#6B7280]">{{ plan.code }}</p>
                            </div>
                            <span class="rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-semibold text-[#1D4ED8]">
                                {{ money(plan.monthly_price, plan.currency_code) }}/mo
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-[#6B7280]">{{ plan.description || "No description yet." }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Subscription Oversight</h2>
                <p class="text-sm text-[#6B7280]">Tenant billing state, renewal windows, and invoice health.</p>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                <th class="px-4 py-3 font-semibold">Tenant</th>
                                <th class="px-4 py-3 font-semibold">Plan</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Period End</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="subscriptions.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-[#6B7280]">No subscriptions yet.</td>
                            </tr>
                            <tr v-for="subscription in subscriptions" :key="subscription.id" class="border-b border-[#F3F4F6] last:border-b-0">
                                <td class="px-4 py-4 align-top">{{ subscription.tenant?.name || "Unknown tenant" }}</td>
                                <td class="px-4 py-4 align-top">{{ subscription.plan_code_snapshot || subscription.plan?.code || "starter" }}</td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(subscription.status)">
                                        {{ subscription.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-[#6B7280]">{{ shortDate(subscription.current_period_ends_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="page.key === 'providers'" class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Provider Center</h2>
                    <p class="text-sm text-[#6B7280]">Master payment, SMS, email, and push providers stay owner-controlled.</p>
                </div>
                <select v-model="filters.providerType" class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary">
                    <option value="">All provider types</option>
                    <option value="payment">Payment</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                    <option value="push">Push</option>
                </select>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="provider in filteredProviders" :key="provider.id" class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-[#111827]">{{ provider.name }}</p>
                            <p class="text-xs uppercase tracking-[0.14em] text-[#6B7280]">{{ provider.provider_code }} / {{ provider.provider_type }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="provider.status ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#FEF2F2] text-[#B91C1C]'">
                            {{ provider.status ? "Active" : "Disabled" }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-[#6B7280]">Config keys: {{ configKeys(provider.config_json) }}</p>
                </article>
            </div>
        </section>

        <section v-else-if="page.key === 'audit'" class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Audit & Security</h2>
                    <p class="text-sm text-[#6B7280]">Owner actions, domain changes, subscription changes, and support-sensitive events.</p>
                </div>
                <input v-model.trim="filters.q" type="text" placeholder="Search action, tenant, actor" class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary" @keyup.enter="fetchAuditLogs" />
            </div>
            <div class="mt-5 space-y-3">
                <div v-if="auditLogs.length === 0" class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-8 text-center text-sm text-[#6B7280]">
                    No audit logs found yet.
                </div>
                <article v-for="log in auditLogs" :key="log.id" class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="font-semibold text-[#111827]">{{ log.action_code }}</p>
                            <p class="text-sm text-[#6B7280]">{{ log.entity_type }} #{{ log.entity_id || "n/a" }} &middot; {{ log.tenant?.name || "platform" }}</p>
                        </div>
                        <p class="text-xs text-[#6B7280]">{{ shortDate(log.created_at) }}</p>
                    </div>
                    <p class="mt-2 text-xs text-[#6B7280]">Actor: {{ log.actor?.name || "system" }} &middot; Scope: {{ log.actor_scope }}</p>
                </article>
            </div>
        </section>

        <section v-else class="grid gap-6 lg:grid-cols-3">
            <article v-for="item in page.items" :key="item.title" class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ item.kicker }}</p>
                <h2 class="mt-2 text-lg font-semibold text-[#111827]">{{ item.title }}</h2>
                <p class="mt-3 text-sm leading-6 text-[#6B7280]">{{ item.body }}</p>
            </article>
        </section>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";
import PlatformWorkspaceShell from "./PlatformWorkspaceShell.vue";

const pages = {
    features: {
        key: "features",
        title: "Feature Control",
        subtitle: "Tenant modules, presets, and rollout rules without touching daily store operations.",
        items: [
            { kicker: "Modules", title: "Tenant feature gates", body: "Enable advanced stock, POS, returns, campaigns, reports, and other modules per merchant profile." },
            { kicker: "Mode", title: "Simple vs advanced", body: "Keep new merchants simple by default and unlock advanced workflows only when their business is ready." },
            { kicker: "Rollout", title: "Controlled release", body: "Roll new capabilities to selected tenants first, then expand after smoke checks and support review." },
        ],
    },
    support: {
        key: "support",
        title: "Support Desk",
        subtitle: "Merchant visibility, support notes, risk controls, and audited impersonation workflow.",
        items: [
            { kicker: "Visibility", title: "Merchant timeline", body: "Review last login, domain changes, billing state, onboarding state, and recent owner actions." },
            { kicker: "Impersonation", title: "Audited login-as", body: "Owner can enter a merchant workspace for support with a visible banner, timeout, exit action, and audit log." },
            { kicker: "Safety", title: "Risk lock", body: "Sensitive actions can be locked while support investigates billing, fraud, domain, or account issues." },
        ],
    },
    settings: {
        key: "settings",
        title: "Platform Settings",
        subtitle: "Brand defaults, legal defaults, localization defaults, and operational release controls.",
        items: [
            { kicker: "Brand", title: "Global defaults", body: "Marketing brand, fallback storefront suffix, default locale, default currency, and owner-visible platform identity." },
            { kicker: "Policy", title: "Legal defaults", body: "Default privacy, refund, return, shipping, and support policy templates for new tenant onboarding." },
            { kicker: "Release", title: "Maintenance controls", body: "Deploy state, backup freshness, smoke checks, rollback readiness, and queue/scheduler health." },
        ],
    },
};

export default {
    name: "PlatformOperationsComponent",
    components: {
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            domains: [],
            plans: [],
            subscriptions: [],
            providers: [],
            auditLogs: [],
            filters: {
                q: "",
                providerType: "",
            },
        };
    },
    computed: {
        page: function () {
            return pages[this.$route.meta.section] || {
                key: this.$route.meta.section,
                title: this.$route.meta.title,
                subtitle: this.$route.meta.subtitle,
                items: [],
            };
        },
        filteredDomains: function () {
            if (!this.filters.q) {
                return this.domains;
            }

            const term = this.filters.q.toLowerCase();

            return this.domains.filter((domain) => [
                domain.hostname,
                domain.tenant?.name,
                domain.tenant?.slug,
            ].join(" ").toLowerCase().includes(term));
        },
        filteredProviders: function () {
            return this.providers.filter((provider) => !this.filters.providerType || provider.provider_type === this.filters.providerType);
        },
    },
    watch: {
        "$route.meta.section": function () {
            this.fetchPageData();
        },
    },
    mounted() {
        this.fetchPageData();
    },
    methods: {
        fetchPageData: function () {
            this.loading.isActive = true;

            const section = this.page.key;
            let request;

            if (section === "domains") {
                request = axios.get("platform/domains").then((res) => {
                    this.domains = Array.isArray(res?.data?.data) ? res.data.data : [];
                });
            } else if (section === "billing") {
                request = Promise.all([
                    axios.get("platform/plans"),
                    axios.get("platform/subscriptions"),
                ]).then(([plans, subscriptions]) => {
                    this.plans = Array.isArray(plans?.data?.data) ? plans.data.data : [];
                    this.subscriptions = Array.isArray(subscriptions?.data?.data) ? subscriptions.data.data : [];
                });
            } else if (section === "providers") {
                request = axios.get("platform/providers").then((res) => {
                    this.providers = Array.isArray(res?.data?.data) ? res.data.data : [];
                });
            } else if (section === "audit") {
                request = this.fetchAuditLogs(false);
            } else {
                request = Promise.resolve();
            }

            request.finally(() => {
                this.loading.isActive = false;
            });
        },
        fetchAuditLogs: function (toggleLoading = true) {
            if (toggleLoading) {
                this.loading.isActive = true;
            }

            return axios.get("platform/audit-logs", {
                params: {
                    q: this.filters.q || undefined,
                    limit: 75,
                },
            }).then((res) => {
                this.auditLogs = Array.isArray(res?.data?.data) ? res.data.data : [];
            }).finally(() => {
                if (toggleLoading) {
                    this.loading.isActive = false;
                }
            });
        },
        statusClass: function (status) {
            if (["active", "verified", "paid"].includes(status)) {
                return "bg-[#ECFDF3] text-[#047857]";
            }

            if (["failed", "expired", "rejected", "suspended", "disabled"].includes(status)) {
                return "bg-[#FEF2F2] text-[#B91C1C]";
            }

            return "bg-[#FFF7ED] text-[#C2410C]";
        },
        money: function (amount, currency = "USD") {
            return `${currency || "USD"} ${Number(amount || 0).toFixed(2)}`;
        },
        shortDate: function (value) {
            if (!value) {
                return "Not set";
            }

            return new Date(value).toLocaleDateString();
        },
        configKeys: function (config) {
            const keys = Object.keys(config || {});

            return keys.length ? keys.join(", ") : "none";
        },
    },
};
</script>
