<template>
    <section class="dashboard-priority mb-6 sm:mb-8">
        <div class="dashboard-priority-grid">
            <router-link
                v-for="card in metricCards"
                :key="card.key"
                :to="card.route"
                class="dashboard-priority-card"
                :class="card.highlight ? 'is-highlighted' : ''">
                <span class="dashboard-priority-icon" :class="card.iconClass">
                    <i :class="card.icon"></i>
                </span>
                <span class="min-w-0">
                    <span class="dashboard-priority-label">{{ card.label }}</span>
                    <span class="dashboard-priority-value">{{ card.value }}</span>
                    <span class="dashboard-priority-help">{{ card.help }}</span>
                </span>
            </router-link>
        </div>

        <div class="dashboard-quick-actions">
            <div class="min-w-0">
                <h3 class="dashboard-quick-title">{{ $t('label.quick_actions') }}</h3>
                <p class="dashboard-quick-help">{{ $t('label.manage_today_work') }}</p>
            </div>
            <div class="dashboard-quick-grid">
                <router-link
                    v-for="action in visibleActions"
                    :key="action.key"
                    :to="action.route"
                    class="dashboard-quick-action"
                    :class="{ 'opacity-70': action.locked }">
                    <i :class="action.icon"></i>
                    <span>{{ action.label }}</span>
                </router-link>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: "DashboardQuickActionsComponent",
    props: {
        setup: {
            type: Object,
            default: null,
        },
    },
    computed: {
        metrics: function () {
            return this.setup?.metrics || {};
        },
        permissions: function () {
            return this.$store.getters.authPermission || [];
        },
        merchantFeatures: function () {
            return this.setup?.billing?.features?.features || {};
        },
        metricCards: function () {
            const pendingOrders = this.numberValue(this.metrics.pending_orders);
            const lowStockAlerts = this.numberValue(this.metrics.low_stock_alerts);

            return [
                {
                    key: "today_revenue",
                    label: this.$t("label.today_revenue"),
                    value: this.metrics.today_revenue || "0.00",
                    help: this.$t("label.paid_today"),
                    icon: "lab-fill-dollar-circle",
                    iconClass: "revenue",
                    route: { name: "admin.sales-report" },
                },
                {
                    key: "today_orders",
                    label: this.$t("label.today_orders"),
                    value: this.metrics.today_orders || 0,
                    help: this.$t("label.orders_today"),
                    icon: "lab-fill-box",
                    iconClass: "orders",
                    route: { name: "admin.order.list" },
                },
                {
                    key: "pending_orders",
                    label: this.$t("label.pending_orders"),
                    value: pendingOrders,
                    help: pendingOrders > 0 ? this.$t("label.needs_attention") : this.$t("label.all_clear"),
                    icon: "lab-fill-box-time",
                    iconClass: "pending",
                    route: { name: "admin.order.list" },
                    highlight: pendingOrders > 0,
                },
                {
                    key: "low_stock",
                    label: this.$t("label.low_stock_alerts"),
                    value: lowStockAlerts,
                    help: lowStockAlerts > 0 ? this.$t("label.review_stock") : this.$t("label.stock_ok"),
                    icon: "lab-fill-document",
                    iconClass: "stock",
                    route: { name: "admin.stock.list" },
                    highlight: lowStockAlerts > 0,
                },
            ];
        },
        quickActions: function () {
            return [
                {
                    key: "orders",
                    label: this.$t("menu.online_orders"),
                    icon: "lab lab-line-online-orders",
                    route: { name: "admin.order.list" },
                    permission: "online-orders",
                },
                {
                    key: "product",
                    label: this.$t("button.add_product"),
                    icon: "lab lab-line-items",
                    route: { name: "admin.products.list" },
                    permission: "products",
                },
                {
                    key: "pos",
                    label: this.$t("menu.pos"),
                    icon: "lab lab-line-pos",
                    route: { name: "admin.pos" },
                    permission: "pos",
                    feature: "pos",
                },
                {
                    key: "settings",
                    label: this.$t("menu.settings"),
                    icon: "lab lab-line-settings",
                    route: { name: "admin.settings.company" },
                    permission: "settings",
                },
            ];
        },
        visibleActions: function () {
            return this.quickActions
                .filter((action) => this.canAccess(action.permission))
                .map((action) => ({
                    ...action,
                    locked: this.isFeatureLocked(action.feature),
                }));
        },
    },
    methods: {
        numberValue: function (value) {
            const number = Number.parseInt(value, 10);

            return Number.isFinite(number) ? number : 0;
        },
        canAccess: function (permissionName) {
            if (!permissionName || !Array.isArray(this.permissions) || this.permissions.length === 0) {
                return true;
            }

            const permission = this.permissions.find((item) => {
                return item?.name === permissionName || item?.url === permissionName;
            });

            return !permission || permission.access !== false;
        },
        isFeatureLocked: function (feature) {
            if (!feature) {
                return false;
            }

            const planFeature = this.merchantFeatures?.[feature];
            return planFeature?.status === false;
        },
    },
};
</script>
