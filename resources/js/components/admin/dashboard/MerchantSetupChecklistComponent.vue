<template>
    <div class="mb-9">
        <div class="row">
            <div class="col-12 xl:col-8">
                <div class="db-card">
                    <div class="db-card-header border-none">
                        <div>
                            <h3 class="db-card-title">Store Setup Checklist</h3>
                            <p class="text-sm text-paragraph">
                                Complete these steps to launch your store cleanly.
                            </p>
                        </div>
                        <div class="text-right">
                            <h4 class="font-bold text-primary text-xl">{{ progress.percent }}%</h4>
                            <p class="text-xs text-paragraph">
                                {{ progress.completed }} of {{ progress.total }} done
                            </p>
                        </div>
                    </div>
                    <div class="px-5 pb-5">
                        <div class="w-full h-2 rounded-full bg-[#EEF2FF] overflow-hidden mb-5">
                            <div class="h-full rounded-full bg-primary transition-all duration-500"
                                :style="{ width: progress.percent + '%' }"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <button v-for="step in steps" :key="step.key" type="button"
                                class="text-left p-4 rounded-xl border transition-all duration-300 bg-white hover:border-primary/40"
                                :class="step.completed ? 'border-green-100' : 'border-[#E5E7EB]'"
                                @click="goStep(step)">
                                <div class="flex items-start gap-3">
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                        :class="step.completed ? 'bg-green-100 text-green-600' : 'bg-primary/10 text-primary'">
                                        <i :class="step.completed ? 'lab lab-tick-circle' : 'lab lab-arrow-right'"></i>
                                    </span>
                                    <span>
                                        <span class="block font-semibold text-heading">{{ step.title }}</span>
                                        <span class="block text-xs leading-5 text-paragraph">{{ step.description }}</span>
                                    </span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 xl:col-4">
                <div class="db-card h-full">
                    <div class="db-card-header border-none">
                        <div>
                            <h3 class="db-card-title">Storefront Status</h3>
                            <p class="text-sm text-paragraph">Your public store link and recent order state.</p>
                        </div>
                    </div>
                    <div class="px-5 pb-5">
                        <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#E5E7EB] mb-4">
                            <div v-if="storefrontUrl">
                                <div class="flex items-start gap-3 mb-4">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-white border border-[#E5E7EB] text-primary flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-link"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs uppercase tracking-wide text-paragraph mb-1">Website link</p>
                                        <a class="font-semibold text-heading hover:text-primary break-all"
                                            :href="storefrontUrl" target="_blank" rel="noopener">
                                            {{ storefrontHost }}
                                        </a>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <a class="inline-flex items-center gap-2 h-10 px-4 rounded-xl border-2 border-primary text-primary bg-white font-semibold hover:bg-primary hover:text-white transition"
                                        :href="storefrontUrl" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-globe"></i>
                                        <span>Visit Website</span>
                                    </a>
                                    <button
                                        class="inline-flex items-center gap-2 h-10 px-4 rounded-xl border border-[#D9DBE9] text-heading bg-white font-semibold hover:border-primary hover:text-primary transition"
                                        type="button" @click="copyStorefrontUrl">
                                        <i class="fa-regular fa-copy"></i>
                                        <span>{{ copied ? 'Copied' : 'Copy Website' }}</span>
                                    </button>
                                    <button
                                        class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-[#D9DBE9] text-heading bg-white hover:border-primary hover:text-primary transition"
                                        type="button" title="Copy website link" @click="copyStorefrontUrl">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </button>
                                </div>
                            </div>
                            <div v-else>
                                <p class="text-xs uppercase tracking-wide text-paragraph mb-1">Default storefront</p>
                                <p class="font-semibold text-heading">Not ready yet</p>
                            </div>
                            <p class="text-xs text-paragraph mt-3">
                                Custom domains can be connected from Domain settings.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-semibold text-heading mb-3">Recent Orders</h4>
                            <div v-if="recentOrders.length === 0"
                                class="min-h-32 rounded-xl border border-dashed border-[#D9DBE9] flex flex-col items-center justify-center text-center p-5">
                                <i class="lab lab-line-bag text-3xl text-primary mb-2"></i>
                                <p class="font-semibold text-heading">No orders placed yet</p>
                                <p class="text-xs text-paragraph mt-1">Orders will appear here after customers buy.</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="order in recentOrders" :key="order.id"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] p-3">
                                    <div>
                                        <p class="font-semibold text-heading">{{ order.order_serial_no || ('#' + order.id) }}</p>
                                        <p class="text-xs text-paragraph">{{ order.order_datetime }}</p>
                                    </div>
                                    <p class="font-semibold text-primary">{{ order.total }}</p>
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
export default {
    name: "MerchantSetupChecklistComponent",
    props: {
        setup: {
            type: Object,
            default: null,
        },
    },
    computed: {
        steps: function () {
            return this.setup?.checklist || [];
        },
        progress: function () {
            return this.setup?.progress || {
                completed: 0,
                total: this.steps.length,
                percent: 0,
            };
        },
        metrics: function () {
            return this.setup?.metrics || {};
        },
        recentOrders: function () {
            return this.metrics.recent_orders || [];
        },
        storefrontHost: function () {
            return this.metrics.primary_domain?.hostname || this.metrics.fallback_domain?.hostname || "";
        },
        storefrontUrl: function () {
            return this.metrics.storefront_url || (this.storefrontHost ? "https://" + this.storefrontHost : "");
        },
    },
    data() {
        return {
            copied: false,
        };
    },
    methods: {
        goStep(step) {
            if (step.route_name) {
                this.$router.push({ name: step.route_name });
            }
        },
        copyStorefrontUrl() {
            if (!this.storefrontUrl) {
                return;
            }

            const done = () => {
                this.copied = true;
                window.setTimeout(() => {
                    this.copied = false;
                }, 1800);
            };

            if (navigator?.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.storefrontUrl).then(done).catch(() => {
                    this.copyWithTextarea(done);
                });
                return;
            }

            this.copyWithTextarea(done);
        },
        copyWithTextarea(done) {
            const textarea = document.createElement("textarea");
            textarea.value = this.storefrontUrl;
            textarea.setAttribute("readonly", "readonly");
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand("copy");
            document.body.removeChild(textarea);
            done();
        },
    },
};
</script>
