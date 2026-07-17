<template>
    <button @click="openSettingMenu($event)" type="button" class="settings-btn w-full md:hidden flex items-center justify-center gap-2 p-2 rounded bg-primary text-white">
        <span class="capitalize">{{ $t('menu.settings_menu') }}</span>
        <i class="icon fa-solid fa-chevron-down text-sm"></i>
    </button>
    <div class="h-0 overflow-hidden md:h-auto md:overflow-auto transition-all duration-300 font-medium">
        <nav class="db-card p-3">
            <router-link v-for="item in menuItems" :key="item.name" :to="{ name: item.name }" class="db-tab-btn">
                <i :class="item.icon" class="text-sm"></i>
                {{ item.label }}
            </router-link>
        </nav>
    </div>
</template>

<script>
import appService from "../../../services/appService";
export default {
    name: "MenuComponent",
    computed: {
        menuItems: function () {
            return [
                { name: "admin.settings.company", icon: "lab lab-line-company", label: this.$t("menu.company") },
                { name: "admin.settings.shippingSetup", icon: "lab lab-line-truck-check", label: this.$t("menu.shipping_setup") },
                { name: "admin.settings.paymentGateway", icon: "lab lab-line-payment-gateway", label: "Payment Methods" },
                { name: "admin.settings.domains", icon: "lab lab-line-site", label: "Domains" },
                { name: "admin.settings.billing", icon: "lab lab-line-report", label: "Billing" },
                { name: "admin.settings.productCategory", icon: "lab lab-line-item-categories", label: this.$t("menu.product_categories") },
                { name: "admin.settings.productAttribute", icon: "lab lab-line-item-attributes", label: this.$t("menu.product_attributes") },
                { name: "admin.settings.productBrand", icon: "lab lab-line-brand", label: this.$t("menu.product_brands") },
                { name: "admin.settings.supplier", icon: "lab lab-line-supplier", label: this.$t("menu.suppliers") },
                { name: "admin.settings.unit", icon: "lab lab-line-unit", label: this.$t("menu.units") },
                { name: "admin.settings.returnReason", icon: "lab lab-line-order-setup", label: this.$t("menu.return_reasons") },
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
