<template>
    <div v-if="isMerchantWorkspace" class="backend-mobile-nav-shell lg:hidden">
        <div
            v-if="moreMenuOpen"
            class="backend-mobile-nav-overlay"
            @click="closeMoreMenu">
        </div>

        <section
            v-if="moreMenuOpen"
            class="backend-mobile-more-sheet"
            aria-modal="true">
            <div class="backend-mobile-more-handle"></div>
            <div class="flex items-center justify-between gap-3 px-4 pb-3">
                <div>
                    <h3 class="text-base font-semibold text-heading">{{ $t('menu.more') }}</h3>
                    <p class="text-xs text-paragraph">{{ $t('menu.settings') }}, {{ $t('menu.reports') }}, {{ $t('menu.accounts') }}</p>
                </div>
                <button
                    type="button"
                    class="backend-mobile-more-close"
                    @click="closeMoreMenu">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="backend-mobile-more-scroll">
                <div
                    v-for="group in visibleMoreGroups"
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
                            @click="closeMoreMenu">
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
                <span>{{ $t('menu.dashboard') }}</span>
            </router-link>

            <router-link
                :to="adminPath('online-orders')"
                class="backend-mobile-nav-item"
                :class="{ active: isActive(orderUrls) }">
                <span class="relative">
                    <i class="lab lab-line-online-orders"></i>
                    <span v-if="badgeForUrls(orderUrls) > 0" class="backend-mobile-dot"></span>
                </span>
                <span>{{ $t('menu.orders') }}</span>
            </router-link>

            <router-link
                :to="adminPath('pos')"
                class="backend-mobile-nav-fab"
                :class="{ active: isActive(['pos']), 'opacity-70': isFeatureLocked(posItem) }">
                <span class="backend-mobile-fab-button">
                    <i class="lab lab-line-pos"></i>
                    <span
                        v-if="badgeForUrls(sellUrls) > 0"
                        class="backend-mobile-fab-badge">
                        {{ badgeText(badgeForUrls(sellUrls)) }}
                    </span>
                </span>
                <span>{{ $t('menu.sell') }}</span>
            </router-link>

            <router-link
                :to="adminPath('products')"
                class="backend-mobile-nav-item"
                :class="{ active: isActive(productUrls) }">
                <span class="relative">
                    <i class="lab lab-line-items"></i>
                    <span v-if="badgeForUrls(productUrls) > 0" class="backend-mobile-dot"></span>
                </span>
                <span>{{ $t('menu.products') }}</span>
            </router-link>

            <button
                type="button"
                class="backend-mobile-nav-item"
                :class="{ active: moreMenuOpen || isMoreActive }"
                @click="toggleMoreMenu">
                <span class="relative">
                    <i class="fa-solid fa-ellipsis"></i>
                    <span v-if="moreBadgeCount > 0" class="backend-mobile-dot"></span>
                </span>
                <span>{{ $t('menu.more') }}</span>
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

