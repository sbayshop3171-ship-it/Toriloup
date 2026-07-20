<template>
    <div class="row mb-8">
        <div class="col-12 xl:col-8">
            <div v-if="$slots.summaryTop" class="mb-8">
                <slot name="summaryTop" />
            </div>

            <div class="db-card">
                <div class="db-card-header flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="db-card-title">Gateway Wallet</h3>
                        <p class="text-sm text-paragraph mt-1">
                            Online gateway payments collect here after owner fees and holding rules.
                        </p>
                    </div>
                    <router-link :to="{ name: 'merchant.wallet' }" class="db-btn py-2 text-white bg-primary">
                        <i class="lab lab-line-account"></i>
                        <span>Request Withdrawal</span>
                    </router-link>
                </div>

                <div class="db-card-body">
                    <div v-if="flash.text" class="mb-4 rounded-lg border px-4 py-3 text-sm"
                         :class="flash.type === 'success' ? 'border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]' : 'border-[#FED7AA] bg-[#FFF7ED] text-[#C2410C]'">
                        {{ flash.text }}
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div v-for="card in balanceCards" :key="card.label" class="rounded-2xl border border-[#E5E7EB] bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-paragraph">{{ card.label }}</p>
                                    <h4 class="mt-2 text-2xl font-bold text-secondary">{{ money(card.value) }}</h4>
                                </div>
                                <i class="text-lg" :class="card.icon"></i>
                            </div>
                            <p class="mt-2 text-xs text-paragraph">{{ card.help }}</p>

                            <router-link
                                v-if="card.key === 'available'"
                                :to="{ name: 'merchant.wallet' }"
                                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white">
                                Withdraw
                                <i class="lab lab-line-arrow-right"></i>
                            </router-link>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-[#E5E7EB] bg-[#F7F7FC] p-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-paragraph">Gross Gateway Sales</p>
                                <p class="mt-1 text-lg font-semibold text-secondary">{{ money(summary.period_totals?.gross_sales) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-paragraph">Owner Fees</p>
                                <p class="mt-1 text-lg font-semibold text-secondary">{{ money(summary.period_totals?.fees) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-paragraph">Holding Rule</p>
                                <p class="mt-1 text-lg font-semibold text-secondary">{{ summary.settings?.holding_days || 0 }} day(s)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-4">
            <div v-if="$slots.activityTop" class="mb-8">
                <slot name="activityTop" />
            </div>

            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">Recent Wallet Activity</h3>
                        <p class="text-sm text-paragraph mt-1">Credits, payouts, fees, and refunds.</p>
                    </div>
                </div>

                <div class="db-card-body">
                    <div v-if="recentTransactions.length === 0" class="rounded-xl border border-dashed border-[#D9DBE9] p-6 text-center">
                        <i class="lab lab-line-transactions text-primary text-xl"></i>
                        <p class="mt-2 font-medium text-secondary">No wallet transactions yet</p>
                        <p class="mt-1 text-sm text-paragraph">Gateway payments will appear after customers pay online.</p>
                    </div>

                    <div v-else>
                        <div
                            v-for="transaction in recentTransactions"
                            :key="transaction.id"
                            class="flex items-start justify-between gap-3 border-b border-[#EFF0F6] py-3 first:pt-0 last:border-b-0 last:pb-0">
                            <div>
                                <p class="font-medium text-secondary">{{ formatLabel(transaction.type) }}</p>
                                <p class="text-xs text-paragraph">{{ transaction.description || formatDate(transaction.created_at) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold" :class="transaction.direction === 'credit' ? 'text-[#047857]' : 'text-[#B91C1C]'">
                                    {{ transaction.direction === 'credit' ? '+' : '-' }}{{ money(transaction.amount) }}
                                </p>
                                <span class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold" :class="statusClass(transaction.status)">
                                    {{ formatLabel(transaction.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <router-link :to="{ name: 'merchant.wallet' }" class="db-btn mt-4 w-full py-2 text-primary bg-[#FFF4F1]">
                        View Wallet History
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    name: "MerchantWalletSnapshotComponent",
    data() {
        return {
            summary: {
                wallet: {},
                period_totals: {},
                settings: {},
                recent_transactions: [],
            },
            flash: {
                type: "",
                text: "",
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
        recentTransactions() {
            return (this.summary.recent_transactions || []).slice(0, 4);
        },
        balanceCards() {
            return [
                {
                    key: "available",
                    label: "Available",
                    value: this.wallet.available_balance,
                    help: "Ready to withdraw",
                    icon: "lab lab-line-account text-[#047857]",
                },
                {
                    key: "holding",
                    label: "On Hold",
                    value: this.wallet.holding_balance,
                    help: "Waiting for settlement release",
                    icon: "lab lab-line-order-setup text-[#C2410C]",
                },
                {
                    key: "pending",
                    label: "Pending Payout",
                    value: this.wallet.pending_withdrawal_balance,
                    help: "Submitted to owner",
                    icon: "lab lab-line-refresh text-primary",
                },
                {
                    key: "withdrawn",
                    label: "Total Withdrawn",
                    value: this.wallet.total_withdrawn,
                    help: "Approved payouts",
                    icon: "lab lab-line-transactions text-[#3B82F6]",
                },
            ];
        },
    },
    mounted() {
        this.fetchWalletSummary();
    },
    methods: {
        fetchWalletSummary() {
            axios.get("merchant/wallet/summary").then((response) => {
                this.summary = response.data.data || this.summary;
            }).catch((error) => {
                this.flash = {
                    type: "warning",
                    text: error.response?.data?.message || "Wallet summary could not be loaded.",
                };
            });
        },
        money(value) {
            const amount = Number(value || 0);

            return `${this.currencyCode} ${amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
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
