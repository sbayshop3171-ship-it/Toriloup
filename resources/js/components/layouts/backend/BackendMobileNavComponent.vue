<template>
    <div v-if="isMerchantWorkspace" class="backend-mobile-nav-shell lg:hidden">
        <section
            v-if="settingsHubOpen"
            class="backend-mobile-more-sheet"
            aria-modal="true">
            <div class="backend-mobile-more-handle"></div>
            <div class="flex items-center justify-between gap-3 px-4 pb-3">
                <div>
                    <h3 class="text-base font-semibold text-heading">{{ $t("menu.settings") }}</h3>
                    <p class="text-xs text-paragraph">
                        {{ $t("menu.store") }}, {{ $t("menu.business") }}, {{ $t("menu.accounts_and_reports") }}
                    </p>
                </div>
                <button
                    type="button"
                    class="backend-mobile-more-close"
                    @click="closeSettingsHub">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="backend-mobile-more-scroll">
                <div
                    v-for="group in visibleSettingsHubGroups"
                    :key="group.labelKey"
                    class="backend-mobile-more-group">
                    <h4 class="backend-mobile-more-title">{{ $t(group.labelKey) }}</h4>
                    <div class="backend-mobile-more-grid">
                        <router-link
                            v-for="item in group.items"
                            :key="item.url"
                            :to="adminPath(item.url)"
                            class="backend-mobile-more-item"
                            :class="{ active: isActive(item.urls || [item.url]), 'opacity-70': isFeatureLocked(item) }"
                            @click="closeSettingsHub">
                            <span class="backend-mobile-more-icon">
                                <i :class="item.icon"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="backend-mobile-more-label">{{ $t(item.labelKey) }}</span>
                                <span v-if="isFeatureLocked(item)" class="backend-mobile-lock">Lock</span>
                            </span>
                            <span
                                v-if="badgeForUrls(item.urls || [item.url]) > 0"
                                class="backend-mobile-badge">
                                {{ badgeText(badgeForUrls(item.urls || [item.url])) }}
                            </span>
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <nav class="backend-mobile-bottom-nav">
            <router-link
                :to="workspaceHomeRoute"
                class="backend-mobile-nav-item"
                :class="{ active: isDashboardActive }">
                <i class="lab lab-line-dashboard"></i>
                <span>{{ $t("menu.dashboard") }}</span>
            </router-link>

            <router-link
                :to="adminPath('pos')"
                class="backend-mobile-nav-item"
                :class="{ active: isActive(['pos']), 'opacity-70': isFeatureLocked(posItem) }">
                <i class="lab lab-line-pos"></i>
                <span>{{ $t("menu.pos") }}</span>
            </router-link>

            <router-link
                :to="adminPath('online-orders')"
                class="backend-mobile-nav-fab"
                :class="{ active: isActive(orderUrls) }">
                <span class="backend-mobile-fab-button">
                    <i class="lab lab-line-online-orders"></i>
                    <span
                        v-if="badgeForUrls(orderUrls) > 0"
                        class="backend-mobile-fab-badge">
                        {{ badgeText(badgeForUrls(orderUrls)) }}
                    </span>
                </span>
                <span>{{ $t("menu.order") }}</span>
            </router-link>

            <router-link
                :to="adminPath('products')"
                class="backend-mobile-nav-item"
                :class="{ active: isActive(productUrls) }">
                <span class="relative">
                    <i class="lab lab-line-items"></i>
                    <span v-if="badgeForUrls(productUrls) > 0" class="backend-mobile-dot"></span>
                </span>
                <span>{{ $t("menu.products") }}</span>
            </router-link>

            <button
                type="button"
                class="backend-mobile-nav-item"
                :class="{ active: settingsHubOpen || isSettingsActive }"
                @click="toggleSettingsHub">
                <span class="relative">
                    <i class="fa-solid fa-gear"></i>
                    <span v-if="settingsHubBadgeCount > 0" class="backend-mobile-dot"></span>
                </span>
                <span>{{ $t("menu.settings") }}</span>
            </button>
        </nav>
    </div>
</template>

<script>
import appService from "../../../services/appService";
import backendNotificationService from "../../../services/backendNotificationService";
import { isMerchantHost, resolveWorkspaceDashboardRoute } from "../../../services/workspaceService";

const orderUrls = ["online-orders", "pos-orders", "return-orders", "return-and-refunds"];
const productUrls = ["products", "purchase", "damages", "stock", "reviews"];

