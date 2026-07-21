<template>
    <div v-if="isMerchantWorkspace" class="backend-mobile-nav-shell lg:hidden">
        <nav class="backend-mobile-bottom-nav backend-mobile-bottom-nav-compact">
            <router-link
                :to="workspaceHomeRoute"
                class="backend-mobile-nav-item"
                :class="{ active: isDashboardActive }">
                <i class="lab lab-line-dashboard"></i>
                <span>{{ $t("menu.dashboard") }}</span>
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
        </nav>
    </div>
</template>

<script>
import backendNotificationService from "../../../services/backendNotificationService";
import { isMerchantHost, resolveWorkspaceDashboardRoute } from "../../../services/workspaceService";

const orderUrls = ["online-orders", "pos-orders", "return-orders", "return-and-refunds"];
const productUrls = ["products", "purchase", "damages", "stock", "reviews"];

export default {
    name: "BackendMobileNavComponent",
    data() {
        return {
            notificationItems: [],
            notificationStorageKey: backendNotificationService.storageKey,
            notificationSyncEventName: backendNotificationService.syncEventName,
            orderUrls: orderUrls,
            productUrls: productUrls,
        };
    },
    computed: {
        isMerchantWorkspace: function () {
            return isMerchantHost();
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        workspaceHomeRoute: function () {
            return resolveWorkspaceDashboardRoute(this.authInfo?.surface);
        },
        isDashboardActive: function () {
            return ["/dashboard", "/admin/dashboard", "/admin"].includes(this.$route.path);
        },
    },
    mounted() {
        this.loadNotificationItems();
        window.addEventListener("storage", this.handleNotificationStorageEvent);
        window.addEventListener(this.notificationSyncEventName, this.handleNotificationSyncEvent);
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
    },
};
</script>
