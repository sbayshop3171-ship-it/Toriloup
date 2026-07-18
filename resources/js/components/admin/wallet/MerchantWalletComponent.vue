<template>
    <LoadingComponent :props="loading" />

    <div class="space-y-6">
        <div v-if="flash.text" class="db-card">
            <div class="db-card-body">
                <div class="rounded-lg border px-4 py-3 text-sm" :class="flash.type === 'success' ? 'border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]' : 'border-[#FED7AA] bg-[#FFF7ED] text-[#C2410C]'">
                    {{ flash.text }}
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="db-card-title">Wallet & Settlements</h3>
                    <p class="text-sm text-gray-500">Online gateway payments are credited here after owner fees and payout holding rules.</p>
                </div>
                <button type="button" class="db-btn py-2 text-white bg-primary" @click="downloadTransactions">
                    Export CSV
                </button>
            </div>
            <div class="db-card-body">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="card in balanceCards" :key="card.label" class="rounded-2xl border border-gray-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ card.label }}</p>
                        <h4 class="mt-2 text-2xl font-bold text-gray-900">{{ money(card.value) }}</h4>
                        <p class="mt-1 text-xs text-gray-500">{{ card.help }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Gross Sales</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ money(summary.period_totals?.gross_sales) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Owner Fees</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ money(summary.period_totals?.fees) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Refunds</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ money(summary.period_totals?.refunds) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Holding Rule</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ summary.settings?.holding_days || 0 }} day(s)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Request Withdrawal</h3>
                        <p class="text-sm text-gray-500">Choose an owner-approved payout method and submit account details.</p>
                    </div>
                </div>
                <form class="db-card-body space-y-4" @submit.prevent="submitWithdrawal">
                    <label class="db-field">
                        <span class="db-field-title">Payout Method</span>
                        <select v-model="requestForm.payout_method_id" class="db-field-control" @change="syncDestinationFields">
                            <option value="">Select method</option>
                            <option v-for="method in payoutMethods" :key="method.id" :value="method.id">
                                {{ method.name }} - min {{ money(method.min_amount) }}
                            </option>
                        </select>
                    </label>

                    <label class="db-field">
                        <span class="db-field-title">Amount</span>
                        <input v-model.number="requestForm.amount" type="number" min="0" step="0.01" class="db-field-control" placeholder="0.00">
                    </label>

                    <div v-if="selectedMethod" class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                        <p class="font-semibold text-gray-900">{{ selectedMethod.name }}</p>
                        <p v-if="selectedMethod.instructions" class="mt-1">{{ selectedMethod.instructions }}</p>
                        <p class="mt-1">Fee: {{ feeLabel(selectedMethod) }}</p>
                    </div>

                    <div class="space-y-3">
                        <label v-for="field in selectedMethodFields" :key="field.key" class="db-field">
                            <span class="db-field-title">{{ field.label }}</span>
                            <input v-model="requestForm.destination[field.key]" :type="field.type || 'text'" class="db-field-control" :placeholder="field.label">
                        </label>
                    </div>

                    <label class="db-field">
                        <span class="db-field-title">Merchant Note</span>
                        <textarea v-model="requestForm.merchant_note" rows="3" class="db-field-control" placeholder="Optional note for owner"></textarea>
                    </label>

                    <button type="submit" class="db-btn py-2 text-white bg-primary" :disabled="savingWithdrawal || payoutMethods.length === 0">
                        {{ savingWithdrawal ? "Submitting..." : "Request Withdrawal" }}
                    </button>
                </form>
            </div>

            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Available Payout Methods</h3>
                        <p class="text-sm text-gray-500">These are controlled by the owner admin panel.</p>
                    </div>
                </div>
                <div class="db-card-body">
                    <div v-if="payoutMethods.length === 0" class="rounded-xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500">
                        No payout method is active yet. Please contact platform owner.
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div v-for="method in payoutMethods" :key="method.id" class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ method.name }}</p>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ method.code }}</p>
                                </div>
                                <span class="rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-semibold text-[#047857]">Active</span>
                            </div>
                            <p v-if="method.description" class="mt-2 text-sm text-gray-500">{{ method.description }}</p>
                            <p class="mt-3 text-sm text-gray-700">Min: {{ money(method.min_amount) }} <span v-if="method.max_amount">/ Max: {{ money(method.max_amount) }}</span></p>
                            <p class="text-sm text-gray-700">Fee: {{ feeLabel(method) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Wallet Transactions</h3>
                        <p class="text-sm text-gray-500">Credits, refunds, fees, hold releases, and payout movements.</p>
                    </div>
                </div>
                <div class="db-card-body overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500">
                                <th class="px-4 py-3 font-semibold">Date</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Amount</th>
                                <th class="px-4 py-3 font-semibold">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="transactions.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No wallet transactions yet.</td>
                            </tr>
                            <tr v-for="transaction in transactions" :key="transaction.id" class="border-b border-gray-100 last:border-b-0">
                                <td class="px-4 py-4">{{ formatDate(transaction.created_at) }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-gray-900">{{ formatLabel(transaction.type) }}</p>
                                    <p class="text-xs text-gray-500">{{ transaction.description }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(transaction.status)">
                                        {{ transaction.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-semibold" :class="transaction.direction === 'credit' ? 'text-[#047857]' : 'text-[#B91C1C]'">
                                    {{ transaction.direction === 'credit' ? '+' : '-' }}{{ money(transaction.amount) }}
                                </td>
                                <td class="px-4 py-4">{{ money(transaction.balance_after) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Withdrawal Requests</h3>
                        <p class="text-sm text-gray-500">Track pending, approved, and rejected settlement requests.</p>
                    </div>
                </div>
                <div class="db-card-body overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500">
                                <th class="px-4 py-3 font-semibold">Request</th>
                                <th class="px-4 py-3 font-semibold">Method</th>
                                <th class="px-4 py-3 font-semibold">Amount</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="withdrawals.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No withdrawal requests yet.</td>
                            </tr>
                            <tr v-for="withdrawal in withdrawals" :key="withdrawal.id" class="border-b border-gray-100 last:border-b-0">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-gray-900">{{ withdrawal.request_no }}</p>
                                    <p class="text-xs text-gray-500">{{ formatDate(withdrawal.requested_at) }}</p>
                                </td>
                                <td class="px-4 py-4">{{ withdrawal.payout_method?.name || "-" }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-gray-900">{{ money(withdrawal.amount) }}</p>
                                    <p class="text-xs text-gray-500">Fee {{ money(withdrawal.fee_amount) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClass(withdrawal.status)">
                                        {{ withdrawal.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../components/LoadingComponent";

const defaultDestinationFields = [
    { key: "account_name", label: "Account Name", type: "text" },
    { key: "account_number", label: "Account Number", type: "text" },
];

export default {
    name: "MerchantWalletComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: { isActive: false },
            flash: { type: "", text: "" },
            summary: {
                wallet: {},
                settings: {},
                period_totals: {},
            },
            transactions: [],
            withdrawals: [],
            payoutMethods: [],
            savingWithdrawal: false,
            requestForm: {
                payout_method_id: "",
                amount: null,
                destination: {},
                merchant_note: "",
            },
        };
    },
    computed: {
        wallet() {
            return this.summary.wallet || {};
        },
        currencyCode() {
            return this.wallet.currency_code || "USD";
        },
        selectedMethod() {
            return this.payoutMethods.find((method) => Number(method.id) === Number(this.requestForm.payout_method_id)) || null;
        },
        selectedMethodFields() {
            if (!this.selectedMethod) {
                return defaultDestinationFields;
            }

            return Array.isArray(this.selectedMethod.fields) && this.selectedMethod.fields.length > 0
                ? this.selectedMethod.fields
                : defaultDestinationFields;
        },
        balanceCards() {
            return [
                { label: "Available", value: this.wallet.available_balance, help: "Ready to withdraw" },
                { label: "On Hold", value: this.wallet.holding_balance, help: "Pending settlement release" },
                { label: "Pending Payout", value: this.wallet.pending_withdrawal_balance, help: "Requested, owner processing" },
                { label: "Lifetime Earned", value: this.wallet.total_earned, help: "Net online payments credited" },
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
                axios.get("merchant/wallet/summary"),
                axios.get("merchant/wallet/transactions", { params: { per_page: 12 } }),
                axios.get("merchant/wallet/withdrawals", { params: { per_page: 12 } }),
                axios.get("merchant/wallet/payout-methods"),
            ]).then(([summary, transactions, withdrawals, payoutMethods]) => {
                this.summary = summary.data.data || {};
                this.transactions = transactions.data.data || [];
                this.withdrawals = withdrawals.data.data || [];
                this.payoutMethods = payoutMethods.data.data || [];

                if (!this.requestForm.payout_method_id && this.payoutMethods.length > 0) {
                    this.requestForm.payout_method_id = this.payoutMethods[0].id;
                    this.syncDestinationFields();
                }
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Wallet information could not be loaded.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        syncDestinationFields() {
            const nextDestination = {};
            this.selectedMethodFields.forEach((field) => {
                nextDestination[field.key] = this.requestForm.destination[field.key] || "";
            });
            this.requestForm.destination = nextDestination;
        },
        submitWithdrawal() {
            this.savingWithdrawal = true;
            axios.post("merchant/wallet/withdrawals", this.requestForm).then(() => {
                this.showFlash("success", "Withdrawal request submitted to owner.");
                this.requestForm.amount = null;
                this.requestForm.merchant_note = "";
                this.syncDestinationFields();
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Withdrawal request failed.");
            }).finally(() => {
                this.savingWithdrawal = false;
            });
        },
        downloadTransactions() {
            axios.get("merchant/wallet/transactions/export", { responseType: "blob" }).then((response) => {
                this.downloadBlob(response.data, "merchant-wallet-transactions.csv");
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
        money(value) {
            const amount = Number(value || 0);
            return `${this.currencyCode} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
        feeLabel(method) {
            if (!method || method.fee_type === "none" || Number(method.fee_value || 0) === 0) {
                return "No fee";
            }

            return method.fee_type === "percent"
                ? `${Number(method.fee_value)}%`
                : this.money(method.fee_value);
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