const settingsHubGroups = [
    {
        labelKey: "menu.store",
        items: [
            { labelKey: "menu.company", url: "settings/company", icon: "fa-regular fa-building" },
            { labelKey: "menu.site", url: "settings/site", icon: "fa-solid fa-globe" },
            { labelKey: "menu.theme", url: "settings/theme", icon: "fa-solid fa-palette" },
            { labelKey: "menu.sliders", url: "settings/sliders", icon: "fa-regular fa-images" },
            { labelKey: "menu.domains", url: "settings/domains", icon: "fa-solid fa-link" },
            { labelKey: "menu.social_media", url: "settings/social-media", icon: "fa-solid fa-share-nodes" },
            { labelKey: "menu.currencies", url: "settings/currencies", icon: "fa-solid fa-coins" },
            { labelKey: "menu.pages", url: "settings/pages", icon: "fa-regular fa-file-lines" },
            { labelKey: "menu.benefits", url: "settings/benefits", icon: "fa-regular fa-star" },
        ],
    },
    {
        labelKey: "menu.operations",
        items: [
            { labelKey: "menu.shipping_setup", url: "settings/shipping-setup", icon: "fa-solid fa-truck-fast" },
            { labelKey: "menu.payment_methods", url: "settings/payment-gateway", icon: "fa-regular fa-credit-card" },
            { labelKey: "menu.location_setup", url: "settings/location-setup", icon: "fa-solid fa-location-dot" },
            { labelKey: "menu.outlets", url: "settings/outlets", icon: "fa-solid fa-store" },
            { labelKey: "menu.return_reasons", url: "settings/return-reasons", icon: "fa-solid fa-rotate-left" },
            { labelKey: "menu.role_permissions", url: "settings/role", icon: "fa-solid fa-shield-halved" },
        ],
    },
    {
        labelKey: "menu.catalog",
        items: [
            { labelKey: "menu.product_categories", url: "settings/product-categories", icon: "fa-solid fa-table-cells-large" },
            { labelKey: "menu.product_attributes", url: "settings/product-attributes", icon: "fa-solid fa-sliders" },
            { labelKey: "menu.product_brands", url: "settings/product-brands", icon: "fa-solid fa-tags" },
            { labelKey: "menu.suppliers", url: "settings/suppliers", icon: "fa-solid fa-truck-ramp-box" },
            { labelKey: "menu.units", url: "settings/units", icon: "fa-solid fa-ruler-combined" },
            { labelKey: "menu.taxes", url: "settings/taxes", icon: "fa-solid fa-receipt" },
        ],
    },
    {
        labelKey: "menu.business",
        items: [
            { labelKey: "menu.coupons", url: "coupons", icon: "lab lab-line-coupon", feature: "campaigns" },
            { labelKey: "menu.promotions", url: "promotions", icon: "lab lab-line-promotion", feature: "campaigns" },
            { labelKey: "menu.product_sections", url: "product-sections", icon: "lab lab-line-product-section", feature: "campaigns" },
            { labelKey: "menu.push_notifications", url: "push-notifications", icon: "lab lab-line-push-notification" },
            { labelKey: "menu.subscribers", url: "subscribers", icon: "lab lab-line-subscribers" },
        ],
    },
    {
        labelKey: "menu.accounts_and_reports",
        items: [
            { labelKey: "menu.wallet", url: "wallet", icon: "lab lab-line-account" },
            { labelKey: "menu.transactions", url: "transactions", icon: "lab lab-line-transactions" },
            { labelKey: "menu.administrators", url: "administrators", icon: "lab lab-line-administrator" },
            { labelKey: "menu.customers", url: "customers", icon: "lab lab-line-customers" },
            { labelKey: "menu.sales_report", url: "sales-report", icon: "lab lab-line-sales-report", feature: "report_exports" },
            { labelKey: "menu.products_report", url: "products-report", icon: "lab lab-line-products-report", feature: "report_exports" },
        ],
    },
];

