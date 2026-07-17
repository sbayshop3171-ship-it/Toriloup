<template>
    <LoadingComponent :props="loading" />

    <div class="row">
        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <h3 class="db-card-title">All Merchant Customers</h3>
                    <div class="db-card-filter">
                        <button type="button" class="db-btn py-2 text-white bg-primary" @click="fetchCustomers">
                            <i class="lab lab-line-refresh"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-5 border-t border-[#F3F4F6]">
                    <form class="w-full" @submit.prevent="fetchCustomers">
                        <div class="row">
                            <div class="col-12 md:col-8 xl:col-6">
                                <label class="db-field-title after:hidden">Search</label>
                                <input
                                    v-model.trim="filters.q"
                                    type="text"
                                    class="db-field-control"
                                    placeholder="Customer, email, phone, merchant" />
                            </div>
                            <div class="col-12 md:col-4 xl:col-6">
                                <div class="flex flex-wrap gap-3 mt-6">
                                    <button class="db-btn py-2 text-white bg-primary">
                                        <i class="lab lab-line-search"></i>
                                        <span>Search</span>
                                    </button>
                                    <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="clearFilters">
                                        <i class="lab lab-line-cross"></i>
                                        <span>Clear</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Customer</th>
                                <th class="db-table-head-th">Registered Stores</th>
                                <th class="db-table-head-th">Registration Date</th>
                                <th class="db-table-head-th">Orders</th>
                                <th class="db-table-head-th">Spend</th>
                                <th class="db-table-head-th">Latest Activity</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="customers.length > 0">
                            <tr class="db-table-body-tr" v-for="customer in customers" :key="customer.id">
                                <td class="db-table-body-td">
                                    <div class="space-y-1">
                                        <p class="font-medium text-secondary">{{ customer.name || "Unnamed customer" }}</p>
                                        <p class="text-xs text-paragraph">{{ customer.email || customer.phone || "-" }}</p>
                                    </div>
                                </td>
                                <td class="db-table-body-td">
                                    <div class="space-y-1">
                                        <p class="font-medium text-secondary">{{ customer.linked_merchants_count || 0 }} stores</p>
                                        <p class="text-xs text-paragraph">{{ customer.linked_merchants_preview || "-" }}</p>
                                    </div>
                                </td>
                                <td class="db-table-body-td">{{ formatDate(customer.registered_at) }}</td>
                                <td class="db-table-body-td">{{ customer.total_orders || 0 }}</td>
                                <td class="db-table-body-td">{{ money(customer.total_spend) }}</td>
                                <td class="db-table-body-td">{{ formatDateTime(customer.last_activity_at) }}</td>
                                <td class="db-table-body-td hidden-print">
                                    <button type="button" class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]" @click="openCustomer(customer)">
                                        Details
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="7">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No customers found</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12" v-if="selectedCustomer">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">{{ selectedCustomer.name || "Customer Details" }}</h3>
                        <p class="text-sm text-paragraph mt-1">{{ selectedCustomer.email || selectedCustomer.phone || "-" }}</p>
                    </div>
                </div>

                <div class="db-card-body">
                    <div class="row">
                        <div class="col-12 xl:col-4">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Master Customer</h4>
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Name</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.name || "-" }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Email</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.email || "-" }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Phone</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.phone || "-" }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Registration Date</span>
                                        <span class="text-sm font-medium text-secondary">{{ formatDateTime(selectedCustomer.registered_at) }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Linked Stores</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.linked_merchants_count || 0 }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Shadow Profiles</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.shadow_profiles_count || 0 }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Total Orders</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedCustomer.total_orders || 0 }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Total Spend</span>
                                        <span class="text-sm font-medium text-secondary">{{ money(selectedCustomer.total_spend) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 xl:col-8">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Merchant Relationships</h4>
                                <div class="db-table-responsive">
                                    <table class="db-table stripe">
                                        <thead class="db-table-head">
                                            <tr class="db-table-head-tr">
                                                <th class="db-table-head-th">Merchant</th>
                                                <th class="db-table-head-th">Storefront</th>
                                                <th class="db-table-head-th">Orders</th>
                                                <th class="db-table-head-th">Spend</th>
                                                <th class="db-table-head-th">Latest Activity</th>
                                            </tr>
                                        </thead>
                                        <tbody class="db-table-body" v-if="selectedCustomer.linked_merchants?.length">
                                            <tr class="db-table-body-tr" v-for="merchant in selectedCustomer.linked_merchants" :key="`${selectedCustomer.id}-${merchant.tenant_id}-${merchant.customer_id}`">
                                                <td class="db-table-body-td">
                                                    <div class="space-y-1">
                                                        <p class="font-medium text-secondary">{{ merchant.tenant_name || "Unknown merchant" }}</p>
                                                        <span :class="statusClass(merchant.tenant_status)">{{ humanStatus(merchant.tenant_status) }}</span>
                                                    </div>
                                                </td>
                                                <td class="db-table-body-td">{{ merchant.storefront_hostname || merchant.tenant_slug || "-" }}</td>
                                                <td class="db-table-body-td">{{ merchant.orders_count || 0 }}</td>
                                                <td class="db-table-body-td">{{ money(merchant.spend_total) }}</td>
                                                <td class="db-table-body-td">{{ formatDateTime(merchant.last_order_at || merchant.last_login_at) }}</td>
                                            </tr>
                                        </tbody>
                                        <tbody class="db-table-body" v-else>
                                            <tr class="db-table-body-tr">
                                                <td class="db-table-body-td text-center" colspan="5">No merchant relationships found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../components/LoadingComponent";
import appService from "../../../services/appService";

export default {
    name: "OwnerCustomerDirectoryComponent",
    components: {
        LoadingComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            customers: [],
            selectedCustomer: null,
            filters: {
                q: "",
            },
        };
    },
    computed: {
        setting: function () {
            return this.$store.getters["frontendSetting/lists"];
        },
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
                    } else {
                        this.selectedCustomer = null;
                    }
                }
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        clearFilters: function () {
            this.filters.q = "";
            this.fetchCustomers();
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
        humanStatus: function (status) {
            if (status === "suspended") {
                return "Banned";
            }

            if (status === "draft") {
                return "Draft";
            }

            return "Active";
        },
        statusClass: function (status) {
            if (status === "active") {
                return "db-table-badge text-green-600 bg-green-100";
            }

            if (status === "suspended") {
                return "db-table-badge text-red-600 bg-red-100";
            }

            return "db-table-badge text-orange-600 bg-orange-100";
        },
        money: function (amount) {
            return appService.currencyFormat(
                Number(amount || 0),
                Number(this.setting?.site_digit_after_decimal_point ?? 2),
                this.setting?.site_default_currency_symbol || "$",
                this.setting?.site_currency_position || 5
            );
        },
        formatDate: function (value) {
            if (!value) {
                return "-";
            }

            return new Date(value).toLocaleDateString();
        },
        formatDateTime: function (value) {
            if (!value) {
                return "-";
            }

            return new Date(value).toLocaleString();
        },
    },
};
</script>
