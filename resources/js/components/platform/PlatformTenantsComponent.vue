<template>
    <PlatformWorkspaceShell
        title="Merchant Directory"
        subtitle="Cross-tenant visibility for owner review, plan control, domain readiness, and audited merchant support access.">
        <LoadingComponent :props="{ isActive: loading.isActive || loading.detail }" />

        <section class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-[#111827]">Merchants</h2>
                        <p class="text-sm text-[#6B7280]">Owner sees platform-wide health here, while merchants still operate only inside their own scoped workspace.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <input
                            v-model.trim="filters.q"
                            type="text"
                            placeholder="Search store, slug, email"
                            class="h-11 rounded-2xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                            @keyup.enter="fetchTenants" />
                        <select
                            v-model="filters.status"
                            class="h-11 rounded-2xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                            @change="fetchTenants">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <select
                            v-model="filters.plan_code"
                            class="h-11 rounded-2xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                            @change="fetchTenants">
                            <option value="">All plans</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.code">{{ plan.name }}</option>
                        </select>
                        <button
                            type="button"
                            class="h-11 rounded-2xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90"
                            @click="fetchTenants">
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                <th class="px-4 py-3 font-semibold">Merchant</th>
                                <th class="px-4 py-3 font-semibold">Plan</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Products</th>
                                <th class="px-4 py-3 font-semibold">Customers</th>
                                <th class="px-4 py-3 font-semibold">GMV</th>
                                <th class="px-4 py-3 font-semibold">Last Activity</th>
                                <th class="px-4 py-3 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="tenants.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-[#6B7280]">
                                    No merchants matched the current filters.
                                </td>
                            </tr>
                            <tr
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                class="border-b border-[#F3F4F6] last:border-b-0">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-[#111827]">{{ tenant.name }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ tenant.slug }} • {{ tenant.store_code }}</p>
                                    <p class="mt-1 text-xs text-[#6B7280]">{{ tenant.primary_domain || "No primary domain" }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-semibold text-[#1D4ED8]">
                                        {{ tenant.plan_code || "starter" }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                        :class="statusClass(tenant.status)">
                                        {{ tenant.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top font-medium text-[#111827]">{{ tenant.stats?.products_count || 0 }}</td>
                                <td class="px-4 py-4 align-top font-medium text-[#111827]">{{ tenant.stats?.customers_count || 0 }}</td>
                                <td class="px-4 py-4 align-top font-medium text-[#111827]">{{ money(tenant.stats?.gmv_total) }}</td>
                                <td class="px-4 py-4 align-top text-[#6B7280]">{{ formatDateTime(tenant.stats?.last_activity_at) }}</td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]"
                                            @click="openTenant(tenant)">
                                            View
                                        </button>
                                        <button
                                            v-if="tenant.status === 'draft'"
                                            type="button"
                                            class="rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                                            @click="runTenantAction(tenant, 'approve')">
                                            Approve
                                        </button>
                                        <button
                                            v-if="tenant.status !== 'suspended'"
                                            type="button"
                                            class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]"
                                            @click="runTenantAction(tenant, 'suspend')">
                                            Suspend
                                        </button>
                                        <button
                                            v-if="tenant.status === 'suspended'"
                                            type="button"
                                            class="rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                                            @click="runTenantAction(tenant, 'reactivate')">
                                            Reactivate
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div v-if="selectedTenant === null" class="flex min-h-[420px] items-center justify-center rounded-2xl border border-dashed border-[#D1D5DB] bg-[#F9FAFB] p-8 text-center text-sm text-[#6B7280]">
                    Choose a merchant to inspect stats, domains, subscription state, recent actions, and support-entry controls.
                </div>

                <div v-else class="space-y-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-primary">Merchant Detail</p>
                            <h2 class="mt-2 text-2xl font-semibold text-[#111827]">{{ selectedTenant.name }}</h2>
                            <p class="mt-1 text-sm text-[#6B7280]">{{ selectedTenant.slug }} • {{ selectedTenant.primary_domain || "fallback only" }}</p>
                        </div>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                            :class="statusClass(selectedTenant.status)">
                            {{ selectedTenant.status }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Products</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ selectedTenant.stats?.products_count || 0 }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Customers</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ selectedTenant.stats?.customers_count || 0 }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Total Orders</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ selectedTenant.stats?.total_orders_count || 0 }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">GMV</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ money(selectedTenant.stats?.gmv_total) }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-[#111827]">Subscription & Support</h3>
                                <p class="text-sm text-[#6B7280]">Assign plan, monitor renewal state, or enter a safe audited support session.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-2xl bg-[#111827] px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                                @click="startSupportSession">
                                Open support session
                            </button>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                            <select
                                v-model="selectedPlanCode"
                                class="h-11 rounded-2xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary">
                                <option value="">Select plan</option>
                                <option v-for="plan in plans" :key="plan.id" :value="plan.code">{{ plan.name }}</option>
                            </select>
                            <button
                                type="button"
                                class="h-11 rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8] transition hover:border-[#60A5FA]"
                                :disabled="!selectedPlanCode"
                                @click="assignPlan">
                                Assign plan
                            </button>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm text-[#374151]">
                            <p>Current plan: <span class="font-semibold text-[#111827]">{{ selectedTenant.subscription?.plan?.name || selectedTenant.plan_code || "starter" }}</span></p>
                            <p>Subscription state: <span class="font-semibold text-[#111827]">{{ selectedTenant.subscription?.status || "Not provisioned" }}</span></p>
                            <p>Period ends: <span class="font-semibold text-[#111827]">{{ formatDateTime(selectedTenant.subscription?.current_period_ends_at) }}</span></p>
                            <p>Pending custom domains: <span class="font-semibold text-[#111827]">{{ selectedTenant.stats?.pending_custom_domains || 0 }}</span></p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <h3 class="font-semibold text-[#111827]">Domains</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="domain in selectedTenant.domains"
                                :key="domain.id"
                                class="rounded-2xl bg-white px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-[#111827]">{{ domain.hostname }}</p>
                                        <p class="text-xs text-[#6B7280]">{{ domain.domain_type }} <span v-if="domain.is_primary">• primary</span><span v-if="domain.is_fallback"> • fallback</span></p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <p :class="inlineStatusClass(domain.verification_status)">{{ domain.verification_status }}</p>
                                        <p :class="inlineStatusClass(domain.ssl_status)">{{ domain.ssl_status || "unknown" }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!selectedTenant.domains?.length" class="rounded-2xl bg-white px-4 py-4 text-sm text-[#6B7280]">
                                No mapped domains yet.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <h3 class="font-semibold text-[#111827]">Team & Activity</h3>
                        <div class="mt-3 grid gap-2 text-sm text-[#374151]">
                            <p>Members: <span class="font-semibold text-[#111827]">{{ selectedTenant.active_members_count || 0 }}</span> active of {{ selectedTenant.members_count || 0 }}</p>
                            <p>Last merchant activity: <span class="font-semibold text-[#111827]">{{ formatDateTime(selectedTenant.stats?.last_activity_at) }}</span></p>
                            <p>Last customer login: <span class="font-semibold text-[#111827]">{{ formatDateTime(selectedTenant.stats?.last_customer_login_at) }}</span></p>
                            <p>Past due subscription: <span class="font-semibold text-[#111827]">{{ selectedTenant.stats?.has_past_due_subscription ? "Yes" : "No" }}</span></p>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div
                                v-for="member in selectedTenant.members"
                                :key="member.id"
                                class="rounded-2xl bg-white px-4 py-3">
                                <p class="font-medium text-[#111827]">{{ member.user?.name || "Unknown member" }}</p>
                                <p class="text-xs text-[#6B7280]">{{ member.user?.email || member.user?.phone || "No contact" }} • {{ member.role?.name || "No role" }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <h3 class="font-semibold text-[#111827]">Recent Owner Actions</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="log in selectedTenant.recent_owner_actions"
                                :key="`owner-${log.id}`"
                                class="rounded-2xl bg-white px-4 py-3">
                                <p class="font-medium text-[#111827]">{{ log.action_code }}</p>
                                <p class="text-xs text-[#6B7280]">{{ log.actor?.name || "system" }} • {{ formatDateTime(log.created_at) }}</p>
                            </div>
                            <div v-if="!selectedTenant.recent_owner_actions?.length" class="rounded-2xl bg-white px-4 py-4 text-sm text-[#6B7280]">
                                No owner actions recorded yet.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <h3 class="font-semibold text-[#111827]">Recent Merchant Actions</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="log in selectedTenant.recent_merchant_actions"
                                :key="`merchant-${log.id}`"
                                class="rounded-2xl bg-white px-4 py-3">
                                <p class="font-medium text-[#111827]">{{ log.action_code }}</p>
                                <p class="text-xs text-[#6B7280]">{{ log.actor?.name || "merchant" }} • {{ formatDateTime(log.created_at) }}</p>
                            </div>
                            <div v-if="!selectedTenant.recent_merchant_actions?.length" class="rounded-2xl bg-white px-4 py-4 text-sm text-[#6B7280]">
                                No merchant-side owner-visible activity yet.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                        <h3 class="font-semibold text-[#111827]">Recent Support Sessions</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="session in selectedTenant.recent_support_sessions"
                                :key="session.id"
                                class="rounded-2xl bg-white px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-[#111827]">{{ session.status }}</p>
                                        <p class="text-xs text-[#6B7280]">{{ session.owner?.name || "owner" }} • {{ session.reason || "No reason recorded" }}</p>
                                    </div>
                                    <p class="text-xs text-[#6B7280]">{{ formatDateTime(session.started_at) }}</p>
                                </div>
                            </div>
                            <div v-if="!selectedTenant.recent_support_sessions?.length" class="rounded-2xl bg-white px-4 py-4 text-sm text-[#6B7280]">
                                No support sessions have been started yet.
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";
import PlatformWorkspaceShell from "./PlatformWorkspaceShell.vue";

export default {
    name: "PlatformTenantsComponent",
    components: {
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
                detail: false,
            },
            tenants: [],
            plans: [],
            selectedTenant: null,
            selectedPlanCode: "",
            filters: {
                q: "",
                status: "",
                plan_code: "",
            },
        };
    },
    mounted() {
        this.fetchPlans();
        this.fetchTenants();
    },
    methods: {
        fetchPlans: function () {
            axios.get("platform/plans").then((res) => {
                this.plans = Array.isArray(res?.data?.data) ? res.data.data : [];
            });
        },
        fetchTenants: function () {
            this.loading.isActive = true;

            axios.get("platform/tenants", {
                params: {
                    q: this.filters.q || undefined,
                    status: this.filters.status || undefined,
                    plan_code: this.filters.plan_code || undefined,
                },
            }).then((res) => {
                this.tenants = Array.isArray(res?.data?.data) ? res.data.data : [];

                if (this.selectedTenant?.id) {
                    const refreshed = this.tenants.find((tenant) => tenant.id === this.selectedTenant.id);

                    if (!refreshed) {
                        this.selectedTenant = null;
                    }
                }
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        openTenant: function (tenant) {
            this.loading.detail = true;

            axios.get(`platform/tenants/${tenant.id}`)
                .then((res) => {
                    this.selectedTenant = res?.data?.data || null;
                    this.selectedPlanCode = this.selectedTenant?.subscription?.plan?.code || this.selectedTenant?.plan_code || "";
                })
                .finally(() => {
                    this.loading.detail = false;
                });
        },
        runTenantAction: function (tenant, action) {
            this.loading.isActive = true;

            axios.post(`platform/tenants/${tenant.id}/${action}`)
                .then((res) => {
                    const updatedTenant = res?.data?.data;

                    if (!updatedTenant) {
                        return;
                    }

                    this.tenants = this.tenants.map((item) => item.id === updatedTenant.id ? {
                        ...item,
                        ...updatedTenant,
                    } : item);

                    if (this.selectedTenant?.id === updatedTenant.id) {
                        this.selectedTenant = {
                            ...this.selectedTenant,
                            ...updatedTenant,
                        };
                    }
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
        assignPlan: function () {
            if (!this.selectedTenant?.id || !this.selectedPlanCode) {
                return;
            }

            this.loading.detail = true;

            axios.post(`platform/tenants/${this.selectedTenant.id}/subscription`, {
                plan_code: this.selectedPlanCode,
                billing_interval: "monthly",
            }).then((res) => {
                const subscription = res?.data?.data || null;

                if (subscription) {
                    this.selectedTenant = {
                        ...this.selectedTenant,
                        plan_code: subscription.plan?.code || this.selectedPlanCode,
                        subscription,
                    };
                }

                this.fetchTenants();
            }).finally(() => {
                this.loading.detail = false;
            });
        },
        startSupportSession: function () {
            if (!this.selectedTenant?.id) {
                return;
            }

            this.loading.detail = true;

            axios.post("platform/support/impersonations", {
                tenant_id: this.selectedTenant.id,
                reason: "Owner support review",
            }).then((res) => {
                const session = res?.data?.data || null;

                if (session?.launch_url) {
                    window.open(session.launch_url, "_blank", "noopener");
                }

                this.openTenant(this.selectedTenant);
            }).finally(() => {
                this.loading.detail = false;
            });
        },
        statusClass: function (status) {
            if (status === "active") {
                return "bg-[#ECFDF3] text-[#047857]";
            }

            if (status === "suspended") {
                return "bg-[#FEF2F2] text-[#B91C1C]";
            }

            return "bg-[#FFF7ED] text-[#C2410C]";
        },
        inlineStatusClass: function (status) {
            if (["active", "verified"].includes(status)) {
                return "font-semibold text-[#047857]";
            }

            if (["failed", "expired", "rejected"].includes(status)) {
                return "font-semibold text-[#B91C1C]";
            }

            return "font-semibold text-[#C2410C]";
        },
        money: function (amount, currency = "USD") {
            return `${currency} ${Number(amount || 0).toFixed(2)}`;
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
