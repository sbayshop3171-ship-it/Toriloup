<template>
    <PlatformWorkspaceShell
        title="Merchant Control Center"
        subtitle="Business health, identity, products, orders, money, login, risk, and owner actions in one place.">
        <LoadingComponent :props="loading" />

        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#111827]">Merchants</h2>
                    <p class="text-sm text-[#6B7280]">Select a merchant to open the full control center.</p>
                </div>
                <form class="flex flex-col gap-3 md:flex-row" @submit.prevent="fetchMerchants">
                    <input
                        v-model.trim="filters.q"
                        type="text"
                        placeholder="Search store, slug, email, code"
                        class="h-11 w-full rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary md:w-72" />
                    <select
                        v-model="filters.status"
                        class="h-11 rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90">
                            <i class="lab lab-line-search"></i>
                            <span>Search</span>
                        </button>
                        <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border border-[#D1D5DB] bg-white px-4 text-sm font-semibold text-[#374151] transition hover:border-primary hover:text-primary" @click="clearFilters">
                            <i class="lab lab-line-cross"></i>
                            <span>Clear</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                            <th class="px-4 py-3 font-semibold">Merchant</th>
                            <th class="px-4 py-3 font-semibold">Storefront</th>
                            <th class="px-4 py-3 font-semibold">Joined</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Business Health</th>
                            <th class="px-4 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="merchants.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-[#6B7280]">No merchants found.</td>
                        </tr>
                        <tr
                            v-for="merchant in merchants"
                            :key="merchant.id"
                            class="border-b border-[#F3F4F6] transition last:border-b-0 hover:bg-[#F9FAFB]"
                            :class="selectedMerchant?.id === merchant.id ? 'bg-[#F9FAFB]' : ''">
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-[#111827]">{{ merchant.name }}</p>
                                <p class="text-xs text-[#6B7280]">{{ merchant.contact_email || merchant.contact_phone || "-" }}</p>
                                <p class="mt-1 text-xs text-[#6B7280]">{{ merchant.slug }} • {{ merchant.store_code }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-medium text-[#111827]">{{ merchant.storefront_hostname }}</p>
                                <p v-if="merchant.primary_domain && merchant.primary_domain !== merchant.storefront_hostname" class="text-xs text-[#6B7280]">
                                    Live: {{ merchant.primary_domain }}
                                </p>
                            </td>
                            <td class="px-4 py-4 align-top text-[#374151]">{{ formatDate(merchant.created_at) }}</td>
                            <td class="px-4 py-4 align-top">
                                <span :class="statusClass(merchant.status)">{{ humanStatus(merchant.status) }}</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="space-y-1 text-xs text-[#6B7280]">
                                    <p>{{ merchant.products_count || 0 }} products • {{ merchant.orders_count || 0 }} orders</p>
                                    <p>{{ merchant.customers_count || 0 }} customers</p>
                                    <p class="font-semibold text-[#111827]">{{ money(merchant.completed_sales_total) }} delivered sales</p>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="action-btn border-[#C7D2FE] bg-[#EEF2FF] text-[#4338CA]" @click="openMerchant(merchant)">
                                        <i class="fa-regular fa-eye"></i>
                                        <span>Details</span>
                                    </button>
                                    <button type="button" class="action-btn border-[#DDD6FE] bg-[#F5F3FF] text-[#6D28D9]" @click="openImpersonation(merchant)">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                        <span>Login</span>
                                    </button>
                                    <button
                                        v-if="merchant.status === 'draft'"
                                        type="button"
                                        class="action-btn border-[#BFDBFE] bg-[#EFF6FF] text-[#1D4ED8]"
                                        @click="runQuickAction(merchant, 'approve')">
                                        <i class="fa-regular fa-circle-check"></i>
                                        <span>Approve</span>
                                    </button>
                                    <button
                                        v-if="merchant.status === 'suspended'"
                                        type="button"
                                        class="action-btn border-[#BBF7D0] bg-[#F0FDF4] text-[#047857]"
                                        @click="openActionModal(merchant, 'reactivate')">
                                        <i class="fa-solid fa-rotate"></i>
                                        <span>Unsuspend</span>
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="action-btn border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]"
                                        @click="openActionModal(merchant, 'suspend')">
                                        <i class="fa-solid fa-ban"></i>
                                        <span>Suspend</span>
                                    </button>
                                    <button type="button" class="action-btn border-[#FECACA] bg-[#FEF2F2] text-[#B91C1C]" @click="openActionModal(merchant, 'delete')">
                                        <i class="fa-regular fa-trash-can"></i>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="selectedMerchant" class="mt-6 space-y-6">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="truncate text-2xl font-semibold text-[#111827]">{{ selectedMerchant.name }}</h2>
                            <span :class="statusClass(selectedMerchant.status)">{{ humanStatus(selectedMerchant.status) }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[#6B7280]">
                            <span>{{ selectedMerchant.contact_email || "No email" }}</span>
                            <span>{{ selectedMerchant.storefront_hostname }}</span>
                            <span>Joined {{ formatDate(selectedMerchant.created_at) }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="header-btn border-[#DDD6FE] bg-[#F5F3FF] text-[#6D28D9]" @click="openImpersonation(selectedMerchant)">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            <span>Login as Merchant</span>
                        </button>
                        <button type="button" class="header-btn border-[#C7D2FE] bg-[#EEF2FF] text-[#4338CA]" @click="openEditModal">
                            <i class="fa-regular fa-pen-to-square"></i>
                            <span>Edit</span>
                        </button>
                        <button
                            v-if="selectedMerchant.status === 'suspended'"
                            type="button"
                            class="header-btn border-[#BBF7D0] bg-[#F0FDF4] text-[#047857]"
                            @click="openActionModal(selectedMerchant, 'reactivate')">
                            <i class="fa-solid fa-rotate"></i>
                            <span>Unsuspend</span>
                        </button>
                        <button
                            v-else
                            type="button"
                            class="header-btn border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]"
                            @click="openActionModal(selectedMerchant, 'suspend')">
                            <i class="fa-solid fa-ban"></i>
                            <span>Suspend</span>
                        </button>
                        <button type="button" class="header-btn border-[#FECACA] bg-[#FEF2F2] text-[#B91C1C]" @click="openActionModal(selectedMerchant, 'delete')">
                            <i class="fa-regular fa-trash-can"></i>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="card in overviewCards"
                    :key="card.key"
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-[#6B7280]">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-[#111827]">{{ metricValue(card) }}</p>
                        </div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl border" :class="toneClass(card.tone)">
                            <i :class="metricIcon(card.key)"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="overflow-x-auto border-b border-[#E5E7EB] px-4">
                    <nav class="flex min-w-max gap-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="border-b-2 px-4 py-4 text-sm font-semibold transition"
                            :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-[#6B7280] hover:text-primary'"
                            @click="activeTab = tab.key">
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <div v-if="activeTab === 'profile'" class="grid gap-4 lg:grid-cols-2">
                        <InfoPanel title="Business Identity" :items="profileItems" />
                        <InfoPanel title="Members & Domains" :items="memberDomainItems" />
                    </div>

                    <div v-if="activeTab === 'products'" class="overflow-x-auto">
                        <DataTable
                            empty="No products found."
                            :headers="['Product', 'SKU', 'Price', 'Status', 'Updated']"
                            :rows="control.products">
                            <template #row="{ row }">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#111827]">{{ row.name }}</p>
                                </td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.sku || "-" }}</td>
                                <td class="px-4 py-4 font-semibold text-[#111827]">{{ money(row.price) }}</td>
                                <td class="px-4 py-4">
                                    <span :class="row.status_label === 'Active' ? 'pill-green' : 'pill-orange'">{{ row.status_label }}</span>
                                </td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ formatDate(row.updated_at) }}</td>
                            </template>
                        </DataTable>
                    </div>

                    <div v-if="activeTab === 'orders'" class="overflow-x-auto">
                        <DataTable
                            empty="No orders found."
                            :headers="['Order', 'Customer', 'Total', 'Status', 'Payment']"
                            :rows="control.orders">
                            <template #row="{ row }">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#111827]">{{ row.order_serial_no }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ formatDateTime(row.order_datetime || row.created_at) }}</p>
                                </td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.customer?.name || "Guest" }}</td>
                                <td class="px-4 py-4 font-semibold text-[#111827]">{{ money(row.total) }}</td>
                                <td class="px-4 py-4"><span class="pill-blue">{{ row.status_label }}</span></td>
                                <td class="px-4 py-4">
                                    <span :class="row.payment_status_label === 'Paid' ? 'pill-green' : 'pill-orange'">{{ row.payment_status_label }}</span>
                                </td>
                            </template>
                        </DataTable>
                    </div>

                    <div v-if="activeTab === 'finance'" class="grid gap-4 lg:grid-cols-3">
                        <InfoPanel title="Wallet" :items="walletItems" />
                        <InfoPanel title="Payouts" :items="payoutItems" />
                        <InfoPanel title="Order Payments" :items="paymentItems" />
                    </div>

                    <div v-if="activeTab === 'customers'" class="overflow-x-auto">
                        <DataTable
                            empty="No customers found."
                            :headers="['Customer', 'Contact', 'Status', 'Last Login', 'Joined']"
                            :rows="control.customers">
                            <template #row="{ row }">
                                <td class="px-4 py-4 font-semibold text-[#111827]">{{ row.name }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.email || row.phone || "-" }}</td>
                                <td class="px-4 py-4"><span :class="customerStatusClass(row.status)">{{ customerStatusLabel(row.status) }}</span></td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ formatDateTime(row.last_login_at) }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ formatDate(row.created_at) }}</td>
                            </template>
                        </DataTable>
                    </div>

                    <div v-if="activeTab === 'activity'" class="overflow-x-auto">
                        <DataTable
                            empty="No activity found."
                            :headers="['Action', 'Actor', 'Reason', 'IP', 'Time']"
                            :rows="control.activity">
                            <template #row="{ row }">
                                <td class="px-4 py-4 font-semibold text-[#111827]">{{ row.action_code }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.actor?.name || "System" }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.reason || "-" }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ row.ip_address || "-" }}</td>
                                <td class="px-4 py-4 text-[#6B7280]">{{ formatDateTime(row.created_at) }}</td>
                            </template>
                        </DataTable>
                    </div>

                    <div v-if="activeTab === 'risk'" class="grid gap-4 lg:grid-cols-2">
                        <InfoPanel title="Risk Signals" :items="riskSignalItems" />
                        <div class="rounded-xl border border-[#E5E7EB] p-4">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-base font-semibold text-[#111827]">KYC & Review</h3>
                                <span :class="control.risk?.status === 'healthy' ? 'pill-green' : 'pill-orange'">{{ control.risk?.status || "review" }}</span>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div v-for="check in autoLiveCheckItems" :key="check.key" class="flex items-center justify-between gap-4 rounded-xl bg-[#F9FAFB] px-4 py-3">
                                    <span class="text-sm text-[#374151]">{{ check.label }}</span>
                                    <span :class="check.value ? 'pill-green' : 'pill-red'">{{ check.value ? "Pass" : "Review" }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else class="mt-6 rounded-2xl border border-dashed border-[#D1D5DB] bg-white p-10 text-center text-[#6B7280]">
            Select a merchant row to open the Merchant Control Center.
        </section>

        <div v-if="editModal.active" class="fixed inset-0 z-[80] flex items-center justify-center bg-[#111827]/40 px-4">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] px-6 py-4">
                    <h3 class="text-lg font-semibold text-[#111827]">Edit Merchant</h3>
                    <button type="button" class="text-[#6B7280] transition hover:text-[#111827]" @click="closeEditModal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form class="p-6" @submit.prevent="submitEdit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <FieldInput label="Store Name" v-model="editModal.form.name" required />
                        <FieldInput label="Legal Name" v-model="editModal.form.legal_name" />
                        <FieldInput label="Email" v-model="editModal.form.contact_email" type="email" />
                        <FieldInput label="Phone" v-model="editModal.form.contact_phone" />
                        <FieldInput label="Country Code" v-model="editModal.form.country_code" />
                        <FieldInput label="Timezone" v-model="editModal.form.timezone" />
                        <FieldInput label="Locale" v-model="editModal.form.primary_locale" />
                        <FieldInput label="Currency" v-model="editModal.form.primary_currency_code" />
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="header-btn border-[#D1D5DB] bg-white text-[#374151]" @click="closeEditModal">Cancel</button>
                        <button type="submit" class="header-btn border-primary bg-primary text-white">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="actionModal.active" class="fixed inset-0 z-[90] flex items-center justify-center bg-[#111827]/40 px-4">
            <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl">
                <div class="border-b border-[#E5E7EB] px-6 py-4">
                    <h3 class="text-lg font-semibold text-[#111827]">{{ actionModal.title }}</h3>
                    <p class="mt-1 text-sm text-[#6B7280]">{{ actionModal.subtitle }}</p>
                </div>
                <form class="p-6" @submit.prevent="submitAction">
                    <label class="mb-2 block text-sm font-semibold text-[#374151]">Reason</label>
                    <textarea
                        v-model.trim="actionModal.reason"
                        rows="4"
                        class="w-full rounded-xl border border-[#D1D5DB] px-4 py-3 text-sm outline-none transition focus:border-primary"
                        placeholder="Write the admin reason"></textarea>

                    <div v-if="actionModal.type === 'suspend'" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm text-[#374151]">
                            <input v-model="actionModal.options.block_login" type="checkbox" />
                            <span>Block login</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm text-[#374151]">
                            <input v-model="actionModal.options.hide_products" type="checkbox" />
                            <span>Hide products</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm text-[#374151]">
                            <input v-model="actionModal.options.pause_payouts" type="checkbox" />
                            <span>Pause payouts</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm text-[#374151]">
                            <input v-model="actionModal.options.notify_merchant" type="checkbox" />
                            <span>Notify merchant</span>
                        </label>
                    </div>

                    <div v-if="actionModal.type === 'delete'" class="mt-4">
                        <label class="mb-2 block text-sm font-semibold text-[#374151]">Type DELETE</label>
                        <input
                            v-model.trim="actionModal.confirmText"
                            type="text"
                            class="h-11 w-full rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary" />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="header-btn border-[#D1D5DB] bg-white text-[#374151]" @click="closeActionModal">Cancel</button>
                        <button type="submit" class="header-btn" :class="actionSubmitClass">
                            {{ actionModal.submitLabel }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../components/LoadingComponent";
import PlatformWorkspaceShell from "../../platform/PlatformWorkspaceShell.vue";
import appService from "../../../services/appService";

const InfoPanel = {
    name: "InfoPanel",
    props: {
        title: {
            type: String,
            required: true,
        },
        items: {
            type: Array,
            default: () => [],
        },
    },
    template: `
        <div class="rounded-xl border border-[#E5E7EB] p-4">
            <h3 class="text-base font-semibold text-[#111827]">{{ title }}</h3>
            <div class="mt-4 divide-y divide-[#F3F4F6]">
                <div v-for="item in items" :key="item.label" class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <span class="text-sm text-[#6B7280]">{{ item.label }}</span>
                    <span class="max-w-[60%] text-right text-sm font-semibold text-[#111827]">{{ item.value || "-" }}</span>
                </div>
            </div>
        </div>
    `,
};

const DataTable = {
    name: "DataTable",
    props: {
        headers: {
            type: Array,
            required: true,
        },
        rows: {
            type: Array,
            default: () => [],
        },
        empty: {
            type: String,
            default: "No data found.",
        },
    },
    template: `
        <table class="min-w-full text-left text-sm">
            <thead>
                <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                    <th v-for="header in headers" :key="header" class="px-4 py-3 font-semibold">{{ header }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="rows.length === 0">
                    <td :colspan="headers.length" class="px-4 py-10 text-center text-[#6B7280]">{{ empty }}</td>
                </tr>
                <tr v-for="row in rows" :key="row.id" class="border-b border-[#F3F4F6] last:border-b-0">
                    <slot name="row" :row="row"></slot>
                </tr>
            </tbody>
        </table>
    `,
};

const FieldInput = {
    name: "FieldInput",
    props: {
        modelValue: {
            type: [String, Number],
            default: "",
        },
        label: {
            type: String,
            required: true,
        },
        type: {
            type: String,
            default: "text",
        },
        required: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:modelValue"],
    template: `
        <label class="block">
            <span class="mb-2 block text-sm font-semibold text-[#374151]">{{ label }}</span>
            <input
                :value="modelValue"
                :type="type"
                :required="required"
                class="h-11 w-full rounded-xl border border-[#D1D5DB] px-4 text-sm outline-none transition focus:border-primary"
                @input="$emit('update:modelValue', $event.target.value)" />
        </label>
    `,
};

export default {
    name: "OwnerMerchantManagementComponent",
    components: {
        DataTable,
        FieldInput,
        InfoPanel,
        LoadingComponent,
        PlatformWorkspaceShell,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            merchants: [],
            selectedMerchant: null,
            activeTab: "profile",
            filters: {
                q: "",
                status: "",
            },
            tabs: [
                { key: "profile", label: "Profile" },
                { key: "products", label: "Products" },
                { key: "orders", label: "Orders" },
                { key: "finance", label: "Finance" },
                { key: "customers", label: "Customers" },
                { key: "activity", label: "Activity" },
                { key: "risk", label: "Risk/KYC" },
            ],
            editModal: {
                active: false,
                form: {},
            },
            actionModal: {
                active: false,
                type: "",
                title: "",
                subtitle: "",
                submitLabel: "",
                merchant: null,
                reason: "",
                confirmText: "",
                options: {
                    block_login: true,
                    hide_products: false,
                    pause_payouts: false,
                    notify_merchant: false,
                },
            },
        };
    },
    computed: {
        setting: function () {
            return this.$store.getters["frontendSetting/lists"];
        },
        control: function () {
            return this.selectedMerchant?.control_center || {};
        },
        overviewCards: function () {
            return this.control.overview_cards || [];
        },
        profileItems: function () {
            const merchant = this.selectedMerchant || {};
            const profile = this.control.profile || {};

            return [
                { label: "Store Name", value: profile.business_name || merchant.name },
                { label: "Legal Name", value: profile.legal_name },
                { label: "Email", value: profile.email || merchant.contact_email },
                { label: "Phone", value: profile.phone || merchant.contact_phone },
                { label: "Country", value: profile.country_code || merchant.country_code },
                { label: "Locale", value: profile.locale || merchant.primary_locale },
                { label: "Currency", value: profile.currency || merchant.primary_currency_code },
                { label: "Timezone", value: profile.timezone || merchant.timezone },
                { label: "Approved", value: this.formatDateTime(profile.approved_at || merchant.approved_at) },
                { label: "Launched", value: this.formatDateTime(profile.launched_at || merchant.launched_at) },
            ];
        },
        memberDomainItems: function () {
            const merchant = this.selectedMerchant || {};

            return [
                { label: "Members", value: `${merchant.active_members_count || 0} active / ${merchant.members_count || 0} total` },
                { label: "Primary Domain", value: merchant.primary_domain || "-" },
                { label: "Fallback Host", value: merchant.storefront_hostname },
                { label: "Mapped Domains", value: `${merchant.domains?.length || 0}` },
                { label: "Plan", value: merchant.plan_code || "starter" },
                { label: "Onboarding", value: merchant.onboarding_status || "pending" },
            ];
        },
        walletItems: function () {
            const wallet = this.control.finance?.wallet || {};

            return [
                { label: "Available", value: this.money(wallet.available_balance) },
                { label: "Holding", value: this.money(wallet.holding_balance) },
                { label: "Pending Withdrawal", value: this.money(wallet.pending_withdrawal_balance) },
                { label: "Total Earned", value: this.money(wallet.total_earned) },
                { label: "Total Withdrawn", value: this.money(wallet.total_withdrawn) },
                { label: "Fees", value: this.money(wallet.total_fees) },
                { label: "Refunded", value: this.money(wallet.total_refunded) },
                { label: "Last Settled", value: this.formatDateTime(wallet.last_settled_at) },
            ];
        },
        payoutItems: function () {
            const withdrawals = this.control.finance?.withdrawals || {};

            return [
                { label: "Pending", value: withdrawals.pending_count || 0 },
                { label: "Approved", value: withdrawals.approved_count || 0 },
                { label: "Rejected", value: withdrawals.rejected_count || 0 },
            ];
        },
        paymentItems: function () {
            const orders = this.control.finance?.orders || {};

            return [
                { label: "Paid Orders", value: orders.paid_count || 0 },
                { label: "Unpaid Orders", value: orders.unpaid_count || 0 },
                { label: "Completed Orders", value: this.selectedMerchant?.completed_orders_count || 0 },
                { label: "Completed Sales", value: this.money(this.selectedMerchant?.completed_sales_total) },
            ];
        },
        riskSignalItems: function () {
            const signals = this.control.risk?.signals || {};

            return [
                { label: "Verified Contact", value: signals.verified_contact ? "Yes" : "No" },
                { label: "Verified Domain", value: signals.verified_domain ? "Yes" : "No" },
                { label: "Active Payment Method", value: signals.active_payment_method ? "Yes" : "No" },
                { label: "Has Products", value: signals.has_products ? "Yes" : "No" },
                { label: "Has Orders", value: signals.has_orders ? "Yes" : "No" },
                { label: "Last Suspension Reason", value: this.control.risk?.last_suspension_reason || "-" },
            ];
        },
        autoLiveCheckItems: function () {
            const checks = this.selectedMerchant?.auto_live_checks || {};

            return Object.keys(checks).map((key) => ({
                key,
                label: key.split("_").map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(" "),
                value: checks[key],
            }));
        },
        actionSubmitClass: function () {
            if (this.actionModal.type === "delete") {
                return "border-[#DC2626] bg-[#DC2626] text-white";
            }

            if (this.actionModal.type === "suspend") {
                return "border-[#F59E0B] bg-[#F59E0B] text-white";
            }

            if (this.actionModal.type === "impersonate") {
                return "border-primary bg-primary text-white";
            }

            return "border-[#16A34A] bg-[#16A34A] text-white";
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
                        this.openMerchant(refreshed, false);
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
        openMerchant: function (merchant, showLoading = true) {
            if (showLoading) {
                this.loading.isActive = true;
            }

            axios.get(`platform/tenants/${merchant.id}`)
                .then((res) => {
                    this.selectedMerchant = res?.data?.data || null;
                    this.activeTab = "profile";
                })
                .finally(() => {
                    if (showLoading) {
                        this.loading.isActive = false;
                    }
                });
        },
        replaceMerchant: function (merchant) {
            this.merchants = this.merchants.map((item) => item.id === merchant.id ? { ...item, ...merchant } : item);

            if (this.selectedMerchant?.id === merchant.id) {
                this.openMerchant(merchant);
            }
        },
        runQuickAction: function (merchant, action) {
            this.loading.isActive = true;

            axios.post(`platform/tenants/${merchant.id}/${action}`)
                .then((res) => {
                    const updatedMerchant = res?.data?.data;
                    if (updatedMerchant) {
                        this.replaceMerchant(updatedMerchant);
                    }
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
        openEditModal: function () {
            const merchant = this.selectedMerchant || {};

            this.editModal.form = {
                name: merchant.name || "",
                legal_name: merchant.legal_name || "",
                contact_email: merchant.contact_email || "",
                contact_phone: merchant.contact_phone || "",
                country_code: merchant.country_code || "",
                timezone: merchant.timezone || "",
                primary_locale: merchant.primary_locale || "",
                primary_currency_code: merchant.primary_currency_code || "",
            };
            this.editModal.active = true;
        },
        closeEditModal: function () {
            this.editModal.active = false;
            this.editModal.form = {};
        },
        submitEdit: function () {
            if (!this.selectedMerchant?.id) {
                return;
            }

            this.loading.isActive = true;

            axios.patch(`platform/tenants/${this.selectedMerchant.id}`, this.editModal.form)
                .then((res) => {
                    const updatedMerchant = res?.data?.data;
                    if (updatedMerchant) {
                        this.replaceMerchant(updatedMerchant);
                    }
                    this.closeEditModal();
                })
                .finally(() => {
                    this.loading.isActive = false;
                });
        },
        openImpersonation: function (merchant) {
            this.openActionModal(merchant, "impersonate");
        },
        openActionModal: function (merchant, type) {
            const titles = {
                suspend: ["Suspend Merchant", "Merchant login will be blocked while the account is suspended.", "Suspend"],
                reactivate: ["Unsuspend Merchant", "Merchant access will be restored.", "Unsuspend"],
                delete: ["Delete Merchant", "Merchant identity will be released and the email can register again.", "Delete"],
                impersonate: ["Login as Merchant", "A short-lived merchant workspace session will open in a new tab.", "Open Merchant"],
            };

            const [title, subtitle, submitLabel] = titles[type];

            this.actionModal = {
                active: true,
                type,
                title,
                subtitle,
                submitLabel,
                merchant,
                reason: "",
                confirmText: "",
                options: {
                    block_login: true,
                    hide_products: false,
                    pause_payouts: false,
                    notify_merchant: false,
                },
            };
        },
        closeActionModal: function () {
            this.actionModal.active = false;
        },
        submitAction: function () {
            const merchant = this.actionModal.merchant;
            const type = this.actionModal.type;

            if (!merchant?.id) {
                return;
            }

            if (type === "delete" && this.actionModal.confirmText !== "DELETE") {
                this.$toast?.error("Type DELETE to confirm.");
                return;
            }

            this.loading.isActive = true;

            let request;
            const payload = {
                reason: this.actionModal.reason || undefined,
                ...this.actionModal.options,
            };

            if (type === "delete") {
                request = axios.delete(`platform/tenants/${merchant.id}`, { data: { reason: this.actionModal.reason || undefined } });
            } else if (type === "impersonate") {
                request = axios.post(`platform/tenants/${merchant.id}/impersonate`, { reason: this.actionModal.reason || undefined });
            } else {
                const action = type === "reactivate" ? "reactivate" : "suspend";
                request = axios.post(`platform/tenants/${merchant.id}/${action}`, payload);
            }

            request.then((res) => {
                if (type === "delete") {
                    this.merchants = this.merchants.filter((item) => item.id !== merchant.id);
                    this.selectedMerchant = null;
                    this.closeActionModal();
                    return;
                }

                if (type === "impersonate") {
                    const url = res?.data?.data?.merchant_login_url;
                    if (url) {
                        window.open(url, "_blank", "noopener");
                    }
                    this.closeActionModal();
                    return;
                }

                const updatedMerchant = res?.data?.data;
                if (updatedMerchant) {
                    this.replaceMerchant(updatedMerchant);
                }
                this.closeActionModal();
            }).catch((error) => {
                const message = error?.response?.data?.message || "Action failed.";
                this.$toast?.error(message);
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        humanStatus: function (status) {
            if (status === "suspended") {
                return "Suspended";
            }

            if (status === "draft") {
                return "Draft";
            }

            if (status === "archived") {
                return "Archived";
            }

            return "Active";
        },
        statusClass: function (status) {
            if (status === "active") {
                return "inline-flex rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-semibold text-[#047857]";
            }

            if (status === "suspended") {
                return "inline-flex rounded-full bg-[#FEF2F2] px-3 py-1 text-xs font-semibold text-[#B91C1C]";
            }

            return "inline-flex rounded-full bg-[#FFF7ED] px-3 py-1 text-xs font-semibold text-[#C2410C]";
        },
        toneClass: function (tone) {
            const classes = {
                green: "border-[#BBF7D0] bg-[#F0FDF4] text-[#16A34A]",
                blue: "border-[#BFDBFE] bg-[#EFF6FF] text-[#2563EB]",
                purple: "border-[#DDD6FE] bg-[#F5F3FF] text-[#7C3AED]",
                orange: "border-[#FDE68A] bg-[#FFFBEB] text-[#D97706]",
                red: "border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]",
            };

            return classes[tone] || classes.blue;
        },
        metricIcon: function (key) {
            const icons = {
                total_sales: "fa-solid fa-chart-line",
                orders: "fa-solid fa-bag-shopping",
                products: "fa-solid fa-box",
                balance: "fa-solid fa-wallet",
                pending_payout: "fa-solid fa-clock",
                refunds_disputes: "fa-solid fa-rotate-left",
            };

            return icons[key] || "fa-solid fa-circle-info";
        },
        metricValue: function (card) {
            if (card.type === "money") {
                return this.money(card.value);
            }

            return Number(card.value || 0).toLocaleString();
        },
        customerStatusLabel: function (status) {
            return Number(status) === 5 || Number(status) === 1 ? "Active" : "Inactive";
        },
        customerStatusClass: function (status) {
            return this.customerStatusLabel(status) === "Active" ? "pill-green" : "pill-orange";
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

<style scoped>
.action-btn {
    align-items: center;
    border-width: 1px;
    border-radius: 0.75rem;
    display: inline-flex;
    font-size: 0.75rem;
    font-weight: 700;
    gap: 0.375rem;
    min-height: 2.25rem;
    padding: 0.5rem 0.75rem;
}

.header-btn {
    align-items: center;
    border-width: 1px;
    border-radius: 0.75rem;
    display: inline-flex;
    font-size: 0.875rem;
    font-weight: 700;
    gap: 0.5rem;
    min-height: 2.5rem;
    padding: 0.625rem 1rem;
}

.pill-green,
.pill-blue,
.pill-orange,
.pill-red {
    border-radius: 9999px;
    display: inline-flex;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.75rem;
}

.pill-green {
    background: #ECFDF3;
    color: #047857;
}

.pill-blue {
    background: #EFF6FF;
    color: #1D4ED8;
}

.pill-orange {
    background: #FFF7ED;
    color: #C2410C;
}

.pill-red {
    background: #FEF2F2;
    color: #B91C1C;
}
</style>
