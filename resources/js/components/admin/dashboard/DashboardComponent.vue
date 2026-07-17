<template>
    <LoadingComponent :props="loading" />
    <div v-if="demo === 'true' || demo === 'TRUE' || demo === 'True' || demo === '1' || demo === 1"
        class="mb-4 bg-red-100 p-2 pl-4  rounded">
        <h2 class="mb-1">{{ $t('label.reminder') }}</h2>
        <p>{{ $t('label.data_reset') }}</p>
    </div>

    <div class="mb-8">
        <h3 class="capitalize font-bold text-2xl text-primary mb-1.5">{{ visitorMessage() }}</h3>
        <h4 class="capitalize font-medium text-xl text-secondary">{{ authInfo.name }}</h4>
    </div>
    <!--========OVERVIEW START=============-->
    <OverviewComponent :merchant-setup="merchantSetup" :is-merchant-workspace="isMerchantWorkspace" />
    <!--========OVERVIEW END=============-->

    <MerchantSetupChecklistComponent v-if="isMerchantWorkspace" :setup="merchantSetup" />

    <!--========ORDER STATISTIC START=============-->
    <OrderStatisticsComponent />
    <!--========ORDER STATISTIC END=============-->
    <div class="row">
        <!--========SALES SUMMARY START=============-->
        <SalesSummaryComponent />
        <!--========SALES SUMMARY END=============-->

        <!--========ORDERS SUMMARY START=============-->
        <OrderSummaryComponent />
        <!--========ORDERS SUMMARY END=============-->

        <!--========CUSTOMER STATS START=============-->
        <CustomerStatsComponent />
        <!--========CUSTOMER STATS END=============-->

        <!--========TOP CUSTOMERS START=============-->
        <TopCustomersComponent />
        <!--========TOP CUSTOMERS END=============-->

        <!--========MOST POPULAR ITEMS START=============-->
        <TopProductsComponent />
        <!--========MOST POPULAR ITEMS END=============-->
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import OverviewComponent from "./OverviewComponent";
import OrderStatisticsComponent from "./OrderStatisticsComponent";
import SalesSummaryComponent from "./SalesSummaryComponent";
import OrderSummaryComponent from "./OrderSummaryComponent";
import CustomerStatsComponent from "./CustomerStatsComponent";
import TopCustomersComponent from "./TopCustomersComponent";
import TopProductsComponent from "./TopProductsComponent";
import MerchantSetupChecklistComponent from "./MerchantSetupChecklistComponent";
import ENV from "../../../config/env";
import { isMerchantHost } from "../../../services/workspaceService";

export default {
    name: "DashboardComponent",
    components: {
        LoadingComponent,
        OverviewComponent,
        OrderStatisticsComponent,
        SalesSummaryComponent,
        OrderSummaryComponent,
        CustomerStatsComponent,
        TopCustomersComponent,
        TopProductsComponent,
        MerchantSetupChecklistComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            demo: ENV.DEMO,
            merchantSetup: null,
        };
    },
    computed: {
        authInfo: function () {
            return this.$store.getters.authInfo;
        },
        isMerchantWorkspace: function () {
            return isMerchantHost();
        }
    },
    mounted() {
        if (this.isMerchantWorkspace) {
            this.loadMerchantSetup();
        }
    },
    methods: {
        loadMerchantSetup: function () {
            this.$store.dispatch("merchantDashboard/setup").then((res) => {
                this.merchantSetup = res.data.data;
            }).catch(() => {
                this.merchantSetup = null;
            });
        },
        visitorMessage: function () {
            let greet;
            let myDate = new Date();
            let hrs = myDate.getHours();
            if (hrs < 12) {
                greet = this.$t('message.good_morning');
            } else if (hrs >= 12 && hrs <= 17) {
                greet = this.$t('message.good_afternoon');
            } else if (hrs >= 17 && hrs <= 24) {
                greet = this.$t('message.good_evening');
            }
            return greet;
        }
    }
}
</script>
