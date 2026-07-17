<template>
    <LoadingComponent :props="loading" />

    <div class="row">
        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <h3 class="db-card-title">Merchants</h3>
                    <div class="db-card-filter">
                        <button type="button" class="db-btn py-2 text-white bg-primary" @click="fetchMerchants">
                            <i class="lab lab-line-refresh"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-5 border-t border-[#F3F4F6]">
                    <form class="w-full" @submit.prevent="fetchMerchants">
                        <div class="row">
                            <div class="col-12 md:col-6 xl:col-4">
                                <label class="db-field-title after:hidden">Search</label>
                                <input
                                    v-model.trim="filters.q"
                                    type="text"
                                    class="db-field-control"
                                    placeholder="Store, slug, email, code" />
                            </div>
                            <div class="col-12 md:col-6 xl:col-3">
                                <label class="db-field-title after:hidden">Status</label>
                                <select v-model="filters.status" class="db-field-control">
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="draft">Draft</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-12 xl:col-5">
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
                                <th class="db-table-head-th">Merchant</th>
                                <th class="db-table-head-th">Storefront</th>
                                <th class="db-table-head-th">Registration Date</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th">Stats</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="merchants.length > 0">
                            <tr class="db-table-body-tr" v-for="merchant in merchants" :key="merchant.id">
                                <td class="db-table-body-td">
                                    <div class="space-y-1">
                                        <p class="font-medium text-secondary">{{ merchant.name }}</p>
                                        <p class="text-xs text-paragraph">{{ merchant.contact_email || merchant.contact_phone || "-" }}</p>
                                        <p class="text-xs text-paragraph">{{ merchant.store_code }}</p>
                                    </div>
                                </td>
                                <td class="db-table-body-td">
                                    <div class="space-y-1">
                                        <p class="font-medium text-secondary">{{ merchant.storefront_hostname }}</p>
                                        <p v-if="merchant.primary_domain && merchant.primary_domain !== merchant.storefront_hostname" class="text-xs text-paragraph">
                                            Live: {{ merchant.primary_domain }}
                                        </p>
                                    </div>
                                </td>
                                <td class="db-table-body-td">{{ formatDate(merchant.created_at) }}</td>
                                <td class="db-table-body-td">
                                    <span :class="statusClass(merchant.status)">{{ humanStatus(merchant.status) }}</span>
                                </td>
                                <td class="db-table-body-td">
                                    <div class="space-y-1 text-xs text-paragraph">
                                        <p>{{ merchant.products_count || 0 }} products</p>
                                        <p>{{ merchant.customers_count || 0 }} customers</p>
                                        <p class="font-medium text-secondary">{{ money(merchant.completed_sales_total) }} completed</p>
                                    </div>
                                </td>
                                <td class="db-table-body-td hidden-print">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]" @click="openMerchant(merchant)">
                                            Details
                                        </button>
                                        <button
                                            v-if="merchant.status === 'draft'"
                                            type="button"
                                            class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]"
                                            @click="runAction(merchant, 'approve')">
                                            Approve
                                        </button>
                                        <button
                                            v-if="merchant.status === 'suspended'"
                                            type="button"
                                            class="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                                            @click="runAction(merchant, 'reactivate')">
                                            Reactivate
                                        </button>
                                        <button
                                            v-else-if="merchant.status !== 'suspended'"
                                            type="button"
                                            class="rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs font-semibold text-[#B45309]"
                                            @click="runAction(merchant, 'suspend')">
                                            Suspend
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]"
                                            @click="runAction(merchant, 'delete')">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="6">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No merchants found</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12" v-if="selectedMerchant">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">{{ selectedMerchant.name }}</h3>
                        <p class="text-sm text-paragraph mt-1">{{ selectedMerchant.storefront_hostname }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span :class="statusClass(selectedMerchant.status)">{{ humanStatus(selectedMerchant.status) }}</span>
                        <button
                            v-if="selectedMerchant.status === 'suspended'"
                            type="button"
                            class="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                            @click="runAction(selectedMerchant, 'reactivate')">
                            Reactivate
                        </button>
                        <button
                            v-else-if="selectedMerchant.status !== 'suspended'"
                            type="button"
                            class="rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs font-semibold text-[#B45309]"
                            @click="runAction(selectedMerchant, 'suspend')">
                            Suspend
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]"
                            @click="runAction(selectedMerchant, 'delete')">
                            Delete
                        </button>
                    </div>
                </div>

                <div class="db-card-body">
                    <div class="row">
                        <div class="col-12 xl:col-6">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Merchant Directory</h4>
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Store Name</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedMerchant.name }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Store Slug</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedMerchant.storefront_hostname }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Registration Date</span>
                                        <span class="text-sm font-medium text-secondary">{{ formatDateTime(selectedMerchant.created_at) }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Status</span>
                                        <span :class="statusClass(selectedMerchant.status)">{{ humanStatus(selectedMerchant.status) }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Plan</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedMerchant.plan_code || "starter" }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Primary Domain</span>
                                        <span class="text-sm font-medium text-secondary">{{ selectedMerchant.primary_domain || "-" }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 xl:col-6">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Individual Merchant Stats</h4>
                                <div class="row">
                                    <div class="col-12 sm:col-6">
                                        <div class="rounded-lg bg-[#F8FAFC] p-4 mb-4">
                                            <p class="text-xs uppercase text-paragraph mb-2">Products</p>
                                            <p class="text-2xl font-semibold text-secondary">{{ selectedMerchant.products_count || 0 }}</p>
                                        </div>
                                    </div>
                                    <div class="col-12 sm:col-6">
                                        <div class="rounded-lg bg-[#F8FAFC] p-4 mb-4">
                                            <p class="text-xs uppercase text-paragraph mb-2">Customers</p>
                                            <p class="text-2xl font-semibold text-secondary">{{ selectedMerchant.customers_count || 0 }}</p>
                                        </div>
                                    </div>
                                    <div class="col-12 sm:col-6">
                                        <div class="rounded-lg bg-[#F8FAFC] p-4 mb-4">
                                            <p class="text-xs uppercase text-paragraph mb-2">Completed Orders</p>
                                            <p class="text-2xl font-semibold text-secondary">{{ selectedMerchant.completed_orders_count || 0 }}</p>
                                        </div>
                                    </div>
                                    <div class="col-12 sm:col-6">
                                        <div class="rounded-lg bg-[#F8FAFC] p-4 mb-4">
                                            <p class="text-xs uppercase text-paragraph mb-2">Completed Sales</p>
                                            <p class="text-2xl font-semibold text-secondary">{{ money(selectedMerchant.completed_sales_total) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-lg bg-[#F8FAFC] p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-sm text-paragraph">Merchant Members</span>
                                        <span class="text-sm font-medium text-secondary">
                                            {{ selectedMerchant.active_members_count || 0 }} active / {{ selectedMerchant.members_count || 0 }} total
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 xl:col-6">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Domains</h4>
                                <div v-if="selectedMerchant.domains?.length" class="space-y-3">
                                    <div v-for="domain in selectedMerchant.domains" :key="domain.id" class="rounded-lg bg-[#F8FAFC] p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="font-medium text-secondary">{{ domain.hostname }}</p>
                                                <p class="text-xs text-paragraph">{{ domain.domain_type }} <span v-if="domain.is_primary">• primary</span></p>
                                            </div>
                                            <div class="text-right text-xs text-paragraph">
                                                <p>{{ domain.verification_status }}</p>
                                                <p>{{ domain.ssl_status }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-paragraph">No domains mapped yet.</p>
                            </div>
                        </div>

                        <div class="col-12 xl:col-6">
                            <div class="rounded-lg border border-[#EDEFF6] p-4 h-full">
                                <h4 class="font-semibold text-lg mb-4 text-secondary">Merchant Members</h4>
                                <div v-if="selectedMerchant.members?.length" class="space-y-3">
                                    <div v-for="member in selectedMerchant.members" :key="member.id" class="rounded-lg bg-[#F8FAFC] p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="font-medium text-secondary">{{ member.user?.name || "Unknown member" }}</p>
                                                <p class="text-xs text-paragraph">{{ member.user?.email || member.user?.phone || "-" }}</p>
                                            </div>
                                            <div class="text-right text-xs text-paragraph">
                                                <p>{{ member.role?.name || "Member" }}</p>
                                                <p class="capitalize">{{ member.status }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-paragraph">No merchant members found.</p>
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
    name: "OwnerMerchantManagementComponent",
    components: {
        LoadingComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            merchants: [],
            selectedMerchant: null,
            filters: {
                q: "",
                status: "",
            },
        };
    },
    computed: {
        setting: function () {
            return this.$store.getters["frontendSetting/lists"];
        },
    },
    mounted() {
        this.fetchMerchants();
    },
    methods: {
        fetchMerchants: function () {
            this.loading.isActive = true;

            axios.get("platform/tenants", {
                params: {
                    q: this.filters.q || undefined,
                    status: this.filters.status || undefined,
                },
            }).then((res) => {
                this.merchants = Array.isArray(res?.data?.data) ? res.data.data : [];

                if (this.selectedMerchant?.id) {
                    const refreshed = this.merchants.find((merchant) => merchant.id === this.selectedMerchant.id);
                    if (refreshed) {
                        this.openMerchant(refreshed);
                    } else {
                        this.selectedMerchant = null;
                    }
                }
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        clearFilters: function () {
            this.filters.q = "";
            this.filters.status = "";
            this.fetchMerchants();
        },
        openMerchant: function (merchant) {
            this.loading.isActive = true;

            axios.get(`platform/tenants/${merchant.id}`)
                .then((res) => {
                    this.selectedMerchant = res?.data?.data || null;
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
        runAction: async function (merchant, action) {
            const confirmation = action === "delete"
                ? await appService.destroyConfirmation()
                : await appService.submitConfirmation();

            if (!confirmation) {
                return;
            }

            this.loading.isActive = true;

            const request = action === "delete"
                ? axios.delete(`platform/tenants/${merchant.id}`)
                : axios.post(`platform/tenants/${merchant.id}/${action}`);

            request.then((res) => {
                if (action === "delete") {
                    this.merchants = this.merchants.filter((item) => item.id !== merchant.id);

                    if (this.selectedMerchant?.id === merchant.id) {
                        this.selectedMerchant = null;
                    }

                    return;
                }

                const updatedMerchant = res?.data?.data;
                if (!updatedMerchant) {
                    this.fetchMerchants();
                    return;
                }

                this.merchants = this.merchants.map((item) => item.id === updatedMerchant.id ? updatedMerchant : item);

                if (this.selectedMerchant?.id === updatedMerchant.id) {
                    this.selectedMerchant = updatedMerchant;
                }
            }).finally(() => {
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
