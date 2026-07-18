<template>
    <div v-if="theme === 'loading'">
        <LoadingComponent :props="{isActive:true}" />
    </div>

    <div v-if="theme === 'frontend'">
        <FrontendNavbarComponent />
        <FrontendCartComponent />
        <router-view></router-view>
        <FrontendMobileSideBarComponent />
        <FrontendMobileNavBarComponent />
        <FrontendMobileCategoryComponent />
        <FrontendMobileAccountComponent />
        <FrontendCookiesComponent />
        <FrontendFooterComponent />
    </div>

    <div v-if="theme === 'backend'">
        <main class="db-main" v-if="showBackendShell">
            <BackendNavbarComponent />
            <BackendMenuComponent />
            <div class="relative min-h-full">
                <router-view></router-view>
                <div
                    v-if="lockedSubscriptionFeature"
                    class="absolute inset-0 z-30 flex items-start justify-center rounded-2xl bg-white/70 px-4 py-10 backdrop-blur-[1px]">
                    <div class="max-w-lg rounded-2xl border border-[#FED7AA] bg-white p-6 text-center shadow-xl">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#FFF4F1] text-primary">
                            <i class="fa-solid fa-lock text-xl"></i>
                        </div>
                        <h3 class="mt-4 text-xl font-semibold text-[#111827]">{{ lockedSubscriptionFeature.label }} is locked</h3>
                        <p class="mt-2 text-sm leading-6 text-[#6B7280]">
                            This page stays visible for preview, but actions are disabled until this feature is unlocked by your active plan.
                        </p>
                        <button
                            type="button"
                            class="db-btn mt-5 bg-primary px-5 py-2 text-white"
                            @click="openBillingForLockedFeature">
                            View upgrade plans
                        </button>
                    </div>
                </div>
            </div>
        </main>
        <div v-else>
            <router-view></router-view>
        </div>
    </div>

    <div v-if="theme === 'platform'">
        <router-view></router-view>
    </div>
</template>

<script>
import BackendNavbarComponent from "./layouts/backend/BackendNavbarComponent";
import BackendMenuComponent from "./layouts/backend/BackendMenuComponent";
import FrontendNavbarComponent from "./layouts/frontend/FrontendNavBarComponent";
import FrontendFooterComponent from "./layouts/frontend/FrontendFooterComponent";
import FrontendCartComponent from "./layouts/frontend/FrontendCartComponent";
import FrontendMobileNavBarComponent from "./layouts/frontend/FrontendMobileNavBarComponent";
import FrontendMobileCategoryComponent from "./layouts/frontend/FrontendMobileCategoryComponent";
import FrontendMobileAccountComponent from "./layouts/frontend/FrontendMobileAccountComponent";
import FrontendMobileSideBarComponent from "./layouts/frontend/FrontendMobileSideBarComponent";
import FrontendCookiesComponent from "./layouts/frontend/FrontendCookiesComponent";
import DisplayModeEnum from "../enums/modules/displayModeEnum";
import env from "../config/env";
import LoadingComponent from "../components/frontend/components/LoadingComponent.vue";
import { isAdminSurfaceHost, isMerchantHost, resolveGuestHomeRoute } from "../services/workspaceService";
import appService from "../services/appService";

