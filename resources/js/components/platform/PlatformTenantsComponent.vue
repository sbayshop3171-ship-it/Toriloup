<template>
    <PlatformWorkspaceShell
        title="Tenant Control"
        subtitle="Approve, review, and monitor merchant tenants without entering store-operation tools.">
        <LoadingComponent :props="loading" />

        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Merchant Tenants</h2>
                    <p class="text-sm text-[#6B7280]">Platform-side visibility only. Daily store work stays on the merchant host.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        v-model.trim="filters.q"
                        type="text"
                        placeholder="Search by store, slug, email, or code"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary" />
                    <select
                        v-model="filters.status"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                            <th class="px-4 py-3 font-semibold">Store</th>
                            <th class="px-4 py-3 font-semibold">Plan</th>
                            <th class="px-4 py-3 font-semibold">Primary Domain</th>
                            <th class="px-4 py-3 font-semibold">Members</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredTenants.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-[#6B7280]">
                                No tenants matched the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="tenant in filteredTenants"
                            :key="tenant.id"
                            class="border-b border-[#F3F4F6] last:border-b-0">
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-[#111827]">{{ tenant.name }}</p>
                                <p class="text-xs text-[#6B7280]">{{ tenant.slug }} • {{ tenant.store_code }}</p>
                                <p v-if="tenant.contact_email" class="mt-1 text-xs text-[#6B7280]">{{ tenant.contact_email }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-semibold text-[#1D4ED8]">
                                    {{ tenant.plan_code || "starter" }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ tenant.primary_domain || "No primary domain" }}</p>
                                <p class="text-xs text-[#6B7280]">
                                    {{ tenant.domains?.length || 0 }} mapped domain<span v-if="(tenant.domains?.length || 0) !== 1">s</span>
                                </p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ tenant.active_members_count || 0 }}</p>
                                <p class="text-xs text-[#6B7280]">of {{ tenant.members_count || 0 }} total</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                    :class="statusClass(tenant.status)">
                                    {{ tenant.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
            },
            tenants: [],
            filters: {
                q: "",
                status: "",
            },
        };
    },
    computed: {
        filteredTenants: function () {
            return this.tenants.filter((tenant) => {
                if (this.filters.status && tenant.status !== this.filters.status) {
                    return false;
                }

                if (!this.filters.q) {
                    return true;
                }

                const haystack = [
                    tenant.name,
                    tenant.slug,
                    tenant.contact_email,
                    tenant.store_code,
                ].join(" ").toLowerCase();

                return haystack.includes(this.filters.q.toLowerCase());
            });
        },
    },
    mounted() {
        this.fetchTenants();
    },
    methods: {
        fetchTenants: function () {
            this.loading.isActive = true;

            axios.get("platform/tenants")
                .then((res) => {
                    this.tenants = Array.isArray(res?.data?.data) ? res.data.data : [];
                })
                .finally(() => {
                    this.loading.isActive = false;
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
    },
};
</script>
