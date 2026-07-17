<template>
    <button @click="openSettingMenu($event)" type="button" class="settings-btn w-full md:hidden flex items-center justify-center gap-2 p-2 rounded bg-primary text-white">
        <span class="capitalize">{{ $t('menu.settings_menu') }}</span>
        <i class="icon fa-solid fa-chevron-down text-sm"></i>
    </button>
    <div class="h-0 overflow-hidden md:h-auto md:overflow-auto transition-all duration-300 font-medium">
        <nav class="db-card p-3">
            <router-link v-for="item in menuItems" :key="item.name" :to="{ name: item.name }" class="db-tab-btn">
                <i :class="item.icon" class="text-sm"></i>
                {{ item.labelKey ? $t(item.labelKey) : item.label }}
            </router-link>
        </nav>
    </div>
</template>

<script>
import appService from "../../../services/appService";
import { isMerchantHost } from "../../../services/workspaceService";

export default {
    name: "MenuComponent",
    computed: {
        menuItems: function () {
            if (isMerchantHost()) {
                return [
                    { name: "admin.settings.company", icon: "lab lab-line-company", labelKey: "menu.company" },
                    { name: "admin.settings.shippingSetup", icon: "lab lab-line-truck-check", labelKey: "menu.shipping_setup" },
                    { name: "admin.settings.paymentGateway", icon: "lab lab-line-payment-gateway", label: "Payment Methods" },
                    { name: "admin.settings.domains", icon: "lab lab-line-site", label: "Domains" },
                    { name: "admin.settings.billing", icon: "lab lab-line-report", label: "Billing" },
                    { name: "admin.settings.productCategory", icon: "lab lab-line-item-categories", labelKey: "menu.product_categories" },
                    { name: "admin.settings.productAttribute", icon: "lab lab-line-item-attributes", labelKey: "menu.product_attributes" },
                    { name: "admin.settings.productBrand", icon: "lab lab-line-brand", labelKey: "menu.product_brands" },
                    { name: "admin.settings.supplier", icon: "lab lab-line-supplier", labelKey: "menu.suppliers" },
                    { name: "admin.settings.unit", icon: "lab lab-line-unit", labelKey: "menu.units" },
                    { name: "admin.settings.returnReason", icon: "lab lab-line-order-setup", labelKey: "menu.return_reasons" },
                ];
            }

            return [
                { name: "admin.settings.company", icon: "lab lab-line-company", labelKey: "menu.company" },
                { name: "admin.settings.site", icon: "lab lab-line-site", labelKey: "menu.site" },
                { name: "admin.settings.mail", icon: "lab lab-line-mail", labelKey: "menu.mail" },
                { name: "admin.settings.locationSetup", icon: "lab lab-line-location-setup", labelKey: "menu.location_setup" },
                { name: "admin.settings.shippingSetup", icon: "lab lab-line-truck-check", labelKey: "menu.shipping_setup" },
                { name: "admin.settings.otp", icon: "lab lab-line-otp", labelKey: "menu.otp" },
                { name: "admin.settings.notification", icon: "lab lab-line-notification", labelKey: "menu.notification" },
                { name: "admin.settings.notificationAlert", icon: "lab lab-line-notification-alert", labelKey: "menu.notification_alert" },
                { name: "admin.settings.smsGateway", icon: "lab lab-line-sms", labelKey: "menu.sms_gateway" },
                { name: "admin.settings.paymentGateway", icon: "lab lab-line-payment-gateway", labelKey: "menu.payment_gateway" },
                { name: "admin.settings.socialMedia", icon: "lab lab-line-social", labelKey: "menu.social_media" },
                { name: "admin.settings.cookies", icon: "lab lab-line-cookies", labelKey: "menu.cookies" },
                { name: "admin.settings.analytic", icon: "lab lab-line-analytic", labelKey: "menu.analytics" },
                { name: "admin.settings.theme", icon: "lab lab-line-theme", labelKey: "menu.theme" },
                { name: "admin.settings.slider", icon: "lab lab-line-sliders", labelKey: "menu.sliders" },
                { name: "admin.settings.currency", icon: "lab lab-line-currencies", labelKey: "menu.currencies" },
                { name: "admin.settings.language", icon: "lab lab-line-global", labelKey: "menu.languages" },
                { name: "admin.settings.page", icon: "lab lab-line-pages", labelKey: "menu.pages" },
                { name: "admin.settings.benefit", icon: "lab lab-line-benefits", labelKey: "menu.benefits" },
                { name: "admin.settings.productCategory", icon: "lab lab-line-item-categories", labelKey: "menu.product_categories" },
                { name: "admin.settings.productAttribute", icon: "lab lab-line-item-attributes", labelKey: "menu.product_attributes" },
                { name: "admin.settings.productBrand", icon: "lab lab-line-brand", labelKey: "menu.product_brands" },
                { name: "admin.settings.unit", icon: "lab lab-line-unit", labelKey: "menu.units" },
                { name: "admin.settings.tax", icon: "lab lab-line-taxes", labelKey: "menu.taxes" },
                { name: "admin.settings.returnReason", icon: "lab lab-line-order-setup", labelKey: "menu.return_reasons" },
                { name: "admin.settings.supplier", icon: "lab lab-line-supplier", labelKey: "menu.suppliers" },
                { name: "admin.settings.outlet", icon: "lab lab-line-branches", labelKey: "menu.outlets" },
                { name: "admin.settings.role", icon: "lab lab-line-role-permission", labelKey: "menu.role_permissions" },
            ];
        },
    },

    methods: {
        openSettingMenu: function (event) {
            return appService.openSettingMenu(event);
        },
 }
};
</script>