export default {
    name: "DefaultComponent",
    components: {
        FrontendMobileSideBarComponent,
        FrontendMobileAccountComponent,
        FrontendMobileCategoryComponent,
        FrontendMobileNavBarComponent,
        FrontendCartComponent,
        FrontendNavbarComponent,
        FrontendFooterComponent,
        BackendNavbarComponent,
        BackendMenuComponent,
        FrontendCookiesComponent,
        LoadingComponent
    },
    data() {
        return {
            theme: "loading",
        }
    },
    beforeMount() {
        this.displayModeDefine();
        this.theme = this.resolveTheme(this.$route);
        this.ensureMerchantSetupForRoute(this.$route);
        this.$store.dispatch('frontendSetting/lists').then(res => {
            this.$store.dispatch("globalState/init", {
                language_id: res.data.data.site_default_language,
                search_restaurant: "",
                location: null,
                latitude: null,
                longitude: null
            });
        }).catch();

        if (this.shouldVerifyAuth()) {
            this.$store.dispatch("authcheck").then(res => {
                appService.recursiveRouter(this.$router.options.routes, this.$store.getters.authPermission);

                if (res.data.status === false) {
                    this.$router.push(resolveGuestHomeRoute());
                };
            }).catch();
        }
    },
    computed: {
        logged: function () {
            return this.$store.getters.authStatus;
        },
        showBackendShell: function () {
            return this.logged || this.$route?.meta?.auth === true;
        },
        lockedSubscriptionFeature: function () {
            const featureCode = this.$route?.meta?.subscriptionFeature;

            if (!featureCode || !isMerchantHost() || this.$route?.name === "admin.settings.billing") {
                return null;
            }

            const setup = this.$store.getters["merchantDashboard/setup"];

            if (!setup) {
                return null;
            }

            const feature = setup?.billing?.features?.features?.[featureCode];

            if (feature?.status === true) {
                return null;
            }

            return {
                code: featureCode,
                label: feature?.label || this.formatFeatureLabel(featureCode),
            };
        },
        displayMode: function () {
            return this.$store.getters['globalState/lists'].display_mode;
        },
    },
    methods: {
        isAdminSurfaceHost: function () {
            return isAdminSurfaceHost();
        },
        shouldVerifyAuth: function () {
            return this.isAdminSurfaceHost() || env.DEMO === "true" || env.DEMO === true || env.DEMO === "1" || env.DEMO === 1;
        },
        ensureMerchantSetupForRoute: function (route = this.$route) {
            if (!isMerchantHost() || !route?.meta?.subscriptionFeature || this.$store.getters["merchantDashboard/setup"]) {
                return;
            }

            this.$store.dispatch("merchantDashboard/setup").catch(() => {});
        },
        formatFeatureLabel: function (value) {
            return String(value || "")
                .replace(/_/g, " ")
                .replace(/\b\w/g, (letter) => letter.toUpperCase());
        },
        openBillingForLockedFeature: function () {
            if (!this.lockedSubscriptionFeature) {
                return;
            }

            this.$router.push({
                name: "admin.settings.billing",
                query: { upgrade: this.lockedSubscriptionFeature.code },
            });
        },
        isAuthRoute: function (route = this.$route) {
            const authRoutes = [
                "auth.login",
                "auth.adminLogin",
                "auth.merchantRegister",
                "auth.signup",
                "auth.signupVerify",
                "auth.forgotPassword",
                "auth.forgotPasswordVerify",
                "auth.resetPassword",
            ];

            return authRoutes.includes(route?.name);
        },
        resolveTheme: function (route) {
            const adminSurfaceAuthRoutes = [
                "auth.login",
                "auth.adminLogin",
                "auth.merchantRegister",
                "auth.forgotPassword",
                "auth.forgotPasswordVerify",
                "auth.resetPassword",
            ];

            if (this.isAdminSurfaceHost() && adminSurfaceAuthRoutes.includes(route?.name)) {
                return "backend";
            }

            if (route?.meta?.workspace === "platform") {
                return "platform";
            }

            if (route?.meta?.standalone === true) {
                return "platform";
            }

            return route?.meta?.isFrontend === true ? "frontend" : "backend";
        },
        displayModeDefine: function (route = this.$route) {
            let dir = "ltr";
            const attributes = {
                dir: "ltr",
            };

            if (!this.isAuthRoute(route) && this.$store.getters['globalState/lists'].display_mode !== DisplayModeEnum.LTR) {
                dir = "rtl";
            } else {
                dir = "ltr";
            }

            Object.keys(attributes).forEach(attr => {
                document.documentElement.setAttribute(attr, dir);
            });
        }
    },

    watch: {
        $route(e) {
            this.theme = this.resolveTheme(e);
            this.displayModeDefine(e);
            this.ensureMerchantSetupForRoute(e);
        },
        displayMode() {
            this.displayModeDefine();
        }
    },
}
</script>
