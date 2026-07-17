<template>
    <PlatformWorkspaceShell
        title="Customer Directory"
        subtitle="Master customer visibility across merchants, with linked spend and storefront relationships.">
        <LoadingComponent :props="loading" />

        <section class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-[#111827]">Master Customers</h2>
                        <p class="text-sm text-[#6B7280]">Deduped by legacy account first, then email or phone when possible.</p>
                    </div>
                    <input
                        v-model.trim="filters.q"
                        type="text"
                        placeholder="Search customer, email, phone, or merchant"
                        class="h-11 w-full rounded-2xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary lg:max-w-sm"
                        @keyup.enter="fetchCustomers" />
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                <th class="px-4 py-3 font-semibold">Customer</th>
                                <th class="px-4 py-3 font-semibold">Linked Stores</th>
                                <th class="px-4 py-3 font-semibold">Orders</th>
                                <th class="px-4 py-3 font-semibold">Spend</th>
                                <th class="px-4 py-3 font-semibold">Latest Activity</th>
                                <th class="px-4 py-3 font-semibold">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="customers.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-[#6B7280]">
                                    No global customers found yet.
                                </td>
                            </tr>
                            <tr
                                v-for="customer in customers"
                                :key="customer.id"
                                class="border-b border-[#F3F4F6] last:border-b-0">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-[#111827]">{{ customer.name || "Unnamed customer" }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ customer.email || customer.phone || "No primary identity" }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-medium text-[#111827]">{{ customer.linked_merchants_count }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ customer.shadow_profiles_count }} shadow profile<span v-if="customer.shadow_profiles_count !== 1">s</span></p>
                                </td>
                                <td class="px-4 py-4 align-top font-medium text-[#111827]">{{ customer.total_orders }}</td>
                                <td class="px-4 py-4 align-top font-medium text-[#111827]">{{ money(customer.total_spend) }}</td>
                                <td class="px-4 py-4 align-top text-[#6B7280]">{{ formatDateTime(customer.last_activity_at) }}</td>
                                <td class="px-4 py-4 align-top">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]"
                                        @click="openCustomer(customer)">
                                        Open
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div v-if="selectedCustomer === null" class="flex h-full min-h-[320px] items-center justify-center rounded-2xl border border-dashed border-[#D1D5DB] bg-[#F9FAFB] p-8 text-center text-sm text-[#6B7280]">
                    Select a customer to inspect linked merchants, spend history, and recent orders.
                </div>

                <div v-else class="space-y-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-primary">Master Profile</p>
                        <h2 class="mt-2 text-2xl font-semibold text-[#111827]">{{ selectedCustomer.name || "Unnamed customer" }}</h2>
                        <p class="mt-1 text-sm text-[#6B7280]">{{ selectedCustomer.email || selectedCustomer.phone || "No email or phone on file" }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Total Spend</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ money(selectedCustomer.total_spend) }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Total Orders</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ selectedCustomer.total_orders }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Linked Merchants</p>
                            <p class="mt-2 text-xl font-semibold text-[#111827]">{{ selectedCustomer.linked_merchants_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#F9FAFB] p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Latest Activity</p>
                            <p class="mt-2 text-sm font-semibold text-[#111827]">{{ formatDateTime(selectedCustomer.last_activity_at) }}</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-[#6B7280]">Merchant Links</h3>
                        <div class="mt-3 space-y-3">
                            <article
                                v-for="merchant in selectedCustomer.linked_merchants"
                                :key="`${selectedCustomer.id}-${merchant.tenant_id}-${merchant.customer_id}`"
                                class="rounded-2xl border border-[#E5E7EB] bg-[#FCFCFD] p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-[#111827]">{{ merchant.tenant_name || "Unknown store" }}</p>
                                        <p class="text-xs text-[#6B7280]">{{ merchant.tenant_slug || "no-slug" }} • {{ merchant.tenant_status || "unknown" }}</p>
                                    </div>
                                    <div class="text-right text-xs text-[#6B7280]">
                                        <p>{{ merchant.orders_count }} order<span v-if="merchant.orders_count !== 1">s</span></p>
                                        <p>{{ money(merchant.spend_total) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2 text-xs text-[#6B7280] sm:grid-cols-2">
                                    <p>Last order: <span class="font-medium text-[#111827]">{{ formatDateTime(merchant.last_order_at) }}</span></p>
                                    <p>Last login: <span class="font-medium text-[#111827]">{{ formatDateTime(merchant.last_login_at) }}</span></p>
                                </div>

                                <div v-if="merchant.recent_orders?.length" class="mt-4 rounded-2xl bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#6B7280]">Recent Orders</p>
                                    <div class="mt-3 space-y-2">
                                        <div
                                            v-for="order in merchant.recent_orders"
                                            :key="order.id"
                                            class="flex items-center justify-between gap-3 rounded-xl bg-[#F9FAFB] px-3 py-2">
                                            <div>
                                                <p class="text-sm font-semibold text-[#111827]">{{ order.order_serial_no || `Order #${order.id}` }}</p>
                                                <p class="text-xs text-[#6B7280]">{{ order.status }} • {{ order.payment_status }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-semibold text-[#111827]">{{ money(order.total) }}</p>
                                                <p class="text-xs text-[#6B7280]">{{ formatDateTime(order.order_datetime) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
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
    name: "PlatformCustomersComponent",
    components: {
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            filters: {
                q: "",
            },
            customers: [],
            selectedCustomer: null,
        };
    },
    mounted() {
        this.fetchCustomers();
    },
    methods: {
        fetchCustomers: function () {
            this.loading.isActive = true;

            axios.get("platform/customers", {
                params: {
                    q: this.filters.q || undefined,
                },
            }).then((res) => {
                this.customers = Array.isArray(res?.data?.data) ? res.data.data : [];

                if (this.selectedCustomer?.id) {
                    const refreshed = this.customers.find((customer) => customer.id === this.selectedCustomer.id);

                    if (refreshed) {
                        this.openCustomer(refreshed);
                    }
                }
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        openCustomer: function (customer) {
            this.loading.isActive = true;

            axios.get(`platform/customers/${customer.id}`)
                .then((res) => {
                    this.selectedCustomer = res?.data?.data || null;
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
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
