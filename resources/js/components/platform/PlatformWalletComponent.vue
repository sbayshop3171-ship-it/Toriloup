<template>
    <LoadingComponent :props="loading" />

    <div class="row">
        <div class="col-12" v-if="flash.text">
            <div class="mb-4 rounded-lg border bg-white px-4 py-3 text-sm"
                :class="flash.type === 'success' ? 'border-[#BBF7D0] text-[#166534]' : 'border-[#FED7AA] text-[#C2410C]'">
                {{ flash.text }}
            </div>
        </div>

        <div class="col-12">
            <div class="mb-5">
                <h3 class="db-card-title">Wallets</h3>
                <p class="text-sm text-paragraph mt-1">Merchant balances, payout methods, withdrawal approvals, fees, refunds, and settlement exports.</p>
            </div>
        </div>

        <div class="col-12 sm:col-6 xl:col-3" v-for="card in summaryCards" :key="card.label">
            <div class="db-card mb-5">
                <div class="db-card-body">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-paragraph mb-2">{{ card.label }}</p>
                    <h4 class="font-semibold text-[22px] leading-[34px] text-secondary">{{ money(card.value) }}</h4>
                    <p class="text-sm text-paragraph mt-1">{{ card.help }}</p>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-5">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Owner Payout Method</h3>
                        <p class="text-sm text-paragraph mt-1">Enable bKash, Nagad, Rocket, bank, or manual payout options for merchant withdrawal requests.</p>
                    </div>
                </div>
                <div class="db-card-body">
                    <form class="form-row" @submit.prevent="savePayoutMethod">
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Code</label>
                            <input v-model.trim="methodForm.code" type="text" class="db-field-control" placeholder="bkash">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Name</label>
                            <input v-model.trim="methodForm.name" type="text" class="db-field-control" placeholder="bKash">
                        </div>
                        <div class="form-col-12">
                            <label class="db-field-title after:hidden">Instructions</label>
                            <textarea v-model="methodForm.instructions" rows="3" class="db-field-control h-auto py-3" placeholder="What merchant should submit"></textarea>
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Min Amount</label>
                            <input v-model.number="methodForm.min_amount" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Max Amount</label>
                            <input v-model.number="methodForm.max_amount" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Fee Type</label>
                            <select v-model="methodForm.fee_type" class="db-field-control">
                                <option value="none">None</option>
                                <option value="fixed">Fixed</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Fee Value</label>
                            <input v-model.number="methodForm.fee_value" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12">
                            <label class="db-field-title after:hidden">Required Fields</label>
                            <textarea v-model="fieldText" rows="4" class="db-field-control h-auto py-3" placeholder="account_name:Account Name&#10;account_number:Account Number"></textarea>
                            <p class="text-xs text-paragraph mt-2">One field per line: key:Label</p>
                        </div>
                        <div class="form-col-12">
                            <label class="inline-flex items-center gap-2 text-sm text-paragraph">
                                <input v-model="methodForm.status" type="checkbox" class="w-4 h-4">
                                Active for merchants
                            </label>
                        </div>
                        <div class="form-col-12">
                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="db-btn text-white bg-primary" :disabled="savingMethod">
                                    <i class="lab lab-fill-save"></i>
                                    <span>{{ savingMethod ? "Saving..." : "Save Payout Method" }}</span>
                                </button>
                                <button type="button" class="db-btn text-white bg-gray-600" @click="resetMethodForm">
                                    <i class="lab lab-line-refresh"></i>
                                    <span>New Method</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-7">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Payout Method Catalog</h3>
                        <p class="text-sm text-paragraph mt-1">Owner decides what withdrawal rails merchants can use.</p>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Method</th>
                                <th class="db-table-head-th">Limits</th>
                                <th class="db-table-head-th">Fee</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="payoutMethods.length > 0">
                            <tr class="db-table-body-tr" v-for="method in payoutMethods" :key="method.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ method.name }}</p>
                                    <p class="text-xs text-paragraph">{{ method.code }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <p>{{ money(method.min_amount) }}</p>
                                    <p class="text-xs text-paragraph">Max {{ method.max_amount ? money(method.max_amount) : "No limit" }}</p>
                                </td>
                                <td class="db-table-body-td">{{ method.fee_type === 'percent' ? method.fee_value + '%' : money(method.fee_value) }}</td>
                                <td class="db-table-body-td">
                                    <span :class="method.status ? statusClass('completed') : statusClass('off')">
                                        {{ method.status ? "Active" : "Off" }}
                                    </span>
                                </td>
                                <td class="db-table-body-td hidden-print">
                                    <button type="button" class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]" @click="editMethod(method)">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="5">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No payout methods found</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Withdrawal Queue</h3>
                        <p class="text-sm text-paragraph mt-1">Approve after sending merchant payout, or reject to return funds to wallet.</p>
                    </div>
                    <div class="db-card-filter">
                        <button type="button" class="db-btn py-2 text-white bg-primary" @click="downloadExport('withdrawals')">
                            <i class="lab lab-line-export"></i>
                            <span>Export Withdrawals</span>
                        </button>
                        <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="downloadExport('transactions')">
                            <i class="lab lab-line-export"></i>
                            <span>Export Ledger</span>
                        </button>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Merchant</th>
                                <th class="db-table-head-th">Request</th>
                                <th class="db-table-head-th">Method</th>
                                <th class="db-table-head-th">Amount</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="withdrawals.length > 0">
                            <tr class="db-table-body-tr" v-for="withdrawal in withdrawals" :key="withdrawal.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ withdrawal.tenant?.name || "Unknown" }}</p>
                                    <p class="text-xs text-paragraph">{{ withdrawal.tenant?.slug || "-" }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ withdrawal.request_no }}</p>
                                    <p class="text-xs text-paragraph">{{ formatDate(withdrawal.requested_at) }}</p>
                                </td>
                                <td class="db-table-body-td">{{ withdrawal.payout_method?.name || "-" }}</td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ money(withdrawal.amount, withdrawal.currency_code) }}</p>
                                    <p class="text-xs text-paragraph">Fee {{ money(withdrawal.fee_amount, withdrawal.currency_code) }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <span :class="statusClass(withdrawal.status)">{{ formatLabel(withdrawal.status) }}</span>
                                </td>
                                <td class="db-table-body-td hidden-print">
                                    <div v-if="withdrawal.status === 'pending'" class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]" @click="approveWithdrawal(withdrawal)">
                                            Approve
                                        </button>
                                        <button type="button" class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]" @click="rejectWithdrawal(withdrawal)">
                                            Reject
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-paragraph">{{ withdrawal.payout_reference || withdrawal.admin_note || "Processed" }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="6">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No withdrawal requests found</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-6">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Merchant Wallets</h3>
                        <p class="text-sm text-paragraph mt-1">Current balance per tenant.</p>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Tenant</th>
                                <th class="db-table-head-th">Available</th>
                                <th class="db-table-head-th">Holding</th>
                                <th class="db-table-head-th">Withdrawn</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="wallets.length > 0">
                            <tr class="db-table-body-tr" v-for="wallet in wallets" :key="wallet.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ wallet.tenant?.name || wallet.tenant_id }}</p>
                                    <p class="text-xs text-paragraph">{{ wallet.tenant?.slug || "-" }}</p>
                                </td>
                                <td class="db-table-body-td">{{ money(wallet.available_balance, wallet.currency_code) }}</td>
                                <td class="db-table-body-td">{{ money(wallet.holding_balance, wallet.currency_code) }}</td>
                                <td class="db-table-body-td">{{ money(wallet.total_withdrawn, wallet.currency_code) }}</td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="4">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No wallet balance yet</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-6">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Recent Ledger</h3>
                        <p class="text-sm text-paragraph mt-1">Latest credits, refunds, and payout ledger entries.</p>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Tenant</th>
                                <th class="db-table-head-th">Type</th>
                                <th class="db-table-head-th">Amount</th>
                                <th class="db-table-head-th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="transactions.length > 0">
                            <tr class="db-table-body-tr" v-for="transaction in transactions" :key="transaction.id">
                                <td class="db-table-body-td">{{ transaction.tenant?.name || transaction.tenant_id }}</td>
                                <td class="db-table-body-td">{{ formatLabel(transaction.type) }}</td>
                                <td class="db-table-body-td font-medium" :class="transaction.direction === 'credit' ? 'text-[#047857]' : 'text-[#B91C1C]'">
                                    {{ transaction.direction === 'credit' ? '+' : '-' }}{{ money(transaction.amount, transaction.currency_code) }}
                                </td>
                                <td class="db-table-body-td">
                                    <span :class="statusClass(transaction.status)">{{ formatLabel(transaction.status) }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="4">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No ledger entries yet</span>
                                    </div>
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
import LoadingComponent from "../frontend/components/LoadingComponent.vue";

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
    components: { LoadingComponent },
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

                if (this.overview.setup_required) {
                    this.showFlash("warning", this.overview.message || "Wallet storage setup is pending.");
                }
            }).catch((error) => {
                this.showFlash("warning", this.errorMessage(error, "Wallet workspace could not be loaded."));
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
                this.showFlash("warning", this.errorMessage(error, "Payout method could not be saved."));
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
                this.showFlash("warning", this.errorMessage(error, "Withdrawal could not be approved."));
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
                this.showFlash("warning", this.errorMessage(error, "Withdrawal could not be rejected."));
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
            }).catch((error) => {
                this.showFlash("warning", this.errorMessage(error, "Wallet export could not be downloaded."));
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
                return "inline-flex items-center justify-center rounded-lg bg-[#ECFDF3] px-3 py-1 text-xs font-semibold text-[#047857]";
            }

            if (["pending"].includes(status)) {
                return "inline-flex items-center justify-center rounded-lg bg-[#FFF7ED] px-3 py-1 text-xs font-semibold text-[#C2410C]";
            }

            if (["off"].includes(status)) {
                return "inline-flex items-center justify-center rounded-lg bg-[#F3F4F6] px-3 py-1 text-xs font-semibold text-[#6B7280]";
            }

            return "inline-flex items-center justify-center rounded-lg bg-[#FEF2F2] px-3 py-1 text-xs font-semibold text-[#B91C1C]";
        },
        formatLabel(value) {
            return String(value || "-").replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString() : "-";
        },
        errorMessage(error, fallback) {
            const message = error?.response?.data?.message || "";

            if (message.toLowerCase().includes("database error")) {
                return "Wallet database is not ready yet. Please run migrations, then refresh this page.";
            }

            return message || fallback;
        },
    },
};
</script>
