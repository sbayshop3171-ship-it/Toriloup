<template>
    <div :class="isFullMode ? 'mb-9' : ''">
        <div :class="isFullMode ? 'row' : ''">
            <div v-if="showChecklist" :class="isFullMode ? 'col-12 xl:col-8' : ''">
                <div class="db-card dashboard-setup-card">
                    <div class="db-card-header border-none dashboard-setup-header">
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
                    <div class="px-5 pb-5 dashboard-setup-body">
                        <div class="w-full h-2 rounded-full bg-[#EEF2FF] overflow-hidden mb-5">
                            <div class="h-full rounded-full bg-primary transition-all duration-500"
                                :style="{ width: progress.percent + '%' }"></div>
                        </div>
                        <div class="dashboard-setup-grid grid grid-cols-1 md:grid-cols-2 gap-3">
                            <button v-for="step in steps" :key="step.key" type="button"
                                class="dashboard-setup-step text-left p-4 rounded-xl border transition-all duration-300 bg-white hover:border-primary/40"
                                :class="step.completed ? 'border-green-100' : 'border-[#E5E7EB]'"
                                @click="goStep(step)">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="relative w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 shadow-sm ring-1 ring-inset"
                                        :class="stepIcon(step).bubble">
                                        <i class="text-base" :class="stepIcon(step).icon"></i>
                                        <span v-if="step.completed"
                                            class="absolute -right-1 -bottom-1 w-4 h-4 rounded-full bg-green-500 text-white border-2 border-white flex items-center justify-center">
                                            <i class="fa-solid fa-check text-[9px]"></i>
                                        </span>
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
            <div v-if="showStorefrontStatus" :class="isFullMode ? 'col-12 xl:col-4' : ''">
                <div class="db-card dashboard-storefront-card">
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

                        <div class="pt-1">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <h4 class="text-sm font-semibold text-heading">Recent Orders</h4>
                                <router-link v-if="recentOrders.length > 0" :to="{ name: 'admin.order.list' }"
                                    class="text-xs font-semibold text-primary hover:underline">
                                    View All
                                </router-link>
                            </div>
                            <div v-if="recentOrders.length === 0"
                                class="rounded-xl border border-dashed border-[#D9DBE9] flex items-center gap-3 p-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <i class="lab lab-line-bag text-xl"></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-heading">No orders placed yet</p>
                                    <p class="text-xs text-paragraph mt-0.5">Orders will appear here after customers buy.
                                    </p>
                                </div>
                            </div>
                            <div v-else class="space-y-2">
                                <router-link v-for="order in compactRecentOrders" :key="order.id"
                                    :to="{ name: 'admin.order.show', params: { id: order.id } }"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 hover:border-primary/40 hover:shadow-sm transition">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-heading truncate">
                                            {{ order.order_serial_no || ('#' + order.id) }}
                                        </p>
                                        <p class="text-xs text-paragraph">{{ order.order_datetime }}</p>
                                    </div>
                                    <p class="font-semibold text-primary text-sm shrink-0">{{ order.total }}</p>
                                </router-link>
                                <router-link v-if="extraRecentOrderCount > 0" :to="{ name: 'admin.order.list' }"
                                    class="flex items-center justify-center h-9 rounded-xl border border-dashed border-primary/30 bg-primary/5 text-xs font-semibold text-primary hover:bg-primary hover:text-white transition">
                                    +{{ extraRecentOrderCount }} more orders
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ENV from "../../../config/env";

