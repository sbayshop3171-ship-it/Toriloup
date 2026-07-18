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

        <section v-else-if="page.key === 'billing'" class="space-y-6">
            <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Plan Catalog</h2>
                            <p class="text-sm text-[#6B7280]">Publishable plans, cycle pricing, limits, and feature unlocks.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90"
                            @click="createPlan">
                            New Plan
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <button
                            v-for="plan in plans"
                            :key="plan.code"
                            type="button"
                            class="w-full rounded-xl border p-4 text-left transition"
                            :class="selectedPlanCode === plan.code ? 'border-primary bg-[#FFF6F3]' : 'border-[#E5E7EB] bg-[#F9FAFB] hover:border-primary/40'"
                            @click="selectPlan(plan)">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[#111827]">{{ plan.name }}</p>
                                    <p class="text-xs uppercase tracking-[0.14em] text-[#6B7280]">{{ plan.code }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="plan.is_public ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#F3F4F6] text-[#6B7280]'">
                                        {{ plan.is_public ? "Public" : "Hidden" }}
                                    </span>
                                    <span v-if="plan.recommended || plan.badge_label" class="rounded-full bg-[#EEF2FF] px-3 py-1 text-xs font-semibold text-[#4338CA]">
                                        {{ plan.badge_label || "Recommended" }}
                                    </span>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-[#6B7280]">{{ plan.short_description || plan.description || "No description yet." }}</p>
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="font-semibold text-[#111827]">{{ money(plan.prices?.monthly || plan.monthly_price, plan.currency_code) }}/mo</span>
                                <span class="text-[#6B7280]">{{ plan.subscribers_count }} subscribers</span>
                            </div>
                        </button>
                    </div>
                </article>

                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Plan Editor</h2>
                            <p class="text-sm text-[#6B7280]">Edit plan basics, prices, quotas, and the merchant compare table.</p>
                        </div>
                        <div v-if="planForm.code" class="rounded-full bg-[#F9FAFB] px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-[#6B7280]">
                            {{ planForm.code }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Plan Code</span>
                                <input v-model.trim="planForm.code" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="starter" />
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Plan Name</span>
                                <input v-model.trim="planForm.name" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="Free" />
                            </label>
                            <label class="grid gap-2 text-sm md:col-span-2">
                                <span class="font-medium text-[#374151]">Short Description</span>
                                <input v-model.trim="planForm.short_description" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="Start selling today." />
                            </label>
                            <label class="grid gap-2 text-sm md:col-span-2">
                                <span class="font-medium text-[#374151]">Long Description</span>
                                <textarea v-model="planForm.description" rows="3" class="rounded-xl border border-[#D1D5DB] px-4 py-3 outline-none transition focus:border-primary"></textarea>
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Status</span>
                                <select v-model="planForm.status" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Badge Label</span>
                                <input v-model.trim="planForm.badge_label" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="Most Popular" />
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Currency</span>
                                <input v-model.trim="planForm.currency_code" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 uppercase outline-none transition focus:border-primary" maxlength="10" />
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Trial Days</span>
                                <input v-model.number="planForm.trial_days" type="number" min="0" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" />
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Display Order</span>
                                <input v-model.number="planForm.display_order" type="number" min="0" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" />
                            </label>
                            <div class="grid gap-3 text-sm">
                                <span class="font-medium text-[#374151]">Visibility</span>
                                <label class="inline-flex items-center gap-2 text-[#374151]">
                                    <input v-model="planForm.is_public" type="checkbox" />
                                    Public for merchants
                                </label>
                                <label class="inline-flex items-center gap-2 text-[#374151]">
                                    <input v-model="planForm.is_recommended" type="checkbox" />
                                    Recommended plan
                                </label>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-[#111827]">Prices</h3>
                                <p class="text-xs text-[#6B7280]">Monthly, 6 months, and yearly billing cycles.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="grid gap-2 text-sm">
                                    <span class="font-medium text-[#374151]">Monthly</span>
                                    <input v-model.number="planForm.prices.monthly" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" />
                                </label>
                                <label class="grid gap-2 text-sm">
                                    <span class="font-medium text-[#374151]">6 Months</span>
                                    <input v-model.number="planForm.prices.semiannual" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" />
                                </label>
                                <label class="grid gap-2 text-sm">
                                    <span class="font-medium text-[#374151]">Yearly</span>
                                    <input v-model.number="planForm.prices.yearly" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" />
                                </label>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-[#111827]">Limits</h3>
                                <button type="button" class="text-sm font-semibold text-primary" @click="addLimitRow">Add limit</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(limit, index) in planForm.limits" :key="'limit-' + index" class="grid gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4 md:grid-cols-[1.1fr_0.9fr_auto_auto]">
                                    <input v-model.trim="limit.key" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="products" />
                                    <input v-model.number="limit.value" :disabled="limit.is_unlimited" type="number" min="0" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary disabled:bg-[#F3F4F6]" placeholder="0" />
                                    <label class="inline-flex items-center gap-2 text-sm text-[#374151]">
                                        <input v-model="limit.is_unlimited" type="checkbox" />
                                        Unlimited
                                    </label>
                                    <button type="button" class="text-sm font-semibold text-[#B91C1C]" @click="removeLimitRow(index)">Remove</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-[#111827]">Feature Matrix</h3>
                                <button type="button" class="text-sm font-semibold text-primary" @click="addFeatureRow">Add feature</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(feature, index) in planForm.features" :key="'feature-' + index" class="grid gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4 md:grid-cols-6">
                                    <input v-model.trim="feature.code" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="custom_domain" />
                                    <input v-model.trim="feature.label" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="Custom domain" />
                                    <input v-model.trim="feature.group" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="Store & Branding" />
                                    <select v-model="feature.type" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                                        <option value="boolean">Boolean</option>
                                        <option value="text">Text</option>
                                        <option value="integer">Integer</option>
                                        <option value="percent">Percent</option>
                                    </select>
                                    <input v-model="feature.value" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="true / 500 / Unlimited" />
                                    <div class="flex items-center gap-3">
                                        <input v-model.number="feature.sort_order" type="number" min="0" class="h-11 w-full rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="10" />
                                        <button type="button" class="text-sm font-semibold text-[#B91C1C]" @click="removeFeatureRow(index)">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#D1D5DB] px-5 text-sm font-semibold text-[#374151]" @click="resetPlanForm">
                                Reset
                            </button>
                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl bg-primary px-5 text-sm font-semibold text-white transition hover:opacity-90" @click="savePlan">
                                {{ savingPlan ? "Saving..." : "Save Plan" }}
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Subscription Oversight</h2>
                <p class="text-sm text-[#6B7280]">Pending activations, active subscriptions, invoice state, and owner-paid overrides.</p>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                <th class="px-4 py-3 font-semibold">Tenant</th>
                                <th class="px-4 py-3 font-semibold">Plan</th>
                                <th class="px-4 py-3 font-semibold">Cycle</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Invoice</th>
                                <th class="px-4 py-3 font-semibold">Period End</th>
                                <th class="px-4 py-3 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="subscriptions.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-[#6B7280]">No subscriptions yet.</td>
                            </tr>
                            <tr v-for="subscription in subscriptions" :key="subscription.id" class="border-b border-[#F3F4F6] last:border-b-0">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-[#111827]">{{ subscription.tenant?.name || "Unknown tenant" }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ subscription.tenant?.slug || "" }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">{{ subscription.plan?.name || subscription.plan_code_snapshot || "starter" }}</td>
                                <td class="px-4 py-4 align-top capitalize">{{ subscription.billing_interval }}</td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(subscription.status)">
                                        {{ subscription.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-[#111827]">{{ subscription.invoices?.[0]?.invoice_no || "-" }}</p>
                                    <p class="text-xs capitalize text-[#6B7280]">{{ subscription.invoices?.[0]?.status || "no invoice" }}</p>
                                </td>
                                <td class="px-4 py-4 align-top text-[#6B7280]">{{ shortDate(subscription.current_period_ends_at) }}</td>
                                <td class="px-4 py-4 align-top">
                                    <button
                                        v-if="subscription.invoices?.[0]?.status === 'open'"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg bg-primary px-3 text-xs font-semibold text-white transition hover:opacity-90"
                                        @click="markInvoicePaid(subscription)"
                                    >
                                        {{ payingSubscriptionId === subscription.id ? "Marking..." : "Mark Paid" }}
                                    </button>
                                    <span v-else class="text-xs text-[#6B7280]">No action</span>
                                </td>
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
                    <option value="mail">Mail</option>
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
            selectedPlanCode: null,
            planForm: {
                code: "",
                name: "",
                short_description: "",
                description: "",
                status: "draft",
                is_public: true,
                display_order: 0,
                is_recommended: false,
                badge_label: "",
                currency_code: "USD",
                trial_days: 0,
                prices: {
                    monthly: 0,
                    semiannual: 0,
                    yearly: 0,
                },
                limits: [],
                features: [],
            },
            savingPlan: false,
            payingSubscriptionId: null,
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
                    this.retainSelectedPlan();
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
        createPlan: function () {
            const code = window.prompt("Enter a new plan code");

            if (!code) {
                return;
            }

            const normalizedCode = String(code).trim().toLowerCase().replace(/\s+/g, "-");
            this.selectedPlanCode = normalizedCode;
            this.planForm = this.emptyPlanForm(normalizedCode);
        },
        emptyPlanForm: function (code = "") {
            return {
                code,
                name: "",
                short_description: "",
                description: "",
                status: "draft",
                is_public: true,
                display_order: this.plans.length + 1,
                is_recommended: false,
                badge_label: "",
                currency_code: "USD",
                trial_days: 0,
                prices: {
                    monthly: 0,
                    semiannual: 0,
                    yearly: 0,
                },
                limits: [
                    { key: "products", value: 0, is_unlimited: false },
                    { key: "custom_domains", value: 0, is_unlimited: false },
                    { key: "staff_members", value: 0, is_unlimited: false },
                ],
                features: [],
            };
        },
        selectPlan: function (plan) {
            this.selectedPlanCode = plan.code;
            this.planForm = this.hydratePlanForm(plan);
        },
        hydratePlanForm: function (plan) {
            return {
                code: plan.code || "",
                name: plan.name || "",
                short_description: plan.short_description || "",
                description: plan.description || "",
                status: plan.status || "draft",
                is_public: plan.is_public !== false,
                display_order: Number(plan.display_order || 0),
                is_recommended: plan.recommended === true,
                badge_label: plan.badge_label || "",
                currency_code: plan.currency_code || "USD",
                trial_days: Number(plan.trial_days || 0),
                prices: {
                    monthly: Number(plan?.prices?.monthly || plan.monthly_price || 0),
                    semiannual: Number(plan?.prices?.semiannual || 0),
                    yearly: Number(plan?.prices?.yearly || plan.yearly_price || 0),
                },
                limits: Array.isArray(plan.limits) && plan.limits.length > 0
                    ? plan.limits.map((limit) => ({
                        key: limit.key,
                        value: limit.value,
                        is_unlimited: limit.is_unlimited,
                    }))
                    : this.emptyPlanForm().limits,
                features: Array.isArray(plan.features)
                    ? plan.features.map((feature) => ({
                        code: feature.code,
                        label: feature.label,
                        group: feature.group,
                        type: feature.type,
                        value: feature.type === "boolean" ? (feature.enabled ? "true" : "false") : feature.value,
                        sort_order: Number(feature.sort_order || 0),
                    }))
                    : [],
            };
        },
        retainSelectedPlan: function () {
            if (this.plans.length === 0) {
                this.planForm = this.emptyPlanForm();
                return;
            }

            const plan = this.plans.find((item) => item.code === this.selectedPlanCode) || this.plans[0];
            this.selectPlan(plan);
        },
        addLimitRow: function () {
            this.planForm.limits.push({ key: "", value: 0, is_unlimited: false });
        },
        removeLimitRow: function (index) {
            this.planForm.limits.splice(index, 1);
        },
        addFeatureRow: function () {
            this.planForm.features.push({
                code: "",
                label: "",
                group: "Operations",
                type: "boolean",
                value: "false",
                sort_order: (this.planForm.features.length + 1) * 10,
            });
        },
        removeFeatureRow: function (index) {
            this.planForm.features.splice(index, 1);
        },
        resetPlanForm: function () {
            const plan = this.plans.find((item) => item.code === this.selectedPlanCode);
            this.planForm = plan ? this.hydratePlanForm(plan) : this.emptyPlanForm(this.selectedPlanCode || "");
        },
        savePlan: function () {
            if (!this.planForm.code || !this.planForm.name) {
                return;
            }

            this.savingPlan = true;

            axios.put(`platform/plans/${this.planForm.code}`, {
                name: this.planForm.name,
                short_description: this.planForm.short_description,
                description: this.planForm.description,
                status: this.planForm.status,
                is_public: this.planForm.is_public,
                display_order: this.planForm.display_order,
                is_recommended: this.planForm.is_recommended,
                badge_label: this.planForm.badge_label || null,
                currency_code: this.planForm.currency_code,
                trial_days: this.planForm.trial_days,
                prices: {
                    monthly: Number(this.planForm.prices.monthly || 0),
                    semiannual: Number(this.planForm.prices.semiannual || 0),
                    yearly: Number(this.planForm.prices.yearly || 0),
                },
                limits: this.planForm.limits.filter((limit) => limit.key).map((limit) => ({
                    key: limit.key,
                    value: limit.is_unlimited ? null : Number(limit.value || 0),
                    is_unlimited: !!limit.is_unlimited,
                })),
                features: this.planForm.features.filter((feature) => feature.code && feature.label).map((feature) => ({
                    code: feature.code,
                    label: feature.label,
                    group: feature.group || "Operations",
                    type: feature.type || "boolean",
                    value: feature.value,
                    sort_order: Number(feature.sort_order || 0),
                })),
            }).then(() => {
                return Promise.all([
                    axios.get("platform/plans"),
                    axios.get("platform/subscriptions"),
                ]);
            }).then(([plans, subscriptions]) => {
                this.plans = Array.isArray(plans?.data?.data) ? plans.data.data : [];
                this.subscriptions = Array.isArray(subscriptions?.data?.data) ? subscriptions.data.data : [];
                this.retainSelectedPlan();
            }).finally(() => {
                this.savingPlan = false;
            });
        },
        markInvoicePaid: function (subscription) {
            const invoice = subscription?.invoices?.[0];

            if (!invoice) {
                return;
            }

            this.payingSubscriptionId = subscription.id;

            axios.post(`platform/subscriptions/${subscription.id}/invoices/${invoice.id}/mark-paid`).then(() => {
                this.fetchPageData();
            }).finally(() => {
                this.payingSubscriptionId = null;
            });
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
