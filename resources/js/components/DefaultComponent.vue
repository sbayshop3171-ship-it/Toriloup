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
            <section
                v-if="showSupportBanner"
                class="border-b border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                <div class="mx-auto flex max-w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="font-semibold">Audited support session is active.</p>
                        <p>
                            Owner support access is using merchant store
                            <span class="font-semibold">{{ supportSession?.tenant?.name || authInfo?.current_tenant?.name || authInfo?.current_tenant?.tenant?.name || "merchant workspace" }}</span>.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#F59E0B] bg-white px-4 text-xs font-semibold uppercase tracking-[0.16em] text-[#B45309] transition hover:bg-[#FEF3C7]"
                        @click="endSupportSession">
                        Exit support session
                    </button>
                </div>
            </section>
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
import axios from "axios";
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
        this.$store.dispatch('frontendSetting/lists').then(res => {
            this.$store.dispatch("globalState/init", {
                language_id: res.data.data.site_default_language,
                search_restaurant: "",
                location: null,
                latitude: null,
                longitude: null
            });
        }).catch();

        if (this.shouldVerifyAuth() && this.$route?.meta?.skipInitialAuthCheck !== true) {
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
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        showBackendShell: function () {
            return this.logged || this.$route?.meta?.auth === true;
        },
        supportSession: function () {
            return this.authInfo?.support_session || null;
        },
        showSupportBanner: function () {
            return this.authInfo?.surface === "merchant" && this.supportSession?.id;
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
        endSupportSession: function () {
            if (!this.supportSession?.id) {
                return;
            }

            axios.post(`merchant/auth/support-sessions/${this.supportSession.id}/end`)
                .finally(() => {
                    this.$store.commit("authLogout");
                    this.$router.push(resolveGuestHomeRoute());
                });
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