export default {
    name: "BackendMobileNavComponent",
    data() {
        return {
            settingsHubOpen: false,
            notificationItems: [],
            notificationStorageKey: backendNotificationService.storageKey,
            notificationSyncEventName: backendNotificationService.syncEventName,
            orderUrls: orderUrls,
            productUrls: productUrls,
            posItem: { feature: "pos" },
        };
    },
    computed: {
        isMerchantWorkspace: function () {
            return isMerchantHost();
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        permissions: function () {
            return this.$store.getters.authPermission || [];
        },
        merchantSetup: function () {
            return this.$store.getters["merchantDashboard/setup"];
        },
        workspaceHomeRoute: function () {
            return resolveWorkspaceDashboardRoute(this.authInfo?.surface);
        },
        isDashboardActive: function () {
            return ["/dashboard", "/admin/dashboard", "/admin"].includes(this.$route.path);
        },
        visibleSettingsHubGroups: function () {
            return settingsHubGroups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => this.canAccess(item.url)),
                }))
                .filter((group) => group.items.length > 0);
        },
        settingsUrls: function () {
            return this.visibleSettingsHubGroups.flatMap((group) => {
                return group.items.flatMap((item) => item.urls || [item.url]);
            });
        },
        isSettingsActive: function () {
            return this.isActive(this.settingsUrls);
        },
        settingsHubBadgeCount: function () {
            return this.badgeForUrls(this.settingsUrls);
        },
    },
    mounted() {
        this.loadNotificationItems();
        this.syncSettingsHubState();
        window.addEventListener("storage", this.handleNotificationStorageEvent);
        window.addEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
        window.addEventListener("merchant-mobile-settings-hub-change", this.handleSettingsHubChange);

        if (this.isMerchantWorkspace && !this.merchantSetup) {
            this.$store.dispatch("merchantDashboard/setup").catch(() => {});
        }
    },
    beforeUnmount() {
        window.removeEventListener("storage", this.handleNotificationStorageEvent);
        window.removeEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
        window.removeEventListener("merchant-mobile-settings-hub-change", this.handleSettingsHubChange);
    },
    methods: {
        adminPath: function (url) {
            return "/admin/" + String(url || "").replace(/^\/+/, "");
        },
        syncSettingsHubState: function () {
            this.settingsHubOpen = document?.body?.classList?.contains("merchant-mobile-settings-open");
        },
        handleSettingsHubChange: function (event) {
            this.settingsHubOpen = !!event?.detail?.open;
        },
        normalizeRoute: function (url) {
            return backendNotificationService.normalizeRouteUrl(url);
        },
        isActive: function (urls) {
            const current = this.normalizeRoute(this.$route.path);

            return (urls || []).some((url) => {
                const route = this.normalizeRoute(url);
                return current === route || current.startsWith(route + "/");
            });
        },
        canAccess: function (url) {
            const route = this.normalizeRoute(url);
            const routeBase = route.split("/")[0];

            if (!Array.isArray(this.permissions) || this.permissions.length === 0) {
                return true;
            }

            const permission = this.permissions.find((item) => {
                const permissionUrl = this.normalizeRoute(item?.url || "");
                const permissionName = this.normalizeRoute(item?.name || "");

                return [permissionUrl, permissionName].some((value) => {
                    return value === route || value === routeBase || route.startsWith(value + "/");
                });
            });

            return !permission || permission.access !== false;
        },
        isFeatureLocked: function (item) {
            if (!item?.feature || !this.merchantSetup) {
                return false;
            }

            const feature = this.merchantSetup?.billing?.features?.features?.[item.feature];
            return feature?.status !== true;
        },
        badgeForUrls: function (urls) {
            return (urls || []).reduce((total, url) => {
                return total + backendNotificationService.unreadCountForMenu(this.notificationItems, url);
            }, 0);
        },
        badgeText: function (count) {
            return count > 99 ? "99+" : count;
        },
        loadNotificationItems: function () {
            this.notificationItems = backendNotificationService.loadItems();
        },
        handleNotificationStorageEvent: function (event) {
            if (event?.key && event.key !== this.notificationStorageKey) {
                return;
            }
            this.loadNotificationItems();
        },
        handleNotificationSyncEvent: function () {
            this.loadNotificationItems();
        },
        toggleSettingsHub: function () {
            const willOpen = !this.settingsHubOpen;

            if (willOpen) {
                appService.closeSidebar();
                appService.closeMobileFilterSheets();
                appService.openMobileSettingsHub();
                return;
            }

            appService.closeMobileSettingsHub();
        },
        closeSettingsHub: function () {
            appService.closeMobileSettingsHub();
        },
    },
    watch: {
        $route() {
            this.closeSettingsHub();
        },
    },
};
</script>