const moreGroups = [
    {
        labelKey: "menu.pos_and_orders",
        items: [
            { labelKey: "menu.pos_orders", url: "pos-orders", icon: "lab lab-line-push-notification", feature: "pos" },
            { labelKey: "menu.return_orders", url: "return-orders", icon: "lab lab-line-order-setup", feature: "returns" },
            { labelKey: "menu.return_and_refunds", url: "return-and-refunds", icon: "lab lab-line-refresh", feature: "returns" },
        ],
    },
    {
        labelKey: "menu.product_and_stock",
        items: [
            { labelKey: "menu.purchase", url: "purchase", icon: "lab lab-line-add-purchase" },
            { labelKey: "menu.damages", url: "damages", icon: "lab lab-line-addons" },
            { labelKey: "menu.stock", url: "stock", icon: "lab lab-line-stock", feature: "advanced_stock" },
            { labelKey: "menu.reviews", url: "reviews", icon: "lab lab-line-rating-star" },
        ],
    },
    {
        labelKey: "menu.promo",
        items: [
            { labelKey: "menu.coupons", url: "coupons", icon: "lab lab-line-coupon", feature: "campaigns" },
            { labelKey: "menu.promotions", url: "promotions", icon: "lab lab-line-promotion", feature: "campaigns" },
            { labelKey: "menu.product_sections", url: "product-sections", icon: "lab lab-line-product-section", feature: "campaigns" },
        ],
    },
    {
        labelKey: "menu.communications",
        items: [
            { labelKey: "menu.push_notifications", url: "push-notifications", icon: "lab lab-line-push-notification" },
            { labelKey: "menu.subscribers", url: "subscribers", icon: "lab lab-line-subscribers" },
        ],
    },
    {
        labelKey: "menu.users",
        items: [
            { labelKey: "menu.administrators", url: "administrators", icon: "lab lab-line-administrator" },
            { labelKey: "menu.customers", url: "customers", icon: "lab lab-line-customers" },
        ],
    },
    {
        labelKey: "menu.accounts",
        items: [
            { labelKey: "menu.wallet", url: "wallet", icon: "lab lab-line-account" },
            { labelKey: "menu.transactions", url: "transactions", icon: "lab lab-line-transactions" },
        ],
    },
    {
        labelKey: "menu.reports",
        items: [
            { labelKey: "menu.sales_report", url: "sales-report", icon: "lab lab-line-sales-report", feature: "report_exports" },
            { labelKey: "menu.products_report", url: "products-report", icon: "lab lab-line-products-report", feature: "report_exports" },
        ],
    },
    {
        labelKey: "menu.setup",
        items: [
            { labelKey: "menu.settings", url: "settings", icon: "lab lab-line-settings" },
        ],
    },
];

export default {
    name: "BackendMobileNavComponent",
    data() {
        return {
            moreMenuOpen: false,
            notificationItems: [],
            notificationStorageKey: backendNotificationService.storageKey,
            notificationSyncEventName: backendNotificationService.syncEventName,
            orderUrls: orderUrls,
            productUrls: productUrls,
            sellUrls: ["pos", "pos-orders"],
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
        visibleMoreGroups: function () {
            return moreGroups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => this.canAccess(item.url)),
                }))
                .filter((group) => group.items.length > 0);
        },
        moreUrls: function () {
            return this.visibleMoreGroups.flatMap((group) => {
                return group.items.flatMap((item) => item.urls || [item.url]);
            });
        },
        isMoreActive: function () {
            return this.isActive(this.moreUrls);
        },
        moreBadgeCount: function () {
            return this.badgeForUrls(this.moreUrls);
        },
    },
    mounted() {
        this.loadNotificationItems();
        window.addEventListener("storage", this.handleNotificationStorageEvent);
        window.addEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);

        if (this.isMerchantWorkspace && !this.merchantSetup) {
            this.$store.dispatch("merchantDashboard/setup").catch(() => {});
        }
    },
    beforeUnmount() {
        window.removeEventListener("storage", this.handleNotificationStorageEvent);
        window.removeEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
    },
    methods: {
        adminPath: function (url) {
            return "/admin/" + String(url || "").replace(/^\/+/, "");
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
        toggleMoreMenu: function () {
            const willOpen = !this.moreMenuOpen;

            if (willOpen) {
                appService.closeSidebar();
                appService.closeMobileFilterSheets();
            }

            this.moreMenuOpen = willOpen;
            document?.body?.classList?.toggle("overflow-hidden", this.moreMenuOpen);
            document.body.style.overflowY = this.moreMenuOpen ? "hidden" : "auto";
        },
        closeMoreMenu: function () {
            this.moreMenuOpen = false;
            document?.body?.classList?.remove("overflow-hidden");
            document.body.style.overflowY = "auto";
        },
    },
    watch: {
        $route() {
            this.closeMoreMenu();
        },
    },
};
</script>
