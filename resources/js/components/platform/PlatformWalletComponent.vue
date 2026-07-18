<template>
    <PlatformWorkspaceShell title="Wallets & Settlements" subtitle="Merchant balances, payout methods, withdrawal approval, fees, refunds, and settlement exports.">
        <LoadingComponent :props="loading" />

        <div class="space-y-6">
            <div v-if="flash.text" class="rounded-2xl border bg-white p-4 text-sm" :class="flash.type === 'success' ? 'border-[#BBF7D0] text-[#166534]' : 'border-[#FED7AA] text-[#C2410C]'">
                {{ flash.text }}
            </div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">{{ card.label }}</p>
                    <h2 class="mt-2 text-2xl font-bold text-[#111827]">{{ money(card.value) }}</h2>
                    <p class="mt-1 text-sm text-[#6B7280]">{{ card.help }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[420px_1fr]">
                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold">Owner Payout Method</h2>
                        <p class="text-sm text-[#6B7280]">Enable bKash, Nagad, Rocket, bank, or any manual payout option merchants can request.</p>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="savePayoutMethod">
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-[#374151]">Code</span>
                            <input v-model.trim="methodForm.code" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="bkash">
                        </label>
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-[#374151]">Name</span>
                            <input v-model.trim="methodForm.name" type="text" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary" placeholder="bKash">
                        </label>
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-[#374151]">Instructions</span>
                            <textarea v-model="methodForm.instructions" rows="3" class="rounded-xl border border-[#D1D5DB] px-4 py-3 outline-none transition focus:border-primary" placeholder="What merchant should submit"></textarea>
                        </label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Min Amount</span>
                                <input v-model.number="methodForm.min_amount" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Max Amount</span>
                                <input v-model.number="methodForm.max_amount" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Fee Type</span>
                                <select v-model="methodForm.fee_type" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                                    <option value="none">None</option>
                                    <option value="fixed">Fixed</option>
                                    <option value="percent">Percent</option>
                                </select>
                            </label>
                            <label class="grid gap-2 text-sm">
                                <span class="font-medium text-[#374151]">Fee Value</span>
                                <input v-model.number="methodForm.fee_value" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#D1D5DB] px-4 outline-none transition focus:border-primary">
                            </label>
                        </div>
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-[#374151]">Required Fields</span>
                            <textarea v-model="fieldText" rows="4" class="rounded-xl border border-[#D1D5DB] px-4 py-3 outline-none transition focus:border-primary" placeholder="account_name:Account Name&#10;account_number:Account Number"></textarea>
                            <span class="text-xs text-[#6B7280]">One field per line: key:Label</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-[#374151]">
                            <input v-model="methodForm.status" type="checkbox">
                            Active for merchants
                        </label>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-primary px-5 text-sm font-semibold text-white transition hover:opacity-90" :disabled="savingMethod">
                            {{ savingMethod ? "Saving..." : "Save Payout Method" }}
                        </button>
                    </form>
                </article>

                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Payout Method Catalog</h2>
                            <p class="text-sm text-[#6B7280]">Owner decides what withdrawal rails merchants can use.</p>
                        </div>
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#D1D5DB] px-4 text-sm font-semibold text-[#374151]" @click="resetMethodForm">
                            New Method
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <button v-for="method in payoutMethods" :key="method.id" type="button" class="rounded-xl border p-4 text-left transition hover:border-primary" :class="method.status ? 'border-[#D1FAE5] bg-[#F0FDF4]' : 'border-[#E5E7EB] bg-[#F9FAFB]'" @click="editMethod(method)">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[#111827]">{{ method.name }}</p>
                                    <p class="text-xs uppercase tracking-[0.14em] text-[#6B7280]">{{ method.code }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="method.status ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#F3F4F6] text-[#6B7280]'">
                                    {{ method.status ? "Active" : "Off" }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-[#6B7280]">Min {{ money(method.min_amount) }} <span v-if="method.max_amount">/ Max {{ money(method.max_amount) }}</span></p>
                            <p class="text-sm text-[#6B7280]">Fee {{ method.fee_type === 'percent' ? method.fee_value + '%' : money(method.fee_value) }}</p>
                        </button>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Withdrawal Queue</h2>
                        <p class="text-sm text-[#6B7280]">Approve after sending merchant payout, or reject to return funds to wallet.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#D1D5DB] px-4 text-sm font-semibold text-[#374151]" @click="downloadExport('withdrawals')">
                            Export Withdrawals
                        </button>
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#D1D5DB] px-4 text-sm font-semibold text-[#374151]" @click="downloadExport('transactions')">
                            Export Ledger
                        </button>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                <th class="px-4 py-3 font-semibold">Merchant</th>
                                <th class="px-4 py-3 font-semibold">Request</th>
                                <th class="px-4 py-3 font-semibold">Method</th>
                                <th class="px-4 py-3 font-semibold">Amount</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="withdrawals.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-[#6B7280]">No withdrawal requests found.</td>
                            </tr>
                            <tr v-for="withdrawal in withdrawals" :key="withdrawal.id" class="border-b border-[#F3F4F6] last:border-b-0">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#111827]">{{ withdrawal.tenant?.name || "Unknown" }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ withdrawal.tenant?.slug }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#111827]">{{ withdrawal.request_no }}</p>
                                    <p class="text-xs text-[#6B7280]">{{ formatDate(withdrawal.requested_at) }}</p>
                                </td>
                                <td class="px-4 py-4">{{ withdrawal.payout_method?.name || "-" }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#111827]">{{ money(withdrawal.amount) }}</p>
                                    <p class="text-xs text-[#6B7280]">Fee {{ money(withdrawal.fee_amount) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(withdrawal.status)">
                                        {{ withdrawal.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="withdrawal.status === 'pending'" class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white" @click="approveWithdrawal(withdrawal)">
                                            Approve
                                        </button>
                                        <button type="button" class="rounded-lg border border-[#FCA5A5] px-3 py-2 text-xs font-semibold text-[#B91C1C]" @click="rejectWithdrawal(withdrawal)">
                                            Reject
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-[#6B7280]">{{ withdrawal.payout_reference || withdrawal.admin_note || "Processed" }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Merchant Wallets</h2>
                    <p class="text-sm text-[#6B7280]">Current balance per tenant.</p>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                    <th class="px-4 py-3 font-semibold">Tenant</th>
                                    <th class="px-4 py-3 font-semibold">Available</th>
                                    <th class="px-4 py-3 font-semibold">Holding</th>
                                    <th class="px-4 py-3 font-semibold">Withdrawn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="wallets.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-[#6B7280]">No wallet balance yet.</td>
                                </tr>
                                <tr v-for="wallet in wallets" :key="wallet.id" class="border-b border-[#F3F4F6] last:border-b-0">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-[#111827]">{{ wallet.tenant?.name || wallet.tenant_id }}</p>
                                        <p class="text-xs text-[#6B7280]">{{ wallet.tenant?.slug }}</p>
                                    </td>
                                    <td class="px-4 py-4">{{ money(wallet.available_balance, wallet.currency_code) }}</td>
                                    <td class="px-4 py-4">{{ money(wallet.holding_balance, wallet.currency_code) }}</td>
                                    <td class="px-4 py-4">{{ money(wallet.total_withdrawn, wallet.currency_code) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Recent Ledger</h2>
                    <p class="text-sm text-[#6B7280]">Latest credits, refunds, and payout ledger entries.</p>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] text-[#6B7280]">
                                    <th class="px-4 py-3 font-semibold">Tenant</th>
                                    <th class="px-4 py-3 font-semibold">Type</th>
                                    <th class="px-4 py-3 font-semibold">Amount</th>
                                    <th class="px-4 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="transactions.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-[#6B7280]">No ledger entries yet.</td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="border-b border-[#F3F4F6] last:border-b-0">
                                    <td class="px-4 py-4">{{ transaction.tenant?.name || transaction.tenant_id }}</td>
                                    <td class="px-4 py-4">{{ formatLabel(transaction.type) }}</td>
                                    <td class="px-4 py-4 font-semibold" :class="transaction.direction === 'credit' ? 'text-[#047857]' : 'text-[#B91C1C]'">
                                        {{ transaction.direction === 'credit' ? '+' : '-' }}{{ money(transaction.amount, transaction.currency_code) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(transaction.status)">
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </PlatformWorkspaceShell>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";
import PlatformWorkspaceShell from "./PlatformWorkspaceShell.vue";

const defaultMethodForm = function () {
    return {
        code: "",
        name: "",
        description: "",
        instructions: "",
        status: true,
        min_amount: 0,
        max_amount: null,
        fee_type: "none",
        fee_value: 0,
        sort_order: 0,
    };
};

export default {
    name: "PlatformWalletComponent",
    components: { LoadingComponent, PlatformWorkspaceShell },
    data() {
        return {
            loading: { isActive: false },
            flash: { type: "", text: "" },
            overview: {},
            payoutMethods: [],
            withdrawals: [],
            wallets: [],
            transactions: [],
            savingMethod: false,
            fieldText: "account_name:Account Name\naccount_number:Account Number",
            methodForm: defaultMethodForm(),
        };
    },
    computed: {
        summary() {
            return this.overview.summary || {};
        },
        summaryCards() {
            return [
                { label: "Available", value: this.summary.available_balance, help: "Merchant withdrawable balance" },
                { label: "Holding", value: this.summary.holding_balance, help: "Waiting settlement release" },
                { label: "Pending Payout", value: this.summary.pending_withdrawal_balance, help: "Owner action required" },
                { label: "Owner Fees", value: this.summary.total_fees, help: "Platform fees collected" },
            ];
        },
    },
    mounted() {
        this.loadAll();
    },
    methods: {
        loadAll() {
            this.loading.isActive = true;
            Promise.all([
                axios.get("platform/wallet/overview"),
                axios.get("platform/wallet/payout-methods"),
                axios.get("platform/wallet/withdrawals", { params: { per_page: 12 } }),
                axios.get("platform/wallet/wallets", { params: { per_page: 10 } }),
                axios.get("platform/wallet/transactions", { params: { per_page: 10 } }),
            ]).then(([overview, methods, withdrawals, wallets, transactions]) => {
                this.overview = overview.data || {};
                this.payoutMethods = methods.data.data || [];
                this.withdrawals = withdrawals.data.data || [];
                this.wallets = wallets.data.data || [];
                this.transactions = transactions.data.data || [];
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Wallet workspace could not be loaded.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        savePayoutMethod() {
            this.savingMethod = true;
            axios.post("platform/wallet/payout-methods", {
                ...this.methodForm,
                fields: this.parsedFields(),
            }).then(() => {
                this.showFlash("success", "Payout method saved.");
                this.resetMethodForm();
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Payout method could not be saved.");
            }).finally(() => {
                this.savingMethod = false;
            });
        },
        editMethod(method) {
            this.methodForm = {
                code: method.code,
                name: method.name,
                description: method.description || "",
                instructions: method.instructions || "",
                status: method.status,
                min_amount: method.min_amount || 0,
                max_amount: method.max_amount,
                fee_type: method.fee_type || "none",
                fee_value: method.fee_value || 0,
                sort_order: method.sort_order || 0,
            };
            this.fieldText = (method.fields || []).map((field) => `${field.key}:${field.label}`).join("\n") || this.fieldText;
        },
        resetMethodForm() {
            this.methodForm = defaultMethodForm();
            this.fieldText = "account_name:Account Name\naccount_number:Account Number";
        },
        approveWithdrawal(withdrawal) {
            const payoutReference = window.prompt("Payout reference", withdrawal.request_no) || withdrawal.request_no;
            axios.post(`platform/wallet/withdrawals/${withdrawal.id}/approve`, {
                payout_reference: payoutReference,
                admin_note: "Approved from owner wallet console",
            }).then(() => {
                this.showFlash("success", "Withdrawal approved.");
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Withdrawal could not be approved.");
            });
        },
        rejectWithdrawal(withdrawal) {
            const reason = window.prompt("Reject reason");

            if (!reason) {
                return;
            }

            axios.post(`platform/wallet/withdrawals/${withdrawal.id}/reject`, { reason }).then(() => {
                this.showFlash("success", "Withdrawal rejected and wallet balance restored.");
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Withdrawal could not be rejected.");
            });
        },
        parsedFields() {
            return this.fieldText
                .split("\n")
                .map((line) => line.trim())
                .filter(Boolean)
                .map((line) => {
                    const [key, ...labelParts] = line.split(":");
                    return {
                        key: key.trim(),
                        label: (labelParts.join(":").trim() || key.trim()).replace(/_/g, " "),
                        type: "text",
                    };
                })
                .filter((field) => field.key.length > 0);
        },
        downloadExport(type) {
            axios.get("platform/wallet/reports/export", {
                params: { type },
                responseType: "blob",
            }).then((response) => {
                this.downloadBlob(response.data, `platform-wallet-${type}.csv`);
            });
        },
        downloadBlob(blob, filename) {
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        },
        showFlash(type, text) {
            this.flash = { type, text };
        },
        money(value, currencyCode = "USD") {
            const amount = Number(value || 0);
            return `${currencyCode} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
        statusClass(status) {
            if (["available", "approved", "completed"].includes(status)) {
                return "bg-[#ECFDF3] text-[#047857]";
            }

            if (["pending"].includes(status)) {
                return "bg-[#FFF7ED] text-[#C2410C]";
            }

            return "bg-[#FEF2F2] text-[#B91C1C]";
        },
        formatLabel(value) {
            return String(value || "-").replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString() : "-";
        },
    },
};
</script>
