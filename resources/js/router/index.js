import { createRouter, createWebHistory } from "vue-router";
import DashboardComponent from "../components/admin/dashboard/DashboardComponent";
import ExceptionComponent from "../components/exception/ExceptionComponent.vue";
import NotFoundComponent from "../components/exception/NotFoundComponent.vue";
import MerchantPausedComponent from "../components/merchant/MerchantPausedComponent.vue";
import ENV from "../config/env";
import roleEnum from "../enums/modules/roleEnum";
import appService from "../services/appService";
import { detectWorkspaceHost, resolveAuthenticatedHomeRoute, resolveGuestHomeRoute } from "../services/workspaceService";
import store from "../store";
import administratorRoutes from "./modules/administratorRoutes";
import authRoutes from "./modules/authRoutes";
import couponRoutes from "./modules/couponRoutes";
import creditBalanceReportRoutes from "./modules/creditBalanceReportRoutes";
import customerRoutes from "./modules/customerRoutes";
import damageRoutes from "./modules/damageRoutes";
import employeeRoutes from "./modules/employeeRoutes";
import frontendRoutes from "./modules/frontendRoutes";
import onlineOrderRoutes from "./modules/onlineOrderRoutes";
import posOrderRoutes from "./modules/posOrderRoutes";
import posRoutes from "./modules/posRoutes";
import ProductSectionRoutes from "./modules/ProductSectionRoutes";
import productsReportRoutes from "./modules/productsReportRoutes";
import productsRoutes from "./modules/productsRoutes";
import platformRoutes from "./modules/platformRoutes";
import profileRoutes from "./modules/profileRoutes";
import PromotionRoutes from "./modules/PromotionRoutes";
import purchaseRoutes from "./modules/purchaseRoutes";
import pushNotificationRoutes from "./modules/pushNotificationRoutes";
import returnAndRefundRoutes from "./modules/returnAndRefundRoutes";
import returnOrderRoutes from "./modules/returnOrderRoutes";
import reviewRoutes from "./modules/reviewRoutes";
import salesReportRoutes from "./modules/salesReportRoutes";
import settingRoutes from "./modules/settingRoutes";
import stockRoutes from "./modules/stockRoutes";
import subscriberRoutes from "./modules/subscriberRoutes";
import transactionRoutes from "./modules/transactionRoutes";

const resolveRootRedirect = function () {
    const workspace = detectWorkspaceHost();
    const isLoggedIn = store.getters.authStatus;
    const authSurface = store.getters.authInfo?.surface || null;

    if (workspace === "platform") {
        return isLoggedIn && authSurface === "platform"
            ? { name: "platform.dashboard" }
            : { name: "auth.login" };
    }

    if (workspace === "merchant") {
        return isLoggedIn && authSurface === "merchant"
            ? { name: "merchant.dashboard" }
            : { name: "auth.login" };
    }

    return { name: "frontend.home" };
};

const baseRoutes = [
    {
        path: "/",
        redirect: () => resolveRootRedirect(),
        name: "root",
    },
    {
        path: "/admin",
        redirect: () => detectWorkspaceHost() === "platform" ? { name: "platform.dashboard" } : { name: "merchant.dashboard" },
        name: "admin.root",
    },
    {
        path: "/:pathMatch(.*)*",
        name: "route.notFound",
        component: NotFoundComponent,
        meta: {
            isFrontend: true,
        },
    },
    {
        path: "/exception",
        name: "route.exception",
        component: ExceptionComponent,
    },
    {
        path: "/dashboard",
        component: MerchantPausedComponent,
        name: "merchant.dashboard",
        meta: {
            isFrontend: false,
            auth: true,
            standalone: true,
            workspace: "merchant",
        },
    },
    {
        path: "/admin/dashboard",
        component: DashboardComponent,
        name: "admin.dashboard",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "dashboard",
            breadcrumb: "dashboard",
            workspace: "platform",
        },
    },
];

