<template>
    <aside class="db-sidebar">
        <div class="db-sidebar-header">
            <router-link class="flex items-center justify-center w-24 h-12 overflow-hidden" :to="workspaceHomeRoute">
                <img class="w-full h-full object-contain" :src="setting.theme_logo" alt="logo">
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
                    <router-link :to="'/admin/' + menu.url" class="db-sidebar-nav-menu">
                        <i class="text-sm" :class="menu.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + menu.language) }}</span>
                        <span
                            v-if="isOnlineOrderMenu(menu.url) && unreadOrderNotificationCount > 0"
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-white bg-[#FF3B30]">
                            {{ unreadOrderNotificationBadge }}
                        </span>
                    </router-link>
                </li>

                <li class="db-sidebar-nav-item" v-if="menu.children" v-for="children in menu.children" @click.prevent="sidebarActive($event)">
                    <router-link :to="'/admin/' + children.url" class="db-sidebar-nav-menu">
                        <i class="text-sm" :class="children.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + children.language) }}</span>
                        <span
                            v-if="isOnlineOrderMenu(children.url) && unreadOrderNotificationCount > 0"
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] leading-none font-semibold text-white bg-[#FF3B30]">
                            {{ unreadOrderNotificationBadge }}
                        </span>
                    </router-link>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<script>
import appService from "../../../services/appService";
import { resolveWorkspaceDashboardRoute } from "../../../services/workspaceService";
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
        menus: function () {
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
        normalizeMenuUrl: function (url) {
            return String(url || '').replace(/^\/+|\/+$/g, '');
        },
        isOnlineOrderMenu: function (url) {
            return this.normalizeMenuUrl(url) === 'online-orders';
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
