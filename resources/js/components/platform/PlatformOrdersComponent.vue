<template>
    <PlatformWorkspaceShell
        title="Order Monitor"
        subtitle="Read-only order visibility across merchant stores. Store operations stay with each merchant.">
        <LoadingComponent :props="loading" />

        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">All Merchant Orders</h2>
                    <p class="text-sm text-[#6B7280]">Monitor store activity, payment state, and risk signals without changing order status.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:min-w-[620px]">
                    <input
                        v-model.trim="filters.q"
                        type="text"
                        placeholder="Search order, store, or customer"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                        @keyup.enter="fetchOrders(1)" />
                    <select
                        v-model="filters.status"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                        @change="fetchOrders(1)">
                        <option value="">All order statuses</option>
                        <option value="1">Pending</option>
                        <option value="5">Confirmed</option>
                        <option value="7">On The Way</option>
                        <option value="10">Delivered</option>
                        <option value="15">Canceled</option>
                        <option value="20">Rejected</option>
                    </select>
                    <select
                        v-model="filters.payment_status"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                        @change="fetchOrders(1)">
                        <option value="">All payments</option>
                        <option value="5">Paid</option>
                        <option value="10">Unpaid</option>
                    </select>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="item in summaryCards"
                    :key="item.label"
                    class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">{{ item.label }}</p>
                    <p class="mt-2 text-xl font-semibold text-[#111827]">{{ item.value }}</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                            <th class="px-4 py-3 font-semibold">Order</th>
                            <th class="px-4 py-3 font-semibold">Store</th>
                            <th class="px-4 py-3 font-semibold">Customer</th>
                            <th class="px-4 py-3 font-semibold">Payment</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Total</th>
                            <th class="px-4 py-3 font-semibold">Owner Scope</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="orders.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-[#6B7280]">
                                No orders matched the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="order in orders"
                            :key="order.id"
                            class="border-b border-[#F3F4F6] last:border-b-0">
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-[#111827]">{{ order.order_serial_no || ("#" + order.id) }}</p>
                                <p class="text-xs text-[#6B7280]">{{ order.order_datetime || "No timestamp" }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ order.tenant?.name || "Unknown Store" }}</p>
                                <p class="text-xs text-[#6B7280]">{{ order.tenant?.slug || "No slug" }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ order.customer?.name || "Guest Customer" }}</p>
                                <p class="text-xs text-[#6B7280]">{{ order.customer?.email || order.customer?.phone || "No contact" }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ order.payment_method_name || "Unknown" }}</p>
                                <span
                                    class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="paymentStatusClass(order.payment_status)">
                                    {{ order.payment_status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="orderStatusClass(order.status)">
                                    {{ order.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top font-semibold text-[#111827]">
                                {{ order.total_display }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-semibold text-[#1D4ED8]">
                                    Read only
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.total > meta.per_page" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-[#6B7280]">
                    Showing {{ meta.from || 0 }} to {{ meta.to || 0 }} of {{ meta.total }} orders
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-[#D1D5DB] bg-white px-4 py-2 text-sm font-semibold text-[#374151] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="meta.current_page <= 1"
                        @click="fetchOrders(meta.current_page - 1)">
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-[#D1D5DB] bg-white px-4 py-2 text-sm font-semibold text-[#374151] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="fetchOrders(meta.current_page + 1)">
                        Next
                    </button>
                </div>
            </div>
        </section>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";
import PlatformWorkspaceShell from "./PlatformWorkspaceShell.vue";

export default {
    name: "PlatformOrdersComponent",
    components: {
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            orders: [],
            summary: {
                total_orders: 0,
                pending_orders: 0,
                paid_orders: 0,
                gross_sales_display: "",
            },
            meta: {
                current_page: 1,
                from: 0,
                last_page: 1,
                per_page: 25,
                to: 0,
                total: 0,
            },
            filters: {
                q: "",
                status: "",
                payment_status: "",
            },
        };
    },
    computed: {
        summaryCards: function () {
            return [
                { label: "Total Orders", value: this.summary.total_orders || 0 },
                { label: "Pending", value: this.summary.pending_orders || 0 },
                { label: "Paid", value: this.summary.paid_orders || 0 },
                { label: "Gross Sales", value: this.summary.gross_sales_display || "0" },
            ];
        },
    },
    mounted() {
        this.fetchOrders();
    },
    methods: {
        fetchOrders: function (page = 1) {
            this.loading.isActive = true;

            axios.get("platform/orders", {
                params: {
                    ...this.filters,
                    page,
                    per_page: this.meta.per_page,
                },
            }).then((res) => {
                this.orders = Array.isArray(res?.data?.data) ? res.data.data : [];
                this.summary = res?.data?.summary || this.summary;
                this.meta = {
                    ...this.meta,
                    ...(res?.data?.meta || {}),
                };
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        orderStatusClass: function (status) {
            if (status === 10) {
                return "bg-[#ECFDF3] text-[#047857]";
            }

            if (status === 15 || status === 20) {
                return "bg-[#FEF2F2] text-[#B91C1C]";
            }

            if (status === 5 || status === 7) {
                return "bg-[#EFF6FF] text-[#1D4ED8]";
            }

            return "bg-[#FFF7ED] text-[#C2410C]";
        },
        paymentStatusClass: function (status) {
            return status === 5
                ? "bg-[#ECFDF3] text-[#047857]"
                : "bg-[#FEF2F2] text-[#B91C1C]";
        },
    },
};
</script>