export default {
    name: "MerchantSetupChecklistComponent",
    props: {
        setup: {
            type: Object,
            default: null,
        },
        mode: {
            type: String,
            default: "full",
        },
    },
    computed: {
        isFullMode: function () {
            return this.mode === "full";
        },
        showChecklist: function () {
            return ["full", "checklist"].includes(this.mode);
        },
        showStorefrontStatus: function () {
            return ["full", "storefront"].includes(this.mode);
        },
        steps: function () {
            const checklist = this.setup?.checklist;

            if (Array.isArray(checklist) && checklist.length > 0) {
                return checklist;
            }

            return this.defaultChecklist;
        },
        progress: function () {
            const progress = this.setup?.progress;

            if (progress && Number(progress.total || 0) > 0) {
                return progress;
            }

            const completed = this.steps.filter((step) => step.completed === true).length;
            const total = this.steps.length;

            return {
                completed,
                total,
                percent: total > 0 ? Math.round((completed / total) * 100) : 0,
            };
        },
        metrics: function () {
            return this.setup?.metrics || {};
        },
        recentOrders: function () {
            return this.metrics.recent_orders || [];
        },
        compactRecentOrders: function () {
            return this.recentOrders.slice(0, 3);
        },
        extraRecentOrderCount: function () {
            return Math.max(this.recentOrders.length - this.compactRecentOrders.length, 0);
        },
        storefrontHost: function () {
            return this.cleanHost(this.metrics.storefront_hostname)
                || this.cleanHost(this.metrics.primary_domain?.hostname)
                || this.cleanHost(this.metrics.fallback_domain?.hostname)
                || this.fallbackStorefrontHost;
        },
        storefrontUrl: function () {
            const url = String(this.metrics.storefront_url || "").trim();

            if (url) {
                return url;
            }

            return this.storefrontHost ? "https://" + this.storefrontHost : "";
        },
        fallbackStorefrontHost: function () {
            const slug = this.setup?.tenant?.slug
                || this.authTenant?.tenant?.slug
                || this.authTenant?.slug
                || "";
            const suffix = ENV.STOREFRONT_SUFFIX || ENV.MARKETING_HOST || "toriloup.com";

            return slug ? `${slug}.${String(suffix).replace(/^\.+|\.+$/g, "")}` : "";
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        authTenant: function () {
            return this.authInfo.current_tenant || this.authInfo.tenant || null;
        },
        setupIconMap: function () {
            return {
                general_settings: {
                    icon: "fa-solid fa-store",
                    bubble: "bg-[#FFF1F2] text-primary ring-primary/10",
                },
                business_localization: {
                    icon: "fa-solid fa-globe",
                    bubble: "bg-[#FFF7ED] text-[#F97316] ring-[#FED7AA]",
                },
                first_product: {
                    icon: "fa-solid fa-box-open",
                    bubble: "bg-[#EFF6FF] text-[#2563EB] ring-[#BFDBFE]",
                },
                shipping_delivery: {
                    icon: "fa-solid fa-truck-fast",
                    bubble: "bg-[#ECFDF3] text-[#16A34A] ring-[#BBF7D0]",
                },
                payment_methods: {
                    icon: "fa-solid fa-credit-card",
                    bubble: "bg-[#F5F3FF] text-[#7C3AED] ring-[#DDD6FE]",
                },
                launch_domain: {
                    icon: "fa-solid fa-rocket",
                    bubble: "bg-[#EEF2FF] text-[#4F46E5] ring-[#C7D2FE]",
                },
            };
        },
    },
    data() {
        return {
            copied: false,
            defaultChecklist: [
                {
                    key: "general_settings",
                    title: "General Settings",
                    description: "Add store name, logo, contact, and address.",
                    route_name: "admin.settings.company",
                    completed: false,
                },
                {
                    key: "business_localization",
                    title: "Business & Localization",
                    description: "Confirm currency, timezone, language, and country.",
                    route_name: "admin.settings.company",
                    completed: false,
                },
                {
                    key: "first_product",
                    title: "Add Your First Product",
                    description: "Upload product details, images, pricing, and stock.",
                    route_name: "admin.products.list",
                    completed: false,
                },
                {
                    key: "shipping_delivery",
                    title: "Shipping & Delivery Setup",
                    description: "Set delivery method and store-level delivery charges.",
                    route_name: "admin.settings.shippingSetup",
                    completed: false,
                },
                {
                    key: "payment_methods",
                    title: "Payment Method Setup",
                    description: "Enable COD or owner-approved online payment methods.",
                    route_name: "admin.settings.paymentGateway",
                    completed: false,
                },
                {
                    key: "launch_domain",
                    title: "Launch & Domain Verification",
                    description: "Keep fallback subdomain active and provision the custom domain hostname when ready.",
                    route_name: "admin.settings.domains",
                    completed: false,
                },
            ],
        };
    },
    methods: {
        goStep(step) {
            if (step.route_name) {
                this.$router.push({ name: step.route_name });
            }
        },
        stepIcon(step) {
            return this.setupIconMap[step.key] || {
                icon: "fa-solid fa-list-check",
                bubble: "bg-primary/10 text-primary ring-primary/10",
            };
        },
        cleanHost(value) {
            return String(value || "")
                .replace(/^https?:\/\//, "")
                .replace(/\/.*$/, "")
                .trim();
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
