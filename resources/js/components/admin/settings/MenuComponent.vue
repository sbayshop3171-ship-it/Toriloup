<template>
    <button
        @click="openSettingMenu($event)"
        type="button"
        aria-expanded="false"
        class="settings-btn settings-menu-toggle w-full md:hidden"
    >
        <div class="settings-menu-toggle-copy">
            <span class="settings-menu-toggle-eyebrow">{{ $t("menu.settings_menu") }}</span>
            <strong class="settings-menu-toggle-title">{{ currentItemLabel }}</strong>
        </div>
        <i class="icon fa-solid fa-chevron-down text-sm"></i>
    </button>

    <div class="settings-menu-panel h-0 overflow-hidden md:h-auto md:overflow-auto transition-all duration-300 font-medium">
        <nav class="db-card settings-menu-card">
            <div class="settings-menu-search">
                <div class="settings-menu-searchbox">
                    <i class="fa-solid fa-magnifying-glass settings-menu-search-icon"></i>
                    <input
                        v-model.trim="menuSearch"
                        type="text"
                        class="settings-menu-search-input"
                        placeholder="Search settings"
                    />
                </div>
            </div>

            <div v-if="filteredMenuGroups.length === 0" class="settings-menu-empty">
                No settings matched your search.
            </div>

            <section
                v-for="group in filteredMenuGroups"
                :key="group.title"
                class="settings-menu-group"
            >
                <div class="settings-menu-group-header">
                    <h4 class="settings-menu-group-title">{{ group.title }}</h4>
                    <span class="settings-menu-group-count">{{ group.items.length }}</span>
                </div>

                <div class="settings-menu-group-list">
                    <router-link
                        v-for="item in group.items"
                        :key="item.name"
                        :to="settingRoute(item)"
                        class="db-tab-btn settings-menu-link"
                        :class="{
                            active: isCurrentItem(item),
                            'opacity-75': isFeatureLocked(item)
                        }"
                        @click="closeMobileSettingMenu"
                    >
                        <span class="settings-menu-link-icon">
                            <i :class="item.icon" class="text-sm"></i>
                        </span>
                        <span class="settings-menu-link-text">{{ menuItemLabel(item) }}</span>
                        <span
                            v-if="isFeatureLocked(item)"
                            class="settings-menu-link-badge"
                        >
                            Lock
                        </span>
                    </router-link>
                </div>
            </section>
        </nav>
    </div>
</template>

<script>
import appService from "../../../services/appService";
import { isMerchantHost } from "../../../services/workspaceService";

const merchantGroups = [
    {
        title: "Store",
        items: [
            { name: "admin.settings.company", icon: "lab lab-line-company", labelKey: "menu.company" },
            { name: "admin.settings.site", icon: "lab lab-line-currencies", label: "Currency Settings" },
            { name: "admin.settings.domains", icon: "lab lab-line-site", label: "Domains", feature: "custom_domain" },
            { name: "admin.settings.socialMedia", icon: "lab lab-line-social", labelKey: "menu.social_media" },
            { name: "admin.settings.theme", icon: "lab lab-line-theme", labelKey: "menu.theme" },
            { name: "admin.settings.slider", icon: "lab lab-line-sliders", labelKey: "menu.sliders" },
            { name: "admin.settings.currency", icon: "lab lab-line-currencies", labelKey: "menu.currencies" },
            { name: "admin.settings.page", icon: "lab lab-line-pages", labelKey: "menu.pages" },
            { name: "admin.settings.benefit", icon: "lab lab-line-benefits", labelKey: "menu.benefits" },
        ],
    },
    {
        title: "Operations",
        items: [
            { name: "admin.settings.locationSetup", icon: "lab lab-line-location-setup", labelKey: "menu.location_setup" },
            { name: "admin.settings.shippingSetup", icon: "lab lab-line-truck-check", labelKey: "menu.shipping_setup" },
            { name: "admin.settings.paymentGateway", icon: "lab lab-line-payment-gateway", label: "Payment Methods" },
            { name: "admin.settings.billing", icon: "lab lab-line-report", label: "Billing" },
        ],
    },
    {
        title: "Catalog",
        items: [
            { name: "admin.settings.productCategory", icon: "lab lab-line-item-categories", labelKey: "menu.product_categories" },
            { name: "admin.settings.productAttribute", icon: "lab lab-line-item-attributes", labelKey: "menu.product_attributes" },
            { name: "admin.settings.productBrand", icon: "lab lab-line-brand", labelKey: "menu.product_brands" },
            { name: "admin.settings.supplier", icon: "lab lab-line-supplier", labelKey: "menu.suppliers" },
            { name: "admin.settings.unit", icon: "lab lab-line-unit", labelKey: "menu.units" },
            { name: "admin.settings.tax", icon: "lab lab-line-taxes", labelKey: "menu.taxes" },
        ],
    },
    {
        title: "Access",
        items: [
            { name: "admin.settings.outlet", icon: "lab lab-line-branches", labelKey: "menu.outlets" },
            { name: "admin.settings.returnReason", icon: "lab lab-line-order-setup", labelKey: "menu.return_reasons", feature: "returns" },
            { name: "admin.settings.role", icon: "lab lab-line-role-permission", labelKey: "menu.role_permissions" },
        ],
    },
];

