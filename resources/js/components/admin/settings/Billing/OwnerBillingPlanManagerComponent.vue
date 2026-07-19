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
            <div class="mb-4 rounded-lg border bg-white px-4 py-3 text-sm"
                 :class="catalogEnforced ? 'border-[#BBF7D0] text-[#166534]' : 'border-[#E5E7EB] text-paragraph'">
                <p class="font-semibold text-secondary">{{ catalogEnforced ? "Plan enforcement is active" : "Plan enforcement is relaxed" }}</p>
                <p class="mt-1">
                    {{ catalogEnforced
                        ? "Only active public plans are visible to merchants. Plan limits and unchecked feature unlocks are enforced for non-grandfathered stores."
                        : "No active public plan is available, so merchants can use all plan-gated features without upgrade locks." }}
                </p>
            </div>
        </div>

        <div class="col-12 xl:col-4">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Plan Catalog</h3>
                        <p class="text-sm text-paragraph mt-1">Publish, edit, and attach SaaS plans for merchants.</p>
                    </div>
                    <button type="button" class="db-btn py-2 text-white bg-primary" @click="createPlan">
                        <i class="lab lab-line-add-circle"></i>
                        <span>New Plan</span>
                    </button>
                </div>

                <div class="db-card-body pt-0">
                    <div v-if="plans.length === 0" class="rounded-lg border border-dashed border-[#E5E7EB] p-5 text-center text-sm text-paragraph">
                        No plans found yet.
                    </div>

                    <button
                        v-for="plan in plans"
                        :key="plan.code"
                        type="button"
                        class="mb-3 w-full rounded-lg border p-4 text-left transition last:mb-0"
                        :class="selectedPlanCode === plan.code ? 'border-primary bg-[#FFF4F1]' : 'border-[#E5E7EB] bg-white hover:border-primary'"
                        @click="selectPlan(plan)">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-secondary">{{ plan.name }}</p>
                                <p class="text-xs uppercase tracking-wide text-paragraph">{{ plan.code }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold"
                                      :class="plan.status === 'active' ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#FFF7ED] text-[#C2410C]'">
                                    {{ planStatusLabel(plan.status) }}
                                </span>
                                <span class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold"
                                      :class="plan.is_public ? 'bg-[#ECFDF3] text-[#047857]' : 'bg-[#F3F4F6] text-[#6B7280]'">
                                    {{ plan.is_public ? "Public" : "Hidden" }}
                                </span>
                                <span v-if="plan.recommended || plan.badge_label" class="inline-flex rounded-lg bg-[#FFF4F1] px-2 py-1 text-xs font-semibold text-primary">
                                    {{ plan.badge_label || "Recommended" }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-paragraph">{{ plan.short_description || plan.description || "No description yet." }}</p>
                        <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-secondary">{{ money(plan.prices?.monthly || plan.monthly_price, plan.currency_code) }}/mo</span>
                            <span class="text-paragraph">{{ plan.subscribers_count || 0 }} subscribers</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-8">
            <div class="db-card">
                <div class="db-card-header">
                    <div>
                        <h3 class="db-card-title">{{ selectedPlanCode ? "Edit Membership Plan" : "Add Membership Plan" }}</h3>
                        <p class="text-sm text-paragraph mt-1">Set prices, limits, fees, and feature unlocks for merchant subscriptions.</p>
                    </div>
                    <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="resetPlanForm">
                        <i class="lab lab-line-refresh"></i>
                        <span>Reset</span>
                    </button>
                </div>

                <div class="db-card-body">
                    <form class="form-row" @submit.prevent="savePlan">
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title">Plan Name</label>
                            <input v-model.trim="planForm.name" type="text" class="db-field-control" placeholder="Basic">
                        </div>
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title">Plan Code</label>
                            <input v-model.trim="planForm.code" type="text" class="db-field-control" placeholder="basic">
                        </div>
                        <div class="form-col-12">
                            <label class="db-field-title after:hidden">Short Description</label>
                            <input v-model.trim="planForm.short_description" type="text" class="db-field-control" placeholder="For growing stores.">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title">Status</label>
                            <select v-model="planForm.status" class="db-field-control">
                                <option value="draft">Disabled</option>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Badge Label</label>
                            <input v-model.trim="planForm.badge_label" type="text" class="db-field-control" placeholder="Most Popular">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title">Currency</label>
                            <input v-model.trim="planForm.currency_code" type="text" maxlength="10" class="db-field-control uppercase" placeholder="USD">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Trial Days</label>
                            <input v-model.number="planForm.trial_days" type="number" min="0" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Display Order</label>
                            <input v-model.number="planForm.display_order" type="number" min="0" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Visibility</label>
                            <div class="flex h-[42px] flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-2 text-sm text-paragraph">
                                    <input v-model="planForm.is_public" type="checkbox">
                                    Public
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-paragraph">
                                    <input v-model="planForm.is_recommended" type="checkbox">
                                    Recommended
                                </label>
                            </div>
                        </div>

                        <div class="form-col-12">
                            <h4 class="db-card-title text-base">Prices</h4>
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Monthly</label>
                            <input v-model.number="planForm.prices.monthly" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">6 Months</label>
                            <input v-model.number="planForm.prices.semiannual" type="number" min="0" step="0.01" class="db-field-control">
                        </div>
                        <div class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">Yearly</label>
                            <input v-model.number="planForm.prices.yearly" type="number" min="0" step="0.01" class="db-field-control">
                        </div>

                        <div class="form-col-12">
                            <h4 class="db-card-title text-base">Enforced Limits</h4>
                        </div>
                        <div v-for="limit in limitFields" :key="limit.key" class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">{{ limit.label }}</label>
                            <div class="flex gap-2">
                                <input v-model.number="planForm.limits[limit.key].value" :disabled="planForm.limits[limit.key].is_unlimited" type="number" min="0" class="db-field-control disabled:bg-[#F3F4F6]">
                                <label class="inline-flex min-w-[92px] items-center justify-center gap-2 rounded-lg border border-[#E5E7EB] px-3 text-xs text-paragraph">
                                    <input v-model="planForm.limits[limit.key].is_unlimited" type="checkbox">
                                    Unlimited
                                </label>
                            </div>
                        </div>

                        <div class="form-col-12">
                            <h4 class="db-card-title text-base">Fee Rules</h4>
                        </div>
                        <div v-for="fee in feeFields" :key="fee.code" class="form-col-12 sm:form-col-4">
                            <label class="db-field-title after:hidden">{{ fee.label }}</label>
                            <input v-model.number="planForm.fees[fee.code]" type="number" min="0" step="0.01" class="db-field-control">
                        </div>

                        <div class="form-col-12">
                            <h4 class="db-card-title text-base">Feature Unlocks</h4>
                        </div>
                        <div v-for="feature in featureFields" :key="feature.code" class="form-col-12 sm:form-col-6 xl:form-col-4">
                            <label class="flex min-h-[44px] items-center gap-3 rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-sm text-paragraph">
                                <input v-model="planForm.features[feature.code]" type="checkbox">
                                <span>{{ feature.label }}</span>
                            </label>
                        </div>

                        <div class="form-col-12">
                            <button type="submit" class="db-btn text-white bg-primary" :disabled="savingPlan">
                                <i class="lab lab-fill-save"></i>
                                <span>{{ savingPlan ? "Saving..." : "Submit" }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <div>
                        <h3 class="db-card-title">Subscription History</h3>
                        <p class="text-sm text-paragraph mt-1">Invoice oversight, renewal state, and owner mark-paid workflow.</p>
                    </div>
                </div>

                <div class="db-table-responsive">
                    <table class="db-table stripe">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">Tenant</th>
                                <th class="db-table-head-th">Plan</th>
                                <th class="db-table-head-th">Cycle</th>
                                <th class="db-table-head-th">Status</th>
                                <th class="db-table-head-th">Invoice</th>
                                <th class="db-table-head-th">Period End</th>
                                <th class="db-table-head-th hidden-print">Action</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="subscriptions.length > 0">
                            <tr class="db-table-body-tr" v-for="subscription in subscriptions" :key="subscription.id">
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ subscription.tenant?.name || "Unknown tenant" }}</p>
                                    <p class="text-xs text-paragraph">{{ subscription.tenant?.slug || "-" }}</p>
                                </td>
                                <td class="db-table-body-td">{{ subscription.plan?.name || subscription.plan_code_snapshot || "starter" }}</td>
                                <td class="db-table-body-td capitalize">{{ subscription.billing_interval }}</td>
                                <td class="db-table-body-td">
                                    <span :class="statusClass(subscription.status)">{{ formatLabel(subscription.status) }}</span>
                                </td>
                                <td class="db-table-body-td">
                                    <p class="font-medium text-secondary">{{ subscription.invoices?.[0]?.invoice_no || "-" }}</p>
                                    <p class="text-xs capitalize text-paragraph">{{ subscription.invoices?.[0]?.status || "no invoice" }}</p>
                                </td>
                                <td class="db-table-body-td">{{ shortDate(subscription.current_period_ends_at) }}</td>
                                <td class="db-table-body-td hidden-print">
                                    <button
                                        v-if="subscription.invoices?.[0]?.status === 'open'"
                                        type="button"
                                        class="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#047857]"
                                        @click="markInvoicePaid(subscription)">
                                        {{ payingSubscriptionId === subscription.id ? "Marking..." : "Mark Paid" }}
                                    </button>
                                    <span v-else class="text-xs text-paragraph">No action</span>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="7">
                                    <div class="p-4">
                                        <span class="d-block mt-3 text-lg">No subscriptions yet</span>
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
import LoadingComponent from "../../components/LoadingComponent";

const limitFields = [
    { key: "products", label: "Products" },
    { key: "custom_domains", label: "Custom Domains" },
    { key: "staff_members", label: "Staff Members" },
];

const feeFields = [
    { code: "fee_physical", label: "Physical order fee %", sort_order: 10 },
    { code: "fee_digital", label: "Digital order fee %", sort_order: 20 },
    { code: "fee_resell", label: "Resell order fee %", sort_order: 30 },
];

const featureFields = [
    { code: "custom_domain", label: "Custom Domain", group: "Store & Branding", sort_order: 40 },
    { code: "theme_builder", label: "Theme Builder", group: "Store & Branding", sort_order: 60 },
    { code: "campaigns", label: "Campaigns/Promos", group: "Marketing & Growth", sort_order: 70 },
    { code: "report_exports", label: "Report Exports", group: "Marketing & Growth", sort_order: 80 },
    { code: "advanced_stock", label: "Advanced Stock", group: "Operations", sort_order: 90 },
    { code: "returns", label: "Returns & Refunds", group: "Operations", sort_order: 100 },
    { code: "pos", label: "POS", group: "Operations", sort_order: 110 },
    { code: "external_gateways", label: "External Payment Gateways", group: "Payments & Delivery", sort_order: 120 },
    { code: "third_party_couriers", label: "Third-party Couriers", group: "Payments & Delivery", sort_order: 130 },
];

export default {
    name: "OwnerBillingPlanManagerComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: { isActive: false },
            plans: [],
            subscriptions: [],
            catalogEnforced: false,
            selectedPlanCode: null,
            planForm: this.emptyPlanForm(),
            savingPlan: false,
            payingSubscriptionId: null,
            flash: {
                type: "",
                text: "",
            },
            limitFields,
            feeFields,
            featureFields,
        };
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        fetchData() {
            this.loading.isActive = true;

            Promise.all([
                axios.get("platform/plans"),
                axios.get("platform/subscriptions"),
            ]).then(([plans, subscriptions]) => {
                this.plans = Array.isArray(plans?.data?.data) ? plans.data.data : [];
                this.subscriptions = Array.isArray(subscriptions?.data?.data) ? subscriptions.data.data : [];
                this.catalogEnforced = plans?.data?.meta?.catalog_enforced === true;
                this.retainSelectedPlan();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Billing plans could not be loaded.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        emptyPlanForm(code = "") {
            const limits = {};
            const fees = {};
            const features = {};

            limitFields.forEach((limit) => {
                limits[limit.key] = {
                    value: 0,
                    is_unlimited: false,
                };
            });

            feeFields.forEach((fee) => {
                fees[fee.code] = 0;
            });

            featureFields.forEach((feature) => {
                features[feature.code] = feature.code === "pos";
            });

            return {
                code,
                name: "",
                short_description: "",
                description: "",
                status: "draft",
                is_public: true,
                display_order: this.plans?.length ? this.plans.length + 1 : 1,
                is_recommended: false,
                badge_label: "",
                currency_code: "USD",
                trial_days: 0,
                prices: {
                    monthly: 0,
                    semiannual: 0,
                    yearly: 0,
                },
                limits,
                fees,
                features,
            };
        },
        createPlan() {
            this.selectedPlanCode = null;
            this.planForm = this.emptyPlanForm();
        },
        selectPlan(plan) {
            this.selectedPlanCode = plan.code;
            this.planForm = this.hydratePlanForm(plan);
        },
        hydratePlanForm(plan) {
            const form = this.emptyPlanForm(plan.code || "");

            form.name = plan.name || "";
            form.short_description = plan.short_description || "";
            form.description = plan.description || "";
            form.status = plan.status || "draft";
            form.is_public = plan.is_public !== false;
            form.display_order = Number(plan.display_order || 0);
            form.is_recommended = plan.recommended === true;
            form.badge_label = plan.badge_label || "";
            form.currency_code = plan.currency_code || "USD";
            form.trial_days = Number(plan.trial_days || 0);
            form.prices = {
                monthly: Number(plan?.prices?.monthly || plan.monthly_price || 0),
                semiannual: Number(plan?.prices?.semiannual || 0),
                yearly: Number(plan?.prices?.yearly || plan.yearly_price || 0),
            };

            (plan.limits || []).forEach((limit) => {
                if (form.limits[limit.key]) {
                    form.limits[limit.key] = {
                        value: Number(limit.value || 0),
                        is_unlimited: !!limit.is_unlimited,
                    };
                }
            });

            (plan.features || []).forEach((feature) => {
                if (Object.prototype.hasOwnProperty.call(form.fees, feature.code)) {
                    form.fees[feature.code] = Number(feature.value || feature.display_value || 0);
                }

                if (Object.prototype.hasOwnProperty.call(form.features, feature.code)) {
                    form.features[feature.code] = feature.enabled === true;
                }
            });

            return form;
        },
        retainSelectedPlan() {
            if (this.plans.length === 0) {
                this.planForm = this.emptyPlanForm();
                return;
            }

            const selected = this.selectedPlanCode
                ? this.plans.find((plan) => plan.code === this.selectedPlanCode)
                : null;

            this.selectPlan(selected || this.plans[0]);
        },
        savePlan() {
            if (!this.planForm.code || !this.planForm.name) {
                this.showFlash("warning", "Plan name and plan code are required.");
                return;
            }

            const code = this.normalizeCode(this.planForm.code);
            this.savingPlan = true;

            axios.put(`platform/plans/${code}`, {
                name: this.planForm.name,
                short_description: this.planForm.short_description,
                description: this.planForm.description,
                status: this.planForm.status,
                is_public: this.planForm.is_public,
                display_order: Number(this.planForm.display_order || 0),
                is_recommended: this.planForm.is_recommended,
                badge_label: this.planForm.badge_label || null,
                currency_code: this.planForm.currency_code || "USD",
                trial_days: Number(this.planForm.trial_days || 0),
                prices: {
                    monthly: Number(this.planForm.prices.monthly || 0),
                    semiannual: Number(this.planForm.prices.semiannual || 0),
                    yearly: Number(this.planForm.prices.yearly || 0),
                },
                limits: this.limitPayload(),
                features: this.featurePayload(),
            }).then(() => {
                this.selectedPlanCode = code;
                this.showFlash("success", "Plan saved successfully.");
                this.fetchData();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Plan could not be saved.");
            }).finally(() => {
                this.savingPlan = false;
            });
        },
        limitPayload() {
            return limitFields.map((limit) => ({
                key: limit.key,
                value: this.planForm.limits[limit.key].is_unlimited ? null : Number(this.planForm.limits[limit.key].value || 0),
                is_unlimited: !!this.planForm.limits[limit.key].is_unlimited,
            }));
        },
        featurePayload() {
            const fees = feeFields.map((fee) => ({
                code: fee.code,
                label: fee.label,
                group: "Fees per Order",
                type: "percent",
                value: String(this.planForm.fees[fee.code] || 0),
                sort_order: fee.sort_order,
            }));

            const features = featureFields.map((feature) => ({
                code: feature.code,
                label: feature.label,
                group: feature.group,
                type: "boolean",
                value: !!this.planForm.features[feature.code],
                sort_order: feature.sort_order,
            }));

            return fees.concat(features);
        },
        resetPlanForm() {
            const plan = this.plans.find((item) => item.code === this.selectedPlanCode);
            this.planForm = plan ? this.hydratePlanForm(plan) : this.emptyPlanForm();
        },
        markInvoicePaid(subscription) {
            const invoice = subscription?.invoices?.[0];

            if (!invoice) {
                return;
            }

            this.payingSubscriptionId = subscription.id;

            axios.post(`platform/subscriptions/${subscription.id}/invoices/${invoice.id}/mark-paid`).then(() => {
                this.showFlash("success", "Invoice marked paid.");
                this.fetchData();
            }).catch((error) => {
                this.showFlash("warning", error.response?.data?.message || "Invoice could not be marked paid.");
            }).finally(() => {
                this.payingSubscriptionId = null;
            });
        },
        normalizeCode(value) {
            return String(value || "").trim().toLowerCase().replace(/\s+/g, "-");
        },
        money(amount, currency = "USD") {
            return `${currency || "USD"} ${Number(amount || 0).toFixed(2)}`;
        },
        statusClass(status) {
            if (["active", "paid", "trialing"].includes(status)) {
                return "inline-flex items-center justify-center rounded-lg bg-[#ECFDF3] px-3 py-1 text-xs font-semibold text-[#047857]";
            }

            if (["failed", "expired", "rejected", "suspended", "cancelled"].includes(status)) {
                return "inline-flex items-center justify-center rounded-lg bg-[#FEF2F2] px-3 py-1 text-xs font-semibold text-[#B91C1C]";
            }

            return "inline-flex items-center justify-center rounded-lg bg-[#FFF7ED] px-3 py-1 text-xs font-semibold text-[#C2410C]";
        },
        formatLabel(value) {
            return String(value || "-").replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
        },
        planStatusLabel(value) {
            return value === "draft" ? "Disabled" : this.formatLabel(value);
        },
        shortDate(value) {
            return value ? new Date(value).toLocaleDateString() : "Not set";
        },
        showFlash(type, text) {
            this.flash = { type, text };
        },
    },
};
</script>