const routes = baseRoutes.concat(
    platformRoutes,
    frontendRoutes,
    authRoutes,
    settingRoutes,
    profileRoutes,
    productsRoutes,
    administratorRoutes,
    customerRoutes,
    employeeRoutes,
    transactionRoutes,
    salesReportRoutes,
    creditBalanceReportRoutes,
    pushNotificationRoutes,
    productsRoutes,
    couponRoutes,
    PromotionRoutes,
    ProductSectionRoutes,
    purchaseRoutes,
    stockRoutes,
    returnOrderRoutes,
    damageRoutes,
    onlineOrderRoutes,
    productsReportRoutes,
    posOrderRoutes,
    posRoutes,
    returnAndRefundRoutes,
    subscriberRoutes,
    reviewRoutes
);

const permission = store.getters.authPermission;
appService.recursiveRouter(routes, permission);

const API_URL = ENV.API_URL;
const router = createRouter({
    linkActiveClass: "active",
    mode: "history",
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { left: 0, top: 0 };
    },
});

router.beforeEach((to, from, next) => {
    const hostname = window.location.hostname;
    const workspaceHost = detectWorkspaceHost(hostname);
    const isOwnerHost = workspaceHost === "platform";
    const isMerchantHost = workspaceHost === "merchant";
    const isAdminRoute = to.path === "/admin" || to.path.startsWith("/admin/");
    const isOwnerAdminRoute = isOwnerHost && isAdminRoute;
    const isPlatformRoute = to.meta?.workspace === "platform" || String(to.name || "").startsWith("platform.") || isOwnerAdminRoute;
    const isMerchantWorkspaceRoute = to.meta?.workspace === "merchant" || String(to.name || "").startsWith("merchant.");
    const isLoggedIn = store.getters.authStatus;
    const authSurface = store.getters.authInfo?.surface || null;
    const parsedRoleId = Number.parseInt(store.getters.authInfo?.role_id, 10);
    const roleId = Number.isFinite(parsedRoleId) ? parsedRoleId : null;
    const adminRoleIds = [
        roleEnum.ADMIN,
        roleEnum.MANAGER,
        roleEnum.POS_OPERATOR,
        roleEnum.STUFF,
    ];
    const isAdminUser = roleId !== null && adminRoleIds.includes(roleId);
    const guestOnlyRouteNames = [
        "auth.login",
        "auth.adminLogin",
        "auth.merchantRegister",
        "auth.signup",
        "auth.signupVerify",
        "auth.forgotPassword",
        "auth.forgotPasswordVerify",
        "auth.resetPassword",
    ];

    if (to.name === "auth.merchantRegister" && !isMerchantHost) {
        next({ name: isOwnerHost ? "auth.login" : "auth.signup" });
        return;
    }

    if (isMerchantHost && isPlatformRoute) {
        next(isLoggedIn && authSurface === "merchant" ? { name: "merchant.dashboard" } : { name: "auth.login" });
        return;
    }

    if (isMerchantHost && isAdminRoute) {
        next(isLoggedIn && authSurface === "merchant" ? { name: "merchant.dashboard" } : { name: "auth.login" });
        return;
    }

    if ((isOwnerHost || isMerchantHost) && to.meta?.isFrontend === true && !guestOnlyRouteNames.includes(to.name) && to.name !== "route.notFound") {
        next(isLoggedIn ? resolveAuthenticatedHomeRoute(store.getters.authInfo, hostname) : resolveGuestHomeRoute(hostname));
        return;
    }

    if (isAdminRoute && to.name !== "auth.adminLogin" && !isOwnerHost) {
        if (!isLoggedIn) {
            next({ name: "auth.login" });
            return;
        }

        if (!isAdminUser) {
            next({ name: "frontend.account.overview" });
            return;
        }
    }

    if (to.meta.auth === true) {
        if (!isLoggedIn) {
            next(resolveGuestHomeRoute(hostname));
            return;
        }

        if (isPlatformRoute && authSurface !== "platform") {
            next(resolveAuthenticatedHomeRoute(store.getters.authInfo, hostname));
            return;
        }

        if (isMerchantWorkspaceRoute && authSurface !== "merchant" && !isPlatformRoute) {
            next(resolveAuthenticatedHomeRoute(store.getters.authInfo, hostname));
            return;
        }

        if (to.meta.isFrontend === false && to.meta.access === false) {
            next({
                name: "route.exception",
            });
            return;
        }

        next();
        return;
    }

    if (isLoggedIn && guestOnlyRouteNames.includes(to.name)) {
        next(resolveAuthenticatedHomeRoute(store.getters.authInfo, hostname));
        return;
    }

    next();
});
export default router;
