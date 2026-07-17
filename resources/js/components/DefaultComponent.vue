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
            <router-view></router-view>
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
import { isAdminSurfaceHost, resolveGuestHomeRoute } from "../services/workspaceService";

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
        this.$store.dispatch('frontendSetting/lists').then(res => {
            this.$store.dispatch("globalState/init", {
                language_id: res.data.data.site_default_language,
                search_restaurant: "",
                location: null,
                latitude: null,
                longitude: null
            });
        }).catch();

        if (env.DEMO === "true" || env.DEMO === true || env.DEMO === "1" || env.DEMO === 1) {
            this.$store.dispatch("authcheck").then(res => {
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
        displayMode: function () {
            return this.$store.getters['globalState/lists'].display_mode;
        },
    },
    methods: {
        isAdminSurfaceHost: function () {
            return isAdminSurfaceHost();
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
        },
        displayMode() {
            this.displayModeDefine();
        }
    },
}
</script>
