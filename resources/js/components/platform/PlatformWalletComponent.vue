<template>
    <LoadingComponent :props="loading" />

    <div class="row">
        <div class="col-12" v-if="flash.text">
            <div
                class="mb-4 rounded-lg border bg-white px-4 py-3 text-sm"
                :class="flash.type === 'success' ? 'border-[#BBF7D0] text-[#166534]' : 'border-[#FED7AA] text-[#C2410C]'">
                {{ flash.text }}
            </div>
        </div>

        <div class="col-12">
            <div class="mb-5">
                <h3 class="db-card-title">Wallets</h3>
                <p class="text-sm text-paragraph mt-1">
                    Manage merchant balances, payout methods, withdrawal requests, approvals, and wallet reports.
                </p>
            </div>
        </div>

        <div class="col-12 sm:col-6 xl:col-3" v-for="card in summaryCards" :key="card.label">
            <div class="db-card mb-5">
                <div class="db-card-body flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl" :class="card.iconBox">
                        <i class="text-xl" :class="card.icon"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-paragraph">{{ card.label }}</p>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-secondary">{{ money(card.value) }}</h4>
                        <p class="text-sm text-paragraph">{{ card.help }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="db-card mb-5">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">How Wallet Withdrawals Work</h3>
                        <p class="text-sm text-paragraph mt-1">Simple flow: owner creates methods, merchant requests, owner pays, then approves or rejects.</p>
                    </div>
                </div>
                <div class="db-card-body">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div v-for="step in workflowCards" :key="step.title" class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs font-semibold" :class="step.badgeClass">
                                {{ step.number }}
                            </span>
                            <h4 class="mt-3 font-semibold text-secondary">{{ step.title }}</h4>
                            <p class="mt-1 text-sm leading-6 text-paragraph">{{ step.text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-5">
            <div class="db-card mb-5">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Withdraw Payment Method</h3>
                        <p class="text-sm text-paragraph mt-1">
                            Add bKash, Nagad, Rocket, bank, or any manual payout option. Merchants will only see active methods.
                        </p>
                    </div>
                </div>
                <div class="db-card-body">
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            v-for="preset in methodPresets"
                            :key="preset.code"
                            type="button"
                            class="rounded-lg border border-[#FFE0D6] bg-[#FFF4F1] px-3 py-2 text-xs font-semibold text-primary"
                            @click="applyPreset(preset)">
                            {{ preset.name }} Example
                        </button>
                    </div>

                    <form class="form-row" @submit.prevent="savePayoutMethod">
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title">Method Name</label>
                            <input v-model.trim="methodForm.name" type="text" class="db-field-control" placeholder="bKash">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Code</label>
                            <input v-model.trim="methodForm.code" type="text" class="db-field-control" placeholder="bkash">
                        </div>
                        <div class="form-col-12">
                            <label class="db-field-title after:hidden">Merchant Instruction</label>
                            <textarea
                                v-model="methodForm.instructions"
                                rows="3"
                                class="db-field-control h-auto py-3"
                                placeholder="Example: Submit the bKash number where you want to receive payout."></textarea>
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Minimum Amount</label>
                            <input v-model.number="methodForm.min_amount" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Maximum Amount</label>
                            <input v-model.number="methodForm.max_amount" type="number" min="0" step="0.01" class="db-field-control" placeholder="No limit">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Fee Type</label>
                            <select v-model="methodForm.fee_type" class="db-field-control">
                                <option value="none">No fee</option>
                                <option value="fixed">Fixed amount</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Fee Value</label>
                            <input v-model.number="methodForm.fee_value" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Sort Order</label>
                            <input v-model.number="methodForm.sort_order" type="number" min="0" step="1" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title after:hidden">Status</label>
                            <select v-model="methodForm.status" class="db-field-control">
                                <option :value="true">Active for merchants</option>
                                <option :value="false">Hidden from merchants</option>
                            </select>
                        </div>

                        <div class="form-col-12">
                            <div class="rounded-xl border border-[#E5E7EB]">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E5E7EB] px-4 py-3">
                                    <div>
                                        <h4 class="font-semibold text-secondary">Merchant Form Fields</h4>
                                        <p class="text-xs text-paragraph">These fields appear in merchant withdrawal request form.</p>
                                    </div>
                                    <button type="button" class="db-btn py-2 text-white bg-primary" @click="addField">
                                        <i class="lab lab-line-add"></i>
                                        <span>Add Field</span>
                                    </button>
                                </div>

                                <div class="p-4">
                                    <div
                                        v-for="(field, index) in methodForm.fields"
                                        :key="field.uid"
                                        class="mb-4 rounded-xl border border-[#EEF0F6] p-3 last:mb-0">
                                        <div class="form-row">
                                            <div class="form-col-12 sm:form-col-6">
                                                <label class="db-field-title">Label</label>
                                                <input
                                                    v-model.trim="field.label"
                                                    type="text"
                                                    class="db-field-control"
                                                    placeholder="bKash Number"
                                                    @blur="normalizeFieldKey(index)">
                                            </div>
                                            <div class="form-col-12 sm:form-col-6">
                                                <label class="db-field-title after:hidden">Field Key</label>
                                                <input v-model.trim="field.key" type="text" class="db-field-control" placeholder="bkash_number">
                                            </div>
                                            <div class="form-col-12 sm:form-col-6">
                                                <label class="db-field-title after:hidden">Type</label>
                                                <select v-model="field.type" class="db-field-control">
                                                    <option v-for="type in fieldTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                                </select>
                                            </div>
                                            <div class="form-col-12 sm:form-col-6">
                                                <label class="db-field-title after:hidden">Width</label>
                                                <select v-model.number="field.width" class="db-field-control">
                                                    <option v-for="width in fieldWidths" :key="width.value" :value="width.value">{{ width.label }}</option>
                                                </select>
                                            </div>
                                            <div class="form-col-12">
                                                <label class="db-field-title after:hidden">Placeholder</label>
                                                <input v-model.trim="field.placeholder" type="text" class="db-field-control" placeholder="01700000000">
                                            </div>
                                            <div class="form-col-12" v-if="field.type === 'select'">
                                                <label class="db-field-title after:hidden">Select Options</label>
                                                <textarea
                                                    v-model="field.options_text"
                                                    rows="2"
                                                    class="db-field-control h-auto py-3"
                                                    placeholder="Personal&#10;Agent&#10;Bank Branch"></textarea>
                                                <p class="mt-1 text-xs text-paragraph">One option per line.</p>
                                            </div>
                                            <div class="form-col-12">
                                                <label class="db-field-title after:hidden">Help Text</label>
                                                <input v-model.trim="field.instructions" type="text" class="db-field-control" placeholder="Tell merchant what to submit">
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                            <label class="inline-flex items-center gap-2 text-sm text-paragraph">
                                                <input v-model="field.required" type="checkbox" class="h-4 w-4">
                                                Required field
                                            </label>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]"
                                                @click="removeField(index)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="methodForm.fields.length === 0" class="rounded-xl border border-dashed border-[#D9DBE9] p-5 text-center text-sm text-paragraph">
                                        No merchant form fields yet. Add fields like bKash Number, Account Name, Bank Name, or Branch.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-col-12">
                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="db-btn text-white bg-primary" :disabled="savingMethod">
                                    <i class="lab lab-fill-save"></i>
                                    <span>{{ savingMethod ? "Saving..." : "Save Method" }}</span>
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
            <div class="db-card mb-5">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Payout Method Catalog</h3>
                        <p class="text-sm text-paragraph mt-1">Active methods are visible to merchants. Hidden methods stay owner-only.</p>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Method</th>
                                <th class="db-table-head-th">Merchant Fields</th>
                                <th class="db-table-head-th">Limits / Fee</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="payoutMethods.length > 0">
                            <tr class="db-table-body-tr" v-for="method in payoutMethods" :key="method.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ method.name }}</p>
                                    <p class="text-xs text-paragraph">{{ method.code }}</p>
                                    <p v-if="method.instructions" class="mt-1 text-xs text-paragraph">{{ method.instructions }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="field in method.fields"
                                            :key="field.key"
                                            class="rounded-lg bg-[#F4F6FB] px-2 py-1 text-xs text-paragraph">
                                            {{ field.label }}{{ field.required ? " *" : "" }}
                                        </span>
                                        <span v-if="!method.fields || method.fields.length === 0" class="text-xs text-paragraph">No custom fields</span>
                                    </div>
                                </td>
                                <td class="db-table-body-td">
                                    <p>{{ money(method.min_amount) }} min</p>
                                    <p class="text-xs text-paragraph">Max {{ method.max_amount ? money(method.max_amount) : "No limit" }}</p>
                                    <p class="text-xs text-paragraph">Fee {{ feeLabel(method) }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <span :class="method.status ? statusClass('completed') : statusClass('off')">
                                        {{ method.status ? "Active" : "Hidden" }}
                                    </span>
                                </td>
                                <td class="db-table-body-td hidden-print">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]"
                                        @click="editMethod(method)">
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
                                        <p class="mt-1 text-sm text-paragraph">Create bKash, Nagad, Rocket, or Bank method from the form.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="db-card mb-5">
                <div class="db-card-header border-none flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="db-card-title">Withdrawal Requests</h3>
                        <p class="text-sm text-paragraph mt-1">
                            Check merchant details first, send money manually, then approve. Reject returns the amount to merchant wallet.
                        </p>
                    </div>
                    <div class="db-card-filter">
                        <button
                            v-for="tab in withdrawalTabs"
                            :key="tab.value"
                            type="button"
                            class="rounded-lg px-3 py-2 text-xs font-semibold"
                            :class="statusFilter === tab.value ? 'bg-primary text-white' : 'border border-[#D9DBE9] bg-white text-paragraph'"
                            @click="setWithdrawalFilter(tab.value)">
                            {{ tab.label }}
                        </button>
                        <button type="button" class="db-btn py-2 text-white bg-primary" @click="downloadExport('withdrawals')">
                            <i class="lab lab-line-export"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Merchant</th>
                                <th class="db-table-head-th">Request</th>
                                <th class="db-table-head-th">Payment Details</th>
                                <th class="db-table-head-th">Amount</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="withdrawals.length > 0">
                            <tr class="db-table-body-tr" v-for="withdrawal in withdrawals" :key="withdrawal.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ withdrawal.tenant?.name || "Unknown Merchant" }}</p>
                                    <p class="text-xs text-paragraph">{{ withdrawal.tenant?.slug || "-" }}</p>
                                    <p v-if="withdrawal.requested_by" class="text-xs text-paragraph">
                                        {{ withdrawal.requested_by.name || withdrawal.requested_by.email || "-" }}
                                    </p>
                                </td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ withdrawal.request_no }}</p>
                                    <p class="text-xs text-paragraph">{{ formatDate(withdrawal.requested_at) }}</p>
                                    <p v-if="withdrawal.merchant_note" class="mt-1 text-xs text-paragraph">Note: {{ withdrawal.merchant_note }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ withdrawal.payout_method?.name || "-" }}</p>
                                    <div class="mt-2 space-y-1">
                                        <p v-for="detail in destinationRows(withdrawal)" :key="detail.key" class="text-xs text-paragraph">
                                            <span class="font-semibold text-secondary">{{ detail.label }}:</span>
                                            {{ detail.value || "-" }}
                                        </p>
                                    </div>
                                </td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ money(withdrawal.amount, withdrawal.currency_code) }}</p>
                                    <p class="text-xs text-paragraph">Fee {{ money(withdrawal.fee_amount, withdrawal.currency_code) }}</p>
                                    <p class="text-xs text-paragraph">Debit {{ money(Number(withdrawal.amount || 0) + Number(withdrawal.fee_amount || 0), withdrawal.currency_code) }}</p>
                                </td>
                                <td class="db-table-body-td">
                                    <span :class="statusClass(withdrawal.status)">{{ formatLabel(withdrawal.status) }}</span>
                                    <p v-if="withdrawal.payout_reference" class="mt-1 text-xs text-paragraph">Ref: {{ withdrawal.payout_reference }}</p>
                                    <p v-if="withdrawal.admin_note" class="mt-1 text-xs text-paragraph">{{ withdrawal.admin_note }}</p>
                                </td>
                                <td class="db-table-body-td hidden-print">
                                    <div v-if="withdrawal.status === 'pending'" class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                                            @click="approveWithdrawal(withdrawal)">
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#B91C1C]"
                                            @click="rejectWithdrawal(withdrawal)">
                                            Reject
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-paragraph">Processed</span>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="6">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No withdrawal requests found</span>
                                        <p class="mt-1 text-sm text-paragraph">Merchant requests will appear here in pending status.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-6">
            <div class="db-card mb-5">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Merchant Wallet Balances</h3>
                        <p class="text-sm text-paragraph mt-1">Current withdrawable, holding, pending, and withdrawn balance per merchant.</p>
                    </div>
                    <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="downloadExport('wallets')">
                        Export
                    </button>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Merchant</th>
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
            <div class="db-card mb-5">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Recent Wallet Ledger</h3>
                        <p class="text-sm text-paragraph mt-1">Latest online payment credits, refunds, and payout reservations.</p>
                    </div>
                    <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="downloadExport('transactions')">
                        Export
                    </button>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Merchant</th>
                                <th class="db-table-head-th">Type</th>
                                <th class="db-table-head-th">Amount</th>
                                <th class="db-table-head-th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="transactions.length > 0">
                            <tr class="db-table-body-tr" v-for="transaction in transactions" :key="transaction.id">
                                <td class="db-table-body-td">{{ transaction.tenant?.name || transaction.tenant_id }}</td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ formatLabel(transaction.type) }}</p>
                                    <p class="text-xs text-paragraph">{{ transaction.description || "-" }}</p>
                                </td>
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

