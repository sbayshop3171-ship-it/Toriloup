<template>
    <aside class="db-sidebar">
        <div class="db-sidebar-header">
            <router-link class="flex items-center justify-center w-24 h-12 overflow-hidden" :to="workspaceHomeRoute">
                <img v-if="logoSrc" class="w-full h-full object-contain" :src="logoSrc" alt="logo">
                <span v-else
                    class="w-full h-full rounded-xl border border-dashed border-[#D9DBE9] bg-white text-[#A0A3BD] flex items-center justify-center">
                    <i class="fa-regular fa-image"></i>
                </span>
            </router-link>
            <button @click="closeSidebar" class="fa-solid fa-xmark xmark-btn close-db-menu"></button>
        </div>
        <nav class="db-sidebar-nav">
            <ul class="db-sidebar-nav-list" v-if="menus.length > 0" v-for="menu in menus" :key="menu">
                <li class="db-sidebar-nav-item" v-if="menu.url === '#'" @click.prevent="sidebarActive($event)">
                    <a href="javascript:void(0);" class="db-sidebar-nav-title">
                        {{ $t('menu.' + menu.language) }}
                    </a>
                </li>

                <li class="db-sidebar-nav-item" v-else @click.prevent="sidebarActive($event)">
                    <router-link :to="menuRoute(menu)" class="db-sidebar-nav-menu" :class="{ 'opacity-75': isFeatureLocked(menu) }">
                        <i class="text-sm" :class="menu.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + menu.language) }}</span>
                        <span
                            v-if="isOnlineOrderMenu(menu.url) && unreadOrderNotificationCount > 0"
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-white bg-[#FF3B30]">
                            {{ unreadOrderNotificationBadge }}
                        </span>
                        <span
                            v-if="isFeatureLocked(menu)"
                            class="inline-flex items-center justify-center h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-primary bg-[#FFF4F1]">
                            Lock
                        </span>
                    </router-link>
                </li>

                <li class="db-sidebar-nav-item" v-if="menu.children" v-for="children in menu.children" @click.prevent="sidebarActive($event)">
                    <router-link :to="menuRoute(children)" class="db-sidebar-nav-menu" :class="{ 'opacity-75': isFeatureLocked(children) }">
                        <i class="text-sm" :class="children.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + children.language) }}</span>
                        <span
                            v-if="isOnlineOrderMenu(children.url) && unreadOrderNotificationCount > 0"
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-white bg-[#FF3B30]">
                            {{ unreadOrderNotificationBadge }}
                        </span>
                        <span
                            v-if="isFeatureLocked(children)"
                            class="inline-flex items-center justify-center h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-primary bg-[#FFF4F1]">
                            Lock
                        </span>
                    </router-link>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<script>
import appService from "../../../services/appService";
import { isMerchantHost, isPlatformHost, resolveWorkspaceDashboardRoute } from "../../../services/workspaceService";

const ownerMenus = [
    {
        name: "Dashboard",
        language: "dashboard",
        url: "dashboard",
        icon: "lab lab-line-dashboard",
    },
    {
        name: "Merchants",
        language: "merchants",
        url: "merchants",
        icon: "lab lab-line-administrator",
    },
    {
        name: "Customers",
        language: "customers",
        url: "merchant-customers",
        icon: "lab lab-line-customers",
    },
    {
        name: "Wallets",
        language: "wallets",
        url: "wallets",
        icon: "lab lab-line-account",
    },
    {
        name: "Settings",
        language: "settings",
        url: "settings",
        icon: "lab lab-line-settings",
    },
];

const merchantMenus = [
    {
        name: "Dashboard",
        language: "dashboard",
        url: "dashboard",
        icon: "lab lab-line-dashboard",
    },
    {
        name: "Product & Stock",
        language: "product_and_stock",
        url: "#",
        icon: "lab lab-item",
        children: [
            { name: "Products", language: "products", url: "products", icon: "lab lab-line-items" },
            { name: "Purchase", language: "purchase", url: "purchase", icon: "lab lab-line-add-purchase" },
            { name: "Damages", language: "damages", url: "damages", icon: "lab lab-line-addons" },
            { name: "Stock", language: "stock", url: "stock", icon: "lab lab-line-stock", feature: "advanced_stock" },
            { name: "Reviews", language: "reviews", url: "reviews", icon: "lab lab-line-rating-star" },
        ],
    },
    {
        name: "POS & Orders",
        language: "pos_and_orders",
        url: "#",
        icon: "lab lab-pos",
        children: [
            { name: "POS", language: "pos", url: "pos", icon: "lab lab-line-pos", feature: "pos" },
            { name: "POS Orders", language: "pos_orders", url: "pos-orders", icon: "lab lab-line-push-notification", feature: "pos" },
            { name: "Online Orders", language: "online_orders", url: "online-orders", icon: "lab lab-line-online-orders" },
            { name: "Return Orders", language: "return_orders", url: "return-orders", icon: "lab lab-line-order-setup", feature: "returns" },
            { name: "Return And Refunds", language: "return_and_refunds", url: "return-and-refunds", icon: "lab lab-line-refresh", feature: "returns" },
        ],
    },
    {
        name: "Promo",
        language: "promo",
        url: "#",
        icon: "lab lab-line-promotion",
        children: [
            { name: "Coupons", language: "coupons", url: "coupons", icon: "lab lab-line-coupon", feature: "campaigns" },
            { name: "Promotions", language: "promotions", url: "promotions", icon: "lab lab-line-promotion", feature: "campaigns" },
            { name: "Product Sections", language: "product_sections", url: "product-sections", icon: "lab lab-line-product-section", feature: "campaigns" },
        ],
    },
    {
        name: "Communications",
        language: "communications",
        url: "#",
        icon: "lab lab-line-notification",
        children: [
            { name: "Push Notifications", language: "push_notifications", url: "push-notifications", icon: "lab lab-line-push-notification" },
            { name: "Subscribers", language: "subscribers", url: "subscribers", icon: "lab lab-line-subscribers" },
        ],
    },
    {
        name: "Users",
        language: "users",
        url: "#",
        icon: "lab lab-line-user",
        children: [
            { name: "Administrators", language: "administrators", url: "administrators", icon: "lab lab-line-administrator" },
            { name: "Customers", language: "customers", url: "customers", icon: "lab lab-line-customers" },
        ],
    },
    {
        name: "Accounts",
        language: "accounts",
        url: "#",
        icon: "lab lab-line-account",
        children: [
            { name: "Wallet", language: "wallet", url: "wallet", icon: "lab lab-line-account" },
            { name: "Transactions", language: "transactions", url: "transactions", icon: "lab lab-line-transactions" },
        ],
    },
    {
        name: "Reports",
        language: "reports",
        url: "#",
        icon: "lab lab-line-report",
        children: [
            { name: "Sales Report", language: "sales_report", url: "sales-report", icon: "lab lab-line-sales-report", feature: "report_exports" },
            { name: "Products Report", language: "products_report", url: "products-report", icon: "lab lab-line-products-report", feature: "report_exports" },
        ],
    },
    {
        name: "Setup",
        language: "setup",
        url: "#",
        icon: "lab lab-line-settings",
        children: [
            { name: "Settings", language: "settings", url: "settings", icon: "lab lab-line-settings" },
        ],
    },
];

const cloneMenus = function (menus) {
    return JSON.parse(JSON.stringify(menus));
};

export default {
    name: "BackendMenuComponent",
    data: function () {
        return {
            activeParentId: 1,
            activeChildId: 0,
            notificationStorageKey: "shopking_admin_notifications",
            notificationSyncEventName: "shopking-admin-notification-updated",
            notificationItems: [],
            maxNotificationItems: 20
        }
    },
    computed: {
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        merchantSetup: function () {
            return this.$store.getters['merchantDashboard/setup'];
        },
        merchantBrandLogo: function () {
            return this.merchantSetup?.branding?.company_logo_url || "";
        },
        logoSrc: function () {
            if (isMerchantHost()) {
                return this.merchantBrandLogo;
            }

            return this.setting?.theme_logo || "";
        },
        menus: function () {
            if (isPlatformHost()) {
                return cloneMenus(ownerMenus);
            }

            if (isMerchantHost()) {
                return cloneMenus(merchantMenus);
            }

            return this.$store.getters.authMenu;
        },
        workspaceHomeRoute: function () {
            return resolveWorkspaceDashboardRoute(this.$store.getters.authInfo?.surface);
        },
        unreadOrderNotificationCount: function () {
            return this.notificationItems.filter((item) => !item.read && this.isOrderNotificationItem(item)).length;
        },
        unreadOrderNotificationBadge: function () {
            if (this.unreadOrderNotificationCount > 99) {
                return "99+";
            }
            return this.unreadOrderNotificationCount;
        }
    },

    mounted() {
        this.defaultSidebarActive();
        this.loadMerchantBranding();
        this.loadNotificationItems();
        this.markOnlineOrderNotificationsAsReadByRoute(this.$route.path);
        window.addEventListener('storage', this.handleNotificationStorageEvent);
        window.addEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
    },
    beforeUnmount() {
        window.removeEventListener('storage', this.handleNotificationStorageEvent);
        window.removeEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
    },
    methods: {
        sidebarActive: function (e) {
            const activeMenu = document.querySelector('.db-sidebar-nav-item.active');
            if (activeMenu) {
                activeMenu.classList.remove('active');
            }
            e?.currentTarget?.classList?.add('active');
        },
        defaultSidebarActive: function () {
            if (document?.querySelector(".db-sidebar-nav-menu")?.classList?.contains("active")) {
                document?.querySelector('.db-sidebar-nav-menu')?.parentElement?.classList?.add('active');
            } else {
                document?.querySelector('.router-link-exact-active')?.parentElement?.classList?.add('active');
            }
        },
        closeSidebar : function(){
            return appService.closeSidebar()
        },
        loadMerchantBranding: function () {
            if (!isMerchantHost() || this.merchantSetup) {
                return;
            }

            this.$store.dispatch("merchantDashboard/setup").catch(() => {});
        },
        normalizeMenuUrl: function (url) {
            return String(url || '').replace(/^\/+|\/+$/g, '');
        },
        isOnlineOrderMenu: function (url) {
            return this.normalizeMenuUrl(url) === 'online-orders';
        },
        menuRoute: function (menu) {
            return '/admin/' + menu.url;
        },
        isFeatureLocked: function (menu) {
            if (!isMerchantHost() || !menu?.feature) {
                return false;
            }

            if (!this.merchantSetup) {
                return false;
            }

            const feature = this.merchantSetup?.billing?.features?.features?.[menu.feature];

            return feature?.status !== true;
        },
        isOnOnlineOrderRoute: function (path) {
            return String(path || '').startsWith('/admin/online-orders');
        },
        isOrderNotificationItem: function (item) {
            const type = String(item?.type || '').toLowerCase();
            if (type === 'order') {
                return true;
            }

            const routeUrl = this.normalizeMenuUrl(item?.routeUrl || '');
            if (routeUrl === 'online-orders') {
                return true;
            }

            const title = String(item?.title || '').toLowerCase();
            const body = String(item?.body || '').toLowerCase();
            return title.includes('order') || body.includes('order');
        },
        handleNotificationStorageEvent: function (event) {
            if (event?.key && event.key !== this.notificationStorageKey) {
                return;
            }
            this.loadNotificationItems();
            this.markOnlineOrderNotificationsAsReadByRoute(this.$route.path);
        },
        handleNotificationSyncEvent: function () {
            this.loadNotificationItems();
            this.markOnlineOrderNotificationsAsReadByRoute(this.$route.path);
        },
        emitNotificationSyncEvent: function () {
            window.dispatchEvent(new CustomEvent(this.notificationSyncEventName));
        },
        loadNotificationItems: function () {
            try {
                const rawData = localStorage.getItem(this.notificationStorageKey);
                if (!rawData) {
                    this.notificationItems = [];
                    return;
                }

                const parsedItems = JSON.parse(rawData);
                if (Array.isArray(parsedItems)) {
                    this.notificationItems = parsedItems.slice(0, this.maxNotificationItems);
                } else {
                    this.notificationItems = [];
                }
            } catch (error) {
                this.notificationItems = [];
            }
        },
        persistNotificationItems: function () {
            try {
                localStorage.setItem(this.notificationStorageKey, JSON.stringify(this.notificationItems.slice(0, this.maxNotificationItems)));
                this.emitNotificationSyncEvent();
            } catch (error) {
                // Ignore storage write errors.
            }
        },
        markOrderNotificationsAsRead: function () {
            const hasUnreadOrderNotification = this.notificationItems.some((item) => !item.read && this.isOrderNotificationItem(item));
            if (!hasUnreadOrderNotification) {
                return;
            }

            this.notificationItems = this.notificationItems.map((item) => {
                if (this.isOrderNotificationItem(item)) {
                    return { ...item, read: true };
                }
                return item;
            });
            this.persistNotificationItems();
        },
        markOnlineOrderNotificationsAsReadByRoute: function (path) {
            if (!this.isOnOnlineOrderRoute(path)) {
                return;
            }
            this.markOrderNotificationsAsRead();
        }
    },
    watch: {
        $route(to) {
            this.markOnlineOrderNotificationsAsReadByRoute(to.path);
        }
    }
}
</script>
