import { createRouter, createWebHistory } from "vue-router";
import DashboardComponent from "../components/admin/dashboard/DashboardComponent";
import ExceptionComponent from "../components/exception/ExceptionComponent.vue";
import NotFoundComponent from "../components/exception/NotFoundComponent.vue";
import ENV from "../config/env";
import roleEnum from "../enums/modules/roleEnum";
import appService from "../services/appService";
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

const baseRoutes = [
    {
        path: "/",
        redirect: { name: "frontend.home" },
        name: "root",
    },
    {
        path: "/admin",
        redirect: { name: "admin.dashboard" },
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
        path: "/admin/dashboard",
        component: DashboardComponent,
        name: "admin.dashboard",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "dashboard",
            breadcrumb: "dashboard",
        },
    },
];

const routes = baseRoutes.concat(
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
    const isOwnerHost = Boolean(ENV.OWNER_HOST && hostname === ENV.OWNER_HOST);
    const isMerchantHost = Boolean(ENV.MERCHANT_HOST && hostname === ENV.MERCHANT_HOST);
    const isAdminRoute = to.path === "/admin" || to.path.startsWith("/admin/");
    const isLoggedIn = store.getters.authStatus;
    const parsedRoleId = Number.parseInt(store.getters.authInfo?.role_id, 10);
    const roleId = Number.isFinite(parsedRoleId) ? parsedRoleId : null;
    const adminRoleIds = [
        roleEnum.ADMIN,
        roleEnum.MANAGER,
        roleEnum.POS_OPERATOR,
        roleEnum.STUFF,
    ];
    const isAdminUser = roleId !== null && adminRoleIds.includes(roleId);

    if (to.name === "auth.merchantRegister" && !isMerchantHost) {
        next({ name: isOwnerHost ? "auth.login" : "auth.signup" });
        return;
    }

    if (isAdminRoute && to.name !== "auth.adminLogin") {
        if (!isLoggedIn) {
            next({ name: "auth.adminLogin" });
            return;
        }

        if (!isAdminUser) {
            next({ name: "frontend.account.overview" });
            return;
        }
    }

    if (to.meta.auth === true) {
        if (!isLoggedIn) {
            next({ name: isAdminRoute ? "auth.adminLogin" : "auth.login" });
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

    if (isLoggedIn && guestOnlyRouteNames.includes(to.name)) {
        next({ name: isAdminUser ? "admin.dashboard" : "frontend.account.overview" });
        return;
    }

    next();
});
export default router;