const newField = function (overrides = {}) {
    const label = overrides.label || "";
    const key = overrides.key || slugFieldKey(label);

    return {
        uid: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        key,
        label,
        type: overrides.type || "text",
        required: overrides.required !== false,
        placeholder: overrides.placeholder || "",
        instructions: overrides.instructions || "",
        width: Number(overrides.width || 100),
        options_text: Array.isArray(overrides.options) ? overrides.options.join("\n") : (overrides.options_text || ""),
    };
};

const defaultFields = function () {
    return [
        newField({ key: "account_name", label: "Account Name", placeholder: "Merchant account holder name", width: 50 }),
        newField({ key: "account_number", label: "Account Number", placeholder: "Number or account ID", width: 50 }),
    ];
};

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
        fields: defaultFields(),
    };
};

function slugFieldKey(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");
}

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
            statusFilter: "pending",
            methodForm: defaultMethodForm(),
            fieldTypes: [
                { value: "text", label: "Text" },
                { value: "email", label: "Email" },
                { value: "number", label: "Number" },
                { value: "url", label: "URL" },
                { value: "textarea", label: "Long Text" },
                { value: "select", label: "Select" },
            ],
            fieldWidths: [
                { value: 100, label: "100% full row" },
                { value: 50, label: "50% half row" },
                { value: 33, label: "33% three columns" },
                { value: 25, label: "25% four columns" },
            ],
            methodPresets: [
                {
                    code: "bkash",
                    name: "bKash",
                    instructions: "Merchant should submit the bKash number where payout will be sent.",
                    fields: [
                        { key: "account_number", label: "bKash Number", type: "text", required: true, placeholder: "01700000000", width: 50 },
                        { key: "account_type", label: "Account Type", type: "select", required: true, options: ["Personal", "Agent"], width: 50 },
                    ],
                },
                {
                    code: "nagad",
                    name: "Nagad",
                    instructions: "Merchant should submit the Nagad number where payout will be sent.",
                    fields: [
                        { key: "account_number", label: "Nagad Number", type: "text", required: true, placeholder: "01700000000", width: 50 },
                        { key: "account_name", label: "Account Name", type: "text", required: false, width: 50 },
                    ],
                },
                {
                    code: "bank",
                    name: "Bank Transfer",
                    instructions: "Merchant should submit bank account details exactly as registered with the bank.",
                    fields: [
                        { key: "account_name", label: "Account Name", type: "text", required: true, width: 50 },
                        { key: "account_number", label: "Account Number", type: "text", required: true, width: 50 },
                        { key: "bank_name", label: "Bank Name", type: "text", required: true, width: 50 },
                        { key: "branch_name", label: "Branch Name", type: "text", required: false, width: 50 },
                    ],
                },
            ],
            withdrawalTabs: [
                { value: "pending", label: "Pending" },
                { value: "approved", label: "Approved" },
                { value: "rejected", label: "Rejected" },
                { value: "all", label: "All" },
            ],
        };
    },
    computed: {
        summary() {
            return this.overview.summary || {};
        },
        summaryCards() {
            return [
                {
                    label: "Available",
                    value: this.summary.available_balance,
                    help: "Merchant can request payout",
                    icon: "lab lab-line-account",
                    iconBox: "bg-[#ECFDF3] text-[#047857]",
                },
                {
                    label: "Holding",
                    value: this.summary.holding_balance,
                    help: "Waiting release period",
                    icon: "lab lab-line-time",
                    iconBox: "bg-[#FFF7ED] text-[#C2410C]",
                },
                {
                    label: "Pending Payout",
                    value: this.summary.pending_withdrawal_balance,
                    help: "Owner action required",
                    icon: "lab lab-line-bell",
                    iconBox: "bg-[#FFF4F1] text-primary",
                },
                {
                    label: "Owner Fees",
                    value: this.summary.total_fees,
                    help: "Platform fees collected",
                    icon: "lab lab-line-transactions",
                    iconBox: "bg-[#EFF6FF] text-[#1D4ED8]",
                },
            ];
        },
        workflowCards() {
            return [
                {
                    number: "1",
                    title: "Create Method",
                    text: "Owner adds bKash, Nagad, Rocket, Bank, or manual payout fields.",
                    badgeClass: "bg-[#FFF4F1] text-primary",
                },
                {
                    number: "2",
                    title: "Merchant Requests",
                    text: "Merchant selects method, fills required fields, and submits withdraw request.",
                    badgeClass: "bg-[#EFF6FF] text-[#1D4ED8]",
                },
                {
                    number: "3",
                    title: "Owner Pays",
                    text: "Owner sends money to the submitted number or bank account outside the system.",
                    badgeClass: "bg-[#FFF7ED] text-[#C2410C]",
                },
                {
                    number: "4",
                    title: "Approve / Reject",
                    text: "Approve after payment. Reject returns the reserved amount to merchant wallet.",
                    badgeClass: "bg-[#ECFDF3] text-[#047857]",
                },
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
                axios.get("platform/wallet/withdrawals", { params: this.withdrawalParams() }),
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
        loadWithdrawals() {
            axios.get("platform/wallet/withdrawals", { params: this.withdrawalParams() }).then((response) => {
                this.withdrawals = response.data.data || [];
            }).catch((error) => {
                this.showFlash("warning", this.errorMessage(error, "Withdrawal requests could not be loaded."));
            });
        },
        withdrawalParams() {
            const params = { per_page: 20 };

            if (this.statusFilter !== "all") {
                params.status = this.statusFilter;
            }

            return params;
        },
        setWithdrawalFilter(status) {
            this.statusFilter = status;
            this.loadWithdrawals();
        },
        savePayoutMethod() {
            if (!this.methodForm.name) {
                this.showFlash("warning", "Method name is required.");
                return;
            }

            this.savingMethod = true;
            axios.post("platform/wallet/payout-methods", {
                ...this.methodForm,
                fields: this.normalizedFormFields(),
            }).then(() => {
                this.showFlash("success", "Payout method saved. Merchants can use it when status is active.");
                this.resetMethodForm();
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", this.errorMessage(error, "Payout method could not be saved."));
            }).finally(() => {
                this.savingMethod = false;
            });
        },
        applyPreset(preset) {
            this.methodForm = {
                ...this.methodForm,
                code: preset.code,
                name: preset.name,
                instructions: preset.instructions,
                status: true,
                fields: preset.fields.map((field) => newField(field)),
            };
        },
        editMethod(method) {
            this.methodForm = {
                code: method.code,
                name: method.name,
                description: method.description || "",
                instructions: method.instructions || "",
                status: Boolean(method.status),
                min_amount: method.min_amount || 0,
                max_amount: method.max_amount,
                fee_type: method.fee_type || "none",
                fee_value: method.fee_value || 0,
                sort_order: method.sort_order || 0,
                fields: Array.isArray(method.fields) && method.fields.length > 0
                    ? method.fields.map((field) => newField(field))
                    : defaultFields(),
            };
        },
        resetMethodForm() {
            this.methodForm = defaultMethodForm();
        },
        addField() {
            this.methodForm.fields.push(newField());
        },
        removeField(index) {
            this.methodForm.fields.splice(index, 1);
        },
        normalizeFieldKey(index) {
            const field = this.methodForm.fields[index];

            if (!field || field.key) {
                return;
            }

            field.key = slugFieldKey(field.label);
        },
        normalizedFormFields() {
            return this.methodForm.fields
                .map((field) => {
                    const label = String(field.label || "").trim();
                    const key = slugFieldKey(field.key || label);

                    if (!key || !label) {
                        return null;
                    }

                    return {
                        key,
                        label,
                        type: field.type || "text",
                        required: Boolean(field.required),
                        placeholder: String(field.placeholder || "").trim(),
                        instructions: String(field.instructions || "").trim(),
                        width: Number(field.width || 100),
                        options: String(field.options_text || "")
                            .split("\n")
                            .map((option) => option.trim())
                            .filter(Boolean),
                    };
                })
                .filter(Boolean);
        },
        destinationRows(withdrawal) {
            if (Array.isArray(withdrawal.destination_details) && withdrawal.destination_details.length > 0) {
                return withdrawal.destination_details;
            }

            return Object.entries(withdrawal.destination || {}).map(([key, value]) => ({
                key,
                label: this.formatLabel(key),
                value,
            }));
        },
        approveWithdrawal(withdrawal) {
            const payoutReference = window.prompt("Enter payout transaction/reference number", withdrawal.request_no);

            if (payoutReference === null) {
                return;
            }

            axios.post(`platform/wallet/withdrawals/${withdrawal.id}/approve`, {
                payout_reference: payoutReference || withdrawal.request_no,
                admin_note: "Paid and approved by owner.",
            }).then(() => {
                this.showFlash("success", "Withdrawal approved.");
                this.loadAll();
            }).catch((error) => {
                this.showFlash("warning", this.errorMessage(error, "Withdrawal could not be approved."));
            });
        },
        rejectWithdrawal(withdrawal) {
            const reason = window.prompt("Why are you rejecting this request?");

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

            if (message.toLowerCase().includes("database")) {
                return "Wallet database tables are not ready yet. Please run migrations, then refresh this page.";
            }

            return message || fallback;
        },
    },
};
</script>
