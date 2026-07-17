<template>
    <LoadingComponent :props="loading" />
    <div class="mb-9">
        <h4 class="font-semibold text-xl mb-3 capitalize text-heading">{{ $t("menu.overview") }}</h4>
        <div class="row">
            <div class="col-12 sm:col-6 xl:col-3">
                <div class="bg-admin-pink p-4 rounded-lg flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white">
                        <i class="lab-fill-dollar-circle text-admin-pink text-2xl lab-font-size-24"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide capitalize text-white">{{ $t('label.total_earnings') }}</h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">{{ total_sales }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-12 sm:col-6 xl:col-3">
                <div class="bg-admin-orange p-4 rounded-lg flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white">
                        <i class="lab-fill-box text-admin-orange text-2xl lab-font-size-24"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide capitalize text-white">{{ $t('label.total_orders') }}</h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">{{ total_orders }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-12 sm:col-6 xl:col-3">
                <div class="bg-admin-purple p-4 rounded-lg flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white">
                        <i class="lab-fill-users text-admin-purple text-2xl lab-font-size-24"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide capitalize text-white">
                            {{ isMerchantWorkspace ? 'Total Products' : $t('label.total_customers') }}
                        </h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">
                            {{ isMerchantWorkspace ? total_products : total_customers }}
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-12 sm:col-6 xl:col-3">
                <div class="bg-admin-blue p-4 rounded-lg flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white">
                        <i class="lab-fill-document text-admin-blue text-2xl lab-font-size-24"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide capitalize text-white">
                            {{ isMerchantWorkspace ? 'Low Stock Alerts' : $t('label.total_products') }}
                        </h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">
                            {{ isMerchantWorkspace ? low_stock_alerts : total_products }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
export default {
    name: "OverviewComponent",
    components: { LoadingComponent },
    props: {
        merchantSetup: {
            type: Object,
            default: null,
        },
        isMerchantWorkspace: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            loading: {
                isActive: false,
            },

            total_sales: "0.00",
            total_orders: 0,
            total_customers: 0,
            total_products: 0,
            low_stock_alerts: 0,
        };
    },
    watch: {
        merchantSetup: {
            deep: true,
            handler() {
                this.applyMerchantMetrics();
            },
        },
    },
    mounted() {
        if (this.isMerchantWorkspace) {
            this.applyMerchantMetrics();
            return;
        }

        this.totalSales();
        this.totalOrders();
        this.totalCustomers();
        this.totalProducts();
    },
    methods: {
        applyMerchantMetrics: function () {
            const metrics = this.merchantSetup?.metrics || {};

            this.total_sales = metrics.total_sales || "0.00";
            this.total_orders = metrics.total_orders || 0;
            this.total_customers = metrics.total_customers || 0;
            this.total_products = metrics.total_products || 0;
            this.low_stock_alerts = metrics.low_stock_alerts || 0;
        },
        totalSales: function () {
            this.loading.isActive = true;
            this.$store.dispatch("dashboard/totalSales").then((res) => {
                this.total_sales = res.data.data.total_sales;
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },

        totalOrders: function () {
            this.loading.isActive = true;
            this.$store.dispatch("dashboard/totalOrders").then((res) => {
                this.total_orders = res.data.data.total_orders;
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
        totalCustomers: function () {
            this.loading.isActive = true;
            this.$store.dispatch("dashboard/totalCustomers").then((res) => {
                this.total_customers = res.data.data.total_customers;
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
        totalProducts: function () {
            this.loading.isActive = true;
            this.$store.dispatch("dashboard/totalProducts").then((res) => {
                this.total_products = res.data.data.total_products;
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
    },
}
</script>