const ownerGroups = [
    {
        title: "Core",
        items: [
            { name: "admin.settings.company", icon: "lab lab-line-company", labelKey: "menu.company" },
            { name: "admin.settings.site", icon: "lab lab-line-site", labelKey: "menu.site" },
            { name: "admin.settings.mail", icon: "lab lab-line-mail", labelKey: "menu.mail" },
            { name: "admin.settings.otp", icon: "lab lab-line-otp", labelKey: "menu.otp" },
            { name: "admin.settings.notification", icon: "lab lab-line-notification", labelKey: "menu.notification" },
            { name: "admin.settings.notificationAlert", icon: "lab lab-line-notification-alert", labelKey: "menu.notification_alert" },
            { name: "admin.settings.smsGateway", icon: "lab lab-line-sms", labelKey: "menu.sms_gateway" },
            { name: "admin.settings.paymentGateway", icon: "lab lab-line-payment-gateway", labelKey: "menu.payment_gateway" },
            { name: "admin.settings.billing", icon: "lab lab-line-report", label: "Plans & Billing" },
        ],
    },
    {
        title: "Storefront",
        items: [
            { name: "admin.settings.socialMedia", icon: "lab lab-line-social", labelKey: "menu.social_media" },
            { name: "admin.settings.cookies", icon: "lab lab-line-cookies", labelKey: "menu.cookies" },
            { name: "admin.settings.analytic", icon: "lab lab-line-analytic", labelKey: "menu.analytics" },
            { name: "admin.settings.theme", icon: "lab lab-line-theme", labelKey: "menu.theme" },
            { name: "admin.settings.slider", icon: "lab lab-line-sliders", labelKey: "menu.sliders" },
            { name: "admin.settings.currency", icon: "lab lab-line-currencies", labelKey: "menu.currencies" },
            { name: "admin.settings.language", icon: "lab lab-line-global", labelKey: "menu.languages" },
            { name: "admin.settings.page", icon: "lab lab-line-pages", labelKey: "menu.pages" },
            { name: "admin.settings.benefit", icon: "lab lab-line-benefits", labelKey: "menu.benefits" },
        ],
    },
    {
        title: "Commerce",
        items: [
            { name: "admin.settings.locationSetup", icon: "lab lab-line-location-setup", labelKey: "menu.location_setup" },
            { name: "admin.settings.shippingSetup", icon: "lab lab-line-truck-check", labelKey: "menu.shipping_setup" },
            { name: "admin.settings.productCategory", icon: "lab lab-line-item-categories", labelKey: "menu.product_categories" },
            { name: "admin.settings.productAttribute", icon: "lab lab-line-item-attributes", labelKey: "menu.product_attributes" },
            { name: "admin.settings.productBrand", icon: "lab lab-line-brand", labelKey: "menu.product_brands" },
            { name: "admin.settings.unit", icon: "lab lab-line-unit", labelKey: "menu.units" },
            { name: "admin.settings.tax", icon: "lab lab-line-taxes", labelKey: "menu.taxes" },
            { name: "admin.settings.returnReason", icon: "lab lab-line-order-setup", labelKey: "menu.return_reasons" },
            { name: "admin.settings.supplier", icon: "lab lab-line-supplier", labelKey: "menu.suppliers" },
            { name: "admin.settings.outlet", icon: "lab lab-line-branches", labelKey: "menu.outlets" },
            { name: "admin.settings.role", icon: "lab lab-line-role-permission", labelKey: "menu.role_permissions" },
        ],
    },
];

export default {
    name: "MenuComponent",
    data() {
        return {
            menuSearch: "",
        };
    },
    computed: {
        menuGroups() {
            return isMerchantHost() ? merchantGroups : ownerGroups;
        },
        filteredMenuGroups() {
            const search = this.normalizeSearch(this.menuSearch);

            if (!search) {
                return this.menuGroups;
            }

            return this.menuGroups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => {
                        return this.normalizeSearch(this.menuItemLabel(item)).includes(search);
                    }),
                }))
                .filter((group) => group.items.length > 0);
        },
        flattenedMenuItems() {
            return this.menuGroups.flatMap((group) => group.items);
        },
        currentItemLabel() {
            const currentItem = this.flattenedMenuItems.find((item) => this.isCurrentItem(item));
            return currentItem ? this.menuItemLabel(currentItem) : this.$t("menu.settings_menu");
        },
        merchantSetup: function () {
            return this.$store.getters["merchantDashboard/setup"];
        },
    },
    mounted() {
        if (isMerchantHost() && !this.merchantSetup) {
            this.$store.dispatch("merchantDashboard/setup").catch(() => {});
        }
    },
    methods: {
        openSettingMenu: function (event) {
            return appService.openSettingMenu(event);
        },
        settingRoute: function (item) {
            return { name: item.name };
        },
        menuItemLabel(item) {
            return item.labelKey ? this.$t(item.labelKey) : item.label;
        },
        normalizeSearch(value) {
            return String(value || "").trim().toLowerCase();
        },
        isCurrentItem(item) {
            const routeName = String(this.$route?.name || "");
            return routeName === item.name || routeName.startsWith(`${item.name}.`);
        },
        closeMobileSettingMenu: function () {
            if (!appService.isMobileSidebarBreakpoint()) {
                return;
            }

            document.querySelectorAll(".settings-btn").forEach((btn) => {
                const options = btn.nextElementSibling;

                if (options) {
                    options.style.height = "0px";
                    options.style.margin = "0px";
                }

                btn.querySelector(".icon")?.classList?.remove("fa-chevron-up");
                btn.querySelector(".icon")?.classList?.add("fa-chevron-down");
                btn.setAttribute("aria-expanded", "false");
            });
        },
        isFeatureLocked: function (item) {
            if (!isMerchantHost() || !item?.feature) {
                return false;
            }

            if (!this.merchantSetup) {
                return false;
            }

            const feature = this.merchantSetup?.billing?.features?.features?.[item.feature];

            return feature?.status !== true;
        },
    },
};
</script>
